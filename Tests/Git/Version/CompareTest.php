<?php

namespace Tests\Git\Version\CompareTest;

use function Phpkg\Git\Version\compare;
use function PhpRepos\TestRunner\Assertions\assert_true;
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
    title: 'it should compare when portion',
    case: function () {
        $version1 = "1";
        $version2 = "1.2.4";

        assert_true(0 === compare($version1, $version1));
        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when there is leading or trailing spaces',
    case: function () {
        $version1 = "1.2.3 ";
        $version2 = " 1.2.4";

        assert_true(0 === compare($version1, $version1));
        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare with or without v',
    case: function () {
        $version1 = "0.0";
        $version2 = "v0.5";

        assert_true(0 === compare($version1, $version1));
        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare naturally',
    case: function () {
        $version1 = "1.2.0";
        $version2 = "1.10.0";

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

test(
    title: 'it should compare when "V" passed',
    case: function () {
        $version1 = "V1.2.3";
        $version2 = "V1.2.4";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when 2 digits version given',
    case: function () {
        $version1 = "1.2.0";
        $version2 = "2.0";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should compare when 1 digit version given',
    case: function () {
        $version1 = "8.3.35";
        $version2 = "9";

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);

test(
    title: 'it should consider the version less when there is less digits',
    case: function () {
        $version1 = '1.0';
        $version2 = 'v1.0.0';

        assert_true(-1 === compare($version1, $version2));
    }
);

test(
    title: 'it should compare versions with different stage',
    case: function () {
        $version1 = '1.0alpha';
        $version2 = 'v1.0.0beta';

        assert_true(-1 === compare($version1, $version2));
    }
);

test(
    title: 'it should compare release candidates',
    case: function () {
        $version1 = '1.0RC1';
        $version2 = '1.0RC2';

        assert_true(-1 === compare($version1, $version2));
    }
);

test(
    title: 'it should compare release candidate with stable version',
    case: function () {
        $version1 = '1.0.0-RC1';
        $version2 = '1.0.0';

        assert_true(-1 === compare($version1, $version2));
        assert_true(1 === compare($version2, $version1));
    }
);