<?php

namespace Phpkg\Application\Builder;

use Phpkg\Classes\Config;
use Phpkg\Classes\Dependency;
use Phpkg\Classes\LinkPair;
use Phpkg\Classes\NamespacePathPair;
use Phpkg\Classes\Package;
use Phpkg\Classes\Project;
use Phpkg\PhpFile;
use PhpRepos\Datatype\Map;
use PhpRepos\Datatype\Str;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\FilesystemCollection;
use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\Symlink;
use function Phpkg\Application\PackageManager\build_package_path;
use function Phpkg\Application\PackageManager\build_packages_directory;
use function Phpkg\Application\PackageManager\build_root;
use function Phpkg\Application\PackageManager\import_file_path;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when;
use function PhpRepos\Datatype\Str\after_first_occurrence;

function build(Project $project): void
{
    Directory\renew_recursive(build_root($project));
    Directory\exists_or_create(build_packages_directory($project));

    $is_composer_package = function (Path $package_path) use ($project) {
        return ! $project->dependencies->has(fn (Dependency $dependency) => $dependency->value->root->string() === $package_path->string());
    };

    $copy_package = fn (Path $package, Path $destination) =>
        when(
            $is_composer_package($package),
            fn () =>
            when(
                is_dir($package),
                fn () => Directory\make_recursive($destination) && Directory\preserve_copy_recursively($package, $destination),
                fn () => File\preserve_copy($package, $destination)
            )
        );

    $copy_owner = fn (Path $owner) =>
        when(
            is_dir($owner),
            fn () => Directory\make_recursive(build_packages_directory($project)->append($owner->leaf()))
                && Directory\ls_all($owner)->each(
                    function (Path $package) use ($owner, $project, $is_composer_package, $copy_package) {
                        $destination = build_packages_directory($project)->append($owner->leaf())->append($package->leaf());
                        $copy_package($package, $destination);
                    }
                ),
            fn () => File\preserve_copy($owner, build_packages_directory($project)->append($owner->leaf()))
        );

    Directory\ls_all($project->packages_directory)
        ->each(function (Path $owner) use ($project, $is_composer_package, $copy_owner) {
            $copy_owner($owner);
        });

    $project->dependencies->each(function (Dependency $dependency) use ($project) {
        compile_packages($dependency->value, $project);
    });

    compile_project_files($project);
    create_import_file($project);

    $project->config->entry_points->each(function (Filename $entry_point) use ($project) {
        add_autoloads($project, build_root($project)->append($entry_point));
    });

    $project->dependencies->each(function (Dependency $dependency)  use ($project) {
        $dependency->value->config->executables->each(function (LinkPair $executable) use ($project, $dependency) {
            add_executables($project, build_package_path($project, $dependency->value->repository)->append($executable->value), build_root($project)->append($executable->key));
        });
    });
}

function add_executables(Project $project, Path $source, Path $symlink): void
{
    Symlink\link($source, $symlink);
    add_autoloads($project, $source);
    File\chmod($source, 0774);
}

function compile_packages(Package $package, Project $project): void
{
    Directory\renew_recursive(build_package_path($project, $package->repository));
    package_compilable_files_and_directories($package)
        ->each(fn (Path $filesystem)
        => compile($filesystem, $package->root, build_package_path($project, $package->repository), $project, $package->config));
}

function compile_project_files(Project $project): void
{
    compilable_files_and_directories($project)
        ->each(fn (Path $filesystem)
            => compile($filesystem, $project->root, build_root($project), $project, $project->config)
        );
}

function compile(Path $address, Path $origin, Path $destination, Project $project, Config $config): void
{
    $destination_path = $address->relocate($origin, $destination);

    if (is_dir($address)) {
        Directory\preserve_copy($address, $destination_path);

        Directory\ls_all($address)
            ->each(
                fn (Path $filesystem)
                => compile(
                    $filesystem,
                    $origin->append($address->leaf()),
                    $destination->append($address->leaf()),
                    $project,
                    $config,
                )
            );

        return;
    }

    if (is_link($address)) {
        $source_link = $address->parent()->append(readlink($address));
        Symlink\link($source_link, $destination_path);

        return;
    }

    if (file_needs_modification($address, $config)) {
        compile_file($project, $address, $destination_path);

        return;
    }

    File\preserve_copy($address, $destination_path);
}

function compile_file(Project $project, Path $origin, Path $destination): void
{
    File\create($destination, apply_file_modifications($project, $origin), File\permission($origin));
}

function apply_file_modifications(Project $project, Path $origin): string
{
    $php_file = PhpFile::from_content(File\content($origin));
    $file_imports = $php_file->imports();

    $autoload = $file_imports['classes'];

    foreach ($autoload as $import => $alias) {
        $used_functions = $php_file->used_functions($alias);
        $used_constants = $php_file->used_constants($alias);

        if (count($used_functions) > 0 || count($used_constants) > 0) {
            foreach ($used_constants as $constant) {
                $file_imports['constants'][$import . '\\' . $constant] = $constant;
            }
            foreach ($used_functions as $function) {
                $file_imports['functions'][$import . '\\' . $function] = $function;
            }

            unset($autoload[$import]);
        }
    }

    $imports = array_keys(array_merge($file_imports['constants'], $file_imports['functions']));
    $autoload = array_keys($autoload);

    $paths = new Map([]);

    array_walk($imports, function ($import) use ($project, $paths) {
        $path = $project->namespace_map->first(fn (NamespacePathPair $namespace_path) => $namespace_path->key === $import)?->value;
        $import = $path ? $import : Str\before_last_occurrence($import, '\\');
        $path = $path ?: $project->namespace_map->reduce(function (?Path $carry, NamespacePathPair $namespace_path) use ($import) {
            return str_starts_with($import, $namespace_path->key)
                ? $namespace_path->value->append(after_first_occurrence($import, $namespace_path->key) . '.php')
                : $carry;
        });
        unless(is_null($path), fn () => $paths->push(new NamespacePathPair($import, $path)));
    });

    array_walk($autoload, function ($import) use ($project) {
        $path = $project->namespace_map->reduce(function (?Path $carry, NamespacePathPair $namespace_path) use ($import) {
            return str_starts_with($import, $namespace_path->key)
                ? $namespace_path->value->append(after_first_occurrence($import, $namespace_path->key) . '.php')
                : $carry;
        });
        unless(is_null($path), fn () => $project->import_map->push(new NamespacePathPair($import, $path)));
    });

    if ($paths->count() === 0) {
        return $php_file->code();
    }

    $require_statements = $paths->map(fn(NamespacePathPair $namespace_path) => "require_once '{$namespace_path->value->string()}';");

    $php_file = $php_file->has_namespace()
        ? $php_file->add_after_namespace(PHP_EOL . PHP_EOL . implode(PHP_EOL, $require_statements))
        : $php_file->add_after_opening_tag(PHP_EOL . implode(PHP_EOL, $require_statements) . PHP_EOL);

    return $php_file->code();
}

function package_compilable_files_and_directories(Package $package): FilesystemCollection
{
    $excluded_paths = new FilesystemCollection();
    $excluded_paths->push($package->root->append('.git'));
    $package->config->excludes
        ->each(fn (Filename $exclude) => $excluded_paths->push($package->root->append($exclude)));

    return Directory\ls_all($package->root)
        ->except(fn (Path $file_or_directory) => $excluded_paths->has(fn (Path $excluded) => $excluded->string() === $file_or_directory->string()));
}

function compilable_files_and_directories(Project $project): FilesystemCollection
{
    $excluded_paths = new FilesystemCollection();
    $excluded_paths->push($project->root->append('.git'));
    $excluded_paths->push($project->root->append('.idea'));
    $excluded_paths->push($project->root->append('builds'));
    $excluded_paths->push($project->packages_directory);
    $project->config->excludes
        ->each(fn (Filename $exclude) => $excluded_paths->push($project->root->append($exclude)));

    return Directory\ls_all($project->root)
        ->except(fn (Path $file_or_directory)
        => $excluded_paths->has(fn (Path $excluded) => $excluded->string() === $file_or_directory->string()));
}

function file_needs_modification(Path $file, Config $config): bool
{
    return str_ends_with($file, '.php')
        || $config->entry_points->has(fn (Filename $entry_point) => $entry_point->string() === $file->leaf()->string())
        || $config->executables->has(fn (LinkPair $executable) => $executable->value->string() === $file->leaf()->string());
}

function add_autoloads(Project $project, Path $target): void
{
    $import_path = import_file_path($project);

    $line = "require_once '$import_path';";

    $php_file = PhpFile::from_content(File\content($target));
    $php_file = $php_file->add_after_opening_tag(PHP_EOL . $line . PHP_EOL);
    File\modify($target, $php_file->code());
}

function create_import_file(Project $project): void
{
    $content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [

EOD;


    $import_map = iterator_to_array($project->import_map);
    usort($import_map, function (NamespacePathPair $namespace_path_pair1, NamespacePathPair $namespace_path_pair2) {
        return strcmp($namespace_path_pair1->key, $namespace_path_pair2->key);
    });
    foreach ($import_map as $namespace_path) {
        $content .= <<<EOD
        '$namespace_path->key' => '$namespace_path->value',

EOD;
    }

    $content .= <<<'EOD'
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [

EOD;

    foreach ($project->namespace_map as $namespace_path) {
        $content .= <<<EOD
        '$namespace_path->key' => '$namespace_path->value',

EOD;
    }
    $content .= <<<'EOD'
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            require $realpath;
            return ;
        }
    }
});

EOD;

    File\create(import_file_path($project), $content);
}

