<?php

namespace Tests\PhpFile\IgnoreNamespaceTest;

use Saeghe\Saeghe\PhpFile;
use function Saeghe\TestRunner\Assertions\Boolean\assert_true;

test(
    title: 'it should ignore namespace in classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;
use ArrayAccess;
use function str_starts_with;
use const PHP_BINARY;
use \RecursiveDirectoryIterator;
use function \array_reduce;
use const \PHP_EOL;
use Application\Classes\ClassA;
use function Application\Functions\functionA;
use const Application\Constants\ConstantA;
use const Application\Constants\ConstantB as ConstantC;use const Application\Constants\ConstantD;
use const Application\Constants\{ConstantE, ConstantF as ConstantG, ConstantH};
use function Application\Functions\functionB as functionC;use function Application\Functions\functionD;
use function Application\Functions\{functionE, functionF as functionG, functionH};
use Application\Classes\ClassB as ClassC;use Application\Classes\ClassD;
use Application\Classes\{ClassE, ClassF as ClassG, ClassH};
use Application\Classes\ClassI;use function Application\Functions\functionI;use const Application\Constants\ConstantI;
use Application\Classes\{
    ClassJ,
    ClassK\ClassM as ClassN
};
use Application\SampleFile as AnotherFile, Application\SubDirectory\SimpleClass;
use Application\{ClassO, ClassP}, Application\{ClassQ, ClassR as ClassS, ClassT};

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
    use MyTrait;

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
        $expected = <<<'EOD'
<?php

;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;
use ArrayAccess;
use function str_starts_with;
use const PHP_BINARY;
use \RecursiveDirectoryIterator;
use function \array_reduce;
use const \PHP_EOL;
use Application\Classes\ClassA;
use function Application\Functions\functionA;
use const Application\Constants\ConstantA;
use const Application\Constants\ConstantB as ConstantC;use const Application\Constants\ConstantD;
use const Application\Constants\{ConstantE, ConstantF as ConstantG, ConstantH};
use function Application\Functions\functionB as functionC;use function Application\Functions\functionD;
use function Application\Functions\{functionE, functionF as functionG, functionH};
use Application\Classes\ClassB as ClassC;use Application\Classes\ClassD;
use Application\Classes\{ClassE, ClassF as ClassG, ClassH};
use Application\Classes\ClassI;use function Application\Functions\functionI;use const Application\Constants\ConstantI;
use Application\Classes\{
    ClassJ,
    ClassK\ClassM as ClassN
};
use Application\SampleFile as AnotherFile, Application\SubDirectory\SimpleClass;
use Application\{ClassO, ClassP}, Application\{ClassQ, ClassR as ClassS, ClassT};

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
    use MyTrait;

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
        $php = PhpFile::from_content($content)->ignore_namespace();
        assert_true($expected === $php->code());
    }
);
test(
    title: 'it should ignore imports in classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

function a_function () {
    Application::do_something();
    return new ReturnTypeDeclaration();
}

some_stuff();

use Application\InlineImport;

some_other_stuff();

EOD;

        $expected = <<<'EOD'
<?php

;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

function a_function () {
    Application::do_something();
    return new ReturnTypeDeclaration();
}

some_stuff();

use Application\InlineImport;

some_other_stuff();

EOD;
        $php = PhpFile::from_content($content)->ignore_namespace();
        assert_true($expected === $php->code());
    }
);
