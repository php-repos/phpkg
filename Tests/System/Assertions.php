<?php

namespace Tests\System\Assertions;

use Phpkg\Classes\Package;
use Phpkg\Classes\Project;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Path;
use function Phpkg\Application\PackageManager\temp_path;
use function Phpkg\Application\PackageManager\package_path;
use function PhpRepos\Datatype\Str\replace_first_occurrence;
use function PhpRepos\FileManager\Directory\ls_recursively;
use function PhpRepos\TestRunner\Assertions\assert_true;

function same_content_installed(Project $project, Package $package): void
{
    $temp_root = temp_path($package);
    $package_root = package_path($project, $package);

    assert_true(ls_recursively($temp_root)->vertices()->every(function (Path $path) use ($temp_root, $package_root) {
        $installed_path = replace_first_occurrence($path, $temp_root, $package_root);
        
        if (is_dir($path)) {
            return is_dir($installed_path);
        }
        
        return file_get_contents($path) === file_get_contents($installed_path);
    }), 'Content is not valid: ' . $package->value->owner . '/' . $package->value->repo);
}

function assert_cli_1_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/cli.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'CLI is not installed!');
    assert_true($installed_version->value->version === 'v1.0.0', 'CLI version is not v1.0.0!');
    assert_true(in_array($installed_version->value->hash, ['9d8bd24f9d31b5bf18bc01e89053d311495f714d', 'f7c1eecaee1fbf01f4ea90a375ae8a3cd4944b3e']), 'CLI hash is not for v1.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_cli_1_0_1_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/cli.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'CLI is not installed!');
    assert_true($installed_version->value->version === 'v1.0.1', 'CLI version is not v1.0.1!');
    assert_true($installed_version->value->hash === '390b20410730056c85cef45319d015675f370157', 'CLI hash is not for v1.0.1!');
    same_content_installed($project, $installed_version);
}

function assert_cli_1_1_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/cli.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'CLI is not installed!');
    assert_true($installed_version->value->version === 'v1.1.0', 'CLI version v1.1.0!');
    assert_true($installed_version->value->hash === '3583508a39eae5c6accb3ad898664b53fde2e1cf', 'CLI hash is not for v1.1.0!');
    same_content_installed($project, $installed_version);
}

function assert_cli_1_2_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/cli.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'CLI is not installed!');
    assert_true($installed_version->value->version === 'v1.2.0', 'CLI version is not v1.2.0!');
    assert_true($installed_version->value->hash === 'f7aea9668ad1e5fa6939630bb80e09d170af2beb', 'CLI hash is not for v1.2.0!');
    same_content_installed($project, $installed_version);
}

function assert_cli_1_2_1_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/cli.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'CLI is not installed!');
    assert_true($installed_version->value->version === 'v1.2.1', 'CLI version is not v1.2.1!');
    assert_true($installed_version->value->hash === 'ca52cd9b9477a323cc7737d6f5ebf6dff05cdf89', 'CLI hash is not for v1.2.1!');
    same_content_installed($project, $installed_version);
}

function assert_cli_2_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/cli.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'CLI is not installed!');
    assert_true($installed_version->value->version === 'v2.0.0', 'CLI version is not v2.0.0!');
    assert_true($installed_version->value->hash === 'f4445518f45bc4161037532298b509bc7fb071bd', 'CLI hash is not for v2.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_console_1_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/console.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'Console is not installed!');
    assert_true($installed_version->value->version === 'v1.0.0', 'Console version is not v1.0.0!');
    assert_true($installed_version->value->hash === 'd4509110e3e85a770da17e6e30df991141f24b99', 'Console hash is not for v1.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_datatype_1_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/datatype.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'Datatype is not installed!');
    assert_true($installed_version->value->version === 'v1.0.0', 'Datatype version is not v1.0.0!');
    assert_true($installed_version->value->hash === 'e802ba8c0cb2ffe2282de401bbf9e84a4ce1316a', 'Datatype hash is not for v1.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_datatype_1_0_1_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/datatype.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'Datatype is not installed!');
    assert_true($installed_version->value->version === 'v1.0.1', 'Datatype version is not v1.0.1!');
    assert_true($installed_version->value->hash === 'eb8f1baad1b1f6db40f9dcb61f5d39becd200788', 'Datatype hash is not for v1.0.1!');
    same_content_installed($project, $installed_version);
}

function assert_datatype_1_1_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/datatype.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'Datatype is not installed!');
    assert_true($installed_version->value->version === 'v1.1.0', 'Datatype version is not v1.1.0!');
    assert_true($installed_version->value->hash === '3b068db2d678d9a2eb803951cc602ad6a09fbee9', 'Datatype hash is not for v1.1.0!');
    same_content_installed($project, $installed_version);
}

function assert_datatype_1_2_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/datatype.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'Datatype is not installed!');
    assert_true($installed_version->value->version === 'v1.2.0', 'Datatype version is not v1.2.0!');
    assert_true($installed_version->value->hash === '81dbcabda6f2713ce093fdc846afa3d403486426', 'Datatype hash is not for v1.2.0!');
    same_content_installed($project, $installed_version);
}

function assert_datatype_2_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/datatype.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'Datatype is not installed!');
    assert_true($installed_version->value->version === 'v2.0.0', 'Datatype version is not v2.0.0!');
    assert_true($installed_version->value->hash === '7fe3a27e6b57ff31374927d14c63db07cbaee579', 'Datatype hash is not for v2.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_file_manager_1_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/file-manager.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'FileManager is not installed!');
    assert_true($installed_version->value->version === 'v1.0.0', 'FileManager version is not v1.0.0!');
    assert_true($installed_version->value->hash === 'b120a464839922b0a208bc198fbc06b491f08ee0', 'FileManager hash is not for v1.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_file_manager_1_0_1_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/file-manager.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'FileManager is not installed!');
    assert_true($installed_version->value->version === 'v1.0.1', 'FileManager version is not v1.0.1!');
    assert_true($installed_version->value->hash === '93a425e95107822e8da117708897528d18a9283d', 'FileManager hash is not for v1.0.1!');
    same_content_installed($project, $installed_version);
}

function assert_file_manager_2_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/file-manager.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'FileManager is not installed!');
    assert_true($installed_version->value->version === 'v2.0.0', 'FileManager version is not v2.0.0!');
    assert_true($installed_version->value->hash === 'e5104a58dd4192c95701d414373b3add3e504bff', 'FileManager hash is not for v2.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_file_manager_2_0_1_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/file-manager.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'FileManager is not installed!');
    assert_true($installed_version->value->version === 'v2.0.1', 'FileManager version is not v2.0.1!');
    assert_true($installed_version->value->hash === '81ef0a421f738f193d24f15107084617d6fb33b8', 'FileManager hash is not for v2.0.1!');
    same_content_installed($project, $installed_version);
}

function assert_file_manager_2_0_3_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/file-manager.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'FileManager is not installed!');
    assert_true($installed_version->value->version === 'v2.0.3', 'FileManager version is not v2.0.3!');
    assert_true($installed_version->value->hash === 'a03298b28f31192acf682b0dd015e3bb1aef307b', 'FileManager hash is not for v2.0.3!');
    same_content_installed($project, $installed_version);
}

function assert_test_runner_1_0_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/test-runner.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'TestRunner is not installed!');
    assert_true($installed_version->value->version === 'v1.0.0', 'TestRunner version is not v1.0.0!');
    assert_true(in_array($installed_version->value->hash, ['30f3ce06c760719c7a107532b6755f9882c57b83', 'c5ee7b5d9a228b6e833af414359d486609ee530d']), 'TestRunner hash is not for v1.0.0!');
    same_content_installed($project, $installed_version);
}

function assert_test_runner_1_0_1_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/test-runner.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'TestRunner is not installed!');
    assert_true($installed_version->value->version === 'v1.0.1', 'TestRunner version is not v1.0.1!');
    assert_true($installed_version->value->hash === 'a0e3b93fe1ac7efb6fac24633515d89160756c73', 'TestRunner hash is not for v1.0.1!');
    same_content_installed($project, $installed_version);
}

function assert_test_runner_1_0_2_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/test-runner.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'TestRunner is not installed!');
    assert_true($installed_version->value->version === 'v1.0.2', 'TestRunner version is not v1.0.2!');
    assert_true($installed_version->value->hash === '40c4989e99eda4f13c985f44d081bea321125200', 'TestRunner hash is not for v1.0.2!');
    same_content_installed($project, $installed_version);
}

function assert_test_runner_1_0_3_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/test-runner.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'TestRunner is not installed!');
    assert_true($installed_version->value->version === 'v1.0.3', 'TestRunner version is not v1.0.3!');
    assert_true($installed_version->value->hash === '80afc79a1bac48bce791b0a424357997e8ee75cb', 'TestRunner hash is not for v1.0.3!');
    same_content_installed($project, $installed_version);
}

function assert_test_runner_1_1_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/test-runner.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'TestRunner is not installed!');
    assert_true($installed_version->value->version === 'v1.1.0', 'TestRunner version is not v1.1.0!');
    assert_true($installed_version->value->hash === '119c47953485a6ec127cd896a70e96376306a954', 'TestRunner hash is not for v1.1.0!');
    same_content_installed($project, $installed_version);
}

function assert_test_runner_1_2_0_installed(Project $project): void
{
    $repository = Repository::from_url('https://github.com/php-repos/test-runner.git');
    /** @var Package|null $installed_version */
    $installed_version = $project->meta->packages->first(fn (Package $package) => $package->value->owner === $repository->owner && $package->value->repo === $repository->repo);
    assert_true($installed_version instanceof Package, 'TestRunner is not installed!');
    assert_true($installed_version->value->version === 'v1.2.0', 'TestRunner version is not v1.2.0!');
    assert_true($installed_version->value->hash === '3b6df8cd914747d1317840155f5b4ed73c441250', 'TestRunner hash is not for v1.2.0!');
    same_content_installed($project, $installed_version);
}
