<?php

namespace Tests\System\RunCommand\RunCommandTest;

use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Directory\make_recursive;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\File\exists;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should run the given entry point on the given package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg run https://github.com/php-repos/chuck-norris.git');

        assert_true(str_contains($output, 'Chuck Norris'));
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/github.com/php-repos/chuck-norris'));
    }
);

test(
    title: 'it should run the given version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg run https://github.com/php-repos/dummy-tool.git --version=v1');

        assert_true(str_contains($output, 'v1: Hello world from cli'));
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/github.com/php-repos/dummy-tool'));
    }
);

test(
    title: 'it should run the given version at the latest commit',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg run https://github.com/php-repos/dummy-tool.git --version=development');

        assert_true(str_contains($output, 'Latest commit: Hello world from cli'));
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/github.com/php-repos/dummy-tool'));
    }
);

test(
    title: 'it should run the given version at the latest commit',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg run https://github.com/php-repos/dummy-tool.git --version=development#7a327643799d64632f2e1dbdb69f1f5eb6658881');

        assert_true(str_contains($output, 'Specific commit: Hello world from cli'));
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/github.com/php-repos/dummy-tool'));
    }
);

test(
    title: 'it should show error message when the entry point is not defined',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg run https://github.com/php-repos/chuck-norris.git not-exists.php');

        $lines = explode("\n", trim($output));

        assert_error("Entry point not-exists.php is not defined in the package.", $lines[0] . PHP_EOL);
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/github.com/php-repos/chuck-norris'));
    }
);

test(
    title: 'it should use existed version when it is available',
    case: function (Path $path) {
        $output = Path::from_string(sys_get_temp_dir())->append('run-output.txt');
        $descriptor_spec = [
            STDIN,
            ['file', $output->string(), "a"],
            ['file', $output->string(), "a"]
        ];
        $proc = proc_open('php ' . root() . 'phpkg run https://github.com/php-repos/chuck-norris.git', $descriptor_spec, $pipes);
        proc_close($proc);

        assert_false(exists($path->append('phpkg.config.json')));

        return $output;
    },
    before: function () {
        $path = Path::from_string(sys_get_temp_dir())->append('phpkg/runner/github.com/php-repos/chuck-norris/193d86c969b8d1e5ddf1f2d031e0b7cacd19adac');
        make_recursive($path);

        return $path;
    },
    after: function (Path $output) {
        delete($output);
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/github.com/php-repos/chuck-norris'));
    }
);

test(
    title: 'it should run a composer package',
    case: function (Path $path) {
        $output = shell_exec('php ' . root() . 'phpkg run https://github.com/phpstan/phpstan.git phpstan analyze ' . $path->string() . ' -l 9');

        assert_true(str_contains($output, '[ERROR] Found 2 errors'));
    },
    before: function () {
        $path = Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject');
        $content = <<<'EOD'
<?php

function a($b)
{
 echo $b;
}

a(1);

EOD;

        file_put_contents($path->append('index.php'), $content);

        return $path;
    },
    after: function () {
         reset_empty_project();
    }
);
