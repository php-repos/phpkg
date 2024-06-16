<?php

namespace Tests\Git\GitHub\FileContentTest;

use Phpkg\Git\Exception\InvalidTokenException;
use Phpkg\Git\Exception\NotFoundException;
use function Phpkg\Environments\github_token;
use function Phpkg\Git\GitHub\file_content;
use function Phpkg\System\environment;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return the file content from the given repository on the given commit hash',
    case: function () {
        $file_content = file_content('php-repos', 'test-runner', '30f3ce06c760719c7a107532b6755f9882c57b83', 'phpkg.config-lock.json', github_token(environment()));

        $expected = <<<EOD
{
    "packages": {
        "https:\/\/github.com\/php-repos\/file-manager.git": {
            "owner": "php-repos",
            "repo": "file-manager",
            "version": "v1.0.0",
            "hash": "b120a464839922b0a208bc198fbc06b491f08ee0"
        },
        "git@github.com:php-repos\/test-runner.git": {
            "owner": "php-repos",
            "repo": "test-runner",
            "version": "v1.0.0",
            "hash": "c5ee7b5d9a228b6e833af414359d486609ee530d"
        },
        "https:\/\/github.com\/php-repos\/cli.git": {
            "owner": "php-repos",
            "repo": "cli",
            "version": "v1.0.0",
            "hash": "f7c1eecaee1fbf01f4ea90a375ae8a3cd4944b3e"
        },
        "git@github.com:php-repos\/datatype.git": {
            "owner": "php-repos",
            "repo": "datatype",
            "version": "v1.0.0",
            "hash": "e802ba8c0cb2ffe2282de401bbf9e84a4ce1316a"
        }
    }
}
EOD;

        assert_true(trim($expected) === trim($file_content));
    }
);

test(
    title: 'it should throw exception when token is invalid',
    case: function () {
        try {
            file_content('php-repos', 'test-runner', '30f3ce06c760719c7a107532b6755f9882c57b83', 'phpkg.config-lock.json', 'invalid');
            assert_true(false, 'Expected exception not happened!');
        } catch (InvalidTokenException $exception) {
            assert_true($exception->getMessage() === 'GitHub token is not valid.');
        }
    }
);

test(
    title: 'it should throw exception when the url is not valid',
    case: function () {
        try {
            file_content('php-repos', 'test-run', '30f3ce06c760719c7a107532b6755f9882c57b83', 'phpkg.config-lock.json', github_token(environment()));
            assert_true(false, 'Expected exception not happened!');
        } catch (NotFoundException $exception) {
            assert_true($exception->getMessage() === 'The endpoint not found.');
        }
    }
);
