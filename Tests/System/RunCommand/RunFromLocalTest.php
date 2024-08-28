<?php

namespace Tests\System\RunCommand\RunFromLocalTest;

use Phpkg\Classes\Project;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\Path;
use function Phpkg\Application\PackageManager\commit;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should run a local package',
    case: function (Project $project) {
        $output = shell_exec('php ' . root() . 'phpkg run ' . $project->root);

        assert_true($output === 'Hello from local cli');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));
        $project->config->entry_points->push(new Filename('index.php'));

        $index_content = <<<EOD
<?php

echo 'Hello from local ' . php_sapi_name();

EOD;
        file_put_contents($project->root->append('index.php'), $index_content);

        commit($project);

        return $project;
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should run a local package using relative path',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg run ../../DummyProject');

        assert_true($output === 'Hello from local cli');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));
        $project->config->entry_points->push(new Filename('index.php'));

        $index_content = <<<EOD
<?php

echo 'Hello from local ' . php_sapi_name();

EOD;
        file_put_contents($project->root->append('index.php'), $index_content);

        commit($project);

        return $project;
    },
    after: function () {
        reset_dummy_project();
    }
);
