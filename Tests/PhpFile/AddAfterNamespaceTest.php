<?php

namespace Tests\PhpFile\AddAfterNamespace;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should add after namespace in files with env',
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

namespace Application\Service;
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
        assert_true($expected === PhpFile::from_content($content)->add_after_namespace($addition)->code());
    }
);

test(
    title: 'it should add after namespace in classes',
    case: function () {
        $content = <<<'EOD'
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
<?php

namespace Application\Service;
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
        assert_true($expected === PhpFile::from_content($content)->add_after_namespace($addition)->code());
    }
);

test(
    title: 'it should add after namespace in files without classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

function something()
{

}
EOD;
        $expected = <<<'EOD'
<?php

namespace Application\Service;
require "/home/user/file.php";
require_once "/home/user/function.php";

function something()
{

}
EOD;
        $addition = PHP_EOL . 'require "/home/user/file.php";' . PHP_EOL . 'require_once "/home/user/function.php";';
        assert_true($expected === PhpFile::from_content($content)->add_after_namespace($addition)->code());
    }
);
