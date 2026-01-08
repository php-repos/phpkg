<?php

namespace Phpkg\Infra\Logs;

use PhpRepos\Logger\API\Logs;

/**
 * Logs an informational message with optional data.
 *
 * This function creates and logs an informational message using the underlying
 * PhpRepos logging system. It's a convenience wrapper that automatically
 * sets the log level to 'info' and handles the message formatting.
 *
 * @param string $message The message to be logged
 * @param array $data Optional associative array of data to include with the log entry
 * @return void
 *
 * @example
 * ```php
 * log('Package installation started', [
 *     'package' => 'php-repos/cli',
 *     'version' => '1.0.0',
 *     'user' => 'developer'
 * ]);
 * ```
 */
function log(string $message, array $data = []): void
{
    Logs\log($message, 'INFO', $data);
}

function debug(string $message, array $data = []): void
{
    Logs\debug($message, $data);
}

function notice(string $message, array $data = []): void
{
    Logs\notice($message, $data);
}

function error(string $message, array $data = []): void
{
    Logs\error($message, $data);
}
