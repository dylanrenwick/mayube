<?php

class CardProviderFactory
{
	public static function create(string $providerName): ?CardProvider
	{
		if ($providerName === 'plaid') {
			return new PlaidCardProvider();
		}

		return null;
	}
}
