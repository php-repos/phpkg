<?php

namespace Phpkg\DependencyGraphs;

use Phpkg\Classes\Dependency;
use Phpkg\Classes\Package;
use Phpkg\Datatypes\Edge;
use Phpkg\DependencyGraph;
use PhpRepos\Datatype\Collection;
use function Phpkg\Comparison\first_is_greater_or_equal;
use function Phpkg\Datatypes\Digraphs\add_edge;
use function Phpkg\Datatypes\Digraphs\add_node;
use function Phpkg\Datatypes\Digraphs\depth_first_search;
use function Phpkg\Datatypes\Digraphs\filter;
use function Phpkg\Datatypes\Digraphs\find;
use function Phpkg\Datatypes\Digraphs\has_node;
use function Phpkg\Git\Version\compare;
use function PhpRepos\Datatype\Arr\reduce;

function has_similar_dependency(DependencyGraph $dependency_graph, Dependency $dependency): bool
{
    return has_node($dependency_graph, fn (Dependency $vertex) => $vertex->value->value->owner === $dependency->value->value->owner && $vertex->value->value->repo === $dependency->value->value->repo);
}

function has_identical_dependency(DependencyGraph $dependency_graph, Dependency $dependency): bool
{
    return has_node($dependency_graph, fn (Dependency $vertex) => $vertex->key === $dependency->key);
}

function find_package_dependency(DependencyGraph $dependency_graph, Package $package): Dependency
{
    /** @var Dependency */
    return find($dependency_graph, fn (Dependency $vertex) => $vertex->value->value->owner === $package->value->owner && $vertex->value->value->repo === $package->value->repo);
}

function highest_version_of_dependency(DependencyGraph $dependency_graph, Dependency $dependency): Dependency
{
    // Find all installed version
    $installed_dependencies = filter(
        $dependency_graph,
        fn (Dependency $vertex)
            => $dependency->value->value->owner === $vertex->value->value->owner
                && $dependency->value->value->repo === $vertex->value->value->repo
    );

    return reduce(
        $installed_dependencies->items(),
        function (Dependency $carry, Dependency $installed_dependency) {
            return first_is_greater_or_equal(fn() => compare($carry->value->value->version, $installed_dependency->value->value->version))
                ? $carry
                : $installed_dependency;
        },
        $installed_dependencies->first(),
    );
}

function swap(DependencyGraph $dependency_graph, Dependency $search, Dependency $replace): DependencyGraph
{
    $vertices = $dependency_graph->vertices;
    $edges = $dependency_graph->edges;

    $vertices->forget(fn (Dependency $vertex) => $vertex->key === $search->key);

    if (! $vertices->has(fn (Dependency $vertex) => $vertex->key === $replace->key)) {
        $vertices->put($replace);
    }

    $edges = array_filter($edges->map(function (Edge $edge) use ($search, $replace) {
        if ($edge->key === $search->key) {
            return null;
        }

        if ($edge->value === $search->key) {
            $edge->value = $replace->key;
        }
        return $edge;
    }));

    return new DependencyGraph($vertices, new Collection($edges));
}

function resolve(DependencyGraph $dependency_graph): DependencyGraph
{
    $dependency_graph->vertices->each(function (Dependency $dependency) use (&$dependency_graph) {
        if (highest_version_of_dependency($dependency_graph, $dependency)->key !== $dependency->key) {
            return;
        }

        $dependency_graph = $dependency_graph->vertices
            ->filter(fn (Dependency $vertex) => $vertex->value->value->owner === $dependency->value->value->owner && $dependency->value->value->repo === $vertex->value->value->repo)
            ->filter(fn (Dependency $vertex) => $vertex->key !== $dependency->key)
            ->reduce(
                fn (DependencyGraph $dependency_graph, Dependency $vertex) => swap($dependency_graph, $vertex, $dependency),
                $dependency_graph
            );
    });

    return $dependency_graph;
}

/**
 * @param DependencyGraph $dependency_graph
 * @param Dependency $dependency
 * @return Collection<Dependency>
 */
function dependencies(DependencyGraph $dependency_graph, Dependency $dependency): Collection
{
    return depth_first_search($dependency_graph, $dependency);
}

function add(DependencyGraph $dependency_graph, Dependency $dependency): DependencyGraph
{
    /** @var DependencyGraph */
    return add_node($dependency_graph, $dependency);
}

function add_sub_dependency(DependencyGraph $dependency_graph, Dependency $dependent, Dependency $dependency): DependencyGraph
{
    /** @var DependencyGraph */
    return add_edge($dependency_graph, $dependent, $dependency);
}

function remove(DependencyGraph $dependency_graph, Dependency $dependency): DependencyGraph
{
    /** @var DependencyGraph */
    return \Phpkg\Datatypes\Digraphs\remove($dependency_graph, $dependency);
}
