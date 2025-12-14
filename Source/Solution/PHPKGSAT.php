<?php

namespace Phpkg\Solution;

use PhpRepos\SimpleCSP\SAT;
use Phpkg\Solution\Commits;
use Phpkg\Solution\Data\Version;
use Phpkg\Solution\Exceptions\DependencyResolutionException;
use Phpkg\Solution\Exceptions\VersionIncompatibilityException;
use Phpkg\Solution\Repositories;
use Phpkg\Infra\Arrays;
use function Phpkg\Infra\Logs\debug;
use function Phpkg\Infra\GitHosts\major_part;

class PHPKGSAT extends SAT
{
    public array $variables;
    public array $clauses;
    
    /**
     * @param array<Solution> $csp_solutions
     * @param array<mixed, mixed> $project_config
     * @param bool $ignore_version_compatibility
     */
    public function __construct(private array $csp_solutions, private array $project_config, bool $ignore_version_compatibility)
    {
        $this->variables = [];
        $this->clauses = [];

        $repo_versions = Arrays\group_by_keys($csp_solutions);

        foreach ($repo_versions as $repo => $versions) {
            $version_groups = Arrays\group_by($versions, function (array $assignment) {
                $package = $assignment['value'];
                if ($package === 'absence') return 'absence';
                if ($package === 'project') return 'project';
                if (Versions\is_development($package->commit->version)) return 'development';
                return 'stable';
            });

            $version_groups['stable'] = Arrays\unique($version_groups['stable'] ?? [], fn ($package1, $package2) => Commits\are_equal($package1['value']->commit, $package2['value']->commit));
            
            debug('Versions for repo', [
                'repo' => $repo,
                'version_groups' => $version_groups,
            ]);

            $stable_versions = Arrays\group_by($version_groups['stable'] ?? [], fn (array $assignment) => major_part($assignment['value']->commit->version->tag));

            if (count($stable_versions) > 1) {
                if (!$ignore_version_compatibility)
                    throw new VersionIncompatibilityException("Cannot resolve dependencies for repository {$repo} due to incompatible major versions: " . implode(', ', array_keys($stable_versions)) . ".");

                $stable_versions = Arrays\sort_by_keys_desc($stable_versions, fn ($a, $b) => intval($a) <=> intval($b));
                foreach ($stable_versions as $i => $group) {
                    if ($i === 0) {
                        $version_groups['stable'] = $group;
                        continue;
                    }
                    foreach ($group as $group_assignment) {
                        foreach ($csp_solutions as $solution_index => $solution) {
                            foreach ($solution as $assignment_index => $assignment) {
                                if (Commits\are_equal($assignment['value']->commit, $group_assignment['value']->commit)) {
                                    unset($csp_solutions[$solution_index]);
                                }
                            }
                        }
                    }
                }
            }

            if (isset($version_groups['development']) && count($version_groups['stable']) > 0) {
                throw new DependencyResolutionException("Repository {$repo} has both development and stable versions in the dependency solutions, which is not allowed.");
            }

            $variables = isset($version_groups['development'][0]) ? [$version_groups['development'][0]['value']] : [];

            foreach ($version_groups['stable'] as $assignment) {
                $variables[] = $assignment['value'];
            }

            if (count($variables) === 0) continue;

            $this->variables = array_merge($this->variables, $variables);

            foreach (Arrays\cartesian_product($variables, $variables) as $pair) {
                $package_a = $pair[0];
                $package_b = $pair[1];
                if (Commits\are_equal($package_a->commit, $package_b->commit)) continue;

                // Add clause: not both package_a and package_b can be true
                $index_a = array_search($package_a, $this->variables, true);
                $index_b = array_search($package_b, $this->variables, true);
                $this->clauses[] = [-($index_a + 1), -($index_b + 1)];
            }

            if (Repositories\is_main_package($project_config, $variables[0]->commit->version->repository)) {
                $indices = Arrays\map($variables, fn ($variable) => array_search($variable, $this->variables, true) + 1);
                $this->clauses[] = $indices;
            }

            if (isset($version_groups['development']) && count($version_groups['stable']) > 0) {
                throw new DependencyResolutionException("Repository {$repo} has both development and stable versions in the dependency solutions, which is not allowed.");
            }

            $variables = isset($version_groups['development'][0]) ? [$version_groups['development'][0]['value']] : [];

            foreach ($version_groups['stable'] as $assignment) {
                $variables[] = $assignment['value'];
            }
        }

        foreach ($csp_solutions as $solution) {
            foreach (Arrays\cartesian_product($solution, $solution) as $pair) {
                if ($pair[0]['value'] === 'absence' || $pair[0]['value'] === 'project') continue;
                if ($pair[1]['value'] === 'absence' || $pair[1]['value'] === 'project') continue;
                if (Commits\are_equal($pair[0]['value']->commit, $pair[1]['value']->commit)) continue;
                $index_a = array_search($pair[0]['value'], $this->variables, true);
                $index_b = array_search($pair[1]['value'], $this->variables, true);
                $this->clauses[] = [-($index_a + 1), ($index_b + 1)];
                $this->clauses[] = [-($index_b + 1), ($index_a + 1)];
            }
        }
    }

    public function evaluate(array $solution): float
    {
        $value = 0;
        foreach ($solution as $assignment) {
            if (!$assignment['value']) continue;

            $package = $assignment['variable'];
            if ($package === false
                || !Arrays\has($this->project_config['packages'], fn (Version $version, string $url)
                    => Repositories\are_equal($package->commit->version->repository, $version->repository))
            ) {
                continue;
            }

            $tag = $package->commit->version->tag;
            if (preg_match('/v?(\d+)\.(\d+)\.(\d+)/', $tag, $matches)) {
                $value += (float)$matches[1] * 10000 + (float)$matches[2] * 100 + (float)$matches[3];
            } elseif ($tag === 'development') {
                 $value += 999999;
            }
        }
        return $value;
    }
}
