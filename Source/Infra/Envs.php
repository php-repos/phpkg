<?php

namespace Phpkg\Infra\Envs;

use function Phpkg\Infra\Files\read_json_as_array;

/**
 * Retrieves an environment variable value with an optional default.
 *
 * Gets the value of an environment variable using PHP's getenv() function.
 * If the environment variable is not set or is empty, returns the default value.
 *
 * @param string $key The name of the environment variable to retrieve
 * @param mixed $default The default value to return if the environment variable is not set
 * @return mixed The environment variable value or the default value
 *
 * @example
 * ```php
 * $db_host = get('DB_HOST', 'localhost');
 * $api_key = get('API_KEY'); // Returns null if not set
 * $debug = get('DEBUG_MODE', false);
 * ```
 */
function get(string $key, mixed $default = null): mixed
{
    return getenv($key) ?: $default;
}

/**
 * Gets the temporary directory path string for phpkg operations.
 *
 * Creates a temporary directory path specifically for phpkg operations
 * by appending 'phpkg' to the system's temporary directory. This ensures
 * phpkg has its own isolated temporary space.
 *
 * @return string The Path string representing the phpkg temporary directory
 *
 * @example
 * ```php
 * $temp_path = temp_dir();
 * echo $temp_path; // Outputs something like: /tmp/phpkg
 * ```
 */
function temp_dir(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpkg' . DIRECTORY_SEPARATOR . phpkg_version();
}

/**
 * Reads the phpkg config.json file and returns its contents.
 *
 * @return array The config array with version and modules
 */
function phpkg_config(): array
{
    $phpkg_root = get('PHPKG_ROOT');
    $config_path = $phpkg_root . DIRECTORY_SEPARATOR . 'config.json';
    return read_json_as_array($config_path);
}

/**
 * Gets the phpkg version from config.json.
 *
 * @return string The version string
 */
function phpkg_version(): string
{
    $config = phpkg_config();
    return $config['version'] ?? '3.0.0';
}
