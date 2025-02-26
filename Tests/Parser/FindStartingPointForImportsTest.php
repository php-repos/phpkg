<?php

namespace Tests\Parser\FindStartingPointForImportsTest;

use Phpkg\Parser\Parser;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return 0 when there is no php content',
    case: function () {
        assert_true(0 === Parser\find_starting_point_for_imports(''));
    }
);

test(
    title: 'it should return the position of the last p on opening tag',
    case: function () {
        $content = <<<EOD
<?php
EOD;
        $actual = Parser\find_starting_point_for_imports($content);

        assert_true(5 === $actual);
    }
);

test(
    title: 'it should return the position of the space after p when opening tag gets closed in the same line',
    case: function () {
        $content = <<<EOD
<?php ?>

EOD;
        $actual = Parser\find_starting_point_for_imports($content);

        assert_true(6 === $actual);
    }
);

test(
    title: 'it should return the position of the next line when opening tag goes to the next line',
    case: function () {
        $content = <<<EOD
<?php

EOD;
        $actual = Parser\find_starting_point_for_imports($content);

        assert_true(6 === $actual);
    }
);

test(
    title: 'it should return the position of the last p on opening tag when file starts with hashbang',
    case: function () {
        $content = <<<EOD
#!/usr/bin/env php
<?php
EOD;
        $actual = Parser\find_starting_point_for_imports($content);

        assert_true(24 === $actual);
    }
);

test(
    title: 'it should return the position of the last character of the declare statement',
    case: function () {
        $content = <<<EOD
<?php

declare(strict_types=1);
EOD;
        $actual = Parser\find_starting_point_for_imports($content);

        assert_true(31 === $actual);
    }
);

test(
    title: 'it should return the position of the last character of the declare statement when it is on the same line as opening tag',
    case: function () {
        $content = <<<EOD
<?php declare(strict_types=1);
EOD;
        $actual = Parser\find_starting_point_for_imports($content);

        assert_true(30 === $actual);
    }
);

test(
    title: 'it should return the position of the last character of the namespace declaration',
    case: function () {
        $content = <<<EOD
<?php

namespace App\User;
EOD;
        $actual = Parser\find_starting_point_for_imports($content);

        assert_true(26 === $actual);
    }
);