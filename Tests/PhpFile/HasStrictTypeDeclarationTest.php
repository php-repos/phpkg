<?php

namespace Tests\PhpFile\HasStrictTypeDeclarationTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return false when strict type is not declared',
    case: function () {
        $content = <<<'EOD'
<?php;

declare(ticks=TICK_VALUE);

// File body
EOD;
        assert_false(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);

test(
    title: 'it should return false when strict type is not declared but there are other declarations',
    case: function () {
        $content = <<<'EOD'
<?php;

// File body
EOD;
        assert_false(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);

test(
    title: 'it should return true when strict type declared in the opening line',
    case: function () {
        $content = <<<'EOD'
<?php declare(strict_types=1);

// File body
EOD;
        assert_true(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);

test(
    title: 'it should return true when strict type declared in the opening line and contains spaces',
    case: function () {
        $content = <<<'EOD'
<?php declare( strict_types=1 );

// File body
EOD;
        assert_true(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);

test(
    title: 'it should return true when strict type declared in a new line',
    case: function () {
        $content = <<<'EOD'
<?php

declare(strict_types=1);

// File body
EOD;
        assert_true(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);

test(
    title: 'it should return true when strict type declared in a new line and contains spaces',
    case: function () {
        $content = <<<'EOD'
<?php

declare( strict_types=1 );

// File body
EOD;
        assert_true(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);

test(
    title: 'it should return true when strict type declared in a file contain hashbang',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php declare(strict_types=1);

// File body
EOD;
        assert_true(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);

test(
    title: 'it should return true when strict type declared in a new line in a file contain hashbang',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

declare(strict_types=1);

// File body
EOD;
        assert_true(PhpFile::from_content($content)->has_strict_type_declaration());
    }
);
