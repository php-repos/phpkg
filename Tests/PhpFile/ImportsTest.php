<?php

namespace Tests\PhpFile\ImportsTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return array of imports',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Namespace;

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

class Application
{
    use TraitA;

    public function func()
    {
        $var = ClassA::call();
        $closure = function () use (&$var) {
            return ++$var;
        };
    }
}

EOD;

        assert_true(
            [
                'constants' => [
                    'PHP_BINARY' => 'PHP_BINARY',
                    '\PHP_EOL' => 'PHP_EOL',
                    'Application\Constants\ConstantA' => 'ConstantA',
                    'Application\Constants\ConstantB' => 'ConstantC',
                    'Application\Constants\ConstantD' => 'ConstantD',
                    'Application\Constants\ConstantE' => 'ConstantE',
                    'Application\Constants\ConstantF' => 'ConstantG',
                    'Application\Constants\ConstantH' => 'ConstantH',
                    'Application\Constants\ConstantI' => 'ConstantI',
                ],
                'functions' => [
                    'str_starts_with' => 'str_starts_with',
                    '\array_reduce' => 'array_reduce',
                    'Application\Functions\functionA' => 'functionA',
                    'Application\Functions\functionB' => 'functionC',
                    'Application\Functions\functionD' => 'functionD',
                    'Application\Functions\functionE' => 'functionE',
                    'Application\Functions\functionF' => 'functionG',
                    'Application\Functions\functionH' => 'functionH',
                    'Application\Functions\functionI' => 'functionI',
                ],
                'classes' => [
                    'ArrayAccess' => 'ArrayAccess',
                    '\RecursiveDirectoryIterator' => 'RecursiveDirectoryIterator',
                    'Application\Classes\ClassA' => 'ClassA',
                    'Application\Classes\ClassB' => 'ClassC',
                    'Application\Classes\ClassD' => 'ClassD',
                    'Application\Classes\ClassE' => 'ClassE',
                    'Application\Classes\ClassF' => 'ClassG',
                    'Application\Classes\ClassH' => 'ClassH',
                    'Application\Classes\ClassI' => 'ClassI',
                    'Application\Classes\ClassJ' => 'ClassJ',
                    'Application\Classes\ClassK\ClassM' => 'ClassN',
                    'Application\SampleFile' => 'AnotherFile',
                    'Application\SubDirectory\SimpleClass' => 'SimpleClass',
                    'Application\ClassO' => 'ClassO',
                    'Application\ClassP' => 'ClassP',
                    'Application\ClassQ' => 'ClassQ',
                    'Application\ClassR' => 'ClassS',
                    'Application\ClassT' => 'ClassT',
                ],
            ]
            === PhpFile::from_content($content)->imports(),
            'imports not detected'
        );
    }
);

test(
    title: 'it should detect imports when file does not have namespace',
    case: function () {
        $content = <<<'EOD'
<?php

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

Application::start();

$allFiles = function ($directory) use (&$allFiles) {
    return $files;
};

$anotherClosure = function ($directory) use ($allFiles) {
    return $files;
};

use Application\SampleFile as AnotherFile, Application\SubDirectory\SimpleClass;
use Application\{ClassO, ClassP}, Application\{ClassQ, ClassR as ClassS, ClassT};

EOD;
        assert_true(
            [
                'constants' => [
                    'PHP_BINARY' => 'PHP_BINARY',
                    '\PHP_EOL' => 'PHP_EOL',
                    'Application\Constants\ConstantA' => 'ConstantA',
                    'Application\Constants\ConstantB' => 'ConstantC',
                    'Application\Constants\ConstantD' => 'ConstantD',
                    'Application\Constants\ConstantE' => 'ConstantE',
                    'Application\Constants\ConstantF' => 'ConstantG',
                    'Application\Constants\ConstantH' => 'ConstantH',
                    'Application\Constants\ConstantI' => 'ConstantI',
                ],
                'functions' => [
                    'str_starts_with' => 'str_starts_with',
                    '\array_reduce' => 'array_reduce',
                    'Application\Functions\functionA' => 'functionA',
                    'Application\Functions\functionB' => 'functionC',
                    'Application\Functions\functionD' => 'functionD',
                    'Application\Functions\functionE' => 'functionE',
                    'Application\Functions\functionF' => 'functionG',
                    'Application\Functions\functionH' => 'functionH',
                    'Application\Functions\functionI' => 'functionI',
                ],
                'classes' => [
                    'ArrayAccess' => 'ArrayAccess',
                    '\RecursiveDirectoryIterator' => 'RecursiveDirectoryIterator',
                    'Application\Classes\ClassA' => 'ClassA',
                    'Application\Classes\ClassB' => 'ClassC',
                    'Application\Classes\ClassD' => 'ClassD',
                    'Application\Classes\ClassE' => 'ClassE',
                    'Application\Classes\ClassF' => 'ClassG',
                    'Application\Classes\ClassH' => 'ClassH',
                    'Application\Classes\ClassI' => 'ClassI',
                    'Application\Classes\ClassJ' => 'ClassJ',
                    'Application\Classes\ClassK\ClassM' => 'ClassN',
                    'Application\SampleFile' => 'AnotherFile',
                    'Application\SubDirectory\SimpleClass' => 'SimpleClass',
                    'Application\ClassO' => 'ClassO',
                    'Application\ClassP' => 'ClassP',
                    'Application\ClassQ' => 'ClassQ',
                    'Application\ClassR' => 'ClassS',
                    'Application\ClassT' => 'ClassT',
                ],
            ]
            === PhpFile::from_content($content)->imports(),
            'imports not detected'
        );
    }
);

test(
    title: 'it should detect imports when file starts with env definition',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;

EOD;
        assert_true(
            [
                'constants' => [],
                'functions' => [
                    'PhpRepos\Cli\IO\Read\parameter' => 'parameter',
                    'PhpRepos\Cli\Output\error' => 'error',
                    'PhpRepos\Cli\Output\line' => 'line',
                ],
                'classes' => [],
            ]
            === PhpFile::from_content($content)->imports(),
            'imports not detected'
        );
    }
);

test(
    title: 'it should not stuck if there is no , in the group',
    case: function () {
        $content = <<<'EOD'
<?php

use const PhpRepos\Cli\IO\Read\{Constant};
use function PhpRepos\Cli\IO\Read\{parameter};
use Application\{Classes};

EOD;
        assert_true(
            [
                'constants' => [
                    'PhpRepos\Cli\IO\Read\Constant' => 'Constant',
                ],
                'functions' => [
                    'PhpRepos\Cli\IO\Read\parameter' => 'parameter',
                ],
                'classes' => [
                    'Application\Classes' => 'Classes',
                ],
            ]
            === PhpFile::from_content($content)->imports(),
            'imports not detected'
        );
    }
);

test(
    title: 'it should return correct result when there is many spaces or new line',
    case: function () {
        $content = <<<'EOD'
<?php

use  const  PhpRepos\Cli\IO\Read\{ConstantA};
  use  const  PhpRepos\Cli\IO\Read\{ConstantB};
use   function   PhpRepos\Cli\IO\Read\{parameter};
use   
    function   PhpRepos\Cli\IO\Read\{func};
use 
   Application\{Classes};

$allFiles = function ($directory) use   (&$allFiles) {
    return $directory;
};

$allFiles = function ($directory) 
use 
(&$allFiles) {
    return $directory;
};

EOD;

        assert_true(
            [
                'constants' => [
                    'PhpRepos\Cli\IO\Read\ConstantA' => 'ConstantA',
                    'PhpRepos\Cli\IO\Read\ConstantB' => 'ConstantB',
                ],
                'functions' => [
                    'PhpRepos\Cli\IO\Read\parameter' => 'parameter',
                    'PhpRepos\Cli\IO\Read\func' => 'func',
                ],
                'classes' => [
                    'Application\Classes' => 'Classes',
                ],
            ]
            === PhpFile::from_content($content)->imports(),
            'imports not detected'
        );
    }
);
