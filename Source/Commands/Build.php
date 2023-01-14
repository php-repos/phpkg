<?php

namespace Phpkg\Commands\Build;

use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\LinkPair;
use Phpkg\Classes\Config\NamespacePathPair;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\PhpFile;
use PhpRepos\Cli\IO\Write;
use PhpRepos\Datatype\Map;
use PhpRepos\FileManager\FileType\Json;
use PhpRepos\FileManager\Filesystem\Filename;
use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\Filesystem\Directory;
use PhpRepos\FileManager\Filesystem\File;
use PhpRepos\FileManager\Filesystem\FilesystemCollection;
use PhpRepos\FileManager\Filesystem\Symlink;
use PhpRepos\Datatype\Str;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Datatype\Str\after_first_occurrence;

function run(Environment $environment): void
{
    Write\line('Start building...');
    $project = new Project($environment->pwd->subdirectory(parameter('project', '')));

    if (! $project->config_file->exists()) {
        Write\error('Project is not initialized. Please try to initialize using the init command.');
        return;
    }

    Write\line('Loading configs...');
    $project->config(Config::from_array(Json\to_array($project->config_file)));
    $project->meta = Meta::from_array(Json\to_array($project->meta_file));

    Write\line('Checking packages...');
    $packages_installed = $project->meta->dependencies->every(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        $package->config = $package->config_file->exists() ? Config::from_array(Json\to_array($package->config_file)) : Config::init();
        $project->packages->push($package);
        return $package->is_downloaded();
    });

    if (! $packages_installed) {
        Write\error('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
        return;
    }

    Write\line('Prepare build directory...');
    $build = new Build($project, argument(2, 'development'));
    $build->root()->renew_recursive();
    $build->packages_directory()->exists_or_create();

    Write\line('Make namespaces map...');
    $build->load_namespace_map();

    Write\line('Building packages...');
    $project->packages->each(function (Package $package) use ($project, $build) {
        $key = $project->meta->dependencies->first_key(fn (Dependency $dependency) => $dependency->repository()->is($package->repository));
        Write\line('Building package ' . $key . '...');
        compile_packages($package, $build);
    });

    Write\line('Building the project...');
    compile_project_files($build);

    Write\line('Building entry points...');
    $project->config->entry_points->each(function (Filename $entry_point) use ($build) {
        Write\line('Building entry point ' . $entry_point);
        add_autoloads($build, $build->root()->file($entry_point));
    });

    Write\line('Building executables...');
    $project->packages->each(function (Package $package)  use ($project, $build) {
        $key = $project->meta->dependencies->first_key(fn (Dependency $dependency) => $dependency->repository()->is($package->repository));
        Write\line('Building executables for package ' . $key);
        $package->config->executables->each(function (LinkPair $executable) use ($build, $package) {
            Write\line('Building executable file ' . $executable->symlink() . ' from ' . $executable->source());
            add_executables($build, $build->package_root($package)->file($executable->source()), $build->root()->symlink($executable->symlink()));
        });
    });

    Write\success('Build finished successfully.');
}

function add_executables(Build $build, File $source, Symlink $symlink): void
{
    $symlink->link($source);
    add_autoloads($build, $source);
    $source->chmod(0774);
}

function compile_packages(Package $package, Build $build): void
{
    $build->package_root($package)->renew_recursive();
    package_compilable_files_and_directories($package)
        ->each(fn (Directory|File|Symlink $filesystem)
            => compile($filesystem, $package->root, $build->package_root($package), $build, $package->config));
}

function compile_project_files(Build $build): void
{
    compilable_files_and_directories($build->project)
        ->each(fn (Directory|File|Symlink $filesystem)
            => compile($filesystem, $build->project->root, $build->root(), $build, $build->project->config)
        );
}

function compile(Directory|File|Symlink $address, Directory $origin, Directory $destination, Build $build, Config $config): void
{
    $destination_path = $address->relocate($origin, $destination);

    if ($address instanceof Directory) {
        $address->preserve_copy($destination_path->as_directory());

        $address->ls_all()
            ->each(
                fn (Directory|File|Symlink $filesystem)
                => compile(
                    $filesystem,
                    $origin->subdirectory($address->leaf()),
                    $destination->subdirectory($address->leaf()),
                    $build,
                    $config,
                )
            );

        return;
    }

    if ($address instanceof Symlink) {
        $source_link = $address->parent()->file(readlink($address));
        $destination_path->as_symlink()->link($source_link);

        return;
    }

    if (file_needs_modification($address, $config)) {
        compile_file($build, $address, $destination_path->as_file());

        return;
    }

    $address->preserve_copy($destination_path->as_file());
}

function compile_file(Build $build, File $origin, File $destination): void
{
    $destination->create(apply_file_modifications($build, $origin), $origin->permission());
}

function apply_file_modifications(Build $build, File $origin): string
{
    $php_file = PhpFile::from_content($origin->content());
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
    $excluded_paths->push($package->root->subdirectory('.git'));
    $package->config->excludes
        ->each(fn (Filename $exclude) => $excluded_paths->push($this->root->subdirectory($exclude)));

    return $package->root->ls_all()
        ->except(fn (Directory|File|Symlink $file_or_directory)
            => $excluded_paths->has(fn (Directory|File|Symlink $excluded) => $excluded->path->string() === $file_or_directory->path->string()));
}

function compilable_files_and_directories(Project $project): FilesystemCollection
{
    $excluded_paths = new FilesystemCollection();
    $excluded_paths->push($project->root->subdirectory('.git'));
    $excluded_paths->push($project->root->subdirectory('.idea'));
    $excluded_paths->push($project->root->subdirectory('builds'));
    $excluded_paths->push($project->packages_directory);
    $project->config->excludes
        ->each(fn (Filename $exclude) => $excluded_paths->push($project->root->subdirectory($exclude)));

    return $project->root->ls_all()
        ->except(fn (Directory|File|Symlink $file_or_directory)
        => $excluded_paths->has(fn (Directory|File|Symlink $excluded) => $excluded->path->string() === $file_or_directory->path->string()));
}

function file_needs_modification(File $file, Config $config): bool
{
    return str_ends_with($file, '.php')
        || $config->entry_points->has(fn (Filename $entry_point) => $entry_point->string() === $file->leaf())
        || $config->executables->has(fn (LinkPair $executable) => $executable->source()->string() === $file->leaf());
}

function add_autoloads(Build $build, File $target): void
{
    $content = <<<'EOD'
spl_autoload_register(function ($class) {
    $classes = [

EOD;

    foreach ($build->import_map as $namespace_path) {
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

    $php_file = PhpFile::from_content($target->content());
    $php_file = $php_file->add_after_opening_tag(PHP_EOL . $content . PHP_EOL);
    $target->modify($php_file->code());
}
