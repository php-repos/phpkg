<?php

namespace Tests\Git\GitHub\GetJsonTest;

use Phpkg\Git\Exception\InvalidTokenException;
use PhpRepos\FileManager\FileType\Json;
use function Phpkg\Providers\GitHub\get_json;
use function Phpkg\Providers\GitHub\github_token;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Runner\test;
use const Phpkg\Providers\GitHub\GITHUB_DOMAIN;

test(
    title: 'it should get json response from github api',
    case: function () {
        assert_true('TestRunner package for phpkg' === get_json('repos/php-repos/test-runner')['description']);
    },
    before: function () {
        $credentials = Json\to_array(realpath(root() . 'credentials.json'));
        github_token($credentials[GITHUB_DOMAIN]['token']);
    }
);

test(
    title: 'it should throw exception when token is not valid',
    case: function () {
        try {
            get_json('repos/php-repos/phpkg');
            assert_false(true, 'It should not pass');
        } catch (InvalidTokenException $exception) {
            assert_true($exception->getMessage() === 'GitHub token is not valid.');
        }
    },
    before: function () {
        github_token('');
    }
);
