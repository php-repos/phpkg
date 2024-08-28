<?php

namespace Tests\System\WatchCommandTest;

use function Phpkg\System\is_windows;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\FileManager\File\create;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

if (is_windows()) {
    line('I don\'t know how to make this work on windows. So for now, i\'m ignoring it.');
    return;
}

test(
    title: 'it should watch for changes',
    case: function () {
        $command =  'php ' . root() . 'phpkg watch --wait=1 --project=../../DummyProject > /dev/null 2>&1 & echo $!; ';
        $pid = exec($command, $output);

        $content = <<<'EOD'
<?php

namespace DummyProject;

class SimpleClassForDummyProject
{

}

EOD;


        create(
            realpath(root() . '../../DummyProject/Source/SimpleClassForDummyProject.php'),
            $content
        );

        sleep(2);

        $output = implode(PHP_EOL, $output);

        assert_true(file_exists(realpath(root() . '../../DummyProject/builds')), 'builds directory does not exists! ' . $output);
        assert_true(true === file_exists(realpath(root() . '../../DummyProject/builds/development')), 'development directory does not exists! ' . $output);
        assert_true(true === file_exists(realpath(root() . '../../DummyProject/builds/development/Source')), 'Source directory does not exists! ' . $output);
        assert_true(file_exists(realpath(root() . '../../DummyProject/builds/development/Source/SimpleClassForDummyProject.php')), 'File has not been built! ' . $output);
        posix_kill($pid, SIGKILL);
    },
    before: function () {
        mkdir(realpath(root() . '../../DummyProject/Source'));
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);
