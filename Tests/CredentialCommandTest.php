<?php

namespace Tests\CredentialCommandTest;

use Phpkg\Infra\CLI;
use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show success message when adding new provider',
    case: function () {
        $provider = 'it-should-save-' . time() . '.com';
        $token = 'test_token_12345';
        
        $output = CliRunner\phpkg('credential', [$provider, $token]);
        
        $expected = CLI\capture(function () use ($provider) {
            CLI\line("Adding credential for provider $provider...");
            CLI\success('💾 Credentials file saved.');
        });
        
        Assertions\assert_true($expected === $output);
    }
);

test(
    title: 'it should prevent duplicate credentials for the same provider',
  case: function () {
        $provider = 'it-should-prevent-' . time() . '.com';
        $token = 'test_token_12345';
        
        $output = CliRunner\phpkg('credential', [$provider, $token]);
        
        $expected = CLI\capture(function () use ($provider) {
            CLI\line("Adding credential for provider $provider...");
            CLI\success('💾 Credentials file saved.');
        });
        
        Assertions\assert_true($expected === $output);

        $token = 'ghp_different_token_67890';
        
        $output = CliRunner\phpkg('credential', [$provider, $token]);
        
        $expected =  CLI\capture(function () use ($provider) {
             CLI\line("Adding credential for provider $provider...");
             CLI\error('⚠️ There is a token for the given provider.');
        });
        
        Assertions\assert_true($expected === $output);
    }
);

test(
    title: 'it should replace existing token when --force flag is used',
    case: function () {
        $provider = 'it-should-force-' . time() . '.com';
        $old_token = 'ghp_orignal_token';

        $output = CliRunner\phpkg('credential', [$provider, $old_token]);
    
        $expected =  CLI\capture(function () use ($provider) {
             CLI\line("Adding credential for provider $provider...");
            CLI\success('💾 Credentials file saved.');
        });

        Assertions\assert_true($expected === $output);

        $token = 'ghp_new_token_with_force_12345';

        $output = CliRunner\phpkg('credential', [$provider, $token, '--force']);

        $expected =  CLI\capture(function () use ($provider) {
             CLI\line("Adding credential for provider $provider...");
            CLI\success('💾 Credentials file saved.');
        });
        
        Assertions\assert_true($expected === $output);
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
