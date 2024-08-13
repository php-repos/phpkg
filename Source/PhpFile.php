<?php

namespace Phpkg;

use Closure;
use PhpRepos\Datatype\Str;
use function PhpRepos\Datatype\Arr\has;

class PhpFile
{
    public function __construct(public array $tokens) {}

    public static function from_content(string $content): static
    {
        return new static(token_get_all($content));
    }

    public function code(): string
    {
        $content = '';

        foreach ($this->tokens as $token) {
            $content .= is_string($token) ? $token : $token[1];
        }

        return $content;
    }

    public function add_after_namespace(string $addition): static
    {
        $reached_namespace = false;
        $namespace_finished_at = 0;
        foreach ($this->tokens as $key => $token) {
            if ($reached_namespace) {
                if ($token === ';') {
                    $namespace_finished_at = $key + 1;
                    break;
                }
            } else if (is_array($token)) {
                $reached_namespace = $token[0] === T_NAMESPACE;
            }
        }

        return new static(array_merge(
            array_slice($this->tokens, 0, $namespace_finished_at),
            token_get_all($addition),
            array_slice($this->tokens, $namespace_finished_at),
        ));
    }

    public function add_after_opening_tag(string $addition): static
    {
        $opening_tag_position = 0;
        foreach ($this->tokens as $key => $token) {
            if (is_array($token)) {
                if ($token[0] === T_OPEN_TAG) {
                    $opening_tag_position = $key + 1;
                    break;
                }
                if ($token[0] === T_OPEN_TAG_WITH_ECHO) {
                    break;
                }
            }
        }

        $addition = $opening_tag_position === 0 ? '<?php ' . $addition . ' ?>' : $addition;

        return new static(array_merge(
            array_slice($this->tokens, 0, $opening_tag_position),
            token_get_all($addition),
            array_slice($this->tokens, $opening_tag_position),
        ));
    }

    public function add_after_strict_type_declaration(string $addition): static
    {
        $declare_tag_position = null;
        $strict_type_position = null;
        $addition_position = null;
        foreach ($this->tokens as $key => $token) {
            if (is_null($declare_tag_position) && is_array($token) && $token[0] === T_DECLARE) {
                $declare_tag_position = $key;
            }

            if (! is_null($declare_tag_position) && is_null($strict_type_position) && is_array($token) && $token[0] === T_STRING && $token[1] === 'strict_types') {
                $strict_type_position = $key;
            }

            if (! is_null($declare_tag_position) && ! is_null($strict_type_position) && $token === ';') {
                $addition_position = $key + 1;
                break;
            }
        }

        return new static(array_merge(
            array_slice($this->tokens, 0, $addition_position),
            token_get_all($addition),
            array_slice($this->tokens, $addition_position),
        ));
    }

    public function has_namespace(): bool
    {
        return has($this->tokens, fn ($token) => is_array($token) && $token[0] === T_NAMESPACE);
    }

    public function has_strict_type_declaration(): bool
    {
        $whitespace_stripped = $this->ignore(fn (string|array $token) => is_array($token) && $token[0] === T_WHITESPACE);

        return has($whitespace_stripped->tokens, fn (string|array $token) => is_array($token) && $token[0] === T_STRING && $token[1] === 'strict_types');
    }

    public function ignore(Closure $filter, ?bool $initial = false): static
    {
        $tokens = [];

        foreach ($this->tokens as $token) {
            $initial = $filter($token, $initial);
            if (! $initial) {
                $tokens[] = $token;
            }
        }

        return new static($tokens);
    }

    public function ignore_any_string(): static
    {
        return $this->ignore(function ($token) {
            if (is_string($token)) {
                return false;
            }

            return in_array($token[0], [T_INLINE_HTML, T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE]);
        });
    }

    public function ignore_imports(): static
    {
        return $this->ignore(function ($token, $initial) {
            if (is_string($token)) {
                return $initial && !($token === ';');
            }

            return $initial || $token[0] === T_USE;
        });
    }

    public function ignore_class_signature(): static
    {
        return $this->ignore(function ($token, $initial) {
            if (is_string($token)) {
                return $initial && !($token === '{');
            }

            return $initial || in_array($token[0], $this->class_definition_tokens());
        });
    }

    public function ignore_namespace(): static
    {
        return $this->ignore(function ($token, $initial) {
            if (is_string($token)) {
                return $initial && !($token === ';');
            }

            return $initial || $token[0] === T_NAMESPACE;
        });
    }

    public function imports(): array
    {
        $tokens = [];
        $is_import = false;

        foreach ($this->ignore_any_string()->tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], $this->class_definition_tokens())) {
                    break;
                }

                $is_import = $is_import || $token[0] === T_USE;
            }

            if ($is_import) {
                $tokens[] = $token;
                $is_import = !($token === ';');
            }
        }

        $content = (new static($tokens))->code();

        $lines = array_map(fn ($line) => trim($line), explode(';', $content));
        $lines = array_filter($lines, fn ($line) => str_starts_with($line, 'use '));

        $imports = ['constants' => [], 'functions' => [], 'classes' => []];

        $group_breaker = function (string $line) {
            $imports = [];
            $result = [];

            if (Str\starts_with_regex($line, '\W?use\s+\(')) {
                return [];
            }

            $delimiter = str_starts_with($line, 'use const ') ? 'use const '
                : (str_starts_with($line, 'use function ') ? 'use function ' : 'use ');

            $line = str_replace($delimiter, '', $line);

            while (str_contains($line, '{')) {
                $grouped = Str\between($line, '{', '}');
                $base = Str\before_first_occurrence($line, '{' . $grouped . '}');
                $line = trim(Str\replace_first_occurrence($line, $base . '{' . $grouped . '}', ''));
                $line = str_starts_with($line, ',') ? trim(Str\after_first_occurrence($line, ',')) : $line;

                $comma_separated = explode(',', $grouped);

                foreach ($comma_separated as $import) {
                    $imports[] = $delimiter . trim($base) . trim($import);
                }
            }

            while (str_contains($line, ',')) {
                $combined = explode(',', $line);

                foreach ($combined as $import) {
                    $line = trim(Str\replace_first_occurrence($line, $import . ',', ''));
                    $imports[] = $delimiter . trim($import);
                }
            }

            $imports[] = trim($delimiter . $line);

            $imports = array_filter($imports, fn ($import) => strlen($import) > strlen($delimiter));

            array_walk($imports, function ($import) use (&$result, $delimiter) {
                if (str_contains($import, ' as ')) {
                    [$import, $alias] = explode(' as ', $import);
                    $result[trim($import)] = trim($alias);
                } else {
                    $result[$import] = str_contains($import, '\\')
                        ? Str\after_last_occurrence($import, '\\')
                        : Str\replace_first_occurrence($import, $delimiter, '');
                }
            });

            return $result;
        };

        foreach ($lines as $code_line) {
            $code_line = trim($code_line);

            $items = $group_breaker($code_line);

            array_walk($items, function ($alias, $import) use (&$imports) {
                if (str_starts_with($import, 'use const ')) {
                    $imports['constants'][preg_replace('/use\s+const\s+/', '', $import)] = $alias;
                } else if (str_starts_with($import, 'use function ')) {
                    $imports['functions'][preg_replace('/use\s+function\s+/', '', $import)] = $alias;
                } else {
                    $imports['classes'][preg_replace('/use\s+/', '', $import)] = $alias;
                }
            });
        }

        return $imports;
    }

    public function used_functions(string $alias): array
    {
        $code = $this->ignore_any_string()->ignore_namespace()->ignore_imports()->ignore_class_signature()->code();

        preg_match_all("/$alias(\\\\\w+)+\W/", $code, $usages, PREG_OFFSET_CAPTURE);
        $used_functions = [];
        array_walk($usages[0], function ($used_function) use ($code, &$used_functions, $alias) {
            $used_function = trim($used_function[0]);
            if (
                str_ends_with($used_function, '(')
                && ! str_contains($code, 'new ' . $used_function)
            ) {
                $usage = Str\remove_last_character($used_function);
                $used_functions[] = Str\replace_first_occurrence($usage, $alias . '\\', '');
            }
        });

        return $used_functions;
    }

    public function used_constants(string $alias): array
    {
        $code = $this->ignore_any_string()->ignore_namespace()->ignore_imports()->ignore_class_signature()->code();

        preg_match_all("/\W$alias(\\\\\w+)+\W/", $code, $usages, PREG_OFFSET_CAPTURE);
        $used_constants = [];
        array_walk($usages[0], function ($used_constant) use (&$used_constants, $alias) {
            $used_constant = $used_constant[0];

            if (! in_array(Str\last_character($used_constant), ['(', ':'])) {
                $usage = Str\remove_first_character($used_constant);
                $usage = Str\remove_last_character($usage);
                $used_constants[] = Str\replace_first_occurrence($usage, $alias . '\\', '');
            }
        });

        return $used_constants;
    }

    public function add_autoloads(string $autoloads): static
    {
        if ($this->has_namespace()) {
            return $this->add_after_namespace($autoloads);
        }

        if ($this->has_strict_type_declaration()) {
            return $this->add_after_strict_type_declaration($autoloads);
        }

        return $this->add_after_opening_tag($autoloads);
    }

    private function class_definition_tokens(): array
    {
        $tokens = [T_CLASS, T_TRAIT, T_INTERFACE, T_FINAL, T_ABSTRACT];

        if (defined('T_ENUM')) {
            $tokens[] = T_ENUM;
        }

        return $tokens;
    }
}
