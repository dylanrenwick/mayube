<?php

interface CardProvider
{
	/**
	 * Start provider-specific card creation, redirecting as needed.
	 * When complete, set $_SESSION['new_card_provider_id'] to the local account
	 * primary key and redirect to cards/create. CardsController preserves the
	 * name and provider in the session and inserts the card on that request.
	 * Never accept a provider ID directly from the submitted form.
	 */
	public function createCard(string $cardName): void;

	/** Fetch source changes into the manual-review queue, without posting transactions. */
	public function syncTransactions(Card $card): void;
}
