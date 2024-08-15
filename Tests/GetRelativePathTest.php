<?php

namespace Tests\GetRelativePathTest;

use function Phpkg\Application\Builder\get_relative_path;
use function PhpRepos\TestRunner\Assertions\assert_false;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return relative path between two paths',
    case: function() {
        assert_true('' === get_relative_path('/', '/'), 'Relative path for root does not work!' . get_relative_path('/', '/'));
        assert_true('file.php' === get_relative_path('/', '/file.php'), 'Relative path for root to a file does not work!' . get_relative_path('/', '/file.php'));
        assert_true('directory/file.php' === get_relative_path('/', '/directory/file.php'), 'Relative path for root to a file in directory does not work!' . get_relative_path('/', '/directory/file.php'));
        assert_true('../' === get_relative_path('/file.php', '/'), 'Relative path for file to root does not work!' . get_relative_path('/file.php', '/'));
        assert_true('../../' === get_relative_path('/directory/file.php', '/'), 'Relative path for file in directory to root does not work!' . get_relative_path('/directory/file.php', '/'));
        assert_true('' === get_relative_path('/file.php', '/file.php'), 'Relative path for same file does not work!' . get_relative_path('/file.php', '/file.php'));
        assert_true('file2.php' === get_relative_path('/file1.php', '/file2.php'), 'Relative path for the same directory does not work!' . get_relative_path('/file1.php', '/file2.php'));
        assert_true('subdirectory/file2.php' === get_relative_path('/directory/file1.php', '/directory/subdirectory/file2.php'), 'Relative path from parent to sub directory file does not work!' . get_relative_path('/directory/file1.php', '/directory/subdirectory/file2.php'));
        assert_true('file2.php' === get_relative_path('/directory/file1.php', '/directory/file2.php'), 'Relative path for the same sub directory does not work!' . get_relative_path('/directory/file1.php', '/directory/file2.php'));
        assert_true('../directory2/file2.php' === get_relative_path('/directory1/file1.php', '/directory2/file2.php'), 'Relative path for sub directory to another sub directory does not work!' . get_relative_path('/directory1/file1.php', '/directory2/file2.php'));
        assert_true('subdirectory/file2.php' === get_relative_path('/file1.php', '/subdirectory/file2.php'), 'Relative path for a directory in a sub directory does not work!' . get_relative_path('/file1.php', '/subdirectory/file2.php'));
        assert_true('subdirectory/another-subdirectory/file2.php' === get_relative_path('/file1.php', '/subdirectory/another-subdirectory/file2.php'), 'Relative path for a directory in many sub directory does not work!' . get_relative_path('/file1.php', '/subdirectory/another-subdirectory/file2.php'));
        assert_true('../file2.php' === get_relative_path('/directory/file1.php', '/file2.php'), 'Relative path when from is in a deeper directory does not work!' . get_relative_path('/directory/file1.php', '/file2.php'));
        assert_true('../subdirectory/file2.php' === get_relative_path('/directory/file1.php', '/subdirectory/file2.php'), 'Relative path when from is in a directory and to is in another directory does not work!' . get_relative_path('/directory/file1.php', '/subdirectory/file2.php'));
        assert_true('../../../vendor/autoload.php' === get_relative_path('/Packages/owner/repo/run', '/vendor/autoload.php'), 'Relative path when from is in a directory and to is in another directory does not work!' . get_relative_path('/Packages/owner/repo/run', '/vendor/autoload.php'));
        assert_true('../../repo2/Source/Class.php' === get_relative_path('/home/user/Project/Packages/owner/repo1/Source/Class.php', '/home/user/Project/Packages/owner/repo2/Source/Class.php'), 'Relative path between packages is not working!' . get_relative_path('/home/user/Project/Packages/owner/repo1/Source/Class.php', '/home/user/Project/Packages/owner/repo2/Source/Class.php'));
    }
);
