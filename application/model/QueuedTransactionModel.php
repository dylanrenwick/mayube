<?php

class QueuedTransactionModel
{
	/** Store a source change for review, with amounts in CAD; never post transactions. */
	public static function upsert(int $cardId, string $changeType, array $transaction): void
	{
		if (!in_array($changeType, ['added', 'modified', 'removed'], true)) {
			throw new InvalidArgumentException('Invalid queued transaction change.');
		}
		$database = DatabaseFactory::getFactory()->getConnection();
		$find = $database->prepare('SELECT id FROM queued_transactions WHERE card_id = :card_id AND provider_transaction_id = :transaction_id');
		if (!$find->execute([':card_id' => $cardId, ':transaction_id' => $transaction['provider_transaction_id']])) {
			throw new RuntimeException('Unable to retrieve queued transaction.');
		}
		$id = $find->fetchColumn();
		$values = [':change_type' => $changeType];
		if ($id !== false && $changeType === 'removed') {
			// Keep the original amount, date and description available to reviewers.
			$sql = 'UPDATE queued_transactions SET change_type = :change_type, updated_at = CURRENT_TIMESTAMP WHERE id = :id';
			$values[':id'] = $id;
		} else {
			$values += [
				':amount' => $transaction['amount'],
				':description' => $transaction['description'],
				':date' => $transaction['date'],
				':pending' => $transaction['pending'] ? 1 : 0,
			];
			if ($id === false) {
				$sql = 'INSERT INTO queued_transactions (card_id, provider_transaction_id, change_type, amount, description, date, pending) VALUES (:card_id, :transaction_id, :change_type, :amount, :description, :date, :pending)';
				$values[':card_id'] = $cardId;
				$values[':transaction_id'] = $transaction['provider_transaction_id'];
			} else {
				$sql = 'UPDATE queued_transactions SET change_type = :change_type, amount = :amount, description = :description, date = :date, pending = :pending, updated_at = CURRENT_TIMESTAMP WHERE id = :id';
				$values[':id'] = $id;
			}
		}
		$query = $database->prepare($sql);
		if (!$query->execute($values)) {
			throw new RuntimeException('Unable to queue transaction.');
		}
	}

	public static function getForCard(int $cardId): array
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('SELECT * FROM queued_transactions WHERE card_id = :card_id ORDER BY date, id');
		if (!$query->execute([':card_id' => $cardId])) {
			throw new RuntimeException('Unable to retrieve queued transactions.');
		}
		return $query->fetchAll(PDO::FETCH_OBJ);
	}

	public static function getAdditionsForUser(string $userId): array
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		// Plaid currently supplies card ownership and account masks.
		$sql = <<<SQL
SELECT queued.*, card.name AS card_name, account.mask AS card_mask
FROM queued_transactions AS queued
JOIN cards AS card ON card.id = queued.card_id
JOIN plaid_account AS account ON card.provider = 'plaid' AND account.id = card.provider_id
JOIN plaid_item AS item ON item.id = account.plaid_item_id
WHERE item.user_id = :user_id
	AND queued.change_type = 'added'
	AND NOT EXISTS (
		SELECT 1 FROM transactions AS posted
		WHERE posted.card_id = queued.card_id
			AND posted.provider_transaction_id = queued.provider_transaction_id
	)
ORDER BY queued.date, queued.id
SQL;
		$query = $database->prepare($sql);
		if (!$query->execute([':user_id' => $userId])) {
			throw new RuntimeException('Unable to retrieve the transaction queue.');
		}
		return $query->fetchAll(PDO::FETCH_OBJ);
	}

	/** Accept/reject only owned, net-new additions; all changes are atomic. */
	public static function reviewAdditions(string $userId, string $action, array $items, ?int $sheetId): int
	{
		if (!in_array($action, ['accept', 'accept_all', 'reject'], true) || $items === []) {
			throw new InvalidArgumentException('Select transactions to review.');
		}
		$accepting = $action !== 'reject';
		$requested = [];
		foreach ($items as $item) {
			if (!is_array($item) || !is_int($item['id'] ?? null) || $item['id'] < 1
				|| array_key_exists($item['id'], $requested)) {
				throw new InvalidArgumentException('Invalid transaction selection.');
			}
			if ($accepting && (!is_int($item['category_id'] ?? null) || $item['category_id'] < 1)) {
				throw new InvalidArgumentException('Choose a transaction type for every transaction being accepted.');
			}
			$requested[$item['id']] = $item['category_id'] ?? null;
		}

		$database = DatabaseFactory::getFactory()->getConnection();
		$previousErrorMode = $database->getAttribute(PDO::ATTR_ERRMODE);
		$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		try {
			$database->beginTransaction();
			$available = [];
			foreach (self::getAdditionsForUser($userId) as $transaction) {
				$available[(int) $transaction->id] = $transaction;
			}
			if (array_diff_key($requested, $available) !== []
				|| ($action === 'accept_all' && array_diff_key($available, $requested) !== [])) {
				throw new DomainException('The queue has changed. Reload the page before reviewing these transactions.');
			}

			if ($accepting) {
				$sheet = $database->prepare('SELECT id FROM sheets WHERE id = :sheet_id');
				$sheet->execute([':sheet_id' => $sheetId]);
				if ($sheetId === null || $sheet->fetchColumn() === false) {
					throw new InvalidArgumentException('Choose a destination sheet.');
				}
				$categories = $database->query('SELECT id FROM transaction_categories')->fetchAll(PDO::FETCH_COLUMN);
				$validCategories = array_fill_keys(array_map('intval', $categories), true);
				foreach ($requested as $categoryId) {
					if (!isset($validCategories[$categoryId])) {
						throw new InvalidArgumentException('Choose a valid transaction type for every transaction being accepted.');
					}
				}
				$insert = $database->prepare('INSERT INTO transactions (amount, description, date, sheet_id, category_id, card_id, provider_transaction_id) VALUES (:amount, :description, :date, :sheet_id, :category_id, :card_id, :provider_transaction_id)');
			}
			$delete = $database->prepare("DELETE FROM queued_transactions WHERE id = :id AND change_type = 'added'");
			foreach ($requested as $id => $categoryId) {
				$transaction = $available[$id];
				if ($accepting) {
					if ($transaction->amount === null || $transaction->description === null || $transaction->date === null) {
						throw new InvalidArgumentException('A transaction is missing its amount, name or date.');
					}
					$insert->execute([
						':amount' => $transaction->amount,
						':description' => $transaction->description,
						':date' => $transaction->date,
						':sheet_id' => $sheetId,
						':category_id' => $categoryId,
						':card_id' => $transaction->card_id,
						':provider_transaction_id' => $transaction->provider_transaction_id,
					]);
				}
				$delete->execute([':id' => $id]);
				if ($delete->rowCount() !== 1) {
					throw new DomainException('The queue has changed. Reload the page and try again.');
				}
			}
			$database->commit();
			return count($requested);
		} catch (Throwable $exception) {
			if ($database->inTransaction()) {
				$database->rollBack();
			}
			throw $exception;
		} finally {
			$database->setAttribute(PDO::ATTR_ERRMODE, $previousErrorMode);
		}
	}
}
