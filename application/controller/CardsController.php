<?php

/**
 * The post controller: Just an example of simple create, read, update and delete (CRUD) actions.
 */
class CardsController extends Controller
{
	public function __construct()
	{
		parent::__construct();

		// special authentication check for the entire controller: Note the check-ADMIN-authentication!
		// All methods inside this controller are only accessible for admins (= users that have role type 7)
		Auth::checkAdminAuthentication();
	}
	
	public function index(): void
	{
		$this->View->render('cards/index', [
			"cards" => CardModel::getAllCards()
		]);
	}

	public function add(): void
	{
		$providers = Config::get('CARD_PROVIDERS');
		if (!isset($providers)) {
			$providers = [];
		}

		$this->View->render('cards/add', [
			"providers" => $providers
		]);
	}

	public function create(): void
	{
		if (!isset($_SESSION['new_card_provider_id'])) {
			if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
				Redirect::to('cards/add');
				return;
			}

			if (!is_string($_POST['csrf_token'] ?? null) || !Csrf::isTokenValid()) {
				Session::add('feedback_negative', 'Invalid form token. Please try again.');
				Redirect::to('cards/add');
				return;
			}

			// Reject array inputs before passing them to Request's string accessors.
			if (!is_string($_POST['name'] ?? null) || !is_string($_POST['provider'] ?? null)) {
				Session::add('feedback_negative', 'Enter a card name and select a provider.');
				Redirect::to('cards/add');
				return;
			}

			$name = trim(Request::post('name'));
			$providerName = Request::post('provider');
			$providers = Config::get('CARD_PROVIDERS');

			if ($name === '' || !is_array($providers) || !in_array($providerName, $providers, true)) {
				Session::add('feedback_negative', 'Enter a card name and select an available provider.');
				Redirect::to('cards/add');
				return;
			}

			Session::set('new_card_name', $name);
			Session::set('new_card_provider', $providerName);
			Session::delete('new_card_plaid_item_id');
			Session::delete('new_card_plaid_account_ids');
		}

		try {
			if (!isset($_SESSION['new_card_provider_id'])) {
				$provider = CardProviderFactory::create($providerName);
				if ($provider === null) {
					throw new InvalidArgumentException('Unsupported card provider.');
				}
				$provider->createCard($name);
				// The provider sets new_card_provider_id and redirects back here.
				return;
			}

			// Read the original values, without Session::get()'s HTML escaping.
			$name = $_SESSION['new_card_name'] ?? null;
			$providerName = $_SESSION['new_card_provider'] ?? null;
			$providerId = $_SESSION['new_card_provider_id'];

			if (!is_string($name) || trim($name) === '' || !is_string($providerName) || $providerName === ''
				|| (!is_int($providerId) && !is_string($providerId))) {
				throw new RuntimeException('Invalid pending card creation.');
			}

			$providerId = filter_var($providerId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			if ($providerId === false) {
				throw new RuntimeException('Invalid provider ID.');
			}

			if (CardModel::createCard($name, $providerName, $providerId) === false) {
				$this->clearPendingCardCreation();
				Redirect::to('cards/add');
				return;
			}
		} catch (InvalidArgumentException | RuntimeException $exception) {
			$this->clearPendingCardCreation();
			Session::add('feedback_negative', 'Unable to create the card. Please try again.');
			Redirect::to('cards/add');
			return;
		}

		$this->clearPendingCardCreation();
		Redirect::to('cards/index');
	}

	public function sync(?string $cardId = null): void
	{
		header('Cache-Control: no-store');
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			header('Allow: POST');
			http_response_code(405);
			$this->View->renderJSON(['error' => 'This endpoint requires a POST request.']);
			return;
		}
		if (!is_string($_POST['csrf_token'] ?? null) || !Csrf::isTokenValid()) {
			http_response_code(403);
			$this->View->renderJSON(['error' => 'Invalid form token. Please try again.']);
			return;
		}

		$cardId = $cardId ?? ($_POST['card_id'] ?? null);
		$cardId = is_string($cardId)
			? filter_var($cardId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
			: false;
		if ($cardId === false) {
			http_response_code(400);
			$this->View->renderJSON(['error' => 'A valid card ID is required.']);
			return;
		}

		try {
			$card = CardModel::getCard($cardId);
			if ($card === false) {
				http_response_code(404);
				$this->View->renderJSON(['error' => 'Card not found.']);
				return;
			}
			$provider = CardProviderFactory::create($card->provider);
			if ($provider === null) {
				http_response_code(400);
				$this->View->renderJSON(['error' => 'Unsupported card provider.']);
				return;
			}
			$provider->syncTransactions($card);
			$this->View->renderJSON(['success' => true]);
		} catch (Exception $exception) {
			http_response_code(500);
			$this->View->renderJSON(['error' => 'Unable to sync this card. Please try again.']);
		}
	}

	private function clearPendingCardCreation(): void
	{
		Session::delete('new_card_name');
		Session::delete('new_card_provider');
		Session::delete('new_card_provider_id');
		Session::delete('new_card_plaid_item_id');
		Session::delete('new_card_plaid_account_ids');
	}
}
