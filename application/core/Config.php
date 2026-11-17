<?php

class Config
{
    // this is public to allow better Unit Testing
    public static array $config;

    private static bool $isLoaded = false;

	public static function load(string $path): bool
	{
		if (!file_exists($path)) {
			return false;
		}

		self::$config = require $path;
		self::$isLoaded = true;
		return true;
	}

    public static function get(string $key): mixed
    {
        if (!self::$isLoaded) {
            $config_file =
                __DIR__ . "/../config/config." . Environment::get() . ".php";

			if (!self::load($config_file)) {
				return false;
			}
        }

        return self::$config[$key];
    }
}
