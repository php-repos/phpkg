<?php

namespace Tests\Parser\ParserTest;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\Value;
use Phpkg\Parser\Parser;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it properly should find nothing when there is no usage of anything outside of the current file',
    case: function () {
        $content = <<<EOD
<?php

namespace App\Service;

const SOMETHING = 'something';

function a_service()
{
  App\Service\SOMETHING;
}
EOD;
        $parser = Parser\parse($content);

        assert_true([] === $parser->nodes);
    }
);

test(
    title: 'it should find imported constants',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application;

use const PHP_EOL;
use const \PHP_BINARY;
use const Application\ClassA\ConstA;
use const Application\Functions\ConstB;
use const PackageA\Repo\{ConstC, ConstD};
use const PackageB\Repo\Somewhere\ConstE as ConstF;
use Application\NamespaceA;
use Package\Repo\NamespaceB as NamespaceC;

class User
{
  public function login()
  {
    $const = Application\Somewhere\ConstInline;
    $constFCQN = \Package\Repo\Somewhat\AnotherInlineConst;
    $constPartial = NamespaceA\InlineConst;
    $constAliasNamespace = NamespaceC\Somewhere\ConstInline;
  }
}

EOD;

        $expected = [
            'PHP_EOL' => [
                'namespace' => '',
                'alias' => 'PHP_EOL',
                'actual_name' => 'PHP_EOL',
                'type' => 'constant'
            ],
            'PHP_BINARY' => [
                'namespace' => '',
                'alias' => 'PHP_BINARY',
                'actual_name' => 'PHP_BINARY',
                'type' => 'constant'
            ],
            'Application\ClassA\ConstA' => [
                'namespace' => 'Application\ClassA',
                'alias' => 'ConstA',
                'actual_name' => 'Application\ClassA\ConstA',
                'type' => 'constant'
            ],
            'Application\Functions\ConstB' => [
                'namespace' => 'Application\Functions',
                'alias' => 'ConstB',
                'actual_name' => 'Application\Functions\ConstB',
                'type' => 'constant'
            ],
            'PackageA\Repo\ConstC' => [
                'namespace' => 'PackageA\Repo',
                'alias' => 'ConstC',
                'actual_name' => 'PackageA\Repo\ConstC',
                'type' => 'constant'
            ],
            'PackageA\Repo\ConstD' => [
                'namespace' => 'PackageA\Repo',
                'alias' => 'ConstD',
                'actual_name' => 'PackageA\Repo\ConstD',
                'type' => 'constant'
            ],
            'PackageB\Repo\Somewhere\ConstE' => [
                'namespace' => 'PackageB\Repo\Somewhere',
                'alias' => 'ConstF',
                'actual_name' => 'PackageB\Repo\Somewhere\ConstE',
                'type' => 'constant'
            ],
            'Application\Somewhere\ConstInline' => [
                'namespace' => 'Application\Somewhere',
                'alias' => 'ConstInline',
                'actual_name' => 'Application\Somewhere\ConstInline',
                'type' => 'constant'
            ],
            'Package\Repo\Somewhat\AnotherInlineConst' => [
                'namespace' => 'Package\Repo\Somewhat',
                'alias' => 'AnotherInlineConst',
                'actual_name' => 'Package\Repo\Somewhat\AnotherInlineConst',
                'type' => 'constant'
            ],
            'Application\NamespaceA\InlineConst' => [
                'namespace' => 'Application\NamespaceA',
                'alias' => 'InlineConst',
                'actual_name' => 'Application\NamespaceA\InlineConst',
                'type' => 'constant'
            ],
            'Package\Repo\NamespaceB\Somewhere\ConstInline' => [
                'namespace' => 'Package\Repo\NamespaceB\Somewhere',
                'alias' => 'ConstInline',
                'actual_name' => 'Package\Repo\NamespaceB\Somewhere\ConstInline',
                'type' => 'constant'
            ],
        ];

        $parser = Parser\parse($content);

        $actual = array_filter($parser->nodes, fn ($node) => $node['type'] === 'constant');

        assert_true($expected === $actual);
    },
);

test(
    title: 'it should find imported functions',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application;

use function array_map;
use function \array_reduce;
use function Application\ClassA\funcA;
use function Application\Functions\funcB;
use function PackageA\Repo\{funcC, funcD};
use function PackageB\Repo\Somewhere\funcE as funcF;
use Application\NamespaceA;
use Package\Repo\NamespaceB as NamespaceC;

class User
{
  public function login()
  {
    $varInline = Application\Somewhere\FuncInline();
    $varFCQN = \Package\Repo\Somewhat\AnotherInlineFunc();
    $varPartial = NamespaceA\InlineFunc();
    $varAliasNamespace = NamespaceC\Somewhere\FuncInline();
  }
}

EOD;

        $expected = [
            'array_map' => [
                'namespace' => '',
                'alias' => 'array_map',
                'actual_name' => 'array_map',
                'type' => 'function'
            ],
            'array_reduce' => [
                'namespace' => '',
                'alias' => 'array_reduce',
                'actual_name' => 'array_reduce',
                'type' => 'function'
            ],
            'Application\ClassA\funcA' => [
                'namespace' => 'Application\ClassA',
                'alias' => 'funcA',
                'actual_name' => 'Application\ClassA\funcA',
                'type' => 'function'
            ],
            'Application\Functions\funcB' => [
                'namespace' => 'Application\Functions',
                'alias' => 'funcB',
                'actual_name' => 'Application\Functions\funcB',
                'type' => 'function'
            ],
            'PackageA\Repo\funcC' => [
                'namespace' => 'PackageA\Repo',
                'alias' => 'funcC',
                'actual_name' => 'PackageA\Repo\funcC',
                'type' => 'function'
            ],
            'PackageA\Repo\funcD' => [
                'namespace' => 'PackageA\Repo',
                'alias' => 'funcD',
                'actual_name' => 'PackageA\Repo\funcD',
                'type' => 'function'
            ],
            'PackageB\Repo\Somewhere\funcE' => [
                'namespace' => 'PackageB\Repo\Somewhere',
                'alias' => 'funcF',
                'actual_name' => 'PackageB\Repo\Somewhere\funcE',
                'type' => 'function'
            ],
            'Application\Somewhere\FuncInline' => [
                'namespace' => 'Application\Somewhere',
                'alias' => 'FuncInline',
                'actual_name' => 'Application\Somewhere\FuncInline',
                'type' => 'function'
            ],
            'Package\Repo\Somewhat\AnotherInlineFunc' => [
                'namespace' => 'Package\Repo\Somewhat',
                'alias' => 'AnotherInlineFunc',
                'actual_name' => 'Package\Repo\Somewhat\AnotherInlineFunc',
                'type' => 'function'
            ],
            'Application\NamespaceA\InlineFunc' => [
                'namespace' => 'Application\NamespaceA',
                'alias' => 'InlineFunc',
                'actual_name' => 'Application\NamespaceA\InlineFunc',
                'type' => 'function'
            ],
            'Package\Repo\NamespaceB\Somewhere\FuncInline' => [
                'namespace' => 'Package\Repo\NamespaceB\Somewhere',
                'alias' => 'FuncInline',
                'actual_name' => 'Package\Repo\NamespaceB\Somewhere\FuncInline',
                'type' => 'function'
            ],
        ];

        $parser = Parser\parse($content);

        $actual = array_filter($parser->nodes, fn ($node) => $node['type'] === 'function');

        assert_true($expected === $actual);
    },
);

test(
    title: 'it should find imported classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application;

use ArrayAccess;
use \RecursiveDirectoryIterator;
use Application\Classes\ClassA;
use Application\Classes\InterfaceA;
use Application\Classes\ClassB as ClassC;use Application\Classes\ClassD;
use Application\Classes\{ClassE, ClassF as ClassG, ClassH};
use Application\Classes\ClassI;use function Application\Functions\functionI;use const Application\Constants\ConstantI;
use Application\Classes\{
    ClassJ,
    ClassK\ClassM as ClassN
};
use Application\SampleFile as AnotherFile, Application\SubDirectory\SimpleClass;
use Application\{ClassO, ClassP}; use Application\{ClassQ, ClassR as ClassS, ClassT};
use Application\Traits\TraitA;
use Application\Traits\TraitB;
use Application\Traits\TraitC as TraitD;

class User extends Application\Model implements InterfaceA, Application\Namespace\InterfaceB
{
  use TraitA, TraitD;
  use TraitB {
    something as public;
  }

  public function login()
  {
    new Application\ClassU();
    Package\Repo\ClassV::call();
    Application\Service\ConstA;
    Application\Repository\funcA();
    self::NotNeeded;
    parent::letItGo();
  }
}

EOD;

        $expected = [
            'ArrayAccess' => [
                'namespace' => '',
                'alias' => 'ArrayAccess',
                'actual_name' => 'ArrayAccess',
                'type' => 'class'
            ],
            'RecursiveDirectoryIterator' => [
                'namespace' => '',
                'alias' => 'RecursiveDirectoryIterator',
                'actual_name' => 'RecursiveDirectoryIterator',
                'type' => 'class'
            ],
            'Application\Classes\ClassA' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassA',
                'actual_name' => 'Application\Classes\ClassA',
                'type' => 'class'
            ],
            'Application\Classes\InterfaceA' => [
                'namespace' => 'Application\Classes',
                'alias' => 'InterfaceA',
                'actual_name' => 'Application\Classes\InterfaceA',
                'type' => 'class'
            ],
            'Application\Classes\ClassB' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassC',
                'actual_name' => 'Application\Classes\ClassB',
                'type' => 'class'
            ],
            'Application\Classes\ClassD' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassD',
                'actual_name' => 'Application\Classes\ClassD',
                'type' => 'class'
            ],
            'Application\Classes\ClassE' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassE',
                'actual_name' => 'Application\Classes\ClassE',
                'type' => 'class'
            ],
            'Application\Classes\ClassF' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassG',
                'actual_name' => 'Application\Classes\ClassF',
                'type' => 'class'
            ],
            'Application\Classes\ClassH' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassH',
                'actual_name' => 'Application\Classes\ClassH',
                'type' => 'class'
            ],
            'Application\Classes\ClassI' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassI',
                'actual_name' => 'Application\Classes\ClassI',
                'type' => 'class'
            ],
            'Application\Classes\ClassJ' => [
                'namespace' => 'Application\Classes',
                'alias' => 'ClassJ',
                'actual_name' => 'Application\Classes\ClassJ',
                'type' => 'class'
            ],
            'Application\Classes\ClassK\ClassM' => [
                'namespace' => 'Application\Classes\ClassK',
                'alias' => 'ClassN',
                'actual_name' => 'Application\Classes\ClassK\ClassM',
                'type' => 'class'
            ],
            'Application\SampleFile' => [
                'namespace' => 'Application',
                'alias' => 'AnotherFile',
                'actual_name' => 'Application\SampleFile',
                'type' => 'class'
            ],
            'Application\SubDirectory\SimpleClass' => [
                'namespace' => 'Application\SubDirectory',
                'alias' => 'SimpleClass',
                'actual_name' => 'Application\SubDirectory\SimpleClass',
                'type' => 'class'
            ],
            'Application\ClassO' => [
                'namespace' => 'Application',
                'alias' => 'ClassO',
                'actual_name' => 'Application\ClassO',
                'type' => 'class'
            ],
            'Application\ClassP' => [
                'namespace' => 'Application',
                'alias' => 'ClassP',
                'actual_name' => 'Application\ClassP',
                'type' => 'class'
            ],
            'Application\ClassQ' => [
                'namespace' => 'Application',
                'alias' => 'ClassQ',
                'actual_name' => 'Application\ClassQ',
                'type' => 'class'
            ],
            'Application\ClassR' => [
                'namespace' => 'Application',
                'alias' => 'ClassS',
                'actual_name' => 'Application\ClassR',
                'type' => 'class'
            ],
            'Application\ClassT' => [
                'namespace' => 'Application',
                'alias' => 'ClassT',
                'actual_name' => 'Application\ClassT',
                'type' => 'class'
            ],
            'Application\Traits\TraitA' => [
                'namespace' => 'Application\Traits',
                'alias' => 'TraitA',
                'actual_name' => 'Application\Traits\TraitA',
                'type' => 'class'
            ],
            'Application\Traits\TraitB' => [
                'namespace' => 'Application\Traits',
                'alias' => 'TraitB',
                'actual_name' => 'Application\Traits\TraitB',
                'type' => 'class'
            ],
            'Application\Traits\TraitC' => [
                'namespace' => 'Application\Traits',
                'alias' => 'TraitD',
                'actual_name' => 'Application\Traits\TraitC',
                'type' => 'class'
            ],
            'Application\Model' => [
                'namespace' => 'Application',
                'alias' => 'Model',
                'actual_name' => 'Application\Model',
                'type' => 'class'
            ],
            'Application\Namespace\InterfaceB' => [
                'namespace' => 'Application\Namespace',
                'alias' => 'InterfaceB',
                'actual_name' => 'Application\Namespace\InterfaceB',
                'type' => 'class'
            ],
            'Application\ClassU' => [
                'namespace' => 'Application',
                'alias' => 'ClassU',
                'actual_name' => 'Application\ClassU',
                'type' => 'class'
            ],
            'Package\Repo\ClassV' => [
                'namespace' => 'Package\Repo',
                'alias' => 'ClassV',
                'actual_name' => 'Package\Repo\ClassV',
                'type' => 'class'
            ],
            'Application\Service' => [
                'namespace' => 'Application',
                'alias' => 'Service',
                'actual_name' => 'Application\Service',
                'type' => 'class'
            ],
            'Application\Repository' => [
                'namespace' => 'Application',
                'alias' => 'Repository',
                'actual_name' => 'Application\Repository',
                'type' => 'class'
            ],
        ];

        $parser = Parser\parse($content);

        $actual = array_filter($parser->nodes, fn ($node) => $node['type'] === 'class');

        assert_true($expected === $actual);
    },
);

test(
    title: 'it should find imported classes',
    case: function () {
        $content = <<<'EOD'
<?php

declare(strict_types=1);

namespace Doctrine\Common\Collections\Expr;

use RuntimeException;

abstract class ExpressionVisitor
{
    public function dispatch(Expression $expr)
    {
        throw new RuntimeException('Unknown Expression ' . $expr::class);
    }
}


EOD;
        $expected = [
            'RuntimeException' => [
                'namespace' => '',
                'alias' => 'RuntimeException',
                'actual_name' => 'RuntimeException',
                'type' => 'class'
            ],
        ];

        $parser = Parser\parse($content);

        $actual = array_filter($parser->nodes, fn ($node) => $node['type'] === 'class');

        assert_true($expected === $actual);
    },
);
