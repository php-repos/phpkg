<?php

namespace Tests\System\CredentialCommand\CredentialCommandTest;

use Saeghe\Cli\IO\Write;
use function Saeghe\Saeghe\FileManager\File\delete;
use function Saeghe\Saeghe\FileManager\Path\realpath;
use function Saeghe\Saeghe\FileManager\File\move;

test(
    title: 'it should set credential for github.com',
    case: function () {
        $token = 'a_token';
        $output = shell_exec($_SERVER['PWD'] . "/saeghe credential github.com $token");

        Write\assert_success('Credential for github.com has been set successfully.', $output);

        assert(
            ['github.com' => ['token' => $token]]
            ===
            json_decode(file_get_contents(realpath($_SERVER['PWD'] . '/credentials.json')), true, JSON_THROW_ON_ERROR),
            'Credential content is not set properly!'
        );
    },
    before: function () {
        if (file_exists(realpath($_SERVER['PWD'] . '/credentials.json'))) {
            move(realpath($_SERVER['PWD'] . '/credentials.json'), realpath($_SERVER['PWD'] . '/credentials.json.back'));
        }
    },
    after: function () {
        delete(realpath($_SERVER['PWD'] . '/credentials.json'));
        if (file_exists(realpath($_SERVER['PWD'] . '/credentials.json.back'))) {
            move(realpath($_SERVER['PWD'] . '/credentials.json.back'), realpath($_SERVER['PWD'] . '/credentials.json'));
        }
    },
);

test(
    title: 'it should add credential for github.com',
    case: function () {
        $token = 'a_token';
        $output = shell_exec($_SERVER['PWD'] . "/saeghe credential github.com $token");

        Write\assert_success('Credential for github.com has been set successfully.', $output);

        assert(
            ['gitlab.com' => ['token' => 'gitlab-token'], 'github.com' => ['token' => $token]]
            ===
            json_decode(file_get_contents(realpath($_SERVER['PWD'] . '/credentials.json')), true, JSON_THROW_ON_ERROR),
            'Credential content is not set properly!'
        );
    },
    before: function () {
        if (file_exists(realpath($_SERVER['PWD'] . '/credentials.json'))) {
            move(realpath($_SERVER['PWD'] . '/credentials.json'), realpath($_SERVER['PWD'] . '/credentials.json.back'));
        }

        $credential = fopen(realpath($_SERVER['PWD'] . '/credentials.json'), "w");
        fwrite($credential, json_encode(['gitlab.com' => ['token' => 'gitlab-token']], JSON_PRETTY_PRINT));
        fclose($credential);
    },
    after: function () {
        delete(realpath($_SERVER['PWD'] . '/credentials.json'));
        if (file_exists(realpath($_SERVER['PWD'] . '/credentials.json.back'))) {
            move(realpath($_SERVER['PWD'] . '/credentials.json.back'), realpath($_SERVER['PWD'] . '/credentials.json'));
        }
    },
);