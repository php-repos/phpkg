<?php

namespace Tests\System\CredentialCommand\CredentialCommandTest;

use PhpRepos\Cli\IO\Write;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\File\move;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should set credential for github.com',
    case: function () {
        $token = 'a_token';
        $output = shell_exec('php ' . root() . 'phpkg credential github.com ' . $token);

        Write\assert_success('Credential for github.com has been set successfully.', $output);

        assert_true(
            ['github.com' => ['token' => $token]]
            ===
            JsonFile\to_array(realpath(root() . 'credentials.json')),
            'Credential content is not set properly!'
        );
    },
    before: function () {
        if (file_exists(realpath(root() . 'credentials.json'))) {
            move(realpath(root() . 'credentials.json'), realpath(root() . 'credentials.json.back'));
        }
    },
    after: function () {
        delete(realpath(root() . 'credentials.json'));
        if (file_exists(realpath(root() . 'credentials.json.back'))) {
            move(realpath(root() . 'credentials.json.back'), realpath(root() . 'credentials.json'));
        }
    },
);

test(
    title: 'it should add credential for github.com',
    case: function () {
        $token = 'a_token';
        $output = shell_exec('php ' . root() . 'phpkg credential github.com ' . $token);

        Write\assert_success('Credential for github.com has been set successfully.', $output);

        assert_true(
            ['gitlab.com' => ['token' => 'gitlab-token'], 'github.com' => ['token' => $token]]
            ===
            JsonFile\to_array(realpath(root() . 'credentials.json')),
            'Credential content is not set properly!'
        );
    },
    before: function () {
        if (file_exists(realpath(root() . 'credentials.json'))) {
            move(realpath(root() . 'credentials.json'), realpath(root() . 'credentials.json.back'));
        }

        $credential = fopen(realpath(root() . 'credentials.json'), "w");
        fwrite($credential, json_encode(['gitlab.com' => ['token' => 'gitlab-token']], JSON_PRETTY_PRINT));
        fclose($credential);
    },
    after: function () {
        delete(realpath(root() . 'credentials.json'));
        if (file_exists(realpath(root() . 'credentials.json.back'))) {
            move(realpath(root() . 'credentials.json.back'), realpath(root() . 'credentials.json'));
        }
    },
);
