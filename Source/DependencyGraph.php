<?php

namespace Phpkg;

use Phpkg\Classes\Dependency;
use Phpkg\Classes\Package;
use Phpkg\Classes\Project;
use Phpkg\Datatypes\Digraph;
use function Phpkg\Application\PackageManager\config_from_disk;
use function Phpkg\Application\PackageManager\package_path;
use function Phpkg\DependencyGraphs\add;
use function Phpkg\DependencyGraphs\add_sub_dependency;
use function Phpkg\DependencyGraphs\highest_version_of_dependency;

class DependencyGraph extends Digraph
{
    public static function for(Project $project): static
    {
        $dependency_graph = DependencyGraph::empty();

        $dependency_graph = $project->meta->packages->reduce(function (DependencyGraph $dependency_graph, Package $package) {
            return add($dependency_graph, Dependency::from_package($package));
        }, $dependency_graph);

        return $project->meta->packages->reduce(function (DependencyGraph $dependency_graph, Package $package) use ($project) {
            $dependency = Dependency::from_package($package);

            return config_from_disk(package_path($project, $package))->packages->reduce(function (DependencyGraph $dependency_graph, Package $sub_package) use ($project, $dependency) {
                $sub_package = $project->meta->packages->first(fn (Package $installed_package) => $installed_package->value->owner === $sub_package->value->owner && $installed_package->value->repo === $sub_package->value->repo);
                $sub_dependency = Dependency::from_package($sub_package);
                return add_sub_dependency($dependency_graph, $dependency, highest_version_of_dependency($dependency_graph, $sub_dependency));
            }, $dependency_graph);
        }, $dependency_graph);
    }
}
