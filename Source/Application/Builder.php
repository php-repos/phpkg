<?php

namespace Phpkg\Application\Builder;

use JsonException;
use Phpkg\Classes\BuildMode;
use Phpkg\Classes\Config;
use Phpkg\Classes\LinkPair;
use Phpkg\Classes\NamespaceFilePair;
use Phpkg\Classes\NamespacePathPair;
use Phpkg\Classes\Package;
use Phpkg\Classes\Project;
use Phpkg\PhpFile;
use PhpRepos\Datatype\Map;
use PhpRepos\Datatype\Str;
use PhpRepos\Datatype\Tree;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\FilesystemCollection;
use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\Symlink;
use function Phpkg\Application\PackageManager\config_from_disk;
use function Phpkg\Application\PackageManager\package_path;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\Datatype\Str\after_first_occurrence;

function is_production_build(BuildMode $build_mode): bool
{
    return $build_mode === BuildMode::Production;
}

function get_relative_path(string $from, string $to): string
{
    if ($from === $to) {
        return '';
    }

    $from_tree = new Tree(DIRECTORY_SEPARATOR);
    $to_tree = new Tree(DIRECTORY_SEPARATOR);

    if ($from !== DIRECTORY_SEPARATOR) {
        $previous = DIRECTORY_SEPARATOR;
        foreach (explode(DIRECTORY_SEPARATOR, trim($from, DIRECTORY_SEPARATOR)) as $section) {
            $from_tree->edge($previous, $section);
            $previous = $section;
        }
    }

    if ($to !== DIRECTORY_SEPARATOR) {
        $previous = DIRECTORY_SEPARATOR;
        foreach (explode(DIRECTORY_SEPARATOR, trim($to, DIRECTORY_SEPARATOR)) as $section) {
            $to_tree->edge($previous, $section);
            $previous = $section;
        }
    }

    $first_change = $from_tree->vertices()->first_key(fn (string $from, int $index) => ! isset($to_tree->vertices()->items()[$index]) || $to_tree->vertices()->items()[$index] !== $from);
    $first_change = is_null($first_change) ? $from_tree->vertices()->last_key() : $first_change;

    $relative = '';

    for ($i = min($first_change, $to_tree->vertices()->last_key()); $i < $from_tree->vertices()->last_key(); $i++) {
        $relative .= '..' . DIRECTORY_SEPARATOR;
    }

    for ($i = max(1, $first_change); $i <= $to_tree->vertices()->last_key(); $i++) {
        $relative .= $to_tree->vertices()->items()[$i];

        if ($i !== $to_tree->vertices()->last_key()) {
            $relative .= DIRECTORY_SEPARATOR;
        }
    }

    return $relative;
}

function build_package_path(Project $project, Package $package): Path
{
    return build_packages_directory($project)->append("{$package->value->owner}/{$package->value->repo}");
}

function import_file_path(Project $project): Path
{
    return build_root($project)->append($project->config->import_file);
}

function build_root(Project $project): Path
{
    return $project->root->append('builds')->append($project->build_mode->value);
}

function build_packages_directory(Project $project): Path
{
    return build_root($project)->append($project->config->packages_directory);
}

/**
 * @throws JsonException
 */
function build(Project $project): void
{
    Directory\renew_recursive(build_root($project));
    Directory\exists_or_create(build_packages_directory($project));

    $import_map = new Map();
    $namespace_map = new Map();

    $project->meta->packages->each( function (Package $package) use ($project, $namespace_map, $import_map) {
        config_from_disk(package_path($project, $package))->map->each(function (NamespaceFilePair $namespace_file) use ($project, $namespace_map, $import_map, $package) {
            $path = build_package_path($project, $package)->append($namespace_file->value);
            if (str_ends_with($path, '.php')) {
                $import_map->push(new NamespacePathPair($namespace_file->key, $path));
            } else {
                $namespace_map->push(new NamespacePathPair($namespace_file->key, $path));
            }
        });
    });
    $project->config->map->each(function (NamespaceFilePair $namespace_file) use ($project, $namespace_map, $import_map) {
        $path = build_root($project)->append($namespace_file->value);
        if (str_ends_with($path, '.php')) {
            $import_map->push(new NamespacePathPair($namespace_file->key, $path));
        } else {
            $namespace_map->push(new NamespacePathPair($namespace_file->key, $path));
        }
    });

    $project->meta->packages->each(function (Package $package) use ($project, $import_map, $namespace_map) {
        compile_packages($package, $project, $import_map, $namespace_map);
    });

    compile_project_files($project, $import_map, $namespace_map);
    create_import_file($project, $import_map, $namespace_map);

    $project->config->entry_points->each(function (Filename $entry_point) use ($project) {
        add_autoloads($project, build_root($project)->append($entry_point));
    });

    $project->meta->packages->each(function (Package $package)  use ($project) {
        config_from_disk(package_path($project, $package))->executables->each(function (LinkPair $executable) use ($project, $package) {
            add_executables($project, build_package_path($project, $package)->append($executable->value), build_root($project)->append($executable->key));
        });
    });
}

function add_executables(Project $project, Path $source, Path $symlink): void
{
    Symlink\link($source, $symlink);
    add_autoloads($project, $source);
    File\chmod($source, 0774);
}

/**
 * @throws JsonException
 */
function compile_packages(Package $package, Project $project, Map $import_map, Map $namespace_map): void
{
    Directory\renew_recursive(build_package_path($project, $package));
    package_compilable_files_and_directories($project, $package)
        ->each(fn (Path $filesystem)
        => compile(
            $filesystem,
            package_path($project, $package),
            build_package_path($project, $package),
            $project,
            config_from_disk(package_path($project, $package)),
            $import_map,
            $namespace_map,
        ));
}

function compile_project_files(Project $project, Map $import_map, Map $namespace_map): void
{
    compilable_files_and_directories($project)
        ->each(fn (Path $filesystem)
            => compile($filesystem, $project->root, build_root($project), $project, $project->config, $import_map, $namespace_map)
        );
}

function compile(Path $address, Path $origin, Path $destination, Project $project, Config $config, Map $import_map, Map $namespace_map): void
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
                    $import_map,
                    $namespace_map,
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
        compile_file($project, $address, $destination_path, $import_map, $namespace_map);

        return;
    }

    File\preserve_copy($address, $destination_path);
}

function compile_file(Project $project, Path $origin, Path $destination, Map $import_map, Map $namespace_map): void
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

    array_walk($imports, function ($import) use ($project, $paths, $namespace_map, $import_map) {
        if ($import_map->has(fn (NamespacePathPair $import_map) => $import_map->key === $import)) {
            $path = $import_map->first(fn (NamespacePathPair $import_map) => $import_map->key === $import)->value;
            $paths->push(new NamespacePathPair($import, $path));

            return;
        }

        $path = $namespace_map->first(fn (NamespacePathPair $namespace_path) => $namespace_path->key === $import)?->value;
        $import = $path ? $import : Str\before_last_occurrence($import, '\\');
        $path = $path ?: $namespace_map->reduce(function (?Path $carry, NamespacePathPair $namespace_path) use ($import) {
            return str_starts_with($import, $namespace_path->key)
                ? $namespace_path->value->append(after_first_occurrence($import, $namespace_path->key) . '.php')
                : $carry;
        });
        unless(is_null($path) || ! File\exists(str_replace(build_root($project), $project->root, $path)), fn () => $paths->push(new NamespacePathPair($import, $path)));
    });

    array_walk($autoload, function ($import) use ($project, $import_map, $namespace_map) {
        if ($import_map->has(fn (NamespacePathPair $import_map) => $import_map->key === $import)) {
            $path = $import_map->first(fn (NamespacePathPair $import_map) => $import_map->key === $import)->value;
            $import_map->push(new NamespacePathPair($import, $path));

            return;
        }

        $path = $namespace_map->reduce(function (?Path $carry, NamespacePathPair $namespace_path) use ($import) {
            return str_starts_with($import, $namespace_path->key)
                ? $namespace_path->value->append(after_first_occurrence($import, $namespace_path->key) . '.php')
                : $carry;
        });
        unless(is_null($path), fn () => $import_map->push(new NamespacePathPair($import, $path)));
    });

    if ($paths->count() === 0) {
        $content = $php_file->code();
    } else {
        $requires = $paths->map(fn(NamespacePathPair $namespace_path) => $namespace_path->value->string());
        $require_statements = \PhpRepos\Datatype\Arr\map($requires, function (string $path) use ($project, $destination) {
            if (is_production_build($project->build_mode)) {
                $path = get_relative_path($destination, $path);
                return "require_once __DIR__ . '/{$path}';";
            }
            return "require_once '{$path}';";
        });

        $content = $php_file->add_autoloads(implode('', $require_statements))->code();
    }

    File\create($destination, $content, File\permission($origin));
}

/**
 * @throws JsonException
 */
function package_compilable_files_and_directories(Project $project, Package $package): FilesystemCollection
{
    $package_root = package_path($project, $package);
    $excluded_paths = new FilesystemCollection();
    $excluded_paths->push($package_root->append('.git'));
    config_from_disk(package_path($project, $package))->excludes
        ->each(fn (Filename $exclude) => $excluded_paths->push($package_root->append($exclude)));

    return Directory\ls_all($package_root)
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

    $php_file = PhpFile::from_content(File\content($target));

    if (is_production_build($project->build_mode)) {
        $path = get_relative_path($target, $import_path);
        $line = "require_once __DIR__ . '/$path';";
    } else {
        $line = "require_once '$import_path';";
    }

    File\modify($target, $php_file->add_autoloads($line)->code());
}

/**
 * @throws JsonException
 */
function create_import_file(Project $project, Map $import_map, Map $namespace_map): void
{
    $content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [

EOD;

    $import_map = iterator_to_array($import_map);
    usort($import_map, function (NamespacePathPair $namespace_path_pair1, NamespacePathPair $namespace_path_pair2) {
        return strcmp($namespace_path_pair1->key, $namespace_path_pair2->key);
    });
    foreach ($import_map as $namespace_path) {
        if (File\exists(str_replace(build_root($project), $project->root, $namespace_path->value))) {
            if (is_production_build($project->build_mode)) {
                $path = get_relative_path(import_file_path($project), $namespace_path->value);
                $content .= <<<EOD
        '$namespace_path->key' => __DIR__ . '/$path',

EOD;
            } else {
                $content .= <<<EOD
        '$namespace_path->key' => '$namespace_path->value',

EOD;
            }
        }
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

    foreach ($namespace_map as $namespace_path) {
        if (is_production_build($project->build_mode)) {
            $path = get_relative_path(import_file_path($project), $namespace_path->value);
            $content .= <<<EOD
        '$namespace_path->key' => __DIR__ . '/$path',

EOD;
        } else {
            $content .= <<<EOD
        '$namespace_path->key' => '$namespace_path->value',

EOD;
        }
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
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
    if (count($project->config->autoloads) > 0) {
        $content .= PHP_EOL;
    }

    $project->meta->packages->each(function (Package $package) use ($project, &$content) {
        config_from_disk(package_path($project, $package))->autoloads->each(function (Filename $autoload) use ($project, $package, &$content) {
            $file_path = build_package_path($project, $package)->append($autoload)->string();

            if (File\exists($file_path)) {
                if (is_production_build($project->build_mode)) {
                    $path = get_relative_path(import_file_path($project), $file_path);
                    $content .= "require_once __DIR__ . '/$path';" . PHP_EOL;
                } else {
                    $content .= "require_once '$file_path';" . PHP_EOL;
                }
            }
        });
    });

    $project->config->autoloads->each(function (Filename $autoload) use ($project, &$content) {
        $file_path = build_root($project)->append($autoload)->string();

        if (File\exists($file_path)) {
            if (is_production_build($project->build_mode)) {
                $path = get_relative_path(import_file_path($project), $file_path);
                $content .= "require_once __DIR__ . '/$path';" . PHP_EOL;
            } else {
                $content .= "require_once '$file_path';" . PHP_EOL;
            }
        }
    });

    $import_file = import_file_path($project);
    if (! Directory\exists($import_file->parent())) {
        Directory\make_recursive($import_file->parent());
    }

    File\create($import_file, $content);
}
