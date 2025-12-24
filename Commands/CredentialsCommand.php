<?php

use Phpkg\Business\Credential;
use Phpkg\Solution\Paths;
use function Phpkg\Infra\CLI\table;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;

/**
 * Lists all saved credentials for Git providers.
 * This command displays a table showing the providers and their associated tokens.
 */
return function () {
    line("Loading saved credentials...");

    $outcome = Credential\read();

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    $credentials = $outcome->data['credentials'];

    if (empty($credentials)) {
        line("No credentials found.");
        return 0;
    }

    // Display credentials file path
    $credentials_path = Paths\credentials();
    line("Credentials file: " . $credentials_path);
    line("");

    // Prepare table data
    $headers = ['Provider', 'Token'];
    $rows = [];

    foreach ($credentials as $provider => $token) {
        $rows[] = [$provider, $token];
    }

    // Display the table
    table($headers, $rows);

    return 0;
};
