<?php

namespace Tests\System\BuildCommand\ProductionBuildTest;

use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_false;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;
use function Tests\System\BuildCommand\BuildTest\assert_build_output;

test(
    title: 'it should build with production mode',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build production --project=../../DummyProject');

        assert_build_output($output);

        $build_path = Path::from_string(root())->append('../../DummyProject/builds/production');
        assert_true(Directory\exists($build_path), 'Builds directory not exists!');
        assert_true(File\exists($build_path->append('phpkg.config.json')), 'config file is not copied!');
        assert_true(File\exists($build_path->append('phpkg.config-lock.json')), 'lock file is not copied!');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;

        assert_true($expected === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
    },
    before: function() {
        $config = [];
        $meta = ['packages' => []];
        $path = Path::from_string(root())->append('../../DummyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
    },
    after: function() {
        reset_dummy_project();
    }
);

test(
    title: 'it should compile files with relative path in production mode',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build production --project=../../DummyProject');

        assert_build_output($output);

        $build_path = Path::from_string(root())->append('../../DummyProject/builds/production');
        assert_true(Directory\exists($build_path), 'Builds directory not exists!');
        assert_true(File\exists($build_path->append('phpkg.config.json')), 'config file is not copied!');
        assert_true(File\exists($build_path->append('phpkg.config-lock.json')), 'lock file is not copied!');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'App\In\AnotherClass' => __DIR__ . '/Source/In/AnotherClass.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'App' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

require_once __DIR__ . '/Source/Autoloads/Autoloaded.php';
require_once __DIR__ . '/Source/Autoloads/AnotherAutoloaded.php';

EOD;

        assert_true($expected === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<EOD
<?php

namespace App;require_once __DIR__ . '/Output/Hello.php';require_once __DIR__ . '/Helper.php';

use App\Helper;
use App\In\AnotherClass;
use function App\Output\Hello\world;

class Classname
{
    public function a_method()
    {
        return Helper\helper() . world() . AnotherClass::greeting();
    }
}
EOD;

        assert_true($expected === file_get_contents($build_path->append('Source/Classname.php')), 'Class does not have required relative imports!');
        $expected = <<<EOD
<?php

namespace App\In;require_once __DIR__ . '/../Autoloads/Autoloaded.php';

use App\Autoloads\Autoloaded;

class AnotherClass
{
    public static function greeting()
    {
        return Autoloaded\greeting();
    }
}
EOD;

        assert_true($expected === file_get_contents($build_path->append('Source/In/AnotherClass.php')), 'Another Class does not have required relative imports!');
        $expected = <<<EOD
<?php

namespace App\Autoloads\Autoloaded;require_once __DIR__ . '/AnotherAutoloaded.php';

use function App\Autoloads\AnotherAutoloaded\something;

function greeting()
{
    return 'autoloaded hello' . something();
}
EOD;

        assert_true($expected === file_get_contents($build_path->append('Source/Autoloads/Autoloaded.php')), 'Autoloaded content is not what it should be!');
        $expected = <<<EOD
<?php

namespace App\Autoloads\AnotherAutoloaded;

function something()
{
    return random_int();
}
EOD;
        assert_true($expected === file_get_contents($build_path->append('Source/Autoloads/AnotherAutoloaded.php')), 'Another Autoloaded content is not what it should be!');
    },
    before: function() {
        $config = [
            'map' => ['App' => 'Source'],
            'autoloads' => [
                'Source\Autoloads\Autoloaded.php',
                'Source\Autoloads\AnotherAutoloaded.php',
            ]
        ];
        $meta = ['packages' => []];
        $path = Path::from_string(root())->append('../../DummyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        $content = <<<EOD
<?php

namespace App;

use App\Helper;
use App\In\AnotherClass;
use function App\Output\Hello\world;

class Classname
{
    public function a_method()
    {
        return Helper\helper() . world() . AnotherClass::greeting();
    }
}
EOD;
        File\create($path->append('Source/Classname.php'), $content);
        $content = <<<EOD
<?php

namespace App\In;

use App\Autoloads\Autoloaded;

class AnotherClass
{
    public static function greeting()
    {
        return Autoloaded\greeting();
    }
}
EOD;
        Directory\make_recursive($path->append('Source/In'));
        File\create($path->append('Source/In/AnotherClass.php'), $content);
        $content = <<<EOD
<?php

namespace App\Autoloads\Autoloaded;

use function App\Autoloads\AnotherAutoloaded\something;

function greeting()
{
    return 'autoloaded hello' . something();
}
EOD;

        Directory\make_recursive($path->append('Source/Autoloads'));
        File\create($path->append('Source/Autoloads/Autoloaded.php'), $content);
        $content = <<<EOD
<?php

namespace App\Autoloads\AnotherAutoloaded;

function something()
{
    return random_int();
}
EOD;
        File\create($path->append('Source/Autoloads/AnotherAutoloaded.php'), $content);
        $content = <<<EOD
<?php

namespace App\Helper;

function helper()
{
    return 'this is helper';
}
EOD;

        File\create($path->append('Source/Helper.php'), $content);
        $content = <<<EOD
<?php

namespace App\Output\Hello;

function world()
{
    return 'Hello world';
}
EOD;

        Directory\make($path->append('Source/Output'));
        File\create($path->append('Source/Output/Hello.php'), $content);
    },
    after: function() {
        reset_dummy_project();
    }
);

test(
    title: 'it should compile files with relative path in production mode in packages',
    case: function () {
        shell_exec('php ' . root() . 'phpkg build production --project=../../DummyProject');

        $build_path = Path::from_string(root())->append('../../DummyProject/builds/production');
        assert_true(Directory\exists($build_path), 'Builds directory not exists!');
        assert_true(File\exists($build_path->append('phpkg.config.json')), 'config file is not copied!');
        assert_true(File\exists($build_path->append('phpkg.config-lock.json')), 'lock file is not copied!');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<EOD
<?php

namespace App;require_once __DIR__ . '/../Packages/owner/repo/Source/Helper.php';

use Package\Greeting;
use Package\Helper;

class Classname
{
    public function a_method()
    {
        return Helper\helper() . Greeting::output();
    }
}
EOD;
        assert_true($expected === file_get_contents($build_path->append('Source/Classname.php')), 'Project file content is now what it should be!');
        $expected = <<<EOD
<?php

namespace Package\Helper;require_once __DIR__ . '/Hello.php';

use function Package\Hello\world;

function helper()
{
    return 'hello' . world();
}
EOD;
        assert_true($expected === file_get_contents($build_path->append('Packages/owner/repo/Source/Helper.php')), 'Package helper file content is not what it should be!');
        $expected = <<<EOD
<?php

namespace Package\Hello;

function world()
{
    return 'package world';
}
EOD;
        assert_true($expected === file_get_contents($build_path->append('Packages/owner/repo/Source/Hello.php')), 'Package Hello file content is not what it should be!');
        $expected = <<<EOD
<?php

namespace Package;

class Greeting
{
    public static function output()
    {
        return 'bye bye';
    }
}
EOD;
        assert_true($expected === file_get_contents($build_path->append('Packages/owner/repo/Source/Greeting.php')), 'Package Greeting file content is not what it should be!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Package\Greeting' => __DIR__ . '/Packages/owner/repo/Source/Greeting.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => __DIR__ . '/Packages/owner/repo/Source',
        'App' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});
require_once __DIR__ . '/Packages/owner/repo/Autoloads/Autoloaded.php';

EOD;

        assert_true($expected === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
    },
    before: function () {
        $config = ['map' => ['App' => 'Source'], 'packages' => ['https://github.com/owner/repo.git' => 'development',]];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('../../DummyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        $content = <<<EOD
<?php

namespace App;

use Package\Greeting;
use Package\Helper;

class Classname
{
    public function a_method()
    {
        return Helper\helper() . Greeting::output();
    }
}
EOD;
        File\create($path->append('Source/Classname.php'), $content);
        Directory\make_recursive($path->append('Packages/owner/repo/Source'));
        $config = [
            'map' => ['Package' => 'Source'],
            'autoloads' => ['Autoloads\Autoloaded.php']
        ];
        $meta = ['packages' => []];
        JsonFile\write($path->append('Packages/owner/repo/phpkg.config.json'), $config);
        JsonFile\write($path->append('Packages/owner/repo/phpkg.config-lock.json'), $meta);
        $content = <<<EOD
<?php

namespace Package\Helper;

use function Package\Hello\world;

function helper()
{
    return 'hello' . world();
}
EOD;
        File\create($path->append('Packages/owner/repo/Source/Helper.php'), $content);
        $content = <<<EOD
<?php

namespace Package\Hello;

function world()
{
    return 'package world';
}
EOD;
        File\create($path->append('Packages/owner/repo/Source/Hello.php'), $content);
        $content = <<<EOD
<?php

namespace Package;

class Greeting
{
    public static function output()
    {
        return 'bye bye';
    }
}
EOD;
        File\create($path->append('Packages/owner/repo/Source/Greeting.php'), $content);
        $content = <<<EOD
<?php

function helper()
{
    return 'hello package';
}
EOD;
        Directory\make_recursive($path->append('Packages/owner/repo/Autoloads'));
        File\create($path->append('Packages/owner/repo/Autoloads/Autoloaded.php'), $content);

    },
    after: function() {
        reset_dummy_project();
    }
);

test(
    title: 'it should add relative path to import file in entry-points and executables',
    case: function () {
        shell_exec('php ' . root() . 'phpkg build production --project=../../DummyProject');

        $build_path = Path::from_string(root())->append('../../DummyProject/builds/production');
        assert_true(File\exists($build_path->append('runner')), 'runner file is not copied!');
        $expected = <<<EOD
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
echo 'hello from package';
EOD;

        assert_true($expected === file_get_contents($build_path->append('Packages/owner/repo/run')), 'run file content is not correct!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => __DIR__ . '/../Packages/owner/repo/Source',
        'App' => __DIR__ . '/../Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        assert_true($expected === file_get_contents($build_path->append('vendor/autoload.php')), 'Import file content is not correct!');
        $expected = <<<EOD
<?php
require_once __DIR__ . '/../vendor/autoload.php';
echo 'hello world';
EOD;

        assert_true($expected === file_get_contents($build_path->append('Public/index.php')), 'index file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => ['App' => 'Source'],
            'packages' => ['https://github.com/owner/repo.git' => 'development'],
            'import-file' => 'vendor/autoload.php',
            'entry-points' => ['Public/index.php']
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('../../DummyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        Directory\make($path->append('Public'));
        $content = <<<EOD
<?php

echo 'hello world';
EOD;
        File\create($path->append('Public/index.php'), $content);
        Directory\make_recursive($path->append('Packages/owner/repo/Source'));
        $config = [
            'map' => ['Package' => 'Source'],
            'executables' => ['runner' => 'run']
        ];
        $meta = ['packages' => []];

        JsonFile\write($path->append('Packages/owner/repo/phpkg.config.json'), $config);
        JsonFile\write($path->append('Packages/owner/repo/phpkg.config-lock.json'), $meta);
        $content = <<<EOD
#!/usr/bin/env php
<?php

echo 'hello from package';
EOD;
        File\create($path->append('Packages/owner/repo/run'), $content);
    },
    after: function () {
        reset_dummy_project();
    }
);
