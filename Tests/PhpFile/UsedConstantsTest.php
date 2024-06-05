<?php

namespace Tests\PhpFile\UsedConstantsTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return constants when namespace used as alias',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Namespace\Constants;

class ClassName
{
    public function some_where()
    {
        return $var === Constants\ConstantA;
    }

    /**
    * Not from comment Constants\Subdirectory\ConstantC; 
    * @return mixed
    */
    public function some_where_else()
    {
        return Constants\Subdirectory\ConstantB;
    }
}
EOD;

        assert_true(['ConstantA', 'Subdirectory\ConstantB']  === PhpFile::from_content($content)->used_constants('Constants'));
    }
);

test(
    title: 'it should return nothing when namespace used as compound for classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\AnyNamespace as CompoundNamespace;

class ClassName extends CompoundNamespace, ArrayAccess
{
    public function some_where(): CompoundNamespace
    {
        CompoundNamespace\ClassName::class;
        $var = new CompoundNamespace\ClassA();
        $static = CompoundNamespace\StaticClass::handle();
        $const = CompoundNamespace\ConstInCompoundNamespace::ConstE;
    }
}
EOD;

        assert_true([] === PhpFile::from_content($content)->used_constants('CompoundNamespace'));
    }
);

test(
    title: 'it should return nothing when namespace used as class',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Namespace\ClassA;

class ClassName
{
    public function some_where()
    {
        return $var === new ClassA\ClassB();
    }
}
EOD;
        assert_true([] === PhpFile::from_content($content)->used_constants('ClassA'));
    }
);

test(
    title: 'it should return empty array when the alias has been used twice but there is no constant usage',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Application\Application;

class ClassUseNamespaceTwice extends Application\ExtendClass
{

}
EOD;

        assert_true([] === PhpFile::from_content($content)->used_constants('Application'));
    }
);
