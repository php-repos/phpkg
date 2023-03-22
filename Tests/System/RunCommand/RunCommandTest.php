<?php

namespace Tests\System\RunCommand\RunCommandTest;

use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\IO\Write\assert_error;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Directory\make_recursive;
use function PhpRepos\FileManager\File\content;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should run the given entry point on the given package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg run https://github.com/php-repos/chuck-norris.git');

        assert_true(str_contains($output, 'Chuck Norris'));
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/chuck-norris'));
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
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/chuck-norris'));
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

        assert_true(str_starts_with(content($output), "PHP Warning:  file_get_contents({$path->string()}/phpkg.config.json): Failed to open stream"));

        return $output;
    },
    before: function () {
        $path = Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/chuck-norris/v1.0.0');
        make_recursive($path);

        return $path;
    },
    after: function (Path $output) {
        delete($output);
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/chuck-norris'));
    }
);
