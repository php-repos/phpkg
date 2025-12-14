<?php

namespace Phpkg\Solution;

use PhpRepos\SimpleCSP\CSP;
use Phpkg\Solution\Data\Package;
use Phpkg\Solution\Data\Repository;
use Phpkg\Solution\Commits;
use Phpkg\Solution\Repositories;
use Phpkg\Solution\Versions;
use Phpkg\Infra\GitHosts;
use Phpkg\Infra\Arrays;
use function Phpkg\Infra\Logs\debug;

class PHPKGCSP extends CSP
{
    /** @var array<string, Package> */
    private array $fixed_assignments;

    /**
     * @param array<Package> $packages The list of available packages.
     * @param array $project_config The project configuration.
     * @param bool $ignore_version_compatibility Whether to ignore version compatibility checks.
     */
    public function __construct(
        array $packages,
        private array $project_config,
        private bool $ignore_version_compatibility
    ) {
        $variables = [];
        $domains = [];

        $repository_packages = Arrays\group_by($packages, fn (Package $package)
            => $package->commit->version->repository->identifier());

        foreach ($repository_packages as $repo_id => $repo_packages) {
            $variables[$repo_id] = $repo_packages[0]->commit->version->repository;
            $unique_packages = Arrays\unique($repo_packages, fn (Package $a, Package $b)
                => Commits\are_equal($a->commit, $b->commit));
            if (Arrays\has($unique_packages, fn ($package)
                => Dependencies\claims_same_namespaces($this->project_config, $package))) {
                $domains[$repo_id] = ['project'];
                continue;
            }

            $domains[$repo_id] = ['absence', ...$unique_packages];
        }

        $this->fixed_assignments = [];

        parent::__construct($variables, $domains);
    }

    /**
     * Returns the constraints for this CSP.
     * 
     * Constraints ensure:
     * 1. All main packages from project config are included
     * 2. All dependencies of included packages are included
     */
    public function constraints(): array
    {
        return [
            $this->force_absence_when_main_packages_are_empty(),
            $this->force_package_for_main_packages(),
            $this->main_packages_and_their_dependencies_cannot_be_absent(),
            $this->transitive_packages_cannot_be_absence(),
            $this->assignment_should_satisfy_version_constraints(),
            $this->assignment_must_satisfy_requirements(),
        ];
    }

    /**
     * Returns the variable ordering heuristics for this CSP.
     */
    public function heuristics(): array
    {
        $main_package_first = function ($assignment) {
            foreach ($this->project_config['packages'] as $package_url => $version_tag) {
                $main_repository = Repositories\from($package_url);
                if (!isset($assignment[$main_repository->identifier()])) {
                    return $main_repository->identifier();
                }
            }

            return null;
        };

        return [
            $main_package_first,
        ];
    }

    /**
     * Returns the value ordering functions for this CSP.
     */
    public function orderings(): array
    {
        return [
            function ($var_id, array $values, array $assignment): array {
                $version_groups = Arrays\group_by($values, function (string|Package $value) {
                    if ($value === 'absence') return 'absence';
                    if ($value === 'project') return 'project';
                    if (Versions\is_development($value->commit->version)) return 'development';
                    return 'stable';
                });

                $stable_versions = Arrays\sort($version_groups['stable'] ?? [], fn (Package $a, Package $b)
                    => GitHosts\compare_versions($a->commit->version->tag, $b->commit->version->tag));

                $ordered = [
                    ...$version_groups['project'] ?? [],
                    ...$version_groups['absence'] ?? [],
                    ...$version_groups['development'] ?? [],
                    ...$stable_versions,
                ];

                return $ordered;
            },
        ];
    }

    private function force_absence_when_main_packages_are_empty(): callable
    {
      return function ($assignment, Repository $repository, string|Package $package) {
            debug('Checking if main packages are empty', [
                'repository' => $repository->identifier(),
                'package' => $package === 'absence' || $package === 'project' ? $package : $package->identifier(),
            ]);
            if (count($this->project_config['packages']) > 0) return true;
            return $package === 'absence';
        };
    }

    private function force_package_for_main_packages(): callable
    {
        return function ($assignment, Repository $repository, string|Package $package) {
            debug('Checking if repository is main package', [
                'repository' => $repository->identifier(),
                'package' => $package === 'absence' || $package === 'project' ? $package : $package->identifier(),
            ]);
            if (!Repositories\is_main_package($this->project_config, $repository)) return true;
            return $package !== 'absence' && $package !== 'project';
        };
    }

    private function main_packages_and_their_dependencies_cannot_be_absent(): callable
    {
        return function ($assignment, Repository $repository, string|Package $package) {
            if (count($this->domains) !== count($assignment)) return true;

            debug('Assignment to check for main package and dependencies absence', [
                'assignment' => Arrays\map($assignment, fn ($item) => $item === 'absence' || $item === 'project' ? $item : $item->identifier()),
            ]);

            foreach ($this->project_config['packages'] as $package_url => $version) {
                if ($assignment[$version->repository->identifier()] === 'absence') return false;
                if ($assignment[$version->repository->identifier()] === 'project') return false; // Duplicate check, but safe
                foreach ($assignment[$version->repository->identifier()]->config['packages'] as $dep_package_url => $dep_version) {
                    if ($assignment[$dep_version->repository->identifier()] === 'absence') return false;
                }
            }

            return true;
        };
    }

    private function transitive_packages_cannot_be_absence(): callable
    {
        return function ($assignment, Repository $repository, string|Package $package) {
            if (count($this->domains) !== count($assignment)) return true;

            debug('Assignment to check for transitive package absence', [
                'assignment' => Arrays\map($assignment, fn ($item) => $item === 'absence' || $item === 'project' ? $item : $item->identifier()),
            ]);

            foreach ($assignment as $assigned_package) {
                if ($assigned_package === 'absence'
                    || $assigned_package === 'project'
                    || Dependencies\is_main_package($this->project_config, $assigned_package)) continue;

                foreach ($assigned_package->config['packages'] as $package_url => $version) {
                    if ($assignment[$version->repository->identifier()] === 'absence') return false;
                }
            }

            return true;
        };
    }

    private function assignment_should_satisfy_version_constraints(): callable
    {
        return function ($assignment, Repository $repository, string|Package $package) {
            if (count($this->domains) !== count($assignment)) return true;

            debug('Assignment to be checked for version constraints', [
                'assignment' => Arrays\map($assignment, fn ($item) => $item === 'absence' || $item === 'project' ? $item : $item->identifier()),
            ]);

            foreach ($assignment as $dependency) {
                if ($dependency === 'absence' || $dependency === 'project') continue;
                if (Versions\is_development($dependency->commit->version)) continue;
                if (Dependencies\is_main_package($this->project_config, $dependency)) {
                    $required_version = Dependencies\required_main_package($this->project_config, $dependency);

                    if (Versions\is_development($required_version)) return false;
                    if (GitHosts\compare_versions($required_version->tag, $dependency->commit->version->tag) > 0) return false;
                    // Any version bigger or eual to main requirement is fine.
                    continue;
                }

                foreach ($assignment as $dependant) {
                    if ($dependant === 'absence' || $dependant === 'project') continue;
                    if (!Dependencies\is_main_package($dependant->config, $dependency)) continue;
                    $required_version = Dependencies\required_main_package($dependant->config, $dependency);

                    if (Versions\is_development($required_version)) continue;
                    if (GitHosts\compare_versions($required_version->tag, $dependency->commit->version->tag) > 0) return false;
                    // Let's loop continue to check all minimum requirements
                }
            }

            return true;
        };
    }

    private function assignment_must_satisfy_requirements(): callable
    {
        return function ($assignment, Repository $repository, string|Package $package) {
            if (count($this->domains) !== count($assignment)) return true;

            debug('Assignment to be checked for requirements satisfaction', [
                'assignment' => Arrays\map($assignment, fn ($item) => $item === 'absence' || $item === 'project' ? $item : $item->identifier()),
            ]);

            foreach ($assignment as $repo_id => $dependency) {
                if ($dependency === 'project') continue; //Nothing for now
                if ($dependency === 'absence') {
                    if (Repositories\is_main_package($this->project_config, $this->variables[$repo_id])) return false;
                    foreach ($assignment as $dependant) {
                        if ($dependant === 'absence' || $dependant === 'project') continue;
                        if (Repositories\is_main_package($dependant->config, $this->variables[$repo_id])) return false;
                    }
                    continue;
                }

                $is_main_requirement = Dependencies\is_main_package($this->project_config, $dependency);

                if ($is_main_requirement) {
                    if (isset($this->fixed_assignments[$repo_id]))
                        if (Commits\are_equal($this->fixed_assignments[$repo_id]->commit, $dependency->commit)) {
                          continue;
                        } else {
                            return false;
                        }

                    $required_version = Dependencies\required_main_package($this->project_config, $dependency);

                    if (Versions\is_development($required_version) && Versions\is_development($dependency->commit->version)) {
                        $this->fixed_assignments[$repo_id] = $dependency;
                        continue;
                    }
                    if (Versions\is_development($required_version)) return false;
                    // If a stable version is required, reject development dependencies
                    if (Versions\is_development($dependency->commit->version)) return false;
                    if (GitHosts\compare_versions($required_version->tag, $dependency->commit->version->tag) > 0) return false;
                    if (!$this->ignore_version_compatibility && GitHosts\major_part($required_version->tag) !== GitHosts\major_part($dependency->commit->version->tag)) return false;
                }

                $has_depandant = false;
                foreach ($assignment as $dependant) {
                    if ($dependant === 'absence' || $dependant === 'project') continue;
                    if (Repositories\are_equal($dependant->commit->version->repository, $dependency->commit->version->repository)) continue;
                    if (!Dependencies\is_main_package($dependant->config, $dependency)) continue;
                    $has_depandant = true;
                    $required_version = Dependencies\required_main_package($dependant->config, $dependency);

                    if (Versions\is_development($required_version)) continue;
                    if (Versions\is_development($dependency->commit->version)) continue;
                    if (GitHosts\compare_versions($required_version->tag, $dependency->commit->version->tag) > 0) return false;
                    if (!$this->ignore_version_compatibility && GitHosts\major_part($required_version->tag) !== GitHosts\major_part($dependency->commit->version->tag)) return false;
                }

                if ($is_main_requirement) {
                    $this->fixed_assignments[$repo_id] = $dependency;
                }

                if (!$is_main_requirement && !$has_depandant) return false;
            }

            return true;
        };
    }
}

