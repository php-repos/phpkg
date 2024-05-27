<?php

namespace Tests\Git\Version\HasMajorChangeTest;

use function Phpkg\Git\Version\has_major_change;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should detect when two versions have major version number difference',
    case: function () {
        $version1 = 'v1.2.3';
        $version2 = 'v1.3.4';
        $version3 = 'v2.3.4';
        $version4 = '1.2.3';
        $version5 = '1.3.4';
        $version6 = '2.3.4';
        $version7 = '1_2_3';
        $version8 = '1+2+3';
        $version9 = '1-2-3';
        $version10 = '1.2.3-alfa';

        assert_false(has_major_change($version1, $version1));
        assert_false(has_major_change($version1, $version2));
        assert_true(has_major_change($version1, $version3));
        assert_false(has_major_change($version1, $version4));
        assert_false(has_major_change($version1, $version5));
        assert_true(has_major_change($version1, $version6));
        assert_false(has_major_change($version2, $version1));
        assert_true(has_major_change($version3, $version1));
        assert_false(has_major_change($version4, $version1));
        assert_false(has_major_change($version5, $version1));
        assert_true(has_major_change($version6, $version1));
        assert_false(has_major_change($version7, $version1));
        assert_false(has_major_change($version8, $version1));
        assert_false(has_major_change($version9, $version1));
        assert_false(has_major_change($version10, $version1));
    }
);
