<?php

namespace Tests\DependencyGraph\ResolveTest;

use Phpkg\Classes\Dependency;
use Phpkg\Classes\Package;
use Phpkg\Datatypes\Edge;
use Phpkg\DependencyGraph;
use Phpkg\Git\Repository;
use function Phpkg\DependencyGraphs\add;
use function Phpkg\DependencyGraphs\add_sub_dependency;
use function Phpkg\DependencyGraphs\resolve;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should resolve an empty graph',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 0, 'Wrong vertices for an empty graph');
        assert_true($result->edges->count() === 0, 'Wrong edges for an empty graph');
    }
);

test(
    title: 'it should resolve a single node graph',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $repository = new Repository('domain', 'owner', 'repo');
        $repository->version = 'v1.0.0';
        $repository->hash = 'hash-v1.0.0';
        $package = new Package('git-url-package', $repository);
        $dependency = Dependency::from_package($package);
        $dependency_graph = add($dependency_graph, $dependency);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 1, 'Number of packages does not match');
        assert_true(
            $result->vertices->first(fn (Dependency $vertex) => $dependency->key === $vertex->key) !== null,
            'Wrong dependency added.'
        );
    }
);

test(
    title: 'it should resolve a graph with two nodes',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $repository1 = new Repository('domain', 'owner-a', 'repo-a');
        $repository1->version = 'v1.0.0';
        $repository1->hash = 'hash-v1.0.0';
        $package1 = new Package('git-url-package-a', $repository1);
        $dependency1 = Dependency::from_package($package1);

        $repository2 = new Repository('domain', 'owner-b', 'repo-b');
        $repository2->version = 'v1.0.0';
        $repository2->hash = 'hash-v1.0.0';
        $package2 = new Package('git-url-package-b', $repository2);
        $dependency2 = Dependency::from_package($package2);

        $dependency_graph = add($dependency_graph, $dependency1);
        $dependency_graph = add($dependency_graph, $dependency2);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 2, 'Number of packages does not match');
        assert_true($result->vertices->first()->key === $dependency1->key, 'Wrong dependency added as the first item.');
        assert_true($result->vertices->last()->key === $dependency2->key, 'Wrong dependency added as the last item.');
        assert_true($result->edges->count() === 0, 'Wrong edges defined.');
    }
);

test(
    title: 'it should resolve a graph with two nodes that are same',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $repository1 = new Repository('domain', 'owner-a', 'repo-a');
        $repository1->version = 'v1.0.0';
        $repository1->hash = 'hash-v1.0.0';
        $package1 = new Package('git-url-package-a', $repository1);
        $dependency1 = Dependency::from_package($package1);

        $repository2 = new Repository('domain', 'owner-a', 'repo-a');
        $repository2->version = 'v2.0.0';
        $repository2->hash = 'hash-v2.0.0';
        $package2 = new Package('git-url-package-2', $repository2);
        $dependency2 = Dependency::from_package($package2);

        $dependency_graph = add($dependency_graph, $dependency1);
        $dependency_graph = add($dependency_graph, $dependency2);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 1, 'Number of packages does not match');
        assert_true($result->vertices->first()->key === $dependency2->key, 'Wrong dependency resolved as the dependency.');
    }
);

test(
    title: 'it should resolve a graph with two nodes that are same regardless of the order',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $repository1 = new Repository('domain', 'owner-a', 'repo-a');
        $repository1->version = 'v1.0.0';
        $repository1->hash = 'hash-v1.0.0';
        $package1 = new Package('git-url-package-a', $repository1);
        $dependency1 = Dependency::from_package($package1);

        $repository2 = new Repository('domain', 'owner-a', 'repo-a');
        $repository2->version = 'v2.0.0';
        $repository2->hash = 'hash-v2.0.0';
        $package2 = new Package('git-url-package-2', $repository2);
        $dependency2 = Dependency::from_package($package2);

        $dependency_graph = add($dependency_graph, $dependency2);
        $dependency_graph = add($dependency_graph, $dependency1);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 1, 'Number of packages does not match');
        assert_true($result->vertices->first()->key === $dependency2->key, 'Wrong dependency resolved as the dependency.');
    }
);

test(
    title: 'it should resolve a graph with a sub dependency',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $repository1 = new Repository('domain', 'owner-a', 'repo-a');
        $repository1->version = 'v1.0.0';
        $repository1->hash = 'hash-v1.0.0';
        $package1 = new Package('git-url-package-a', $repository1);
        $dependency1 = Dependency::from_package($package1);

        $repository2 = new Repository('domain', 'owner-b', 'repo-b');
        $repository2->version = 'v1.0.0';
        $repository2->hash = 'hash-v1.0.0';
        $package2 = new Package('git-url-package-b', $repository2);
        $dependency2 = Dependency::from_package($package2);

        $dependency_graph = add($dependency_graph, $dependency1);
        $dependency_graph = add_sub_dependency($dependency_graph, $dependency1, $dependency2);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 2, 'Number of packages does not match');
        assert_true($result->vertices->first()->key === $dependency1->key, 'Wrong dependency resolved as the dependency1.');
        assert_true($result->vertices->last()->key === $dependency2->key, 'Wrong dependency resolved as the dependency2.');
        assert_true($result->edges->first()->key === $dependency1->key, 'Wrong edge defined from dependency1');
        assert_true($result->edges->first()->value === $dependency2->key, 'Wrong edge defined to dependency2');
    }
);

test(
    title: 'it should resolve a graph with packages in different versions',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $a1_repo = new Repository('domain', 'owner-a', 'repo-a');
        $a1_repo->version = 'v1.0.0';
        $a1_repo->hash = 'hash-v1.0.0';
        $a1_package = new Package('git-url-package-a', $a1_repo);
        $a1_dependency = Dependency::from_package($a1_package);

        $a2_repo = new Repository('domain', 'owner-a', 'repo-a');
        $a2_repo->version = 'v2.0.0';
        $a2_repo->hash = 'hash-v2.0.0';
        $a2_package = new Package('git-url-package-a', $a2_repo);
        $a2_dependency = Dependency::from_package($a2_package);

        $b1_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b1_repo->version = 'v1.0.0';
        $b1_repo->hash = 'hash-v1.0.0';
        $b1_package = new Package('git-url-package-b', $b1_repo);
        $b1_dependency = Dependency::from_package($b1_package);

        $b2_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b2_repo->version = 'v2.0.0';
        $b2_repo->hash = 'hash-v2.0.0';
        $b2_package = new Package('git-url-package-b', $b2_repo);
        $b2_dependency = Dependency::from_package($b2_package);

        $c1_repo = new Repository('domain', 'owner-c', 'repo-c');
        $c1_repo->version = 'v1.0.0';
        $c1_repo->hash = 'hash-v1.0.0';
        $c1_package = new Package('git-url-package-c', $c1_repo);
        $c1_dependency = Dependency::from_package($c1_package);

        $dependency_graph = add($dependency_graph, $a1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $a1_dependency, $b1_dependency);

        $dependency_graph = add($dependency_graph, $a2_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $a2_dependency, $b2_dependency);

        $dependency_graph = add($dependency_graph, $c1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $c1_dependency, $a1_dependency);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 3, 'Number of packages does not match!');
        assert_true($result->vertices->first()->key === $a2_dependency->key, 'Dependency A2 expected but not satisfied.');
        assert_true($result->vertices->skip(1)->first()->key === $b2_dependency->key, 'Dependency B2 expected but not satisfied.');
        assert_true($result->vertices->last()->key === $c1_dependency->key, 'Dependency C1 expected but not satisfied.');
        assert_true($result->edges->first()->key === $a2_dependency->key && $result->edges->first()->value === $b2_dependency->key, 'Sub dependencies for A2 does not defined properly!');
        assert_true($result->edges->last()->key === $c1_dependency->key && $result->edges->last()->value === $a2_dependency->key, 'Sub dependencies for C1 does not defined properly!');
    }
);

test(
    title: 'it should ignore the identical package in the graph',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $a1_repo = new Repository('domain', 'owner-a', 'repo-a');
        $a1_repo->version = 'v1.0.0';
        $a1_repo->hash = 'hash-v1.0.0';
        $a1_package = new Package('git-url-package-a', $a1_repo);
        $a1_dependency = Dependency::from_package($a1_package);

        $b1_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b1_repo->version = 'v1.0.0';
        $b1_repo->hash = 'hash-v1.0.0';
        $b1_package = new Package('git-url-package-b', $b1_repo);
        $b1_dependency = Dependency::from_package($b1_package);

        $b2_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b2_repo->version = 'v2.0.0';
        $b2_repo->hash = 'hash-v2.0.0';
        $b2_package = new Package('git-url-package-b', $b2_repo);
        $b2_dependency = Dependency::from_package($b2_package);

        $c1_repo = new Repository('domain', 'owner-c', 'repo-c');
        $c1_repo->version = 'v1.0.0';
        $c1_repo->hash = 'hash-v1.0.0';
        $c1_package = new Package('git-url-package-c', $c1_repo);
        $c1_dependency = Dependency::from_package($c1_package);

        $dependency_graph = add($dependency_graph, $a1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $a1_dependency, $b2_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b2_dependency, $c1_dependency);

        $dependency_graph = add($dependency_graph, $b1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b1_dependency, $a1_dependency);

        $result = resolve($dependency_graph);

        assert_true($dependency_graph->vertices->count() === 3, 'Number of packages does not match!');
        assert_true($result->vertices->first()->key === $a1_dependency->key, 'Dependency A1 expected but not satisfied.');
        assert_true($result->vertices->skip(1)->first()->key === $b2_dependency->key, 'Dependency B2 expected but not satisfied.');
        assert_true($result->vertices->last()->key === $c1_dependency->key, 'Dependency C1 expected but not satisfied.');
        assert_true($result->edges->first(fn (Edge $edge) => $edge->key === $b2_dependency->key)->value === $c1_dependency->key, 'C1 not defined as a sub dependency for B2');
    }
);

test(
    title: 'it should resolve all nodes',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $a1_repo = new Repository('domain', 'owner-a', 'repo-a');
        $a1_repo->version = 'v1.0.0';
        $a1_repo->hash = 'hash-v1.0.0';
        $a1_package = new Package('git-url-package-a', $a1_repo);
        $a1_dependency = Dependency::from_package($a1_package);

        $b1_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b1_repo->version = 'v1.0.0';
        $b1_repo->hash = 'hash-v1.0.0';
        $b1_package = new Package('git-url-package-b', $b1_repo);
        $b1_dependency = Dependency::from_package($b1_package);

        $c1_repo = new Repository('domain', 'owner-c', 'repo-c');
        $c1_repo->version = 'v1.0.0';
        $c1_repo->hash = 'hash-v1.0.0';
        $c1_package = new Package('git-url-package-c', $c1_repo);
        $c1_dependency = Dependency::from_package($c1_package);

        $d1_repo = new Repository('domain', 'owner-d', 'repo-d');
        $d1_repo->version = 'v1.0.0';
        $d1_repo->hash = 'hash-v1.0.0';
        $d1_package = new Package('git-url-package-d', $d1_repo);
        $d1_dependency = Dependency::from_package($d1_package);

        $b2_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b2_repo->version = 'v2.0.0';
        $b2_repo->hash = 'hash-v2.0.0';
        $b2_package = new Package('git-url-package-b', $b2_repo);
        $b2_dependency = Dependency::from_package($b2_package);

        $c2_repo = new Repository('domain', 'owner-c', 'repo-c');
        $c2_repo->version = 'v2.0.0';
        $c2_repo->hash = 'hash-v2.0.0';
        $c2_package = new Package('git-url-package-c', $c2_repo);
        $c2_dependency = Dependency::from_package($c2_package);

        $d2_repo = new Repository('domain', 'owner-d', 'repo-d');
        $d2_repo->version = 'v2.0.0';
        $d2_repo->hash = 'hash-v2.0.0';
        $d2_package = new Package('git-url-package-d', $d2_repo);
        $d2_dependency = Dependency::from_package($d2_package);

        $dependency_graph = add($dependency_graph, $a1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $a1_dependency, $c1_dependency);
        $dependency_graph = add($dependency_graph, $b1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b1_dependency, $d1_dependency);
        $dependency_graph = add($dependency_graph, $b2_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b2_dependency, $c2_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b2_dependency, $d2_dependency);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 4, 'Number of packages does not match!');
        assert_true($result->vertices->first()->key === $a1_dependency->key, 'Dependency A1 expected but not satisfied.');
        assert_true($result->vertices->skip(1)->first()->key === $b2_dependency->key, 'Dependency B2 expected but not satisfied.');
        assert_true($result->vertices->skip(2)->first()->key === $c2_dependency->key, 'Dependency C2 expected but not satisfied.');
        assert_true($result->vertices->last()->key === $d2_dependency->key, 'Dependency D2 expected but not satisfied.');
        assert_true($result->edges->first(fn (Edge $edge) => $a1_dependency->key === $edge->key)->value === $c2_dependency->key, 'C2 not defined as a sub dependency for A1');
        assert_true($result->edges->first(fn (Edge $edge) => $b2_dependency->key === $edge->key)->value === $c2_dependency->key, 'C2 not defined as a sub dependency for B2');
        assert_true($result->edges->last(fn (Edge $edge) => $b2_dependency->key === $edge->key)->value === $d2_dependency->key, 'D2 not defined as a sub dependency for B2');
    }
);

test(
    title: 'it should resolve nodes when dependencies change',
    case: function () {
        $dependency_graph = DependencyGraph::empty();

        $a1_repo = new Repository('domain', 'owner-a', 'repo-a');
        $a1_repo->version = 'v1.0.0';
        $a1_repo->hash = 'hash-v1.0.0';
        $a1_package = new Package('git-url-package-a', $a1_repo);
        $a1_dependency = Dependency::from_package($a1_package);

        $b1_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b1_repo->version = 'v1.0.0';
        $b1_repo->hash = 'hash-v1.0.0';
        $b1_package = new Package('git-url-package-b', $b1_repo);
        $b1_dependency = Dependency::from_package($b1_package);

        $c1_repo = new Repository('domain', 'owner-c', 'repo-c');
        $c1_repo->version = 'v1.0.0';
        $c1_repo->hash = 'hash-v1.0.0';
        $c1_package = new Package('git-url-package-c', $c1_repo);
        $c1_dependency = Dependency::from_package($c1_package);

        $d1_repo = new Repository('domain', 'owner-d', 'repo-d');
        $d1_repo->version = 'v1.0.0';
        $d1_repo->hash = 'hash-v1.0.0';
        $d1_package = new Package('git-url-package-d', $d1_repo);
        $d1_dependency = Dependency::from_package($d1_package);

        $b2_repo = new Repository('domain', 'owner-b', 'repo-b');
        $b2_repo->version = 'v2.0.0';
        $b2_repo->hash = 'hash-v2.0.0';
        $b2_package = new Package('git-url-package-b', $b2_repo);
        $b2_dependency = Dependency::from_package($b2_package);

        $c2_repo = new Repository('domain', 'owner-c', 'repo-c');
        $c2_repo->version = 'v2.0.0';
        $c2_repo->hash = 'hash-v2.0.0';
        $c2_package = new Package('git-url-package-c', $c2_repo);
        $c2_dependency = Dependency::from_package($c2_package);

        $d2_repo = new Repository('domain', 'owner-d', 'repo-d');
        $d2_repo->version = 'v2.0.0';
        $d2_repo->hash = 'hash-v2.0.0';
        $d2_package = new Package('git-url-package-d', $d2_repo);
        $d2_dependency = Dependency::from_package($d2_package);

        $dependency_graph = add($dependency_graph, $a1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $a1_dependency, $c1_dependency);
        $dependency_graph = add($dependency_graph, $b1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b1_dependency, $c1_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b1_dependency, $d1_dependency);
        $dependency_graph = add($dependency_graph, $b2_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $b2_dependency, $c2_dependency);
        $dependency_graph = add_sub_dependency($dependency_graph, $c2_dependency, $d2_dependency);

        $result = resolve($dependency_graph);

        assert_true($result->vertices->count() === 4, 'Number of packages does not match!');
        assert_true($result->vertices->first()->key === $a1_dependency->key, 'Dependency A1 expected but not satisfied.');
        assert_true($result->vertices->skip(1)->first()->key === $b2_dependency->key, 'Dependency B2 expected but not satisfied.');
        assert_true($result->vertices->skip(2)->first()->key === $c2_dependency->key, 'Dependency C2 expected but not satisfied.');
        assert_true($result->vertices->last()->key === $d2_dependency->key, 'Dependency D2 expected but not satisfied.');
        assert_true($result->edges->first(fn (Edge $edge) => $a1_dependency->key === $edge->key)->value === $c2_dependency->key, 'C2 not defined as a sub dependency for A1');
        assert_true($result->edges->first(fn (Edge $edge) => $b2_dependency->key === $edge->key)->value === $c2_dependency->key, 'C2 not defined as a sub dependency for B2');
        assert_true($result->edges->first(fn (Edge $edge) => $c2_dependency->key === $edge->key)->value === $d2_dependency->key, 'D2 not defined as a sub dependency for C2');
    }
);
