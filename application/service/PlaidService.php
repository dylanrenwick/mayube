<?php

class PlaidService
{
	private static function request(string $endpoint, array $data): array
	{
		$env = Config::get('PLAID_ENV') ?: 'sandbox';

		if (!in_array($env, ['sandbox', 'production'], true)) {
			throw new RuntimeException('Invalid Plaid environment');
		}

		$url = "https://{$env}.plaid.com{$endpoint}";

		$curl = curl_init($url);

		curl_setopt_array(
			$curl,
			[
				CURLOPT_POST => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'PLAID-CLIENT-ID: ' . Config::get('PLAID_CLIENT'),
					'PLAID-SECRET: ' . Config::get('PLAID_SECRET'),
				],
				CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
				CURLOPT_TIMEOUT => 30,
			]
		);

		$response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ($response === false) {
			throw new RuntimeException(curl_error($curl));
		}

		$result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

		if ($status < 200 || $status > 300) {
			throw new RuntimeException($result['error_message'] ?? "Plaid HTTP {$status}");
		}

		return $result;
	}

	public static function createLinkToken(string $userId): string
	{
		$response = self::request(
			'/link/token/create',
			[
				'client_name' => 'Mayube Site',
				'language' => 'en',
				'country_codes' => ['CA'],
				'products' => ['transactions'],
				'user' => [
					'client_user_id' => $userId
				],
			]
		);
	}

	public static function exchangePublicToken(string $publicToken): array
	{
		return self::request(
			'/item/public_token/exchange',
			[
				'public_token' => $publicToken,
			]
		);
	}

	public static function syncTransactions(string $accessToken, ?string $cursor = null): array
	{
		$data = ['access_token' => $accessToken];

		if ($cursor !== null) {
			$data['cursor'] = $cursor;
		}

		return $this->request('/transactions/sync', $data);
	}
}
