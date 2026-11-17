<?php

class CardModel
{
	public static function getAllCards()
	{
		$database = DatabaseFactory::getFactory()->getConnection();

		$sql = <<<SQL
SELECT
	card.id,
	card.name,
	card.provider
FROM cards AS card
ORDER BY card.id
SQL;
		$query = $database->prepare($sql);
		$query->execute();

		return $query->fetchAll();
	}

	public static function getCard(int $cardId): Card|false
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

		$card = $query->fetch(PDO::FETCH_OBJ);
		if ($card === false) {
			return false;
		}
		return new Card((int) $card->id, $card->name, $card->provider, (int) $card->provider_id);
	}

	public static function createCard(string $cardName, string $cardProvider, int $providerId): int|false
	{
		$database = DatabaseFactory::getFactory()->getConnection();

		$sql = "INSERT INTO cards (name, provider, provider_id) VALUES (:card_name, :provider, :provider_id)";

		$query = $database->prepare($sql);
		$query->bindValue(':card_name', $cardName, PDO::PARAM_STR);
		$query->bindValue(':provider', $cardProvider, PDO::PARAM_STR);
		$query->bindValue(':provider_id', $providerId, PDO::PARAM_INT);
		$query->execute();

		if ($query->rowCount() == 1) {
			return (int) $database->lastInsertId();
		}

		// default return
		Session::add('feedback_negative', Text::get('FEEDBACK_NOTE_CREATION_FAILED'));
		return false;
	}
}
