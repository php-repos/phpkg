<?php

namespace Phpkg\Parser\Parser;

use Phpkg\Parser\NodeParser;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\Lexer\Emulative;

function parse(string $code): NodeParser
{
    $parser = (new ParserFactory())->createForHostVersion();
    $traverser = new NodeTraverser();

    $traverser->addVisitor($visitor = new NodeParser());

    $ast = $parser->parse($code);
    $traverser->traverse($ast);

    return $visitor;
}

function find_starting_point_for_imports(string $code): int
{
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
