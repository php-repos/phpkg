<?php

namespace Tests\PhpFile\AddAfterStrictTypeDeclarationTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should add in the first line when strict type declared in the opening line',
    case: function () {
        $content = <<<'EOD'
<?php declare(strict_types=1);

// File body
EOD;

        $expect = <<<'EOD'
<?php declare(strict_types=1);$added_code;

// File body
EOD;
        assert_true($expect === PhpFile::from_content($content)->add_after_strict_type_declaration('$added_code;')->code());
    }
);

test(
    title: 'it should add to the declare line when declared with whitespaces',
    case: function () {
        $content = <<<'EOD'
<?php declare( strict_types=1 );

// File body
EOD;
        $expect = <<<'EOD'
<?php declare( strict_types=1 );$added_code;

// File body
EOD;
        assert_true($expect === PhpFile::from_content($content)->add_after_strict_type_declaration('$added_code;')->code());
    }
);

test(
    title: 'it should add to the declare line when declared in a new line',
    case: function () {
        $content = <<<'EOD'
<?php

declare(strict_types=1);

// File body
EOD;

        $expect = <<<'EOD'
<?php

declare(strict_types=1);$added_code;

// File body
EOD;

        assert_true($expect === PhpFile::from_content($content)->add_after_strict_type_declaration('$added_code;')->code());
    }
);

test(
    title: 'it should add to the declare line when declared containing spaces',
    case: function () {
        $content = <<<'EOD'
<?php

declare( strict_types=1 );

// File body
EOD;
        $expect = <<<'EOD'
<?php

declare( strict_types=1 );$added_code;

// File body
EOD;
        assert_true($expect === PhpFile::from_content($content)->add_after_strict_type_declaration('$added_code;')->code());
    }
);

test(
    title: 'it should add to the declare line when the file contains hashbang',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php declare(strict_types=1);

// File body
EOD;

        $expect = <<<'EOD'
#!/usr/bin/env php
<?php declare(strict_types=1);$added_code;

// File body
EOD;

        assert_true($expect === PhpFile::from_content($content)->add_after_strict_type_declaration('$added_code;')->code());
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
        $expect = <<<'EOD'
#!/usr/bin/env php
<?php

declare(strict_types=1);$added_code;

// File body
EOD;

        assert_true($expect === PhpFile::from_content($content)->add_after_strict_type_declaration('$added_code;')->code());
    }
);
