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
}
