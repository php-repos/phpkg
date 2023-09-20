<?php

namespace Tests\Git\Version\CompareTest;

use function Phpkg\Git\Version\compare;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should compare when patch versions are different',
    case: function () {
        $version1 = "1.2.3";
        $version2 = "1.2.4";

        assert_true(0 === compare($version1, $version1));
        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when minor versions are different',
    case: function () {
        $version1 = "1.2.3";
        $version2 = "1.3.3";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when major versions are different',
    case: function () {
        $version1 = "1.2.3";
        $version2 = "2.0.0";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when versions start with "v" and have major.minor.patch',
    case: function () {
        $version1 = "v1.2.3";
        $version2 = "v1.2.4";

        assert_true(0 === compare($version1, $version1));
        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when major versions are different and start with "v"',
    case: function () {
        $version1 = "v1.2.3";
        $version2 = "v2.0.0";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when minor versions are different and start with "v"',
    case: function () {
        $version1 = "v1.2.3";
        $version2 = "v1.3.0";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when patch versions are different and start with "v"',
    case: function () {
        $version1 = "v1.2.3";
        $version2 = "v1.2.4";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);
