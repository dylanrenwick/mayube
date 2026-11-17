<?php

class PlaidApiException extends RuntimeException
{
	public function __construct(public string $errorCode, string $message)
	{
		parent::__construct($message);
	}
}
