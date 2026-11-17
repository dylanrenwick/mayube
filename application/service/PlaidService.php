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
		if (!is_array($result)) {
			throw new RuntimeException('Plaid returned an invalid response.');
		}

		if ($status < 200 || $status >= 300) {
			throw new PlaidApiException($result['error_code'] ?? 'UNKNOWN', $result['error_message'] ?? "Plaid HTTP {$status}");
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

		if (!isset($response['link_token']) || !is_string($response['link_token']) || $response['link_token'] === '') {
			throw new RuntimeException('Plaid did not return a link token.');
		}

		return $response['link_token'];
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

	public static function getAccounts(string $accessToken): array
	{
		$response = self::request('/accounts/get', ['access_token' => $accessToken]);
		$accounts = $response['accounts'] ?? null;
		if (!is_array($accounts) || $accounts === []) {
			throw new RuntimeException('Plaid did not return any accounts.');
		}
		foreach ($accounts as $account) {
			if (!is_array($account) || !is_string($account['account_id'] ?? null) || $account['account_id'] === ''
				|| !is_string($account['name'] ?? null) || !is_string($account['type'] ?? null)
				|| (isset($account['mask']) && !is_string($account['mask']))
				|| (isset($account['subtype']) && !is_string($account['subtype']))) {
				throw new RuntimeException('Plaid returned an invalid account.');
			}
		}
		return $accounts;
	}

	public static function syncTransactions(string $accessToken, ?string $cursor = null): array
	{
		$data = ['access_token' => $accessToken];

		if ($cursor !== null) {
			$data['cursor'] = $cursor;
		}

		return self::request('/transactions/sync', $data);
	}
}
