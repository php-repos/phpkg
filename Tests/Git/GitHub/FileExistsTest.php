<?php

namespace Tests\Git\GitHub\FileExistsTest;

use Phpkg\Git\Exception\InvalidTokenException;
use function Phpkg\Environments\github_token;
use function Phpkg\Git\GitHub\file_exists;
use function Phpkg\System\environment;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return true when the file in the given repository at the given commit hash exists',
    case: function () {
        assert_true(file_exists('php-repos', 'test-runner', '30f3ce06c760719c7a107532b6755f9882c57b83', 'phpkg.config-lock.json', github_token(environment())));
    }
);

test(
    title: 'it should return false when the file in the given repository at the given commit hash exists',
    case: function () {
        assert_false(file_exists('php-repos', 'test-runner', '30f3ce06c760719c7a107532b6755f9882c57b83', 'not-exists.txt', github_token(environment())));
    }
);

test(
    title: 'it should throw exception when token is invalid',
    case: function () {
        try {
            file_exists('php-repos', 'test-runner', '30f3ce06c760719c7a107532b6755f9882c57b83', 'phpkg.config-lock.json', 'invalid');
            assert_true(false, 'Expected exception not happened!');
        } catch (InvalidTokenException $exception) {
            assert_true($exception->getMessage() === 'GitHub token is not valid.');
        }
    }
);

test(
    title: 'it should return false when the url is not valid',
    case: function () {
        assert_false(file_exists('php-repos', 'test-run', '30f3ce06c760719c7a107532b6755f9882c57b83', 'phpkg.config-lock.json', github_token(environment())));
    }
);
