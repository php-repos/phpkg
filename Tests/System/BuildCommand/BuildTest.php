<?php

namespace Tests\System\BuildCommand\BuildTest;

use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_false;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

function assert_build_output(string $output): void
{
    $lines = explode("\n", trim($output));

    assert_true(4 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Start building...", $lines[0] . PHP_EOL);
    assert_line("Checking packages...", $lines[1] . PHP_EOL);
    assert_line("Building...", $lines[2] . PHP_EOL);
    assert_success("Build finished successfully.", $lines[3] . PHP_EOL);
}

test(
    title: 'it should create build directory, environment directory and import file',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
    before: function () {
        $config = [];
        $meta = ['packages' => []];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should put imports in the given import file path',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
        assert_true(Directory\exists($build_path), 'Builds directory not exists!');
        assert_true(File\exists($build_path->append('phpkg.config.json')), 'config file is not copied!');
        assert_true(File\exists($build_path->append('phpkg.config-lock.json')), 'lock file is not copied!');
        assert_true(File\exists($build_path->append('vendor/autoload.php')), 'Import file not exists!');
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

        assert_true($expected === file_get_contents($build_path->append('vendor/autoload.php')), 'Import file content is not correct!');
    },
    before: function () {
        $config = ['import-file' => 'vendor/autoload.php'];
        $meta = ['packages' => []];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should create build directory, environment directory and import file for production also',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build production --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/production');
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
    before: function () {
        $config = [];
        $meta = ['packages' => []];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'the build should be contains the given map',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ]
        ];
        $meta = ['packages' => []];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'the build should be contains of autoload files when they exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

require_once '@BUILDS_DIRECTORY/helper.php';

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(File\exists($build_path->append('helper.php')));
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ]
        ];
        $meta = ['packages' => []];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add autoload files from packages when they exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

require_once '@BUILDS_DIRECTORY/Packages/owner/repo/helper.php';
require_once '@BUILDS_DIRECTORY/helper.php';

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(File\exists($build_path->append('helper.php')));
        assert_true(File\exists($build_path->append('Packages/owner/repo/helper.php')));
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ]
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');

        // Setup package
        $config = [
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should build entry points',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

require_once '@BUILDS_DIRECTORY/Packages/owner/repo/helper.php';
require_once '@BUILDS_DIRECTORY/helper.php';

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(File\exists($build_path->append('helper.php')));
        assert_true(File\exists($build_path->append('Packages/owner/repo/helper.php')));
        $expected = <<<EOD
<?php
require_once '@BUILDS_DIRECTORY/phpkg.imports.php';
// public/index file

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('public/index.php')), 'public/index file content is not correct!');
        $expected = <<<EOD
<?php
require_once '@BUILDS_DIRECTORY/phpkg.imports.php';
// index file

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('index.php')), 'index file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ],
            'entry-points' => [
                'public/index.php',
                'index.php',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ]
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
        Directory\make($path->append('public'));
        File\create($path->append('public/index.php'), <<<EDO
<?php

// public/index file

EDO
);
        File\create($path->append('index.php'), <<<EOD
<?php

// index file

EOD
);

        // Setup package
        $config = [
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add the require statement after declare strict type',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

require_once '@BUILDS_DIRECTORY/Packages/owner/repo/helper.php';
require_once '@BUILDS_DIRECTORY/helper.php';

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(File\exists($build_path->append('helper.php')));
        assert_true(File\exists($build_path->append('Packages/owner/repo/helper.php')));
        $expected = <<<EOD
<?php declare(strict_types=1);require_once '@BUILDS_DIRECTORY/phpkg.imports.php';

// public/index file

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('public/index.php')), 'public/index file content is not correct!');
        $expected = <<<EOD
<?php

declare(strict_types=0);require_once '@BUILDS_DIRECTORY/phpkg.imports.php';

// index file

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('index.php')), 'index file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ],
            'entry-points' => [
                'public/index.php',
                'index.php',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ]
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
        Directory\make($path->append('public'));
        File\create($path->append('public/index.php'), <<<EDO
<?php declare(strict_types=1);

// public/index file

EDO
        );
        File\create($path->append('index.php'), <<<EOD
<?php

declare(strict_types=0);

// index file

EOD
        );

        // Setup package
        $config = [
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should exclude defined paths in the exclude part',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

require_once '@BUILDS_DIRECTORY/Packages/owner/repo/helper.php';
require_once '@BUILDS_DIRECTORY/helper.php';

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(File\exists($build_path->append('helper.php')));
        assert_true(File\exists($build_path->append('Packages/owner/repo/helper.php')));
        $expected = <<<EOD
<?php declare(strict_types=1);require_once '@BUILDS_DIRECTORY/phpkg.imports.php';

// public/index file

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('public/index.php')), 'public/index file content is not correct!');
        $expected = <<<EOD
<?php

declare(strict_types=0);require_once '@BUILDS_DIRECTORY/phpkg.imports.php';

// index file

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('index.php')), 'index file content is not correct!');
        assert_false(File\exists($build_path->append('.gitignore')));
        assert_false(Directory\exists($build_path->append('node_modules')));
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ],
            'excludes' => [
                'node_modules',
                '.gitignore'
            ],
            'entry-points' => [
                'public/index.php',
                'index.php',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ]
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
        Directory\make($path->append('public'));
        File\create($path->append('public/index.php'), <<<EDO
<?php declare(strict_types=1);

// public/index file

EDO
        );
        File\create($path->append('index.php'), <<<EOD
<?php

declare(strict_types=0);

// index file

EOD
        );
        Directory\make($path->append('node_modules'));
        File\create($path->append('gitignore'), '');

        // Setup package
        $config = [
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add executables for packages',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

require_once '@BUILDS_DIRECTORY/Packages/owner/repo/helper.php';
require_once '@BUILDS_DIRECTORY/helper.php';

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(File\exists($build_path->append('helper.php')));
        assert_true(File\exists($build_path->append('Packages/owner/repo/helper.php')));
        $expected = <<<EOD
<?php declare(strict_types=1);require_once '@BUILDS_DIRECTORY/phpkg.imports.php';

// public/index file

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('public/index.php')), 'public/index file content is not correct!');
        $expected = <<<EOD
<?php

declare(strict_types=0);require_once '@BUILDS_DIRECTORY/phpkg.imports.php';

// index file

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('index.php')), 'index file content is not correct!');
        assert_false(File\exists($build_path->append('.gitignore')));
        assert_false(Directory\exists($build_path->append('node_modules')));
        $expected = <<<EOD
<?php
require_once '@BUILDS_DIRECTORY/phpkg.imports.php';
// bin/executable file

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) , file_get_contents($build_path->append('Packages/owner/repo/bin/executable.php')), 'bin/executable file content is not correct!');
        $expected = <<<EOD
<?php
require_once '@BUILDS_DIRECTORY/phpkg.imports.php';
// executable file

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Packages/owner/repo/executable.php')), 'executable file content is not correct!');
        assert_true(0774 === File\permission($build_path->append('package-bin')), 'package-bin permission is not correct!');
        assert_true(readlink($build_path->append('package-bin')) === $build_path->append('Packages/owner/repo/bin/executable.php')->string(), 'package-bin symlink target is not correct!');
        assert_true(0774 === File\permission($build_path->append('exec')), 'exec permission is not correct!');
        assert_true(readlink($build_path->append('exec')) === $build_path->append('Packages/owner/repo/executable.php')->string(), 'exec symlink target is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ],
            'excludes' => [
                'node_modules',
                '.gitignore'
            ],
            'entry-points' => [
                'public/index.php',
                'index.php',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ]
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
        Directory\make($path->append('public'));
        File\create($path->append('public/index.php'), <<<EDO
<?php declare(strict_types=1);

// public/index file

EDO
        );
        File\create($path->append('index.php'), <<<EOD
<?php

declare(strict_types=0);

// index file

EOD
        );
        Directory\make($path->append('node_modules'));
        File\create($path->append('gitignore'), '');

        // Setup package
        $config = [
            'autoloads' => [
                'helper.php',
                'not-exists.php',
            ],
            'executables' => [
                "package-bin" => "bin/executable.php",
                "exec" => "executable.php",
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        File\create($path->append('helper.php'), '');
        Directory\make($path->append('bin'));
        File\create($path->append('bin/executable.php'), <<<EOD
<?php

// bin/executable file

EOD
);
        File\create($path->append('executable.php'), <<<EOD
<?php

// executable file

EOD
);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add require statement for the imported file, when they exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Service;

function a_service()
{
  //
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Service.php')), 'Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Client;require_once '@BUILDS_DIRECTORY/Source/Service.php';

use App\Service;
use App\NotExist;

function client()
{
    NotExist\do_anything();
    return Service\a_service();
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ]
        ];
        $meta = [];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        File\create($path->append('Source/Service.php'), <<<'EOD'
<?php

namespace App\Service;

function a_service()
{
  //
}

EOD
);
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App\Client;

use App\Service;
use App\NotExist;

function client()
{
    NotExist\do_anything();
    return Service\a_service();
}

EOD
);
    },
    after: function () {
        reset_empty_project();
    },
);

test(
    title: 'it should add require statement for the imported file from packages, when they exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace Package\Service;

function a_service()
{

}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Packages/owner/repo/Src/Service.php')), 'Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Client;require_once '@BUILDS_DIRECTORY/Packages/owner/repo/Src/Service.php';

use Package\Service;
use Package\NotExist;

function client()
{
    NotExist\do_anything();
    return Service\a_service();
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App\Client;

use Package\Service;
use Package\NotExist;

function client()
{
    NotExist\do_anything();
    return Service\a_service();
}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/Service.php'), <<<'EOD'
<?php

namespace Package\Service;

function a_service()
{

}

EOD
);
    },
    after: function () {
        reset_empty_project();
    },
);

test(
    title: 'it should add require statement for used functions, when they exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Service;

function main_service()
{

}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Service.php')), 'Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace Package\Service;

function a_service()
{

}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Packages/owner/repo/Src/Service.php')), 'Package Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Client;require_once '@BUILDS_DIRECTORY/Source/Service.php';require_once '@BUILDS_DIRECTORY/Packages/owner/repo/Src/Service.php';

use function App\Service\main_service;
use function App\NotExists\not_exists_main_service;
use function Package\Service\a_service;
use function Package\NotExists\not_exists_a_service;

function client()
{
    return main_service(not_exists_a_service()) ?? a_service(not_exists_a_service());
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App\Client;

use function App\Service\main_service;
use function App\NotExists\not_exists_main_service;
use function Package\Service\a_service;
use function Package\NotExists\not_exists_a_service;

function client()
{
    return main_service(not_exists_a_service()) ?? a_service(not_exists_a_service());
}

EOD
        );

        File\create($path->append('Source/Service.php'), <<<'EOD'
<?php

namespace App\Service;

function main_service()
{

}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/Service.php'), <<<'EOD'
<?php

namespace Package\Service;

function a_service()
{

}

EOD
        );
    },
    after: function () {
        reset_empty_project();
    },
);

test(
    title: 'it should add require statement for used functions, when they exists and defined by alias',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Service;

function main_service()
{

}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Service.php')), 'Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace Package\Service;

function a_service()
{

}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Packages/owner/repo/Src/Service.php')), 'Package Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Client;require_once '@BUILDS_DIRECTORY/Source/Service.php';require_once '@BUILDS_DIRECTORY/Packages/owner/repo/Src/Service.php';

use function App\Service\main_service as Service;
use function App\NotExists\not_exists_main_service;
use function Package\Service\a_service as PackageService;
use function Package\NotExists\not_exists_a_service;

function client()
{
    return Service(not_exists_a_service()) ?? PackageService(not_exists_a_service());
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App\Client;

use function App\Service\main_service as Service;
use function App\NotExists\not_exists_main_service;
use function Package\Service\a_service as PackageService;
use function Package\NotExists\not_exists_a_service;

function client()
{
    return Service(not_exists_a_service()) ?? PackageService(not_exists_a_service());
}

EOD
        );

        File\create($path->append('Source/Service.php'), <<<'EOD'
<?php

namespace App\Service;

function main_service()
{

}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/Service.php'), <<<'EOD'
<?php

namespace Package\Service;

function a_service()
{

}

EOD
        );
    },
    after: function () {
        reset_empty_project();
    },
);

test(
    title: 'it should add require statement for used constants, when they exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
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
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Service;

const Name = 'main_service';

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Service.php')), 'Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace Package\Service;

const Package_Service = 'package_service';

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Packages/owner/repo/Src/Service.php')), 'Package Service file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Client;require_once '@BUILDS_DIRECTORY/Source/Service.php';require_once '@BUILDS_DIRECTORY/Packages/owner/repo/Src/Service.php';

use const App\Service\Name;
use const App\NotExists\NotExists;
use const Package\Service\Package_Service;
use const Package\NotExists\Package_not_exists;

function client()
{
    return Name . NotExists ?: Package_Service . Package_not_exists;
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App\Client;

use const App\Service\Name;
use const App\NotExists\NotExists;
use const Package\Service\Package_Service;
use const Package\NotExists\Package_not_exists;

function client()
{
    return Name . NotExists ?: Package_Service . Package_not_exists;
}

EOD
        );

        File\create($path->append('Source/Service.php'), <<<'EOD'
<?php

namespace App\Service;

const Name = 'main_service';

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/Service.php'), <<<'EOD'
<?php

namespace Package\Service;

const Package_Service = 'package_service';

EOD
        );
    },
    after: function () {
        reset_empty_project();
    },
);

test(
    title: 'it should add class to import file when used and exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'App\User' => '@BUILDS_DIRECTORY/Source/User.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App;

class User
{

}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/User.php')), 'User file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Client;

use App\User;
use App\NotExist;

function client()
{
    return new NotExists(new User());
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        File\create($path->append('Source/User.php'), <<<'EOD'
<?php

namespace App;

class User
{

}

EOD);

        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App\Client;

use App\User;
use App\NotExist;

function client()
{
    return new NotExists(new User());
}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
    },
    after: function () {
        reset_empty_project();
    },
);

test(
    title: 'it should add class from packages to import file when used and exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $build_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/builds/development');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Package\User' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src/User.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace Package;

class User
{

}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Packages/owner/repo/Src/User.php')), 'User file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Client;

use Package\User;
use Package\NotExist;

function client()
{
    return new NotExists(new User());
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App\Client;

use Package\User;
use Package\NotExist;

function client()
{
    return new NotExists(new User());
}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/User.php'), <<<'EOD'
<?php

namespace Package;

class User
{

}

EOD);
    },
    after: function () {
        reset_empty_project();
    },
);

test(
    title: 'it should add interface file to import when exists',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $project_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        $build_path = $project_path->append('builds/development');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'App\Interfaces\ClientInterface' => '@BUILDS_DIRECTORY/Source/Interfaces/ClientInterface.php',
        'Package\PackageInterface' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src/PackageInterface.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(file_get_contents($project_path->append('Packages/owner/repo/Src/PackageInterface.php')) === file_get_contents($build_path->append('Packages/owner/repo/Src/PackageInterface.php')), 'PackageInterface file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Interfaces/ClientInterface.php')) === file_get_contents($build_path->append('Source/Interfaces/ClientInterface.php')), 'ClientInterface file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Client.php')) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        Directory\make($path->append('Source/Interfaces'));
        File\create($path->append('Source/Interfaces/ClientInterface.php'), <<<'EOD'
<?php

namespace App\Interfaces;

interface ClientInterface
{
    public function do_it();
}

EOD
);
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App;

use App\Interfaces\ClientInterface;
use App\Interfaces\NotExistsInterface;
use Package\PackageInterface;

class Client implements ClientInterface, NotExistsInterface, PackageInterface
{

}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/PackageInterface.php'), <<<'EOD'
<?php

namespace Package;

interface PackageInterface
{

}

EOD);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add abstract file to import',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $project_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        $build_path = $project_path->append('builds/development');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'App\Abstracts\ClientParent' => '@BUILDS_DIRECTORY/Source/Abstracts/ClientParent.php',
        'Package\PackageAbstract' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src/PackageAbstract.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(file_get_contents($project_path->append('Packages/owner/repo/Src/PackageAbstract.php')) === file_get_contents($build_path->append('Packages/owner/repo/Src/PackageAbstract.php')), 'PackageAbstract file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Abstracts/ClientParent.php')) === file_get_contents($build_path->append('Source/Abstracts/ClientParent.php')), 'ClientParent file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Client.php')) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/PackageClient.php')) === file_get_contents($build_path->append('Source/PackageClient.php')), 'PackageClient file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        Directory\make($path->append('Source/Abstracts'));
        File\create($path->append('Source/Abstracts/ClientParent.php'), <<<'EOD'
<?php

namespace App\Abstracts;

abstract class ClientPrent
{
    public function do_it()
    {

    }
}

EOD
        );
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App;

use App\Abstracts\ClientParent;

class Client extends ClientParent
{

}

EOD
        );

        File\create($path->append('Source/PackageClient.php'), <<<'EOD'
<?php

namespace App;

use Package\PackageAbstract;

class PackageClient extends PackageAbstract
{

}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/PackageAbstract.php'), <<<'EOD'
<?php

namespace Package;

abstract class PackageAbstract
{

}

EOD);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add used traits file to import',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $project_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        $build_path = $project_path->append('builds/development');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'App\Traits\InlineTrait' => '@BUILDS_DIRECTORY/Source/Traits/InlineTrait.php',
        'App\Traits\NewLineTrait' => '@BUILDS_DIRECTORY/Source/Traits/NewLineTrait.php',
        'Package\PackageTrait' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src/PackageTrait.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(file_get_contents($project_path->append('Packages/owner/repo/Src/PackageTrait.php')) === file_get_contents($build_path->append('Packages/owner/repo/Src/PackageTrait.php')), 'PackageTrait file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Traits/InlineTrait.php')) === file_get_contents($build_path->append('Source/Traits/InlineTrait.php')), 'InlineTrait file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Traits/NewLineTrait.php')) === file_get_contents($build_path->append('Source/Traits/NewLineTrait.php')), 'NewLineTrait file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Client.php')) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        Directory\make($path->append('Source/Traits'));
        File\create($path->append('Source/Traits/InlineTrait.php'), <<<'EOD'
<?php

namespace App\Traits;

trait InlineTrait
{

}

EOD
        );
        File\create($path->append('Source/Traits/NewLineTrait.php'), <<<'EOD'
<?php

namespace App\Traits;

trait NewLineTrait
{

}

EOD
        );
        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App;

use App\Traits\InlineTrait;
use App\Traits\NewLineTrait;
use Package\PackageTrait;

class Client
{
    use PackageTrait, InlineTrait;
    use NewLineTrait;
}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
        File\create($path->append('Src/PackageTrait.php'), <<<'EOD'
<?php

namespace Package;

trait PackageTrait
{

}

EOD);
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should build properly when there is grouped uses',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $project_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        $build_path = $project_path->append('builds/development');
        assert_true(File\exists($build_path->append('phpkg.imports.php')), 'Import file not exists!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'App\Grouped\AnyClass' => '@BUILDS_DIRECTORY/Source/Grouped/AnyClass.php',
        'App\Grouped\ParameterType' => '@BUILDS_DIRECTORY/Source/Grouped/ParameterType.php',
        'App\Grouped\ReturnType' => '@BUILDS_DIRECTORY/Source/Grouped/ReturnType.php',
        'App\Grouped\TheAbstract' => '@BUILDS_DIRECTORY/Source/Grouped/TheAbstract.php',
        'App\Grouped\TheInterface' => '@BUILDS_DIRECTORY/Source/Grouped/TheInterface.php',
        'App\Grouped\TheTrait' => '@BUILDS_DIRECTORY/Source/Grouped/TheTrait.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/TheAbstract.php')) === file_get_contents($build_path->append('Source/Grouped/TheAbstract.php')), 'TheAbstract file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/TheInterface.php')) === file_get_contents($build_path->append('Source/Grouped/TheInterface.php')), 'TheInterface file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/TheTrait.php')) === file_get_contents($build_path->append('Source/Grouped/TheTrait.php')), 'TheTrait file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/ParameterType.php')) === file_get_contents($build_path->append('Source/Grouped/ParameterType.php')), 'ParameterType file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/ReturnType.php')) === file_get_contents($build_path->append('Source/Grouped/ReturnType.php')), 'ReturnType file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/AnyClass.php')) === file_get_contents($build_path->append('Source/Grouped/AnyClass.php')), 'AnyClass file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/Functions.php')) === file_get_contents($build_path->append('Source/Grouped/Functions.php')), 'Functions file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Grouped/Constants.php')) === file_get_contents($build_path->append('Source/Grouped/Constants.php')), 'Constants file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App;require_once '@BUILDS_DIRECTORY/Source/Grouped/Constants.php';require_once '@BUILDS_DIRECTORY/Source/Grouped/Functions.php';

use App\Grouped\{TheInterface, TheAbstract, TheTrait, ParameterType, ReturnType, AnyClass as ServiceClass};
use function App\Grouped\Functions\{func1, func2 as func3};
use const App\Grouped\Constants\{const1 as const2, const3};

class Client extends TheAbstract implements TheInterface
{
    use TheTrait;

    public function do_it(ParameterType $parameter_type): ReturnType
    {
        $var = new ServiceClass();

        return new ReturnType();
    }
}

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        Directory\make($path->append('Source/Grouped'));
        File\create($path->append('Source/Grouped/TheAbstract.php'), <<<'EOD'
<?php

namespace App\Grouped;

abstract class TheAbstract
{

}

EOD
        );

        File\create($path->append('Source/Grouped/TheInterface.php'), <<<'EOD'
<?php

namespace App\Grouped;

interface TheInterface
{

}

EOD
        );

        File\create($path->append('Source/Grouped/TheTrait.php'), <<<'EOD'
<?php

namespace App\Grouped;

trait TheTrait
{

}

EOD
        );

        File\create($path->append('Source/Grouped/ParameterType.php'), <<<'EOD'
<?php

namespace App\Grouped;

class ParameterType
{

}

EOD
        );

        File\create($path->append('Source/Grouped/ReturnType.php'), <<<'EOD'
<?php

namespace App\Grouped;

class ReturnType
{
    public function __construct()
    {
        var_dump('ok');
    }
}

EOD
        );

        File\create($path->append('Source/Grouped/AnyClass.php'), <<<'EOD'
<?php

namespace App\Grouped;

class AnyClass
{

}

EOD
        );

        File\create($path->append('Source/Grouped/Functions.php'), <<<'EOD'
<?php

namespace App\Grouped\Functions;

function func1()
{

}

function func2()
{

}

EOD
        );

        File\create($path->append('Source/Grouped/Constants.php'), <<<'EOD'
<?php

namespace App\Grouped\Constants;

const const2 = 'a const';
const const3 = 'another const';

EOD
        );

        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App;

use App\Grouped\{TheInterface, TheAbstract, TheTrait, ParameterType, ReturnType, AnyClass as ServiceClass};
use function App\Grouped\Functions\{func1, func2 as func3};
use const App\Grouped\Constants\{const1 as const2, const3};

class Client extends TheAbstract implements TheInterface
{
    use TheTrait;

    public function do_it(ParameterType $parameter_type): ReturnType
    {
        $var = new ServiceClass();

        return new ReturnType();
    }
}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should build used functions and constants when namespace imported using use',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $project_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        $build_path = $project_path->append('builds/development');
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
        'Package' => '@BUILDS_DIRECTORY/Packages/owner/repo/Src',
        'App' => '@BUILDS_DIRECTORY/Source',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Functions/Dir.php')) === file_get_contents($build_path->append('Source/Functions/Dir.php')), 'Dir file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Functions/File.php')) === file_get_contents($build_path->append('Source/Functions/File.php')), 'File file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Functions/JsonFile.php')) === file_get_contents($build_path->append('Source/Functions/JsonFile.php')), 'JsonFile file content is not correct!');
        assert_true(file_get_contents($project_path->append('Source/Functions/TextFile.php')) === file_get_contents($build_path->append('Source/Functions/TextFile.php')), 'TextFile file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App;require_once '@BUILDS_DIRECTORY/Source/Functions/Dir.php';require_once '@BUILDS_DIRECTORY/Source/Functions/File.php';require_once '@BUILDS_DIRECTORY/Source/Functions/JsonFile.php';require_once '@BUILDS_DIRECTORY/Source/Functions/TextFile.php';require_once '@BUILDS_DIRECTORY/Source/Functions/Str.php';require_once '@BUILDS_DIRECTORY/Source/Functions/Integers.php';

use App\Functions\Dir;
use App\Functions\File as Files;
use App\Functions\{JsonFile, TextFile as Text};
use App\Functions\Str as Strs, App\Functions\Integers;

class Client
{
    public function do_it()
    {
        $directory = Dir\all();
        $files = Files\get($directory);
        JsonFile\write('');
        Text\read();
        if (Strs\count() > Integers\sum()) {

        }
    }
}

EOD;

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/Client.php')), 'Client file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
            ],
            'packages' => [
                'https://github.com/owner/repo.git' => 'development',
            ],
        ];
        $meta = ['packages' => [
            'https://github.com/owner/repo.git' => [
                'owner' => 'owner',
                'repo' => 'repo',
                'version' => 'development',
                'hash' => '123abc',
            ]
        ]];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        Directory\make($path->append('Source/Functions'));
        File\create($path->append('Source/Functions/Dir.php'), <<<'EOD'
<?php

namespace App\Functions\Dir;

function all()
{

}

EOD
        );
        File\create($path->append('Source/Functions/File.php'), <<<'EOD'
<?php

namespace App\Functions\File;

function get($directory)
{

}

EOD
        );

        File\create($path->append('Source/Functions/JsonFile.php'), <<<'EOD'
<?php

namespace App\Functions\JsonFile;

function write('')
{

}

EOD
        );
        File\create($path->append('Source/Functions/TextFile.php'), <<<'EOD'
<?php

namespace App\Functions\TextFile;

function read()
{

}

EOD
        );
        File\create($path->append('Source/Functions/Str.php'), <<<'EOD'
<?php

namespace App\Functions\Str;

function count()
{

}

EOD
        );
        File\create($path->append('Source/Functions/Integers.php'), <<<'EOD'
<?php

namespace App\Functions\Integers;

function sum()
{

}

EOD
        );

        File\create($path->append('Source/Client.php'), <<<'EOD'
<?php

namespace App;

use App\Functions\Dir;
use App\Functions\File as Files;
use App\Functions\{JsonFile, TextFile as Text};
use App\Functions\Str as Strs, App\Functions\Integers;

class Client
{
    public function do_it()
    {
        $directory = Dir\all();
        $files = Files\get($directory);
        JsonFile\write('');
        Text\read();
        if (Strs\count() > Integers\sum()) {

        }
    }
}

EOD
        );

        // Setup package
        $config = [
            'map' => [
                'Package' => 'Src'
            ]
        ];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject/Packages/owner/repo');
        Directory\make_recursive($path);
        JsonFile\write($path->append('phpkg.config.json'), $config);
        Directory\make($path->append('Src'));
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add used imported files to the classmap',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_build_output($output);
        $project_path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        $build_path = $project_path->append('builds/development');
        $expected = <<<EOD
<?php

namespace App\Handler;require_once '@BUILDS_DIRECTORY/Helper/Helpers.php';require_once '@BUILDS_DIRECTORY/Source/functions.php';

use App\Helper\Service;
use App\World;
use function App\Helper\Helpers\help;
use function App\Functions\line;

function handle()
{
    return new Service(help() . new World()) . line();
}

EOD;
        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('Source/App.php')), 'App file content is not correct!');
        $expected = <<<EOD
<?php

namespace App\Helper\Helpers;

function help()
{
    return 'Help';
}

EOD;
        assert_true($expected === file_get_contents($build_path->append('Helper/Helpers.php')), 'Helper file content is not correct!');
        $expected = <<<'EOD'
<?php

namespace App\Helper;

class Service
{
    public function __construct(public string $content) {}
}

EOD;
        assert_true($expected === file_get_contents($build_path->append('Services/Service.php')), 'Service file content is not correct!');
        $expected = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'App\Functions\line' => '@BUILDS_DIRECTORY/Source/functions.php',
        'App\Helper\NotUsedService' => '@BUILDS_DIRECTORY/Services/NotUsedService.php',
        'App\Helper\Service' => '@BUILDS_DIRECTORY/Services/Service.php',
        'App\World' => '@BUILDS_DIRECTORY/Source/World.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'App' => '@BUILDS_DIRECTORY/Source',
        'App\Helper' => '@BUILDS_DIRECTORY/Helper',
        'App\NotUsed' => '@BUILDS_DIRECTORY/ValidNamespace',
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

        assert_true(str_replace('@BUILDS_DIRECTORY', $build_path->string(), $expected) === file_get_contents($build_path->append('phpkg.imports.php')), 'Import file content is not correct!');
    },
    before: function () {
        $config = [
            'map' => [
                'App' => 'Source',
                'App\\Helper' => 'Helper',
                'App\\NotUsed' => 'ValidNamespace',
                'App\\Helper\\Service' => 'Services/Service.php',
                'App\\Helper\\NotUsedService' => 'Services/NotUsedService.php',
                'App\\Functions\\line' => 'Source/functions.php',
            ]
        ];
        $meta = [];
        $path = Path::from_string(root())->append('TestRequirements/Fixtures/EmptyProject');
        JsonFile\write($path->append('phpkg.config.json'), $config);
        JsonFile\write($path->append('phpkg.config-lock.json'), $meta);
        Directory\make($path->append('Source'));
        Directory\make($path->append('Helper'));
        Directory\make($path->append('Services'));
        $content = <<<EOD
<?php

namespace App\Handler;

use App\Helper\Service;
use App\World;
use function App\Helper\Helpers\help;
use function App\Functions\line;

function handle()
{
    return new Service(help() . new World()) . line();
}

EOD;
        File\create($path->append('Source/App.php'), $content);
        $content = <<<EOD
<?php

namespace App\Functions;

function line()
{
    return PHP_EOL;
}

EOD;
        File\create($path->append('Source/functions.php'), $content);
        $content = <<<EOD
<?php

namespace App;

class World
{
    public static function world() 
    {
        return 'World';
    }
}

EOD;
        File\create($path->append('Source/World.php'), $content);

        $content = <<<EOD
<?php

namespace App\Helper\Helpers;

function help()
{
    return 'Help';
}

EOD;
        File\create($path->append('Helper/Helpers.php'), $content);
        $content = <<<'EOD'
<?php

namespace App\Helper;

class Service
{
    public function __construct(public string $content) {}
}

EOD;
        File\create($path->append('Services/Service.php'), $content);
        File\create($path->append('Services/NotUsedService.php'), '');
    },
    after: function () {
        reset_empty_project();
    }
);
