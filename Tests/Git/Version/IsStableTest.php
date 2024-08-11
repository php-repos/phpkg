<?php

namespace Tests\Git\Version\IsStableTest;

use function Phpkg\Git\Version\is_stable;
use function PhpRepos\TestRunner\Assertions\assert_false;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should consider semantic version without any addition as stable',
    case: function () {
        $version = '1.2.3';

        assert_true(is_stable($version));
    }
);

test(
    title: 'it should consider semantic version as stable when starts with v',
    case: function () {
        $version = 'v1.2.3';

        assert_true(is_stable($version));
    }
);

test(
    title: 'it should consider semantic version as stable when starts with V',
    case: function () {
        $version = 'V1.2.3';

        assert_true(is_stable($version));
    }
);

test(
    title: 'it should not consider alpha version as stable',
    case: function () {
        $version = '1.2.3-alpha';

        assert_false(is_stable($version));
    }
);

test(
    title: 'it should not consider version when there is appendix',
    case: function () {
        $version = '1.2.3beta1';

        assert_false(is_stable($version));
    }
);

test(
    title: 'it should detect using all supported separators',
    case: function () {
        assert_false(is_stable('1.2.3.alpha'));
        assert_false(is_stable('1.2.3-alpha'));
        assert_false(is_stable('1.2.3+alpha'));
        assert_false(is_stable('1.2.3_alpha'));
        assert_false(is_stable('1-2-3-alpha'));
        assert_false(is_stable('1_2_3-alpha'));
        assert_false(is_stable('1+2+3-alpha'));
    }
);
