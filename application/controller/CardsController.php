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
}
