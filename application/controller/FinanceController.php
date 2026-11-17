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
		$this->View->render('finance/index', [
			"sheets" => FinanceModel::getAllSheets()
		]);
	}

	public function queue(): void
	{
		header('Cache-Control: no-store');
		$this->View->render('finance/queue', [
			'transactions' => QueuedTransactionModel::getAdditionsForUser((string) Session::get('user_id')),
			'transaction_types' => FinanceModel::getAllCategories(),
			'sheets' => FinanceModel::getAllSheets(),
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
			$sheetId = $_POST['sheet_id'] ?? null;
			$sheetId = is_string($sheetId)
				? filter_var($sheetId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
				: false;
			$count = QueuedTransactionModel::reviewAdditions(
				(string) Session::get('user_id'), $action, $items, $sheetId === false ? null : $sheetId
			);
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

	public function sheet(int $sheet_id): void
	{
		$sheet = FinanceModel::getSheet($sheet_id);
		if ($sheet === false) {
			$controller = new ErrorController();
			$controller->error404();
			return;
		}

		$this->View->renderMetaPreview($sheet->title, "", "");
		$this->View->render("finance/sheet", [
			"sheet" => $sheet,
			"transactions" => FinanceModel::getSheetTransactions($sheet_id),
			"categories" => FinanceModel::getAllCategories(),
		]);
	}

	public function create(): void
	{
		$sheet_title = Request::get('title');
		if (!$sheet_title) {
			Redirect::to('finance/index');
			return;
		}
		$sheet_id = FinanceModel::createSheet($sheet_title);
		if ($sheet_id === false) {
			Redirect::to('finance/index');
			return;
		}

		Redirect::to('finance/sheet/'.$sheet_id);	
	}

	public function createTransaction(): void
	{
		$sheet_id = Request::get('sheet_id');
		$amount = Request::get('amount');
		$description = Request::get('title');
		$category_id = Request::get('category');
		$timestamp = Request::get('timestamp');

		FinanceModel::createTransaction(
			$sheet_id,
			$amount,
			$description,
			$category_id,
			$timestamp
		);

		Redirect::to('finance/sheet/'.$sheet_id);	
	}

	public function updateTransaction(): void
	{
		$id = Request::get('transaction_id');
		$amount = Request::get('amount');
		$description = Request::get('title');
		$category_id = Request::get('category');
		$timestamp = Request::get('timestamp');

		$success = FinanceModel::updateTransaction(
			$id,
			$amount,
			$description,
			$category_id,
			$timestamp
		);

		if ($success) {
			$this->View->renderJSON([
				'success' => true,
				'new_state' => [
					'amount' => $amount,
					'title' => $description,
					'category' => $category_id,
					'date' => $timestamp
				]
			]);
		} else {
			$this->View->renderJSON([
				'success' => false,
				'input' => [
					'id' => $id,
					'amount' => $amount,
					'title' => $description,
					'category' => $category_id,
					'date' => $timestamp
				]
			]);
		}
	}
}
