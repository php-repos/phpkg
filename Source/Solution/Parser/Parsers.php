<?php

namespace Phpkg\Solution\Parser\Parsers;

use Phpkg\Solution\Parser\NodeParser;
use Phpkg\Solution\Parser\SymbolRegistry;
use Phpkg\Infra\Strings;
use PhpParser\Lexer\Emulative;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use function PhpRepos\Datatype\Arr\has;
use function Phpkg\Infra\Logs\debug;
use function Phpkg\Infra\Logs\log;

function get_registry(string $code): SymbolRegistry
{
    log('Getting registry from code.', ['sample' => Strings\first_characters($code, 20)]);
    $parser = parse($code);

    $classes = array_filter($parser->nodes, fn ($node) => $node['type'] === 'class');
    $constants = array_filter($parser->nodes, fn ($node) => $node['type'] === 'constant');
    $functions = array_filter($parser->nodes, fn ($node) => $node['type'] === 'function');

    foreach ($classes as $import => $node) {
        if (has($constants, fn ($node) => $node['namespace'] === $import) || has($functions, fn ($node) => $node['namespace'] === $import)) {
            unset($classes[$import]);
        }
    }

    $imports = array_merge(
        array_map(fn ($node) => $node['actual_name'], $constants),
        array_map(fn ($node) => $node['actual_name'], $functions),
    );

    $namespaces = array_map(fn ($node) => $node['actual_name'], $classes);

    return new SymbolRegistry(
        $classes,
        $functions,
        $constants,
        $imports,
        $namespaces,
    );
}

function parse(string $code): NodeParser
{
    debug('Parsing code.', ['sample' => Strings\first_characters($code, 20)]);
    $parser = (new ParserFactory())->createForHostVersion();
    $traverser = new NodeTraverser();

    $traverser->addVisitor($visitor = new NodeParser());

    $ast = $parser->parse($code);
    $traverser->traverse($ast);

    return $visitor;
}

function find_starting_point_for_imports(string $code): int
{
    log('Finding starting point for imports.', ['sample' => Strings\first_characters($code, 20)]);
    $lexer = new Emulative();

    $tokens = $lexer->tokenize($code);

    $position = 0;

    foreach ($tokens as $index => $token) {
        if ($position === 0) {
            if (in_array($token->id, [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                $position = $token->getEndPos();
            }
        }

        if ($token->id === T_DECLARE) {
            $declaresStrictType = false;
            $nextTokens = array_slice($tokens, $index + 1);
            foreach ($nextTokens as $nextToken) {
                if (!$declaresStrictType && $nextToken->id === T_STRING && $nextToken->text === 'strict_types') {
                    $declaresStrictType = true;
                }

                if ($declaresStrictType) {
                    if ($nextToken->text === ';') {
                        $position = $nextToken->getEndPos();
                        break;
                    }
                }
            }
        }

        if ($token->id === T_NAMESPACE) {
            $nextTokens = array_slice($tokens, $index + 1);
            foreach ($nextTokens as $nextToken) {
                if ($nextToken->text === ';') {
                    $position = $nextToken->getEndPos();
                    break;
                }
            }
            break;
        }
    }

    return $position;
}
