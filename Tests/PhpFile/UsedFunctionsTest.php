<?php

namespace Tests\PhpFile\UsedFunctionsTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return functions when namespace used as alias',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Namespace\Functions;

class ClassName
{
    public function some_where()
    {
        return $var === Functions\functionA();
    }
    
    /**
    * Not from comment Functions\functionC() 
    * @return void
    */
    public function some_where_else()
    {
        Functions\Subdirectory\functionB();
    }
}
EOD;

        assert_true(['functionA', 'Subdirectory\functionB'] === PhpFile::from_content($content)->used_functions('Functions'));
    }
);

test(
    title: 'it should return true when multiple use statement used',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application;

use Application\SampleFile as AnotherFile, Application\SubDirectory\SimpleClass;

function call()
{
    AnotherFile\anImportantFunction();

    $x = AnotherFile\anImportantFunction() || new SimpleClass();
}
EOD;

        assert_true(['anImportantFunction', 'anImportantFunction'] === PhpFile::from_content($content)->used_functions('AnotherFile'));
    }
);


test(
    title: 'it should return correct list when namespace used as compound for classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\AnyNamespace as CompoundNamespace;

class ClassName
{
    public function some_where()
    {
        CompoundNamespace\ClassName::class;
        $var = new CompoundNamespace\ClassA();
        $static = CompoundNamespace\StaticClass::handle();
        $const = CompoundNamespace\ConstInCompoundNamespace::ConstE;
        CompoundNamespace();
    }
}
EOD;
        assert_true([] === PhpFile::from_content($content)->used_functions('CompoundNamespace'));
    }
);

test(
    title: 'it should return correct list when namespace used as class',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Namespace\ClassA;

class ClassName
{
    public function some_where()
    {
        $var = (new ClassA\ClassB())->call();
        return $var === new ClassA\ClassB();
    }
}
EOD;
        assert_true([] === PhpFile::from_content($content)->used_functions('ClassA'));
    }
);

test(
    title: 'it should return correct list when namespace used as class statically',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Namespace\ClassA;

class ClassName
{
    public function some_where()
    {
        return $var === ClassA\ClassB::call();
    }
}
EOD;
        assert_true([] === PhpFile::from_content($content)->used_functions('ClassA'));
    }
);

test(
    title: 'it should return empty array when the alias has been used twice but there is no function usage',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Application\Application;

class ClassUseNamespaceTwice extends Application
{

}
EOD;
        assert_true([] === PhpFile::from_content($content)->used_functions('Application'));
    }
);
