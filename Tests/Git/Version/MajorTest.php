<?php

namespace Tests\Git\Version\MajorTest;

use function Phpkg\Git\Version\major;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should detect the major part',
    case: function () {
        assert_true('1' === major('1.2.3'), 'Major is not working!');
        assert_true('2' === major('v2.4.5'), 'Major with leading v is not working!');
        assert_true('3' === major('V3.6.7'), 'Major with leading V is not working!');
        assert_true('4' === major('4-8-9'), 'Major with - separator is not working!');
        assert_true('5' === major('v5+10+11'), 'Major with + separator is not working!');
        assert_true('6' === major('v6_12_13'), 'Major with _ separator is not working!');
        assert_true('7' === major('7.14.15-alpha'), 'Major with special version string is not working!');
        assert_true('12345678' === major('v12345678'), 'Major with no separator is not working!');
    }
);
