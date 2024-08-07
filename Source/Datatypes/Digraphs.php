<?php

namespace Phpkg\Datatypes\Digraphs;

use Closure;
use Phpkg\Datatypes\Digraph;
use Phpkg\Datatypes\Edge;
use Phpkg\Datatypes\Node;
use PhpRepos\Datatype\Collection;
use PhpRepos\Datatype\Map;

function add_node(Digraph $digraph, Node $node): Digraph
{
    $static = get_class($digraph);
    $vertices = $digraph->vertices;
    $edges = $digraph->edges;
    $vertices->push($node);

    return new $static($vertices, $edges);
}

function add_edge(Digraph $digraph, Node $source, Node $destination): Digraph
{
    $static = get_class($digraph);
    $vertices = $digraph->vertices;
    $edges = $digraph->edges;
    if (! $vertices->has(fn (Node $node) => $node->key === $source->key)) {
        $vertices->push($source);
    }

    if (! $vertices->has(fn (Node $node) => $node->key === $destination->key)) {
        $vertices->push($destination);
    }
    $edges->push(new Edge($source->key, $destination->key));

    return new $static($vertices, $edges);
}

function remove(Digraph $digraph, Node $node): Digraph
{
    $static = get_class($digraph);
    $vertices = $digraph->vertices;
    $edges = $digraph->edges;
    $vertices->forget(fn (Node $vertex) => $vertex->key === $node->key);
    $edges->forget(fn (Edge $edge) => $edge->key === $node->key || $edge->value === $node->key);

    return new $static($vertices, $edges);
}

function has_node(Digraph $digraph, Closure $condition): bool
{
    return $digraph->vertices->has(fn (Node $node) => $condition($node));
}

function find(Digraph $digraph, Closure $condition): Node
{
    return $digraph->vertices->first(fn (Node $node) => $condition($node));
}

function filter(Digraph $digraph, Closure $condition): Map
{
    return $digraph->vertices->filter(fn (Node $node) => $condition($node));
}

/**
 * @param Digraph $digraph
 * @param Node $node
 * @return array<Node>
 */
function out_neighbors(Digraph $digraph, Node $node): array
{
    return $digraph->edges
        ->filter(fn (Edge $edge) => $edge->key === $node->key)
        ->map(fn (Edge $edge) => find($digraph, fn (Node $vertex) => $vertex->key === $edge->value));
}

/**
 * @param Digraph $digraph
 * @param Node $node
 * @return array<Node>
 */
function in_neighbors(Digraph $digraph, Node $node): array
{
    return $digraph->edges
        ->filter(fn (Edge $edge) => $edge->value === $node->key)
        ->map(fn (Edge $edge) => find($digraph, fn (Node $vertex) => $vertex->key === $edge->key));
}

/**
 * @param Digraph $digraph
 * @param Node $root
 * @return Collection<Node>
 */
function depth_first_search(Digraph $digraph, Node $root): Collection
{
    $visited = [];
    $nodes = new Collection();

    $dfs = function (Node $root) use ($digraph, &$visited, $nodes, &$dfs) {
        if (isset($visited[$root->key])) {
            return;
        }

        $visited[$root->key] = true;
        $nodes->push($root);

        foreach (out_neighbors($digraph, $root) as $node) {
            $dfs($node);
        }
    };

    $dfs($root);

    return $nodes;
}
