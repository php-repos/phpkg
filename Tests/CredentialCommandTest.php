<?php

namespace Tests\CredentialCommandTest;

use PhpRepos\Cli\Output;
use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show success message when adding new provider',
    case: function () {
        $provider = 'unique-test-provider-' . time() . '.com';
        $token = 'test_token_12345';
        
        $output = CliRunner\phpkg('credential', [$provider, $token]);
        
        $expected = Output\capture(function () use ($provider) {
            Output\line("Adding credential for provider $provider...");
            Output\success('💾 Credentials file saved.');
        });
        
        Output\assert_output($expected, $output);
    }
);

test(
    title: 'it should prevent duplicate credentials for the same provider',
    case: function () {
        $provider = 'github.com';
        $token = 'ghp_different_token_67890';
        
        $output = CliRunner\phpkg('credential', [$provider, $token]);
        
        $expected = Output\capture(function () use ($provider) {
            Output\line("Adding credential for provider $provider...");
            Output\error('⚠️ There is a token for the given provider.');
        });
        
        Output\assert_output($expected, $output);
    }
);

test(
    title: 'it should handle empty provider gracefully',
    case: function () {
        $provider = '';
        $token = 'ghp_test_token_12345';
        
        $output = CliRunner\phpkg('credential', [$provider, $token]);
        
        // Should show error about failed credential addition
        Assertions\assert_true(str_contains($output, 'Failed to add credential'), 'Should handle empty provider gracefully');
    }
);

test(
    title: 'it should handle empty token gracefully',
    case: function () {
        $provider = 'github.com';
        $token = '';
        
        $output = CliRunner\phpkg('credential', [$provider, $token]);
        
        // Should show error about failed credential addition
        Assertions\assert_true(str_contains($output, 'Failed to add credential'), 'Should handle empty token gracefully');
    }
);
