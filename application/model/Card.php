<?php

class Card
{
	public function __construct(
		public int $id,
		public string $name,
		public string $provider,
		public int $provider_id
	) {
	}
}
