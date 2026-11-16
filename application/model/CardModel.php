<?php

class CardModel
{
	public static function getAllCards()
	{
		$database = DatabaseFactory::getFactory()->getConnection();

		$sql = <<<SQL
SELECT
	card.id,
	card.name
FROM cards AS card
ORDER BY card.id
SQL;
		$query = $database->prepare($sql);
		$query->execute();

		return $query->fetchAll();
	}

	public static function getCard(int $cardId)
	{
		$database = DatabaseFactory::getFactory()->getConnection();

		$sql = <<<SQL
SELECT
	card.id,
	card.name,
	card.provider,
	card.provider_id
FROM cards AS card
WHERE card.id = :card_id
ORDER BY card.id
SQL;
		$query = $database->prepare($sql);
		$query->bindValue(':card_id', $cardId, PDO::PARAM_INT);
		$query->execute();

		return $query->fetch();
	}

	public static function createCard(string $cardName, string $cardProvider, int $providerId)
	{
		$database = DatabaseFactory::getFactory()->getConnection();

		$sql = "INSERT INTO cards (name, provider, provider_id) VALUES (:card_name, :provider, :provider_id)";

		$query = $database->prepare($sql);
		$query->bindValue(':card_name', $cardName, PDO::PARAM_STR);
		$query->bindValue(':provider', $cardProvider, PDO::PARAM_STR);
		$query->bindValue(':provider_id', $providerId, PDO::PARAM_INT);
		$query->execute();

		if ($query->rowCount() == 1) {
			$sql = "SELECT id FROM sheets WHERE name = :card_name LIMIT 1";

			$query = $database->prepare($sql);
			$query->bindValue(':card_name', $cardName, PDO::PARAM_STR);
			$query->execute();

			$sheet_id = $query->fetch()->id;

			return $sheet_id;
		}

		// default return
		Session::add('feedback_negative', Text::get('FEEDBACK_NOTE_CREATION_FAILED'));
		return false;
	}
}
