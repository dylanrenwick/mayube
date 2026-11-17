<?php

class PlaidCardProvider implements CardProvider
{
	public function createCard(string $cardName): void
	{
		// CardsController has saved the pending card details in the session.
		Redirect::to('plaid/link');
	}

	public function syncTransactions(Card $card): void
	{
		$userId = $_SESSION['user_id'] ?? null;
		if ($card->provider !== 'plaid' || (!is_string($userId) && !is_int($userId)) || (string) $userId === '') {
			throw new InvalidArgumentException('Invalid card or user for Plaid sync.');
		}
		$item = PlaidModel::getSyncItem($card->provider_id, (string) $userId);
		if ($item === false) {
			throw new RuntimeException('Plaid item not found for this user.');
		}
		$encryptedToken = base64_decode($item->access_token, true);
		if ($encryptedToken === false) {
			throw new RuntimeException('Invalid stored Plaid credentials.');
		}
		$accessToken = Encryption::decrypt($encryptedToken);
		if ($accessToken === '') {
			throw new RuntimeException('Invalid stored Plaid credentials.');
		}

		// Fetch every page before writing anything. A failed pagination loop can
		// safely restart from the stored Item cursor without partial queue changes.
		for ($attempt = 0; $attempt < 3; $attempt++) {
			try {
				$changes = $this->fetchChanges($accessToken, $item->cursor);
				break;
			} catch (PlaidApiException $exception) {
				if ($exception->errorCode !== 'TRANSACTIONS_SYNC_MUTATION_DURING_PAGINATION' || $attempt === 2) {
					throw $exception;
				}
			}
		}

		$database = DatabaseFactory::getFactory()->getConnection();
		if (!$database->beginTransaction()) {
			throw new RuntimeException('Unable to start Plaid sync transaction.');
		}
		try {
			PlaidModel::lockSyncCursor((int) $item->id, (string) $userId, $item->cursor);
			$cards = [];
			foreach (PlaidModel::getSyncCards((int) $item->id) as $linkedCard) {
				$cards[$linkedCard->account_id][] = (int) $linkedCard->id;
			}
			foreach (['added', 'modified', 'removed'] as $changeType) {
				foreach ($changes[$changeType] as $transaction) {
					$accountId = $transaction['account_id'] ?? null;
					if (!is_string($accountId) || $accountId === '') {
						throw new RuntimeException('Plaid returned an invalid transaction account.');
					}
					if (!isset($cards[$accountId])) {
						// Discard transactions for accounts without a local card.
						continue;
					}
					$queued = $this->normalizeTransaction($transaction, $changeType);
					foreach ($cards[$accountId] as $cardId) {
						QueuedTransactionModel::upsert($cardId, $changeType, $queued);
					}
				}
			}
			PlaidModel::updateSyncCursor((int) $item->id, $changes['cursor']);
			if (!$database->commit()) {
				throw new RuntimeException('Unable to commit Plaid sync.');
			}
		} catch (Throwable $exception) {
			if ($database->inTransaction()) {
				$database->rollBack();
			}
			throw $exception;
		}
	}

	private function fetchChanges(string $accessToken, ?string $cursor): array
	{
		$changes = ['added' => [], 'modified' => [], 'removed' => []];
		$seenCursors = [];
		do {
			$page = PlaidService::syncTransactions($accessToken, $cursor);
			if (!is_bool($page['has_more'] ?? null) || !is_string($page['next_cursor'] ?? null)) {
				throw new RuntimeException('Invalid Plaid sync response.');
			}
			foreach (['added', 'modified', 'removed'] as $changeType) {
				if (!is_array($page[$changeType] ?? null)) {
					throw new RuntimeException('Invalid Plaid sync changes.');
				}
				foreach ($page[$changeType] as $transaction) {
					if (!is_array($transaction)) {
						throw new RuntimeException('Invalid Plaid transaction.');
					}
					$changes[$changeType][] = $transaction;
				}
			}
			$nextCursor = $page['next_cursor'];
			if ($page['has_more'] && ($nextCursor === $cursor || isset($seenCursors[$nextCursor]))) {
				throw new RuntimeException('Plaid sync pagination did not advance.');
			}
			$seenCursors[$nextCursor] = true;
			$cursor = $nextCursor;
		} while ($page['has_more']);
		$changes['cursor'] = $cursor;
		return $changes;
	}

	private function normalizeTransaction(array $transaction, string $changeType): array
	{
		$id = $transaction['transaction_id'] ?? null;
		if (!is_string($id) || $id === '') {
			throw new RuntimeException('Plaid returned an invalid transaction ID.');
		}
		$removed = $changeType === 'removed';
		$amount = $transaction['amount'] ?? null;
		$description = $transaction['merchant_name'] ?? $transaction['name'] ?? null;
		$date = $transaction['date'] ?? null;
		$pending = $transaction['pending'] ?? false;
		if (!$removed && ((!is_int($amount) && !is_float($amount)) || !is_finite((float) $amount)
			|| !is_string($description) || !is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
			|| !is_bool($pending))) {
			throw new RuntimeException('Plaid returned an invalid transaction.');
		}
		return [
			'provider_transaction_id' => $id,
			'amount' => $removed ? null : (string) $amount,
			'description' => $removed ? null : $description,
			'date' => $removed ? null : $date,
			'pending' => $removed ? false : $pending,
		];
	}
}
