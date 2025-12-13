<?php

namespace Phpkg\BusinessSpecifications\Package;

use Phpkg\SoftwareSolutions\Caches;
use Phpkg\SoftwareSolutions\Commits;
use Phpkg\SoftwareSolutions\Data\Package;
use Phpkg\SoftwareSolutions\Dependencies;
use Phpkg\SoftwareSolutions\Exceptions\DependencyResolutionException;
use Phpkg\SoftwareSolutions\Exceptions\NotWritableException;
use Phpkg\SoftwareSolutions\Exceptions\VersionIncompatibilityException;
use Phpkg\SoftwareSolutions\Paths;
use Phpkg\SoftwareSolutions\Repositories;
use Phpkg\SoftwareSolutions\Versions;
use Phpkg\BusinessSpecifications\Config;
use Phpkg\BusinessSpecifications\Credential;
use Phpkg\BusinessSpecifications\Meta;
use Phpkg\BusinessSpecifications\Outcome;
use Phpkg\BusinessSpecifications\Project;
use function PhpRepos\Observer\Observer\propose;
use function PhpRepos\Observer\Observer\broadcast;
use PhpRepos\Observer\Signals\Plan;
use PhpRepos\Observer\Signals\Event;

function register_alias(string $project, string $alias, string $package_url): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to register the given alias to the given package URL for the current project.', [
            'root' => $root,
            'alias' => $alias,
            'package_url' => $package_url,
        ]));

        if (!Repositories\is_valid_package_identifier($package_url)) {
            broadcast(Event::create('The given package URL seems invalid!', [
                'root' => $root,
                'alias' => $alias,
                'package_url' => $package_url,
            ]));
            return new Outcome(false, '❌ The given package URL seems invalid.');
        }

        $outcome = Config\read($root);
        if (!$outcome->success) {
            broadcast(Event::create('This does not seem to be a phpkg project!', [
                'root' => $root,
                'alias' => $alias,
                'package_url' => $package_url,
            ]));
            return new Outcome(false, '❌ This does not seem to be a phpkg project.');

        }

        $config = $outcome->data['config'];

        foreach ($config['aliases'] as $registered_alias => $registered_url) {
            if ($registered_alias !== $alias) continue;

            broadcast(Event::create('It seems the alias has been registered for another package!', [
                'root' => $root,
                'alias' => $alias,
                'package_url' => $package_url,
                'existing_package_url' => $registered_url,
            ]));
            return new Outcome(false, '⚠️ The alias has been registered for another package.');
        }

        $config['aliases'][$alias] = $package_url;

        $outcome = Config\save($root, $config);
        if (!$outcome->success) {
            broadcast(Event::create('It seems we could not save the config file!', [
                'root' => $root,
                'alias' => $alias,
                'package_url' => $package_url,
            ]));
            return new Outcome(false, '💾 Could not save the config file.');
        }

        broadcast(Event::create('I registered the given alias for the given package URL.', [
            'root' => $root,
            'alias' => $alias,
            'package_url' => $package_url,
        ]));
        return new Outcome(true, '✅ Alias registered successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '🔒 The path is not writable: ' . $e->getMessage());
    }

}

function load(string $url, string $version): Outcome
{
    propose(Plan::create('I try to load a package\'s dependencies from git host for the given URL and version.', [
        'url' => $url,
        'version' => $version,
    ]));

    $outcome = Credential\read();
    if (!$outcome->success) {
        broadcast(Event::create('I could not find any credentials!', [
            'url' => $url,
            'version' => $version ?: 'latest',
        ]));

        return new Outcome(false, '🔑 Could not read credentials.');
    }

    $credentials = $outcome->data['credentials'];

    if ($version !== 'development') {
        $version = Versions\match_highest_version($url, $version, $credentials);
    } else {
        $version = Versions\prepare($url, $version, $credentials);
    }

    if (Caches\remote_data_exists($version)) {
        $cached_data = Caches\get_remote_data($version);

        broadcast(Event::create('I found a cached response.', [
            'url' => $url,
            'version' => $version,
            'commit' => $cached_data['commit'],
            'config' => $cached_data['config'],
            'packages' => $cached_data['packages'] ?? null,
        ]));
        return new Outcome(true, '💾 Package loaded from cache.', [
            'commit' => $cached_data['commit'],
            'config' => $cached_data['config'],
            'packages' => $cached_data['packages'] ?? null,
        ]);
    }

    if (Versions\is_development($version)) {
        $commit = Commits\find_latest_commit($version);
    } else {
        $commit = Commits\find_version_commit($version);
    }

    $outcome = Config\load($url, $version->tag, $commit->hash);
    if (!$outcome->success) {
        broadcast(Event::create('I could not get config for the package!', [
            'url' => $url,
            'commit' => $commit,
        ]));
        return new Outcome(false, '📦 Could not get config for the package.');
    }

    $config = $outcome->data['config'];

    Caches\set_remote_data($version, $commit, $config);

    $packages = [];

    foreach ($config['packages'] as $package_url => $package_version) {
        $outcome = load($package_url, $package_version->tag);
        if (!$outcome->success) {
            broadcast(Event::create('I could not load a dependency package!', [
                'url' => $url,
                'commit' => $commit,
                'package' => $package_version,
            ]));
            return new Outcome(false, "📦 Could not load a dependency package: {$package_version->identifier()}");
        }

        $packages[] = [
            'commit' => $outcome->data['commit'],
            'config' => $outcome->data['config'],
        ];

        if ($outcome->data['packages'] === null) continue;

        foreach ($outcome->data['packages'] as $dependency) {
            $packages[] = [
                'commit' => $dependency['commit'],
                'config' => $dependency['config'],
            ];
        }
    }

    Caches\update_remote_data($version, $packages); 

    broadcast(Event::create('I loaded package\'s dependencies for the given package.', [
        'url' => $url,
        'commit' => $commit,
        'config' => $config,
        'packages' => $packages,
    ]));
    return new Outcome(true, '✅ Package loaded.', [
        'commit' => $commit,
        'config' => $config,
        'packages' => $packages,
    ]);
}

function add(string $project, string $identifier, ?string $version, ?bool $ignore_version_compatibility = false): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to add the given package to the project.', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version ?: 'latest',
            'ignore_version_compatibility' => $ignore_version_compatibility,
        ]));

        $outcome = Config\read($root);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project config!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
            ]));
            return new Outcome(false, '📄 Could not read current config for the project.');
        }

        $config = $outcome->data['config'];
        $vendor = Paths\packages_directory($root, $config);

        $outcome = Meta\read($root, $vendor);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
            ]));
            return new Outcome(false, '📦 Could not read current dependencies for the project. ' . $outcome->message);
        }

        $packages = $outcome->data['packages'];

        $url = $identifier;
        foreach ($config['aliases'] as $alias => $package_url) {
            if ($alias !== $identifier) continue;

            $url = $package_url;
            break;
        }

        if (!Repositories\is_valid_package_identifier($url)) {
            if (Repositories\can_guess_a_repo($identifier)) {
                $url = Repositories\guess_the_repo($identifier);
            } else {
                broadcast(Event::create('The given identifier is invalid!', [
                    'root' => $root,
                    'identifier' => $identifier,
                    'url' => $url,
                    'version' => $version ?: 'latest',
                    'config' => $config,
                ]));
                return new Outcome(false, '❌ The given identifier is invalid.');
            }
        }

        $outcome = Credential\read();
        if (!$outcome->success) {
            broadcast(Event::create('I could not read credentials!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
                'config' => $config,
            ]));
            return new Outcome(false, '🔑 Could not read credentials.');
        }

        $credentials = $outcome->data['credentials'];

        $repository = Repositories\prepare($url, $credentials);

        foreach ($config['packages'] as $package_url => $package_version) {
            if (Repositories\are_equal($repository, $package_version->repository)) {
                broadcast(Event::create('The package is already added to this project!', [
                    'root' => $root,
                    'url' => $url,
                    'identifier' => $identifier,
                    'version' => $version ?: 'latest',
                    'config' => $config,
                ]));
                return new Outcome(false, '⚠️ The package is already added to this project.');
            }
        }

        if ($version !== 'development' && !Repositories\has_any_tag($repository)) {
            broadcast(Event::create('I could not detect any release for the given package!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
                'config' => $config,
            ]));
            return new Outcome(false, '🔍 Could not detect any release for the given package.');
        }

        if (empty($version)) {
            $version = Versions\find_latest_version($repository)->tag;
        }

        $outcome = load($url, $version);
        if (!$outcome->success) {
            broadcast(Event::create('I could not get package and its dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
                'config' => $config,
            ]));
            return new Outcome(false, '📦 Could not get package and its dependencies.');
        }

        $new_package = new Package($outcome->data['commit'], $outcome->data['config']);
        $additional_packages = [$new_package];
        foreach ($outcome->data['packages'] as $dependency) {
            $additional_packages[] = new Package($dependency['commit'], $dependency['config']);
        }

        propose(Plan::create('I try to resolve dependencies for adding a package.', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version,
            'url' => $url,
        ]));

        $new_packages = [...$packages, ...$additional_packages];

        $new_config = $config;
        $new_config['packages'][$url] = $new_package->commit->version;

        $new_packages = Dependencies\resolve($new_packages, $new_config, $ignore_version_compatibility);
        $new_config = Dependencies\update_main_packages($new_packages, $new_config);

        $outcome = Config\save($root, $new_config);
        if (!$outcome->success) {
            broadcast(Event::create('I could not save the config file after resolving dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
                'config' => $new_config,
            ]));
            return new Outcome(false, '💾 Could not save the config file after resolving dependencies.');
        }

        $outcome = Project\sync($root, $vendor, $new_packages);

        if (!$outcome->success) {
            $sync_message = $outcome->message;
            propose(Plan::create('I try to revert changes in the config, as sync has failed.', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version,
                'url' => $url,
            ]));

            $outcome = Config\save($root, $config);
            if (!$outcome->success) {
                broadcast(Event::create('Critical: Could not revert changes in the config after sync failure!', [
                    'root' => $root,
                    'identifier' => $identifier,
                    'version' => $version ?: 'latest',
                    'url' => $url,
                    'config' => $config,
                ]));
                return new Outcome(false, '⚡ Critical: Could not revert changes in the config after sync failure.');
            }
            broadcast(Event::create('Sync failed after adding a package!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
            ]));
            return new Outcome(false, '🔄 Sync failed after adding the package. ' . $sync_message);
        }
        broadcast(Event::create('I added the given package to the project.', [
            'root' => $root,
            'config' => $config,
            'package' => $new_package,
            'packages' => $new_packages,
        ]));
        return new Outcome(true, '✅ Package added successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '🔒 The path is not writable: ' . $e->getMessage());
    } catch (DependencyResolutionException $e) {
        broadcast(Event::create('Dependency resolution failed!', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version ?: 'latest',
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '❌ Failed to add the package. ' . $e->getMessage());
    } catch (VersionIncompatibilityException $e) {
        broadcast(Event::create('Version incompatibility issue found!', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version ?: 'latest',
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '❌ Failed to add package. ' . $e->getMessage());
    }
}

function update(string $project, string $identifier, ?string $version, ?bool $ignore_version_compatibility): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to update the given package in the project.', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version ?: 'latest',
            'ignore_version_compatibility' => $ignore_version_compatibility,
        ]));

        $outcome = Config\read($root);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project config!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
            ]));
            return new Outcome(false, '📄 Could not read current config for the project.');
        }

        $config = $outcome->data['config'];
        $vendor = Paths\packages_directory($root, $config);

        $outcome = Meta\read($root, $vendor);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
            ]));
            return new Outcome(false, '📦 Could not read current dependencies for the project. ' . $outcome->message);
        }

        $packages = $outcome->data['packages'];

        $url = $identifier;
        foreach ($config['aliases'] as $alias => $package_url) {
            if ($alias !== $identifier) continue;
            
            $url = $package_url;
            break;
        }

        if (!Repositories\is_valid_package_identifier($url)) {
            if (Repositories\can_guess_a_repo($identifier)) {
                $url = Repositories\guess_the_repo($identifier);
            } else {
                broadcast(Event::create('The given package identifier is invalid!', [
                    'root' => $root,
                    'identifier' => $identifier,
                    'version' => $version ?: 'latest',
                    'url' => $url,
                ]));
                return new Outcome(false, 'The given package identifier is invalid.');
            }
        }

        $outcome = Credential\read();
        if (!$outcome->success) {
            broadcast(Event::create('I could not read credentials!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
            ]));
            return new Outcome(false, '🔑 Could not read credentials.');
        }

        $credentials = $outcome->data['credentials'];

        $repository = Repositories\prepare($url, $credentials);

        $old_url = null;
        $old_version = null;
        foreach ($config['packages'] as $package_url => $package_version) {
            if (Repositories\are_equal($repository, $package_version->repository)) {
                $old_url = $package_url;
                $old_version = $package_version;
                break;
            }
        }

        if (!$old_version || !$old_url) {
            broadcast(Event::create('Package not found in your project!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
            ]));
            return new Outcome(false, '🔍 Package not found in your project.');
        }

        if (empty($version)) {
            $version = Versions\find_latest_version($repository)->tag;
        }

        $outcome = load($url, $version);
        if (!$outcome->success) {
            broadcast(Event::create('I could not get package and its dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
                'config' => $config,
            ]));
            return new Outcome(false, '📦 Could not get package and its dependencies.');
        }
        
        $new_package = new Package($outcome->data['commit'], $outcome->data['config']);
        $additional_packages = [$new_package];
        foreach ($outcome->data['packages'] as $dependency) {
            $additional_packages[] = new Package($dependency['commit'], $dependency['config']);
        }

        propose(Plan::create('I try to resolve dependencies for updating a package.', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version,
            'url' => $url,
        ]));

        $new_packages = [...$packages, ...$additional_packages];

        $new_config = $config;
        $new_config['packages'][$old_url] = $new_package->commit->version;

        $new_packages = Dependencies\resolve($new_packages, $new_config, $ignore_version_compatibility);
        $new_config = Dependencies\update_main_packages($new_packages, $new_config);
        
        $outcome = Config\save($root, $new_config);
        if (!$outcome->success) {
            broadcast(Event::create('I could not save the config file after resolving dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
                'config' => $new_config,
            ]));
            return new Outcome(false, '💾 Could not save the config file after resolving dependencies.');
        }

        $outcome = Project\sync($root, $vendor, $new_packages);

        if (!$outcome->success) {
            $sync_message = $outcome->message;
            propose(Plan::create('I try to revert changes in the config, as sync has failed.', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version,
                'url' => $url,
            ]));

            $outcome = Config\save($root, $config);
            if (!$outcome->success) {
                broadcast(Event::create('Critical: Could not revert changes in the config after sync failure!', [
                    'root' => $root,
                    'identifier' => $identifier,
                    'version' => $version ?: 'latest',
                    'url' => $url,
                    'config' => $config,
                ]));
                return new Outcome(false, '⚡ Critical: Could not revert changes in the config after sync failure.');
            }
            broadcast(Event::create('Sync failed after updating a package!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $version ?: 'latest',
                'url' => $url,
            ]));
            return new Outcome(false, '🔄 Sync failed after updating the package. ' . $sync_message);
        }

        broadcast(Event::create('I updated the given package in the project.', [
            'root' => $root,
            'config' => $config,
            'old_version' => $old_version,
            'package' => $new_package,
            'packages' => $new_packages,
        ]));
        return new Outcome(true, '🔄 Package updated successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '🔒 The path is not writable: ' . $e->getMessage());
    } catch (DependencyResolutionException $e) {
        broadcast(Event::create('Dependency resolution failed!', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version ?: 'latest',
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '❌ Failed to update the package. ' . $e->getMessage());
    } catch (VersionIncompatibilityException $e) {
        broadcast(Event::create('Version incompatibility issue found!', [
            'root' => $root,
            'identifier' => $identifier,
            'version' => $version ?: 'latest',
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '❌ Failed to update the package. ' . $e->getMessage());
    }
}

function remove(string $project, string $identifier): Outcome
{
    try {
        $root = Paths\detect_project($project);

        propose(Plan::create('I try to remove the given package from the project.', [
            'root' => $root,
            'identifier' => $identifier,
        ]));

        $outcome = Config\read($root);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project config!', [
                'root' => $root,
                'identifier' => $identifier,
            ]));

            return new Outcome(false, '📄 Could not read current config for the project.');
        }

        $config = $outcome->data['config'];
        $vendor = Paths\packages_directory($root, $config);

        $outcome = Meta\read($root, $vendor);
        if (!$outcome->success) {
            broadcast(Event::create('I could not read the project dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
            ]));

            return new Outcome(false, '📦 Could not read current dependencies for the project. ' . $outcome->message);
        }

        $packages = $outcome->data['packages'];

        $url = $identifier;
        foreach ($config['aliases'] as $alias => $package_url) {
            if ($alias !== $identifier) continue;
            
            $url = $package_url;
            break;
        }

        if (!Repositories\is_valid_package_identifier($url)) {
            if (Repositories\can_guess_a_repo($identifier)) {
                $url = Repositories\guess_the_repo($identifier);
            } else {
                broadcast(Event::create('The given package identifier is invalid!', [
                    'root' => $root,
                    'identifier' => $identifier,
                    'url' => $url,
                ]));
                return new Outcome(false, 'The given package identifier is invalid.');
            }
        }

        $outcome = Credential\read();
        if (!$outcome->success) {
            broadcast(Event::create('I could not read credentials!', [
                'root' => $root,
                'identifier' => $identifier,
                'url' => $url,
            ]));
            return new Outcome(false, '🔑 Could not read credentials.');
        }

        $credentials = $outcome->data['credentials'];

        $repository = Repositories\prepare($url, $credentials);

        $old_url = null;
        $old_version = null;
        foreach ($config['packages'] as $package_url => $package_version) {
            if (Repositories\are_equal($repository, $package_version->repository)) {
                $old_url = $package_url;
                $old_version = $package_version;
                break;
            }
        }

        if (!$old_version || !$old_url) {
            broadcast(Event::create('The package not found in your project!', [
                'root' => $root,
                'identifier' => $identifier,
                'url' => $url,
            ]));
            return new Outcome(false, '🔍 The package not found in your project.');
        }

        $commit = Dependencies\find($repository, $packages)->commit;

        $new_config = $config;

        unset($new_config['packages'][$old_url]);

        propose(Plan::create('I try to resolve dependencies for removing a package.', [
            'root' => $root,
            'identifier' => $identifier,
            'url' => $url,
        ]));

        $new_packages = Dependencies\resolve($packages, $new_config, false);
        $new_config = Dependencies\update_main_packages($new_packages, $new_config);

        $outcome = Config\save($root, $new_config);
        if (!$outcome->success) {
            broadcast(Event::create('I could not save the config file after resolving dependencies!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $old_version,
                'url' => $url,
                'config' => $new_config,
            ]));
            return new Outcome(false, '💾 Could not save the config file after resolving dependencies.');
        }

        $outcome = Project\sync($root, $vendor, $new_packages);

        if (!$outcome->success) {
            $sync_message = $outcome->message;
            propose(Plan::create('I try to revert changes in the config, as sync has failed.', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $old_version,
                'url' => $url,
            ]));

            $outcome = Config\save($root, $config);
            if (!$outcome->success) {
                broadcast(Event::create('Critical: Could not revert changes in the config after sync failure!', [
                    'root' => $root,
                    'identifier' => $identifier,
                    'version' => $old_version,
                    'url' => $url,
                    'config' => $config,
                ]));
                return new Outcome(false, '⚡ Critical: Could not revert changes in the config after sync failure.');
            }
            broadcast(Event::create('Sync failed after removing a package!', [
                'root' => $root,
                'identifier' => $identifier,
                'version' => $old_version,
                'url' => $url,
            ]));
            return new Outcome(false, '🔄 Sync failed after removing the package. ' . $sync_message);
        }

        broadcast(Event::create('I removed the given package from the project.', [
            'root' => $root,
            'config' => $config,
            'old_version' => $old_version,
            'identifier' => $identifier,
            'url' => $url,
            'packages' => $new_packages,
        ]));
        return new Outcome(true, '🗑️ Package removed successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'root' => $root,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '🔒 The path is not writable: ' . $e->getMessage());
    } catch (DependencyResolutionException $e) {
        broadcast(Event::create('Dependency resolution failed!', [
            'root' => $root,
            'identifier' => $identifier,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '❌ Failed to remove the package. ' . $e->getMessage());
    } catch (VersionIncompatibilityException $e) {
        broadcast(Event::create('Version incompatibility issue found!', [
            'root' => $root,
            'identifier' => $identifier,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '❌ Failed to remove the package. ' . $e->getMessage());
    }
}
