<?php

namespace Tests\PhpFile\AddAfterOpeningTagTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should add after opening tag in files with env',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
}
EOD;
        $expected = <<<'EOD'
#!/usr/bin/env php
<?php

require "/home/user/file.php";
require_once "/home/user/function.php";
namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
}
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_opening_tag($addition)->code());
    }
);

test(
    title: 'it should add after opening tag in classes',
    case: function () {
        $content = <<<'EOD'
<?php

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
}
EOD;
        $expected = <<<'EOD'
<?php

require "/home/user/file.php";
require_once "/home/user/function.php";
use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
}
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_opening_tag($addition)->code());
    }
);

test(
    title: 'it should add after opening tag in files without classes',
    case: function () {
        $content = <<<'EOD'
<?php

function something()
{

}
EOD;
        $expected = <<<'EOD'
<?php

require "/home/user/file.php";
require_once "/home/user/function.php";
function something()
{

}
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_opening_tag($addition)->code());
    }
);

test(
    title: 'it should add after opening tag when there is strict mode code',
    case: function () {
        $content = <<<'EOD'
<?php declare(strict_types=1);

function something()
{

}
EOD;
        $expected = <<<'EOD'
<?php 
require "/home/user/file.php";
require_once "/home/user/function.php";declare(strict_types=1);

function something()
{

}
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_opening_tag($addition)->code());
    }
);

test(
    title: 'it should add at the first line when there is no opening tag',
    case: function () {
        $content = <<<'EOD'
<html>
<head>
<title>
<?= $title; ?>
</title>
</head>
</html>
EOD;
        $expected = <<<'EOD'
<?php 
require "/home/user/file.php";
require_once "/home/user/function.php"; ?><html>
<head>
<title>
<?= $title; ?>
</title>
</head>
</html>
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_opening_tag($addition)->code());
    }
);

test(
    title: 'it should add at the first line when there is early short echo',
    case: function () {
        $content = <<<'EOD'
<html>
<head>
<title>
<?= $title; ?>
</title>
</head>
<body>
<?php
$var = inner_code();
?>
</body>
</html>
EOD;
        $expected = <<<'EOD'
<?php 
require "/home/user/file.php";
require_once "/home/user/function.php"; ?><html>
<head>
<title>
<?= $title; ?>
</title>
</head>
<body>
<?php
$var = inner_code();
?>
</body>
</html>
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_opening_tag($addition)->code());
    }
);

test(
    title: 'it should add for the very first opening tag',
    case: function () {
        $content = <<<'EOD'
<html>
<head>
<?php if ($something) do_something; ?>
<title>
<?= $title; ?>
</title>
</head>
<body>
<?php
$var = inner_code();
?>
</body>
</html>
EOD;
        $expected = <<<'EOD'
<html>
<head>
<?php 
require "/home/user/file.php";
require_once "/home/user/function.php";if ($something) do_something; ?>
<title>
<?= $title; ?>
</title>
</head>
<body>
<?php
$var = inner_code();
?>
</body>
</html>
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_opening_tag($addition)->code());
    }
);
