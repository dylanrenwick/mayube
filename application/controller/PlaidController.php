<?php

class PlaidController extends Controller
{
	public function __construct()
	{
		parent::__construct();
		Auth::checkAdminAuthentication();
	}

	public function link(): void
	{
		$cardName = $_SESSION['new_card_name'] ?? null;
		$providerName = $_SESSION['new_card_provider'] ?? null;

		if (!is_string($cardName) || trim($cardName) === '' || $providerName !== 'plaid') {
			Session::add('feedback_negative', 'Start adding a Plaid card before opening Plaid Link.');
			Redirect::to('cards/add');
			return;
		}

		$this->View->render('plaid/link', [
			'card_name' => $cardName,
		]);
	}

	public function getlink(): void
	{
		$cardName = $_SESSION['new_card_name'] ?? null;
		if (!is_string($cardName) || trim($cardName) === '' || ($_SESSION['new_card_provider'] ?? null) !== 'plaid') {
			http_response_code(400);
			$this->View->renderJSON(['error' => 'Start adding a Plaid card before opening Plaid Link.']);
			return;
		}

		header('Cache-Control: no-store');
		try {
			$linkToken = PlaidService::createLinkToken((string) Session::get('user_id'));
		} catch (RuntimeException | JsonException $exception) {
			http_response_code(502);
			$this->View->renderJSON(['error' => 'Unable to start Plaid Link. Please try again.']);
			return;
		}

		$this->View->renderJSON(['link_token' => $linkToken]);
	}

	public function exchange(): void
	{
		if (!$this->validateCardPost()) {
			return;
		}
		$publicToken = $_POST['public_token'] ?? null;
		if (!is_string($publicToken) || trim($publicToken) === '') {
			$this->renderError(400, 'A public token is required.');
			return;
		}

		try {
			$userId = (string) $_SESSION['user_id'];
			$tokenHash = hash('sha256', $publicToken);
			$item = PlaidModel::getExchangedItem($userId, $tokenHash);
			if ($item === false) {
				$credentials = PlaidService::exchangePublicToken($publicToken);
				$accessToken = $credentials['access_token'] ?? null;
				$remoteItemId = $credentials['item_id'] ?? null;
				if (!is_string($accessToken) || $accessToken === '' || !is_string($remoteItemId) || $remoteItemId === '') {
					throw new RuntimeException('Plaid returned invalid credentials.');
				}
				// Persist credentials before fetching accounts, so retries do not
				// exchange the same one-time public token a second time.
				$itemId = PlaidModel::createItem($userId, $remoteItemId, $accessToken, $tokenHash);
			} else {
				$itemId = (int) $item->id;
				$encryptedToken = base64_decode($item->access_token, true);
				if ($encryptedToken === false) {
					throw new RuntimeException('Invalid stored credentials.');
				}
				$accessToken = Encryption::decrypt($encryptedToken);
				if ($accessToken === '') {
					throw new RuntimeException('Invalid stored credentials.');
				}
			}

			$accounts = PlaidModel::saveAccounts($itemId, PlaidService::getAccounts($accessToken));
			Session::set('new_card_plaid_item_id', $itemId);
			Session::set('new_card_plaid_account_ids', array_column($accounts, 'id'));
			Session::delete('new_card_provider_id');
			$this->View->renderJSON(['success' => true, 'accounts' => $accounts]);
		} catch (Exception $exception) {
			$this->renderError(500, 'Unable to connect your accounts. Please try again.');
		}
	}

	public function selectaccount(): void
	{
		if (!$this->validateCardPost()) {
			return;
		}
		$accountId = $_POST['account_id'] ?? null;
		$accountId = is_string($accountId)
			? filter_var($accountId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
			: false;
		$itemId = $_SESSION['new_card_plaid_item_id'] ?? null;
		$availableAccounts = $_SESSION['new_card_plaid_account_ids'] ?? [];
		if ($accountId === false || !is_int($itemId) || !is_array($availableAccounts)
			|| !in_array($accountId, $availableAccounts, true)) {
			$this->renderError(400, 'Select an account from this Plaid connection.');
			return;
		}

		try {
			if (!PlaidModel::accountBelongsToItem($accountId, $itemId, (string) $_SESSION['user_id'])) {
				$this->renderError(400, 'Select an account from this Plaid connection.');
				return;
			}
			Session::set('new_card_provider_id', $accountId);
			$this->View->renderJSON(['success' => true]);
		} catch (Exception $exception) {
			$this->renderError(500, 'Unable to select your account. Please try again.');
		}
	}

	private function validateCardPost(): bool
	{
		header('Cache-Control: no-store');
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			header('Allow: POST');
			$this->renderError(405, 'This endpoint requires a POST request.');
			return false;
		}
		if (!is_string($_POST['csrf_token'] ?? null) || !Csrf::isTokenValid()) {
			$this->renderError(403, 'Invalid form token. Reload this page and try again.');
			return false;
		}
		$name = $_SESSION['new_card_name'] ?? null;
		$userId = $_SESSION['user_id'] ?? null;
		if (!is_string($name) || trim($name) === '' || ($_SESSION['new_card_provider'] ?? null) !== 'plaid'
			|| (!is_int($userId) && !is_string($userId)) || (string) $userId === '') {
			$this->renderError(400, 'Start adding a Plaid card before connecting an account.');
			return false;
		}
		return true;
	}

	private function renderError(int $status, string $message): void
	{
		http_response_code($status);
		$this->View->renderJSON(['error' => $message]);
	}
}
