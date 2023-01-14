<?php

namespace Tests\PhpFile\IgnoreAnyStringTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should ignore by given closure',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php
// Comment
/**
* @return ReturnTypeDeclaration
*/
?>
<html><?= $var ?>
<body><?php call_func(); ?></body>
</html>
EOD;
        $expected = <<<'EOD'

?>
<?= $var ?>
call_func(); ?>
EOD;

        $result = PhpFile::from_content($content)->ignore_any_string();

        assert_true($expected, $result->code());
    }
);
