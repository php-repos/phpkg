<?php

namespace Tests\System\ServeCommand\ServeCommandTest;

use Exception;
use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\IO\Write\assert_error;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Directory\make_recursive;
use function PhpRepos\FileManager\File\content;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should serve the given application',
    case: function () {
        $command = 'php ' . root() . 'phpkg serve https://github.com/php-repos/daily-routine.git';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        assert_true($process !== false, 'Failed to run the serve command');

        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        $output = '';
        $error = '';
        sleep(1);

        assert_true(str_contains($output .= stream_get_contents($pipes[1]), 'Serving https://github.com/php-repos/daily-routine.git on http://localhost:8000'), 'Serve did not work.');
        assert_true(empty($error .= stream_get_contents($pipes[2])), 'there is an error while serving: ' . $error);

        $serve_is_running = true;
        while ($serve_is_running) {
            usleep(200);
            $serve_is_running =
                ! str_contains($output .= stream_get_contents($pipes[1]), 'Development Server (http://localhost:8000) started')
                && empty($error .= stream_get_contents($pipes[2]));
        }

        assert_project_is_working();
        assert_kill_works_for_webserver($process);
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/daily-routine'));
    }
);

test(
    title: 'it should show error message when the entry point is not defined for serve',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg serve https://github.com/php-repos/daily-routine.git not-exists.php');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Serving https://github.com/php-repos/daily-routine.git on http://localhost:8000", $lines[0] . PHP_EOL);
        assert_error("Entry point not-exists.php is not defined in the package.", $lines[1] . PHP_EOL);
    },
    after: function () {
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/daily-routine'));
    }
);

test(
    title: 'it should use existed version when it is available for serve',
    case: function (Path $path) {
        $output = Path::from_string(sys_get_temp_dir())->append('run-output.txt');
        $descriptor_spec = [
            STDIN,
            ['file', $output->string(), "a"],
            ['file', $output->string(), "a"]
        ];
        $proc = proc_open('php ' . root() . 'phpkg serve https://github.com/php-repos/daily-routine.git', $descriptor_spec, $pipes);
        proc_close($proc);

        assert_true(str_contains(content($output), "PHP Warning:  file_get_contents({$path->string()}/phpkg.config.json): Failed to open stream"));

        return $output;
    },
    before: function () {
        $path = Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/daily-routine/v1.0.0');
        make_recursive($path);

        return $path;
    },
    after: function (Path $output) {
        delete($output);
        delete_recursive(Path::from_string(sys_get_temp_dir())->append('phpkg/runner/php-repos/daily-routine'));
    }
);

function assert_project_is_working(): void
{
    $response = get();

    assert_true(empty($response['error']), $response['error']);
    assert_true(str_contains($response['response'], '<title>Daily Routine</title>'), 'Application\'s response is not correct.' . $response['response']);
}

function assert_kill_works_for_webserver($process): void
{
    // 10 Seconds!
    $timeout = 10000000;
    $interval = 2000;

    $port_is_open = function () {
        $socket = @fsockopen('localhost', 8000, $error_code, $error_message, 0.2);

        if ($socket === false) {
            return false;
        }

        return fclose($socket);
    };

    while ($timeout > $interval) {
        posix_kill(proc_get_status($process)['pid'], SIGINT);
        posix_kill(proc_get_status($process)['pid'], SIGTERM);

        usleep($interval);

        if ($port_is_open()) {
            $timeout -= $interval;
        } else {
            sleep(1);
            assert_false(proc_get_status($process)['running'], 'Serve process is running!');
            return;
        }
    }

    throw new Exception('Timeout reached to close the port.');
}

function get(): array
{
    $url = "http://localhost:8000/";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: text/html"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return compact('response', 'error');
}
