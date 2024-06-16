<?php

namespace Tests\Git\GitHub\GetJsonTest;

use function Phpkg\Environments\github_token;
use function Phpkg\Git\GitHub\get_json;
use function Phpkg\Http\Response\Responses\to_array;
use function Phpkg\System\environment;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should get json response from github api',
    case: function () {
        $response = get_json('repos/php-repos/test-runner', github_token(environment()))->response;

        assert_true('TestRunner package for phpkg' === to_array($response)['description']);
    }
);
