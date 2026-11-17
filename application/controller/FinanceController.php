<?php

/**
 * The post controller: Just an example of simple create, read, update and delete (CRUD) actions.
 */
class FinanceController extends Controller
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
		$this->View->render('finance/index');
	}

	public function queue(): void
	{
		header('Cache-Control: no-store');
		$this->View->render('finance/queue', [
			'transactions' => QueuedTransactionModel::getAdditionsForUser((string) Session::get('user_id')),
			'transaction_types' => FinanceModel::getAllCategories(),
		]);
	}

	public function reviewQueue(): void
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
			$this->View->renderJSON(['error' => 'Invalid form token. Reload the page and try again.']);
			return;
		}

		try {
			$action = $_POST['action'] ?? null;
			$items = $_POST['items'] ?? null;
			if (!is_string($action) || !is_string($items)) {
				throw new InvalidArgumentException('Invalid queue action.');
			}
			$items = json_decode($items, true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($items)) {
				throw new InvalidArgumentException('Invalid transaction selection.');
			}
			$count = QueuedTransactionModel::reviewAdditions((string) Session::get('user_id'), $action, $items);
			$verb = $action === 'reject' ? 'Rejected' : 'Accepted';
			Session::add('feedback_positive', $verb . ' ' . $count . ' transaction' . ($count === 1 ? '.' : 's.'));
			$this->View->renderJSON(['success' => true, 'count' => $count]);
		} catch (JsonException | InvalidArgumentException $exception) {
			http_response_code(400);
			$this->View->renderJSON(['error' => $exception instanceof JsonException ? 'Invalid transaction selection.' : $exception->getMessage()]);
		} catch (DomainException $exception) {
			http_response_code(409);
			$this->View->renderJSON(['error' => $exception->getMessage()]);
		} catch (Throwable $exception) {
			http_response_code(500);
			$this->View->renderJSON(['error' => 'Unable to review these transactions. Please try again.']);
		}
	}

	public function sheets(): void
	{
		$month = $_GET['month'] ?? date('Y-m');
		try {
			if (!is_string($month)) {
				throw new InvalidArgumentException('Choose a valid month.');
			}
			$monthStart = FinanceModel::getMonthStart($month);
		} catch (InvalidArgumentException $exception) {
			Session::add('feedback_negative', $exception->getMessage());
			Redirect::to('finance/sheets');
			return;
		}

		header('Cache-Control: no-store');
		$this->View->render('finance/sheet', [
			'month' => $month,
			'month_title' => $monthStart->format('F Y'),
			'default_date' => $month === date('Y-m') ? date('Y-m-d') : $monthStart->format('Y-m-d'),
			'transactions' => FinanceModel::getMonthTransactions((string) Session::get('user_id'), $month),
			'categories' => FinanceModel::getAllCategories(),
		]);
	}

	public function sheet(): void
	{
		$this->sheets();
	}

	public function createTransaction(): void
	{
		if (!$this->validateTransactionPost()) {
			return;
		}
		try {
			[$amount, $description, $categoryId, $date] = $this->transactionInput();
			FinanceModel::createTransaction((string) Session::get('user_id'), $amount, $description, $categoryId, $date);
			Session::add('feedback_positive', 'Transaction created.');
			Redirect::to('finance/sheets?month=' . rawurlencode(substr($date, 0, 7)));
			return;
		} catch (InvalidArgumentException $exception) {
			Session::add('feedback_negative', $exception->getMessage());
		} catch (Throwable $exception) {
			Session::add('feedback_negative', 'Unable to create transaction. Please try again.');
		}
		$month = $_POST['month'] ?? '';
		Redirect::to('finance/sheets' . (is_string($month) ? '?month=' . rawurlencode($month) : ''));
	}

	public function updateTransaction(): void
	{
		if (!$this->validateTransactionPost()) {
			return;
		}
		try {
			$id = $_POST['transaction_id'] ?? null;
			$id = is_string($id) ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
			if ($id === false) {
				throw new InvalidArgumentException('A valid transaction ID is required.');
			}
			[$amount, $description, $categoryId, $date] = $this->transactionInput();
			if (!FinanceModel::updateTransaction((string) Session::get('user_id'), $id, $amount, $description, $categoryId, $date)) {
				http_response_code(404);
				$this->View->renderJSON(['error' => 'Transaction not found.']);
				return;
			}
			$this->View->renderJSON(['success' => true]);
		} catch (InvalidArgumentException $exception) {
			http_response_code(400);
			$this->View->renderJSON(['error' => $exception->getMessage()]);
		} catch (Throwable $exception) {
			http_response_code(500);
			$this->View->renderJSON(['error' => 'Unable to update transaction. Please try again.']);
		}
	}

	private function validateTransactionPost(): bool
	{
		header('Cache-Control: no-store');
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			header('Allow: POST');
			http_response_code(405);
			$this->View->renderJSON(['error' => 'This endpoint requires a POST request.']);
			return false;
		}
		if (!is_string($_POST['csrf_token'] ?? null) || !Csrf::isTokenValid()) {
			http_response_code(403);
			$this->View->renderJSON(['error' => 'Invalid form token. Reload the page and try again.']);
			return false;
		}
		return true;
	}

	private function transactionInput(): array
	{
		foreach (['amount', 'title', 'category', 'timestamp'] as $key) {
			if (!is_string($_POST[$key] ?? null)) {
				throw new InvalidArgumentException('Enter an amount, name, transaction type and date.');
			}
		}
		$categoryId = filter_var($_POST['category'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($categoryId === false) {
			throw new InvalidArgumentException('Choose a valid transaction type.');
		}
		return [$_POST['amount'], $_POST['title'], $categoryId, $_POST['timestamp']];
	}
}
