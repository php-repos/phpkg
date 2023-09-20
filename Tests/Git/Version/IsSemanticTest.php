<?php

namespace Tests\Git\Version\IsSemanticTest;

use function Phpkg\Git\Version\is_semantic;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should detect semantic versions',
    case: function () {
        assert_true(is_semantic('1.2.3'));
        assert_true(is_semantic('v1.2.3'));
        assert_false(is_semantic('development'));
        assert_false(is_semantic('1-2-3'));
    }
);
