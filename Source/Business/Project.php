<?php

namespace Phpkg\Business\Project;

use Exception;
use PhpRepos\Datatype\Str;
use PhpRepos\FileManager\Directories;
use PhpRepos\FileManager\Files;
use Phpkg\Solution\Commits;
use Phpkg\Solution\Composers;
use Phpkg\Solution\Dependencies;
use Phpkg\Solution\Paths;
use Phpkg\Solution\PHPKGs;
use Phpkg\Solution\Repositories;
use Phpkg\Solution\Versions;
use Phpkg\Solution\Exceptions\NotWritableException;
use PhpRepos\Observer\Signals\Event;
use Phpkg\Business\Config;
use Phpkg\Business\Credential;
use Phpkg\Business\Meta;
use Phpkg\Business\Package;
use Phpkg\Business\Outcome;
use PhpRepos\Observer\Signals\Plan;
use Phpkg\Infra\Exception\ArchiveDownloadException;
use Phpkg\Infra\Envs;
use function PhpRepos\Observer\Observer\broadcast;
use function Phpkg\Solution\Parser\Parsers\find_starting_point_for_imports;
use function Phpkg\Solution\Parser\Parsers\get_registry;
use function Phpkg\Infra\Arrays\first;
use function Phpkg\Infra\Arrays\map;
use function Phpkg\Infra\Arrays\reduce;
use function PhpRepos\Datatype\Arr\any;
use function PhpRepos\FileManager\Paths\append;
use function PhpRepos\FileManager\Paths\parent;
use function PhpRepos\FileManager\Paths\relative_path;
use function PhpRepos\Observer\Observer\propose;

function init(string $project, ?string $packages_directory): Outcome
{
    try {
        $packages_directory = $packages_directory ?: 'Packages';

        propose(Plan::create('I try to init a project in the given path.', [
            'project' => $project,
            'packages_directory' => $packages_directory,
        ]));

        $root = Paths\is_absolute_path($project)
          ? Paths\under($project)
          : Paths\under_current_working_directory($project);

        if (Paths\find($root)) {
            if (Paths\file_itself_exists(Paths\phpkg_config_path($root))) {
                broadcast(Event::create('The project is already initialized!', [
                    'root' => $root,
                ]));
                return new Outcome(false, '⚠️ Project is already initialized.');
            }
        } else if (!Paths\make_recursively($root)) {
            broadcast(Event::create('The path does not exist and I could not make it!', [
                'root' => $root,
            ]));
            return new Outcome(false, '❌ Could not make the directory.');
        }

        $config = PHPKGs\config(['packages-directory' => $packages_directory]);

        Config\save($root, $config);
        Meta\save($root, []);

        broadcast(Event::create('I initialized a project on the given project.', [
            'root' => $root,
            'config' => $config,
        ]));
        return new Outcome(true, '✅ Project initialized successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, "🔒 Path $root is not writable.");
    }
}

function sync(string $root, string $vendor, array $packages): Outcome
{
    try {
        $root = Paths\detect_project($root);

        propose(Plan::create('I try to sync the given packages in the project root.', [
            'root' => $root,
            'packages' => $packages,
        ]));

        $outcome = Meta\read($root, $vendor);
        if ($outcome->success) {
            $current_packages = $outcome->data['packages'];
        } else {
            $current_packages = [];
        }

        foreach ($packages as $package) {
            $package->root(Paths\package_root($vendor, $package->commit->version->repository->owner, $package->commit->version->repository->repo));
            if (Dependencies\contains_package($current_packages, $package)) {
                $package->checksum(Paths\checksum($package->root));
                continue;
            }

            $temp_path = Paths\temp_installer_directory($package);
            if (Paths\exists($temp_path)) {
                if (Paths\phpkg_config_exists($temp_path)) {
                    if (!isset($package->checksum)) {
                        $package->checksum(Paths\checksum($temp_path));
                        continue;
                    }
                    if (Paths\verify_checksum($temp_path, $package->checksum)) {
                        continue;
                    }
                }
                Paths\delete_recursively($temp_path);
            }

            if (!Dependencies\download_to($package, $temp_path)) {
                broadcast(Event::create('I could not download a package!', [
                    'root' => $root,
                    'package' => $package,
                ]));
                return new Outcome(false, '⬇️ Could not download ' . $package->identifier());
            }
            if (!Paths\phpkg_config_exists($temp_path)) {
                Config\save($temp_path, $package->config);
            }

            if (!isset($package->checksum)) {
                $package->checksum(Paths\checksum($temp_path));
                continue;
            }

            if (!Paths\verify_checksum($temp_path, $package->checksum)) {
                broadcast(Event::create('Checksum verification failed for a downloaded package!', [
                    'root' => $root,
                    'package' => $package,
                    'temp_path' => $temp_path,
                    'checksum' => $package->checksum,
                ]));
                return new Outcome(false, '🔐 Checksum verification failed for ' . $package->identifier() . '.');
            }
        }

        foreach ($packages as $package) {
            if (Dependencies\contains_repository($current_packages, $package->commit->version->repository)) {
                if (Paths\exists($package->root) && !Paths\delete_recursively($package->root)) {
                    broadcast(Event::create('I could not delete the previous version of an updated package!', [
                        'root' => $root,
                        'package' => $package,
                    ]));
                    return new Outcome(false, '🗑️ Could not delete previous version of ' . $package->identifier() . '.');
                }
            }

            $temp_path = Paths\temp_installer_directory($package);
            if (!Paths\preserve_copy_directory_content($temp_path, $package->root)
                || !Paths\verify_checksum($package->root, $package->checksum)
            ) {
                broadcast(Event::create('I could not move a package to its root!', [
                    'root' => $root,
                    'package' => $package,
                    'temp_path' => $temp_path,
                ]));
                return new Outcome(false, '📦 Could not move ' . $package->identifier() . ' to its root.');
            }
        }

        foreach ($current_packages as $package) {
            if (Dependencies\has_repository($packages, $package->commit->version->repository)) {
                continue;
            }
            if (!Paths\delete_recursively($package->root)) {
                broadcast(Event::create('I could not delete the package root of a removed package!', [
                    'root' => $root,
                    'package' => $package,
                ]));
                return new Outcome(false, '🗑️ Could not delete package ' . $package->identifier() . '.');
            }
            Paths\delete_owner_when_empty($package->root);
        }

        $outcome = Meta\save($root, $packages);
        if (!$outcome->success) {
            broadcast(Event::create('I could not save the meta file after syncing!', [
                'root' => $root,
                'vendor' => $vendor,
                'packages' => $packages,
            ]));
            return new Outcome(false, '💾 Could not save the meta file after syncing.');
        }

        broadcast(Event::create('I synced the project packages successfully.', [
            'root' => $root,
            'vendor' => $vendor,
            'packages' => $packages,
        ]));

        return new Outcome(true, '🔄 Project packages synced successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, "🔒 Path $root is not writable.");
    } catch (ArchiveDownloadException $e) {
        broadcast(Event::create('I could not download an archive during syncing!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '⬇️ Could not download an archive during syncing. ' . $e->getMessage());
    }
}

function install(string $project, bool $force): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to install a project in the given path.', [
            'root' => $root,
            'force' => $force,
        ]));

        $outcome = Config\read($root);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project config!', [
                'root' => $root,
            ]));

            return new Outcome(false, '🔍 Could not read the project.');
        }

        $config = $outcome->data['config'];
        $vendor = Paths\packages_directory($root, $config);

        $outcome = Credential\read();
        if (!$outcome->success) {
            broadcast(Event::create('I could not find any credentials!', [
                'root' => $root,
            ]));
            return new Outcome(false, '🔑 No credentials found.');
        }

        $credentials = $outcome->data['credentials'];

        $meta_path = Paths\phpkg_meta_path($root);

        if (Paths\exists($vendor)) {
            if (Paths\is_empty_directory($vendor)) {
                Paths\delete_directory($vendor);
            } else {
                if (!$force) {
                    broadcast(Event::create('The packages directory is not empty!', [
                        'root' => $root,
                        'config' => $config,
                        'vendor' => $vendor,
                    ]));
                    return new Outcome(false, '📁 The packages directory is not empty.');
                }
                Paths\delete_recursively($vendor);
            }
        }

        if (!Paths\find($vendor) && !Paths\make_recursively($vendor)) {
            broadcast(Event::create('Packages directory does not exist and I could not make it!', [
                'root' => $root,
                'config' => $config,
                'vendor' => $vendor,
            ]));

            return new Outcome(false, '📁 Failed to make the packages directory.');
        }

        $packages = [];

        if (Paths\file_itself_exists($meta_path)) {
            $meta = Paths\to_array($meta_path);

            if (empty($meta['packages'])) {
                broadcast(Event::create('No packages found in the meta file!', [
                    'root' => $root,
                    'config' => $config,
                    'meta' => $meta,
                    'packages' => $packages,
                ]));
            }

            foreach ($meta['packages'] as $package_url => $package_meta) {
                $outcome = Config\load($package_url, $package_meta['version'], $package_meta['hash']);
                if (!$outcome->success) {
                    broadcast(Event::create('I could not load a package config!', [
                        'root' => $root,
                        'package_url' => $package_url,
                        'version' => $package_meta['version'],
                        'hash' => $package_meta['hash'],
                    ]));
                    return new Outcome(false, '📦 I could not load a package config: ' . $outcome->message);
                }

                $package_root = Paths\package_root($vendor, $package_meta['owner'], $package_meta['repo']);
                $commit = Commits\prepare($package_url, $package_meta['version'], $package_meta['hash'], $credentials);
                $package = Dependencies\setup($commit, $outcome->data['config'], $package_root);
                if (isset($package_meta['checksum'])) {
                    $package->checksum($package_meta['checksum']);
                }
                $packages[] = $package;
            }
        } else {
            foreach ($config['packages'] as $package_url => $version) {
                $outcome = Package\load($package_url, $version->tag);
                if (!$outcome->success) {
                    broadcast(Event::create('I could not load a package config!', [
                        'root' => $root,
                        'version' => $version,
                    ]));
                    return new Outcome(false, '📦 I could not load a package config: ' . $outcome->message);
                }
                $package = Dependencies\loaded_package($outcome->data['commit'], $outcome->data['config']);
                $additional_packages = [$package];
                foreach ($outcome->data['packages'] as $dependency) {
                    $dependency = Dependencies\loaded_package($dependency['commit'], $dependency['config']);
                    $dependency->root(Paths\package_root($vendor, $dependency->commit->version->repository->owner, $dependency->commit->version->repository->repo));
                    $additional_packages[] = $dependency;
                }

                $packages = [...$packages, ...$additional_packages];
            }
        }

        propose(Plan::create('I try to resolve dependencies for installing the project.', [
            'root' => $root,
        ]));

        $packages = Dependencies\resolve($packages, $config, false);

        $outcome = sync($root, $vendor, $packages);

        if (!$outcome->success) {
            broadcast(Event::create('I could not sync the packages during installation!', [
                'root' => $root,
                'config' => $config,
                'vendor' => $vendor,
                'packages' => $packages,
            ]));
            return new Outcome(false, '🔄 Could not sync the packages during installation. ' . $outcome->message);
        }

        broadcast(Event::create('I installed the project packages successfully.', [
            'root' => $root,
            'config' => $config,
            'vendor' => $vendor,
            'meta_path' => $meta_path,
            'meta' => $meta ?? [],
            'packages' => $packages,
        ]));
        
        return new Outcome(true, '✅ Project installed successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, "🔒 Path '$root' is not writable.");
    } catch(DependencyResolutionException $e) {
        broadcast(Event::create('I could not resolve dependencies during installation!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '🔗 Could not resolve dependencies during installation. ' . $e->getMessage());
    } catch (ArchiveDownloadException $e) {
        broadcast(Event::create('I could not download an archive during installation!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '⬇️ Could not download an archive during installation. ' . $e->getMessage());
    }
}

function run(string $url_or_path, ?string $version, ?string $entry_point): Outcome
{
    propose(Plan::create('I try to run a project from the given URL or path.', [
        'url_or_path' => $url_or_path,
        'version' => $version ?: 'latest',
        'entry_point' => $entry_point ?: '',
    ]));

    $outcome = Credential\read();
    if (!$outcome->success) {
        broadcast(Event::create('I could not find any credentials!', [
            'url_or_path' => $url_or_path,
            'version' => $version ?: 'latest',
            'entry_point' => $entry_point ?: '',
        ]));
        return new Outcome(false, 'No credentials found.');
    }

    $credentials = $outcome->data['credentials'];

    if (!Paths\has_path_identifier($url_or_path)) {
        if (! Repositories\is_valid_package_identifier($url_or_path)) {
            if (Repositories\can_guess_a_repo($url_or_path)) {
                $url_or_path = Repositories\guess_the_repo($url_or_path);
            } else {
                broadcast(Event::create('The given URL or path is not a valid package identifier!', [
                    'url_or_path' => $url_or_path,
                    'version' => $version ?: 'latest',
                    'entry_point' => $entry_point ?: '',
                ]));
                return new Outcome(false, '❌ Invalid package identifier.');
            }
        }
        $repository = Repositories\prepare($url_or_path, $credentials);

        if (!$version) {
            $version = Versions\find_latest_version($repository)->tag;
        }

        $outcome = Package\load($url_or_path, $version);
        if (!$outcome->success) {
            broadcast(Event::create('I could not load a package config!', [
                'url_or_path' => $url_or_path,
                'version' => $version,
            ]));
            return new Outcome(false, '📦 Could not load a package config: ' . $outcome->message);
        }

        $root = Paths\temp_runner_directory($outcome->data['commit']);
        if (!Paths\exists($root)) {
            $package = Dependencies\setup($outcome->data['commit'], $outcome->data['config'], $root);
            if (!Dependencies\download_to($package, $root)) {
                broadcast(Event::create('I could not download the package for running!', [
                    'url_or_path' => $url_or_path,
                    'version' => $version,
                    'package' => $package,
                ]));
                return new Outcome(false, '⬇️ Could not download package for running.');
            }
            if (!Paths\phpkg_config_exists($root)) {
                Config\save($root, $package->config);
            }
        }
    } else {
        $root = Paths\detect_project($url_or_path);
    }

    if (!Paths\find($root)) {
        broadcast(Event::create('I could not find the project root directory!', [
            'root' => $root,
        ]));
        return new Outcome(false, '🔍 Project root directory does not exist.');
    }

    $outcome = Config\read($root);
    if (!$outcome->success) {
        broadcast(Event::create('I could not read the project config!', [
            'root' => $root,
        ]));
        return new Outcome(false, '📄 Could not read the project config.');
    }

    $config = $outcome->data['config'];

    $outcome = install($root, true);
    if (!$outcome->success) {
        broadcast(Event::create('I could not install the project packages!', [
            'root' => $root,
            'config' => $config,
        ]));
        return new Outcome(false, '📦 Could not install the project packages.');
    }

    $outcome = build($root);
    if (!$outcome->success) {
        broadcast(Event::create('I could not build the project!', [
            'root' => $root,
            'config' => $config,
        ]));
        return new Outcome(false, '🔨 Could not build the project.');
    }

    $entry_points = $outcome->data['entry_points'] ?? [];

    if (empty($entry_points)) {
        broadcast(Event::create('I could not find any entry points in the project!', [
            'root' => $root,
            'config' => $config,
            'entry_points' => $entry_points,
        ]));
        return new Outcome(false, '🎯 No entry points found in the project.');
    }

    $entry_point = $entry_point ? ($outcome->data['build_path'] ?? '') . DIRECTORY_SEPARATOR . $entry_point : $entry_points[0];

    if ($entry_point && !\Phpkg\Infra\Files\file_exists($entry_point)) {
        broadcast(Event::create('I could not find the entry point file!', [
            'root' => $root,
            'config' => $config,
            'entry_points' => $entry_points,
            'entry_point' => $entry_point,
        ]));
        return new Outcome(false, '🔍 Entry point file does not exist.');
    }

    broadcast(Event::create('I ran the project successfully.', [
        'root' => $root,
        'config' => $config,
        'entry_points' => $entry_points,
        'entry_point_path' => $entry_point,
    ]));
    return new Outcome(true, '🚀 Project ran successfully.', ['entry_point' => $entry_point]);
}

function migrate(string $project, bool $ignore_version_compatibility = false): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to migrate a composer project to phpkg.', [
            'root' => $root,
            'ignore_version_compatibility' => $ignore_version_compatibility,
        ]));

        if (!Paths\find($root)) {
            broadcast(Event::create('The directory does not exist or is not a valid project root.', [
                'root' => $root,
            ]));
            return new Outcome(false, '🔍 Directory does not exist or is not a valid project root.');
        }

        $outcome = Config\read($root);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project config!', [
                'root' => $root,
            ]));
            return new Outcome(false, '📄 Could not read the project config.');
        }

        $composer_config = Paths\composer_config_path($root);

        if (!Paths\file_itself_exists($composer_config)) {
            broadcast(Event::create('There is no composer config file!', [
                'root' => $root,
            ]));
            return new Outcome(false, '🔍 There is no composer config file.');
        }

        $composer_lock = Paths\composer_lock_path($root);

        if (!Paths\file_itself_exists($composer_lock)) {
            broadcast(Event::create('There is no composer lock file!', [
                'root' => $root,
            ]));
            return new Outcome(false, '🔍 There is no composer lock file.');
        }

        $outcome = Credential\read();
        if (!$outcome->success) {
            broadcast(Event::create('I could not find any credentials!', [
                'root' => $root,
            ]));
            return new Outcome(false, '🔑 No credentials found.');
        }

        $credentials = $outcome->data['credentials'];

        $config = PHPKGs\config(Composers\config(Paths\to_array($composer_config), $credentials));
        $vendor = Paths\under($root, 'vendor');
        $packages = Composers\detect_packages(Paths\to_array($composer_lock), $vendor, $credentials);

        propose(Plan::create('I try to resolve dependencies for migrating a project.', [
            'root' => $root,
        ]));

        $resolved_packages = Dependencies\resolve($packages, $config, false);
        
        $outcome = Config\save($root, $config);
        if (!$outcome->success) {
            broadcast(Event::create('I could not save the config file after migration!', [
                'root' => $root,
                'config' => $config,
            ]));
            return new Outcome(false, '💾 Could not save the config file after migration.');
        }

        Paths\delete_recursively(Paths\composer_vendor_path($root));

        $outcome = sync($root, $vendor, $packages);
        if (!$outcome->success) {
            broadcast(Event::create('I could not sync the packages after migration!', [
                'root' => $root,
                'config' => $config,
                'vendor' => $vendor,
                'packages' => $packages,
            ]));
            return new Outcome(false, '🔄 Could not sync the packages after migration.');
        }

        broadcast(Event::create('I migrated the composer project to phpkg successfully.', [
            'root' => $root,
            'config' => $config,
            'vendor' => $vendor,
            'packages' => $packages,
        ]));
        return new Outcome(true, '🔄 Project migrated successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, "🔒 Path $root is not writable.");
    }
}

function build(string $project): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to build a project in the given root.', [
            'root' => $root,
        ]));

        $outcome = Config\read($root);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project config!', [
                'root' => $root,
            ]));

            return new Outcome(false, '📄 Could not read the project config. ' . $outcome->message);
        }

        $config = $outcome->data['config'];
        $vendor = Paths\under($root, $config['packages-directory']);
        $builds = Paths\under($root, 'build');
        $build_vendor = Paths\under($builds, $config['packages-directory']);
        $import_file = Paths\under($builds, $config['import-file']);

        if (Paths\find($builds)) {
            if (!Paths\delete_recursively($builds)) {
                broadcast(Event::create('I could not delete the previous build directory!', [
                    'root' => $root,
                    'builds' => $builds,
                ]));
                return new Outcome(false, '🗑️ Could not delete the previous build directory.');
            }
        }

        if (!Paths\make_recursively($builds)) {
            broadcast(Event::create('I could not create the build directory!', [
                'root' => $root,
                'builds_directory' => $builds,
            ]));
            return new Outcome(false, '📁 Could not create the build directory.');
        }

        if (!Paths\make_recursively($build_vendor)) {
            broadcast(Event::create('I could not create the build vendor directory!', [
                'root' => $root,
                'build_vendor' => $build_vendor,
            ]));
            return new Outcome(false, '📁 Could not create the build vendor directory.');
        }

        $outcome = Meta\read($root, $vendor);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project meta!', [
                'root' => $root,
                'config' => $config,
            ]));

            return new Outcome(false, '📄 Could not read the project meta. ' . $outcome->message);
        }

        $packages = $outcome->data['packages'];

        // Check if there's an import file in the project root (suggests building inside a build directory)
        $project_import_file = Paths\under($root, $config['import-file']);
        if (Paths\file_itself_exists($project_import_file)) {
            propose(Plan::create('I detected an import file in the current directory, which suggests this is a build directory. I will go to the parent root directory and build it instead.', [
                'root' => $root,
                'import_file' => $project_import_file,
                'config' => $config,
            ]));
            
            // Go to parent root directory (one level up from build)
            $parent_root = Paths\under($root, '../');
            
            // Build the parent project
            return build($parent_root);
        }

        $import_map = [];
        $namespace_map = [];

        foreach ($packages as $package) {
            foreach ($package->config['map'] as $map_namespace => $map_path) {
                $map_path = Paths\under($package->root, $map_path);
                if (Paths\is_php_file($map_path)) {
                    $import_map[$map_namespace] = $map_path;
                } else {
                    $namespace_map[$map_namespace] = $map_path;
                }
            }
        }

        foreach ($config['map'] as $map_namespace => $map_path) {
            $map_path = Paths\under($root, $map_path);
            if (Paths\is_php_file($map_path)) {
                $import_map[$map_namespace] = $map_path;
            } else {
                $namespace_map[$map_namespace] = $map_path;
            }
        }

        $compile = function (string $path, string $file_root, array $config) use ($root, &$import_map, &$namespace_map) {
            $relative_file_path = relative_path($file_root, $path); // source/to/file
            $relative_path = relative_path($root, $path); // Packages/owner/repo/source/to/file
            $destination = Paths\under($root, 'build', $relative_path); // /path-to-project/build/Packages/owner/repo/source/to/file

            if (Paths\find($path)) {
                return Paths\ensure_directory_exists($destination);
            }

            if (Paths\file_is_symlink($path)) {
                return Paths\symlink($destination, Paths\symlink_destination($path));
            }

            if (!PHPKGs\has_entry_point($config, $relative_file_path)
                && !PHPKGs\has_executable($config, $relative_file_path)
                && !Paths\is_php_file($path)
            ) {
                return Paths\preserve_copy($path, $destination);
            }

            $content = Files\content($path);

            try {
                $registry = get_registry($content);
            } catch (Exception $e) {
                throw new Exception("Failed to parse file: $path. Error: {$e->getMessage()}");
            }

            $file_imports = [];

            foreach ($registry->imports as $import) {
                if (any($import_map, fn (string $map_path, string $map_namespace) => $map_namespace === $import)) {
                    $import_path = first($import_map, fn (string $map_path, string $map_namespace) => $map_namespace === $import);
                    $import_path = Paths\under($import_path);
                    $file_imports[$import] = $import_path;
                    break;
                }

                $import_path = any($namespace_map, fn (string $map_path, string $map_namespace) => $map_namespace === $import)
                    ? first($namespace_map, fn (string $map_path, string $map_namespace) => $map_namespace === $import)
                    : null;
                $import = $import_path ? $import : Str\before_last_occurrence($import, '\\');
                $import_path = $import_path ?: reduce($namespace_map, function (?string $carry, string $map_path, string $map_namespace) use ($import) {
                    return str_starts_with($import, $map_namespace)
                        ? append($map_path, Str\after_first_occurrence($import, $map_namespace) . '.php')
                        : $carry;
                });
                if (is_null($import_path)) {
                    continue; // Skip if no import path is found
                }

                $import_path = Paths\under($import_path);

                if (!Paths\file_itself_exists($import_path)) {
                    continue; // Skip if the import path does not exist
                }

                $file_imports[$import] = Paths\under($root, 'build', relative_path($root, $import_path));
            }

            foreach ($registry->namespaces as $namespace) {
                if (any($namespace_map, fn (string $map_path, string $map_namespace) => $map_namespace === $namespace)) {
                    $namespace_path = first($namespace_map, fn (string $map_path, string $map_namespace) => $map_namespace === $namespace);
                } else {
                    $namespace_path = reduce($namespace_map, function (?string $carry, string $map_path, string $map_namespace) use ($namespace) {
                        return str_starts_with($namespace, $map_namespace)
                            ? append($map_path, Str\after_first_occurrence($namespace, $map_namespace) . '.php')
                            : $carry;
                    });

                    if (is_null($namespace_path)) {
                        continue; // Skip if no namespace path is found
                    }
                }

                $namespace_path = Paths\under($namespace_path);
                $import_map[$namespace] = $namespace_path;
            }

            if (count($file_imports) > 0) {
                $require_statements = map($file_imports, function (string $import) use ($destination, $path) {
                    $import = relative_path(parent($destination), $import);
                    return "require_once __DIR__ . '/$import';";
                });

                $single_line_require_statements = implode('', $require_statements);
                $position = find_starting_point_for_imports($content);

                $content = $position === 0 ? $content : substr_replace($content, $single_line_require_statements, $position, 0);
            }

            return Paths\write($destination, $content, Paths\permission($path));
        };

        foreach ($packages as $package) {
            $excludes = [];
            $excludes[] = PHPKGs\exclude_path($package->root, '.git');
            foreach ($package->config['excludes'] as $exclude) {
                $excludes[] = PHPKGs\exclude_path($package->root, $exclude);
            }
            foreach (Directories\ls_all_recursively($package->root, fn ($current, $key, $iterator)
                => ! Paths\is_excluded($excludes, Paths\normalize($current))) as $path) {
                $compile($path, $package->root, $package->config);
            }
        }

        $excludes = [
            '.git',
            '.idea',
            '.phpstorm.meta.php',
            'build',
            $vendor,
            ...$config['excludes'],
        ];

        foreach ($excludes as $key => $exclude) {
             $excludes[$key] = PHPKGs\exclude_path($root, $exclude);
        }

        foreach (Directories\ls_all_recursively($root, fn ($current, $key, $iterator)
            => ! Paths\is_excluded($excludes, Paths\normalize($current))) as $path) {
            $compile($path, $root, $config);
        }

        $content = <<<'EOD'
    <?php

    spl_autoload_register(function ($class) {
        $classes = [

    EOD;

        uksort($import_map, 'strcmp');
        foreach ($import_map as $map_namespace => $map_path) {
            $build_namespace_path = Paths\under($builds, relative_path($root, $map_path));
            if (Files\exists($build_namespace_path)) {
                $path = relative_path(parent($import_file), $build_namespace_path);
                $content .= <<<EOD
            '{$map_namespace}' => __DIR__ . '/$path',

    EOD;
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

        foreach ($namespace_map as $map_namespace => $map_path) {
            $build_namespace_path = Paths\under($builds, relative_path($root, $map_path));
            $path = relative_path(parent($import_file), $build_namespace_path);
            $content .= <<<EOD
            '{$map_namespace}' => __DIR__ . '/$path',

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
                if (file_exists($realpath)) {
                    require $realpath;
                }

                return ;
            }
        }
    });

    EOD;
        if (count($config['autoloads']) > 0) {
            $content .= PHP_EOL;
        }

        foreach ($packages as $package) {
            $package_build_directory = Paths\under($build_vendor, $package->commit->version->repository->owner, $package->commit->version->repository->repo);
            foreach ($package->config['autoloads'] as $autoload) {
                if (!str_ends_with($autoload, '.php')) {
                    continue;
                }
                $file_path = Paths\under($package_build_directory, $autoload);
                if (Files\exists($file_path)) {
                    $path = relative_path(parent($import_file), $file_path);
                    $content .= "require_once __DIR__ . '/$path';" . PHP_EOL;
                }
            }
        }

        foreach ($config['autoloads'] as $autoload) {
            if (str_ends_with($autoload, '.php')) {
                $file_path = Paths\under($builds, $autoload);
                if (Files\exists($file_path)) {
                    $path = relative_path(parent($import_file), $file_path);
                    $content .= "require_once __DIR__ . '/$path';" . PHP_EOL;
                }
            }
        }

        Paths\write($import_file, $content);

        $entry_points = [];

        foreach ($config['entry-points'] as $entry_point) {
            $entry_point_path = Paths\under($builds, $entry_point);
            if (!Paths\file_itself_exists($entry_point_path)) {
                continue;
            }

            $content = Paths\read($entry_point_path); // For entry point, we add the import to the transpiled file.
            $path = relative_path(parent($entry_point_path), $import_file);
            $line = "require_once __DIR__ . '/$path';";

            $position = find_starting_point_for_imports($content);

            $content = $position === 0 ? $content : substr_replace($content, $line, $position, 0);
            
            Paths\write($entry_point_path, $content);
            $entry_points[] = $entry_point_path;
        }

        $executables = [];

        foreach ($packages as $package) {
            $package_build_directory = Paths\under($build_vendor, $package->commit->version->repository->owner, $package->commit->version->repository->repo);

            foreach ($package->config['executables'] as $executable => $source) {
                $executable = Paths\under($builds, $executable);
                $source = Paths\under($package_build_directory, $source);

                if (Paths\file_itself_exists($executable)) {
                    continue;
                }

                if (!Paths\file_itself_exists($source)) {
                    continue;
                }

                $path = relative_path(parent($source), $import_file);
                $line = "require_once __DIR__ . '/$path';";

                $content = Paths\read($source);
                $position = find_starting_point_for_imports($content);

                $content = $position === 0 ? $content : substr_replace($content, $line, $position, 0);

                Paths\write($source, $content);
                Files\chmod($source, 0774);
                Paths\symlink($source, $executable);
                $executables[] = $executable;
            }
        }

        broadcast(Event::create('I built the project successfully.', [
            'root' => $root,
            'config' => $config,
            'vendor' => $vendor,
            'builds' => $builds,
            'build_vendor' => $build_vendor,
            'import_file' => $import_file,
            'entry_points' => $entry_points,
            'executables' => $executables,
            'excludes' => $excludes,
        ]));
        return new Outcome(true, '🔨 Project built successfully.', [
            'root' => $root,
            'entry_points' => $entry_points,
            'build_path' => $builds,
        ]);
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, "🔒 Path $root is not writable.");
    } catch (Exception $e) {
        broadcast(Event::create('An error occurred during the build process!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, "⚡ An error occurred during the build process: " . $e->getMessage());
    }
}

function flush(string $project): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to flush build and temp directories for the project.', [
            'root' => $root,
        ]));

        $builds = Paths\under($root, 'build');
        $temp_dir = Envs\temp_dir();

        $deleted_build = false;
        $deleted_temp = false;

        // Delete build directory if it exists
        if (Paths\find($builds)) {
            if (!Paths\delete_recursively($builds)) {
                broadcast(Event::create('I could not delete the build directory!', [
                    'root' => $root,
                    'builds' => $builds,
                ]));
                return new Outcome(false, '🗑️ Could not delete the build directory.');
            }
            $deleted_build = true;
            broadcast(Event::create('I deleted the build directory.', [
                'root' => $root,
                'builds' => $builds,
            ]));
        }

        // Delete temp directory if it exists
        if (Paths\find($temp_dir)) {
            if (!Paths\delete_recursively($temp_dir)) {
                broadcast(Event::create('I could not delete the temp directory!', [
                    'root' => $root,
                    'temp_dir' => $temp_dir,
                ]));
                // Don't fail if temp directory deletion fails, just report it
                broadcast(Event::create('Build directory was deleted, but temp directory deletion failed.', [
                    'root' => $root,
                    'temp_dir' => $temp_dir,
                ]));
            } else {
                $deleted_temp = true;
                broadcast(Event::create('I deleted the temp directory.', [
                    'root' => $root,
                    'temp_dir' => $temp_dir,
                ]));
            }
        }

        if (!$deleted_build && !$deleted_temp) {
            broadcast(Event::create('No build or temp directories found to delete.', [
                'root' => $root,
            ]));
            return new Outcome(true, '✨ No build or temp directories found to delete.');
        }

        $messages = [];
        if ($deleted_build) {
            $messages[] = 'build directory';
        }
        if ($deleted_temp) {
            $messages[] = 'temp directory';
        }

        $message = 'Successfully deleted ' . implode(' and ', $messages) . '.';

        broadcast(Event::create('I flushed the project directories successfully.', [
            'root' => $root,
            'deleted_build' => $deleted_build,
            'deleted_temp' => $deleted_temp,
        ]));

        return new Outcome(true, $message);
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, "🔒 Path is not writable: " . $e->getMessage());
    }
}
