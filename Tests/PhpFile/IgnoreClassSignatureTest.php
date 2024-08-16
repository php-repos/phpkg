<?php

namespace Tests\PhpFile\IgnoreClassSignatureTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should ignore class signature in oop',
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
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
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
        $php = PhpFile::from_content($content)->ignore_class_signature();
        assert_true($expected === $php->code());
    }
);

test(
    title: 'it should ignore abstract class signature in oop',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
abstract class ClassUseNamespaceTwice extends Application\ExtendClass
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
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
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
        $php = PhpFile::from_content($content)->ignore_class_signature();
        assert_true($expected === $php->code());
    }
);

test(
    title: 'it should ignore interface signature in oop',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
interface ClassUseNamespaceTwice extends Application\ExtendClass
{
    public function anything(public string $key) {}
    
    /**
    * @return ReturnTypeDeclaration
    */
    protected function doIt(): ReturnTypeDeclaration {}
}
EOD;
        $expected = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
{
    public function anything(public string $key) {}
    
    /**
    * @return ReturnTypeDeclaration
    */
    protected function doIt(): ReturnTypeDeclaration {}
}
EOD;
        $php = PhpFile::from_content($content)->ignore_class_signature();
        assert_true($expected === $php->code());
    }
);

test(
    title: 'it should ignore final class signature in oop',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
final class ClassUseNamespaceTwice extends Application\ExtendClass
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
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
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
        $php = PhpFile::from_content($content)->ignore_class_signature();
        assert_true($expected === $php->code());
    }
);

test(
    title: 'it should ignore trait signature in oop',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
trait ClassUseNamespaceTwice extends Application\ExtendClass
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
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
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
        $php = PhpFile::from_content($content)->ignore_class_signature();
        assert_true($expected === $php->code());
    }
);

if (PHP_VERSION_ID > 80100) {
    test(
        title: 'it should ignore enum signature in oop',
        case: function () {
            $content = <<<'EOD'
<?php

namespace Application\Service;

enum Suit
{
    case Hearts;
    case Diamonds;
    case Clubs;
    case Spades;
}
EOD;
            $expected = <<<'EOD'
<?php

namespace Application\Service;

{
    case Hearts;
    case Diamonds;
    case Clubs;
    case Spades;
}
EOD;
            $php = PhpFile::from_content($content)->ignore_class_signature();
            assert_true($expected === $php->code());
        }
    );

    test(
        title: 'it should ignore backed enum signature in oop',
        case: function () {
            $content = <<<'EOD'
<?php

namespace Application\Service;

enum Suit
{
    case Hearts;
    case Diamonds;
    case Clubs;
    case Spades;
}
EOD;
            $expected = <<<'EOD'
<?php

namespace Application\Service;

{
    case Hearts;
    case Diamonds;
    case Clubs;
    case Spades;
}
EOD;
            $php = PhpFile::from_content($content)->ignore_class_signature();
            assert_true($expected === $php->code());
        }
    );

    test(
        title: 'it should return content for files',
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

            $php = PhpFile::from_content($content)->ignore_class_signature();
            assert_true($content === $php->code());
        }
    );

}
