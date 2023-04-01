<?php

namespace Phpkg\Application\Builder;

use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\LinkPair;
use Phpkg\Classes\Config\NamespacePathPair;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\PhpFile;
use PhpRepos\Datatype\Map;
use PhpRepos\Datatype\Str;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\FilesystemCollection;
use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\Symlink;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when;
use function PhpRepos\Datatype\Str\after_first_occurrence;

function build(Project $project, Build $build): void
{
    Directory\renew_recursive($build->root());
    Directory\exists_or_create($build->packages_directory());

    $is_composer_package = function (Path $package_path) use ($project) {
        return ! $project->packages->has(fn (Package $package) => $package->root->string() === $package_path->string());
    };

    Directory\ls_all($project->packages_directory)->each(function (Path $owner) use ($build, $is_composer_package) {
        Directory\ls_all($owner)->each(function (Path $package) use ($owner, $build, $is_composer_package) {
            when(
                $is_composer_package($package),
                fn () =>
                    Directory\make_recursive($build->packages_directory()->append($owner->leaf())->append($package->leaf())) &&
                    Directory\preserve_copy_recursively(
                        $package,
                        $build->packages_directory()->append($owner->leaf())->append($package->leaf())
                    ),
            );
        });
    });



    $build->load_namespace_map();

    $project->packages->each(function (Package $package) use ($project, $build) {
        compile_packages($package, $build);
    });

    compile_project_files($build);
    create_import_file($build);

    $project->config->entry_points->each(function (Filename $entry_point) use ($build) {
        add_autoloads($build, $build->root()->append($entry_point));
    });

    $project->packages->each(function (Package $package)  use ($project, $build) {
        $package->config->executables->each(function (LinkPair $executable) use ($build, $package) {
            add_executables($build, $build->package_root($package)->append($executable->source()), $build->root()->append($executable->symlink()));
        });
    });
}

function add_executables(Build $build, Path $source, Path $symlink): void
{
    Symlink\link($source, $symlink);
    add_autoloads($build, $source);
    File\chmod($source, 0774);
}

function compile_packages(Package $package, Build $build): void
{
    Directory\renew_recursive($build->package_root($package));
    package_compilable_files_and_directories($package)
        ->each(fn (Path $filesystem)
        => compile($filesystem, $package->root, $build->package_root($package), $build, $package->config));
}

function compile_project_files(Build $build): void
{
    compilable_files_and_directories($build->project)
        ->each(fn (Path $filesystem)
        => compile($filesystem, $build->project->root, $build->root(), $build, $build->project->config)
        );
}

function compile(Path $address, Path $origin, Path $destination, Build $build, Config $config): void
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
                    $build,
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
        compile_file($build, $address, $destination_path);

        return;
    }

    File\preserve_copy($address, $destination_path);
}

function compile_file(Build $build, Path $origin, Path $destination): void
{
    File\create($destination, apply_file_modifications($build, $origin), File\permission($origin));
}

function apply_file_modifications(Build $build, Path $origin): string
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

    array_walk($imports, function ($import) use ($build, $paths) {
        $path = $build->namespace_map->first(fn (NamespacePathPair $namespace_path) => $namespace_path->namespace() === $import)?->path();
        $import = $path ? $import : Str\before_last_occurrence($import, '\\');
        $path = $path ?: $build->namespace_map->reduce(function (?Path $carry, NamespacePathPair $namespace_path) use ($import) {
            return str_starts_with($import, $namespace_path->namespace())
                ? $namespace_path->path()->append(after_first_occurrence($import, $namespace_path->namespace()) . '.php')
                : $carry;
        });
        unless(is_null($path), fn () => $paths->push(new NamespacePathPair($import, $path)));
    });

    array_walk($autoload, function ($import) use ($build) {
        $path = $build->namespace_map->reduce(function (?Path $carry, NamespacePathPair $namespace_path) use ($import) {
            return str_starts_with($import, $namespace_path->namespace())
                ? $namespace_path->path()->append(after_first_occurrence($import, $namespace_path->namespace()) . '.php')
                : $carry;
        });
        unless(is_null($path), fn () => $build->import_map->push(new NamespacePathPair($import, $path)));
    });

    if ($paths->count() === 0) {
        return $php_file->code();
    }

    $require_statements = $paths->map(fn(NamespacePathPair $namespace_path) => "require_once '{$namespace_path->path()->string()}';");

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
        || $config->executables->has(fn (LinkPair $executable) => $executable->source()->string() === $file->leaf()->string());
}

function add_autoloads(Build $build, Path $target): void
{
    $import_path = $build->import_path();

    $line = "require_once '$import_path';";

    $php_file = PhpFile::from_content(File\content($target));
    $php_file = $php_file->add_after_opening_tag(PHP_EOL . $line . PHP_EOL);
    File\modify($target, $php_file->code());
}

function create_import_file(Build $build): void
{
    $content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [

EOD;


    $import_map = iterator_to_array($build->import_map);
    usort($import_map, function (NamespacePathPair $namespace_path_pair1, NamespacePathPair $namespace_path_pair2) {
        return strcmp($namespace_path_pair1->namespace(), $namespace_path_pair2->namespace());
    });
    foreach ($import_map as $namespace_path) {
        $content .= <<<EOD
        '{$namespace_path->namespace()}' => '{$namespace_path->path()}',

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

    foreach ($build->namespace_map as $namespace_path) {
        $content .= <<<EOD
        '{$namespace_path->namespace()}' => '{$namespace_path->path()}',

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

    File\create($build->import_path(), $content);
}

