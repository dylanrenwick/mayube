<?php

class FinanceModel
{
	public static function getMonthStart(string $month): DateTimeImmutable
	{
		if (!preg_match('/^(?!0000)\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
			throw new InvalidArgumentException('Choose a valid month.');
		}
		return new DateTimeImmutable($month . '-01');
	}

	public static function getMonthTransactions(string $userId, string $month): array
	{
		$start = self::getMonthStart($month);
		$database = DatabaseFactory::getFactory()->getConnection();
		$sql = <<<SQL
SELECT transaction_record.id, transaction_record.amount, transaction_record.date,
	transaction_record.description, category.id AS category_id,
	category.name AS category_name, card.name AS card_name
FROM transactions AS transaction_record
JOIN transaction_categories AS category ON category.id = transaction_record.category_id
LEFT JOIN cards AS card ON card.id = transaction_record.card_id
WHERE transaction_record.user_id = :user_id
	AND transaction_record.date >= :month_start
	AND transaction_record.date < :next_month
ORDER BY transaction_record.date DESC, transaction_record.id DESC
SQL;
		$query = $database->prepare($sql);
		if (!$query->execute([
			':user_id' => $userId,
			':month_start' => $start->format('Y-m-d'),
			':next_month' => $start->modify('+1 month')->format('Y-m-d'),
		])) {
			throw new RuntimeException('Unable to retrieve transactions.');
		}
		return $query->fetchAll(PDO::FETCH_OBJ);
	}

	public static function getAllCategories(): array
	{
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('SELECT id, name FROM transaction_categories ORDER BY name, id');
		if (!$query->execute()) {
			throw new RuntimeException('Unable to retrieve transaction types.');
		}
		return $query->fetchAll(PDO::FETCH_OBJ);
	}

	public static function createTransaction(string $userId, string $amount, string $description, int $categoryId, string $date): int
	{
		self::validateTransaction($amount, $description, $categoryId, $date);
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('INSERT INTO transactions (user_id, amount, description, category_id, date) VALUES (:user_id, :amount, :description, :category_id, :date)');
		if (!$query->execute([
			':user_id' => $userId,
			':amount' => $amount,
			':description' => trim($description),
			':category_id' => $categoryId,
			':date' => $date,
		])) {
			throw new RuntimeException('Unable to create transaction.');
		}
		return (int) $database->lastInsertId();
	}

	public static function updateTransaction(string $userId, int $id, string $amount, string $description, int $categoryId, string $date): bool
	{
		self::validateTransaction($amount, $description, $categoryId, $date);
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('UPDATE transactions SET amount = :amount, description = :description, category_id = :category_id, date = :date WHERE id = :id AND user_id = :user_id');
		if (!$query->execute([
			':amount' => $amount,
			':description' => trim($description),
			':category_id' => $categoryId,
			':date' => $date,
			':id' => $id,
			':user_id' => $userId,
		])) {
			throw new RuntimeException('Unable to update transaction.');
		}
		return $query->rowCount() === 1;
	}

	private static function validateTransaction(string $amount, string $description, int $categoryId, string $date): void
	{
		$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		if (!is_numeric($amount) || !is_finite((float) $amount) || trim($description) === ''
			|| $parsedDate === false || $parsedDate->format('Y-m-d') !== $date || substr($date, 0, 4) === '0000') {
			throw new InvalidArgumentException('Enter a valid amount, name and date.');
		}
		$database = DatabaseFactory::getFactory()->getConnection();
		$query = $database->prepare('SELECT id FROM transaction_categories WHERE id = :category_id');
		if (!$query->execute([':category_id' => $categoryId])) {
			throw new RuntimeException('Unable to retrieve transaction type.');
		}
		if ($query->fetchColumn() === false) {
			throw new InvalidArgumentException('Choose a valid transaction type.');
		}
	}
}
