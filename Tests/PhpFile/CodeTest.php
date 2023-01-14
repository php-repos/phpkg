<?php

namespace Tests\PhpFile\CodeTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return the code for classes',
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
    public function __construct(public readonly string $key)
    {}
    
    /**
    * @return ReturnTypeDeclaration
    */
    protected function doIt(): ReturnTypeDeclaration
    {
        $const = Application\Constants\Const;
        return new ReturnTypeDeclaration();
    }
}
EOD;

        assert_true($content === PhpFile::from_content($content)->code());
    }
);

test(
    title: 'it should return the code for files',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

$var = strlen($string);
Application::run($var);
EOD;

        assert_true($content === PhpFile::from_content($content)->code());
    }
);
