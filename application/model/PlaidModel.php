<?php

class PlaidModel
{
	public static function getExchangedItem(string $userId, string $publicTokenHash): object|false
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('SELECT id, access_token FROM plaid_item WHERE user_id = :user_id AND public_token_hash = :token_hash');
		if (!$query->execute([':user_id' => $userId, ':token_hash' => $publicTokenHash])) {
			throw new RuntimeException('Unable to retrieve Plaid item.');
		}
		return $query->fetch(PDO::FETCH_OBJ);
	}

	public static function createItem(string $userId, string $itemId, string $accessToken, string $publicTokenHash): int
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('INSERT INTO plaid_item (user_id, item_id, access_token, public_token_hash) VALUES (:user_id, :item_id, :access_token, :token_hash)');
		if (!$query->execute([
			':user_id' => $userId,
			':item_id' => $itemId,
			':access_token' => base64_encode(Encryption::encrypt($accessToken)),
			':token_hash' => $publicTokenHash,
		])) {
			throw new RuntimeException('Unable to save Plaid item.');
		}
		return (int) $database->lastInsertId();
	}

	public static function saveAccounts(int $itemId, array $accounts): array
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$database->beginTransaction();
		try {
			$find = $database->prepare('SELECT id FROM plaid_account WHERE plaid_item_id = :item_id AND account_id = :account_id');
			$insert = $database->prepare('INSERT INTO plaid_account (plaid_item_id, account_id, name, mask, type, subtype) VALUES (:item_id, :account_id, :name, :mask, :type, :subtype)');
			$result = [];
			foreach ($accounts as $account) {
				if (!$find->execute([':item_id' => $itemId, ':account_id' => $account['account_id']])) {
					throw new RuntimeException('Unable to retrieve Plaid account.');
				}
				$id = $find->fetchColumn();
				if ($id === false) {
					if (!$insert->execute([
						':item_id' => $itemId,
						':account_id' => $account['account_id'],
						':name' => $account['name'],
						':mask' => $account['mask'] ?? null,
						':type' => $account['type'],
						':subtype' => $account['subtype'] ?? null,
					])) {
						throw new RuntimeException('Unable to save Plaid account.');
					}
					$id = $database->lastInsertId();
				}
				$result[] = [
					'id' => (int) $id,
					'name' => $account['name'],
					'mask' => $account['mask'] ?? null,
					'type' => $account['type'],
					'subtype' => $account['subtype'] ?? null,
				];
			}
			$database->commit();
			return $result;
		} catch (Throwable $exception) {
			if ($database->inTransaction()) {
				$database->rollBack();
			}
			throw $exception;
		}
	}

	public static function accountBelongsToItem(int $accountId, int $itemId, string $userId): bool
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('SELECT account.id FROM plaid_account AS account JOIN plaid_item AS item ON item.id = account.plaid_item_id WHERE account.id = :account_id AND item.id = :item_id AND item.user_id = :user_id');
		if (!$query->execute([':account_id' => $accountId, ':item_id' => $itemId, ':user_id' => $userId])) {
			throw new RuntimeException('Unable to retrieve Plaid account.');
		}
		return $query->fetchColumn() !== false;
	}

	public static function getSyncItem(int $accountId, string $userId): object|false
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('SELECT item.id, item.access_token, item.`cursor` FROM plaid_item AS item JOIN plaid_account AS account ON account.plaid_item_id = item.id WHERE account.id = :account_id AND item.user_id = :user_id');
		if (!$query->execute([':account_id' => $accountId, ':user_id' => $userId])) {
			throw new RuntimeException('Unable to retrieve Plaid item.');
		}
		return $query->fetch(PDO::FETCH_OBJ);
	}

	/** Read linked cards inside the sync transaction; unlinked accounts are ignored. */
	public static function getSyncCards(int $itemId): array
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare("SELECT card.id, account.account_id FROM cards AS card JOIN plaid_account AS account ON card.provider_id = account.id WHERE card.provider = 'plaid' AND account.plaid_item_id = :item_id");
		if (!$query->execute([':item_id' => $itemId])) {
			throw new RuntimeException('Unable to retrieve cards for Plaid item.');
		}
		return $query->fetchAll(PDO::FETCH_OBJ);
	}

	/** Lock the Item before checking the cursor to serialize concurrent sync writes. */
	public static function lockSyncCursor(int $itemId, string $userId, ?string $expectedCursor): void
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$sql = 'SELECT `cursor` FROM plaid_item WHERE id = :item_id AND user_id = :user_id';
		if ($database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
			$sql .= ' FOR UPDATE';
		}
		$query = $database->prepare($sql);
		if (!$query->execute([':item_id' => $itemId, ':user_id' => $userId])) {
			throw new RuntimeException('Unable to retrieve Plaid sync cursor.');
		}
		$item = $query->fetch(PDO::FETCH_OBJ);
		if ($item === false || $item->cursor !== $expectedCursor) {
			throw new RuntimeException('Plaid item changed during sync. Please retry.');
		}
	}

	public static function updateSyncCursor(int $itemId, string $cursor): void
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('UPDATE plaid_item SET `cursor` = :cursor WHERE id = :item_id');
		if (!$query->execute([':cursor' => $cursor, ':item_id' => $itemId])) {
			throw new RuntimeException('Unable to save Plaid sync cursor.');
		}
	}
}
