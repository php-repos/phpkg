<?php

namespace Phpkg\Solution\Parser;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class NodeParser extends NodeVisitorAbstract
{
    public string $namespace = '';
    public array $nodes = [];
    private array $namespace_aliases = [];
    private array $local_constants = [];
    private array $local_functions = [];

    public function enterNode(Node $node): int|Node|null
    {
        // Check for namespace declaration
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name ? $node->name->toString() : '';
        }

        // Handle top-level constant definitions
        if ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                $full_name = $this->namespace ? $this->namespace . '\\' . $const->name->toString() : $const->name->toString();
                $this->local_constants[$full_name] = $const->name->toString();
            }
        }

        // Handle use const statements
        if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_CONSTANT) {
            foreach ($node->uses as $use) {
                if ($use instanceof Node\Stmt\UseUse) {
                    $full_name = $use->name->toString();
                    $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                    $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                    $this->nodes[$full_name] = [
                        'namespace' => $namespace_part,
                        'alias' => $alias,
                        'actual_name' => $full_name,
                        'type' => 'constant'
                    ];
                }
            }
        }
        // Handle grouped use const statements
        elseif ($node instanceof Node\Stmt\GroupUse && $node->type === Node\Stmt\Use_::TYPE_CONSTANT) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $full_name = $prefix . '\\' . $use->name->toString();
                $alias = $use->alias ? $use->alias->toString() : $use->name->toString();
                $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                $this->nodes[$full_name] = [
                    'namespace' => $namespace_part,
                    'alias' => $alias,
                    'actual_name' => $full_name,
                    'type' => 'constant'
                ];
            }
        }
        // Handle use function statements
        elseif ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_FUNCTION) {
            foreach ($node->uses as $use) {
                if ($use instanceof Node\Stmt\UseUse) {
                    $full_name = $use->name->toString();
                    $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                    $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                    $this->nodes[$full_name] = [
                        'namespace' => $namespace_part,
                        'alias' => $alias,
                        'actual_name' => $full_name,
                        'type' => 'function'
                    ];
                }
            }
        }
        // Handle grouped use function statements
        elseif ($node instanceof Node\Stmt\GroupUse && $node->type === Node\Stmt\Use_::TYPE_FUNCTION) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $full_name = $prefix . '\\' . $use->name->toString();
                $alias = $use->alias ? $use->alias->toString() : $use->name->toString();
                $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                $this->nodes[$full_name] = [
                    'namespace' => $namespace_part,
                    'alias' => $alias,
                    'actual_name' => $full_name,
                    'type' => 'function'
                ];
            }
        }
        // Handle use class statements and namespace imports
        elseif ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
            foreach ($node->uses as $use) {
                if ($use instanceof Node\Stmt\UseUse) {
                    $full_name = $use->name->toString();
                    $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                    $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                    $this->nodes[$full_name] = [
                        'namespace' => $namespace_part,
                        'alias' => $alias,
                        'actual_name' => $full_name,
                        'type' => 'class'
                    ];
                    $this->namespace_aliases[$alias] = $full_name;
                }
            }
        }
        // Handle grouped use class statements
        elseif ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $full_name = $prefix . '\\' . $use->name->toString();
                $alias = $use->alias ? $use->alias->toString() : $use->name->toString();
                $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                $this->nodes[$full_name] = [
                    'namespace' => $namespace_part,
                    'alias' => $alias,
                    'actual_name' => $full_name,
                    'type' => 'class'
                ];
                $this->namespace_aliases[$alias] = $full_name;
            }
        }
        // Handle function definitions
        elseif ($node instanceof Node\Stmt\Function_) {
            $name = $this->namespace ? $this->namespace . '\\' . $node->name->toString() : $node->name->toString();
            $this->local_functions[$name] = $node->name->toString();
        }
        // Handle class, trait, and interface definitions (only for extends, implements, and traits)
        elseif ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_ || $node instanceof Node\Stmt\Trait_) {
            // Handle extended classes
            if ($node instanceof Node\Stmt\Class_ && $node->extends) {
                $extends_name = $node->extends->toString();
                if (str_contains($extends_name, '\\')) {
                    $clean_name = ltrim($extends_name, '\\');
                    $namespace_part = strrpos($clean_name, '\\') !== false ? substr($clean_name, 0, strrpos($clean_name, '\\')) : '';
                    $this->nodes[$clean_name] = [
                        'namespace' => $namespace_part,
                        'alias' => substr($clean_name, strrpos($clean_name, '\\') + 1),
                        'actual_name' => $clean_name,
                        'type' => 'class'
                    ];
                }
            }

            // Handle implemented interfaces
            if ($node instanceof Node\Stmt\Class_ && $node->implements) {
                foreach ($node->implements as $interface) {
                    $interface_name = $interface->toString();
                    if (str_contains($interface_name, '\\')) {
                        $clean_name = ltrim($interface_name, '\\');
                        $namespace_part = strrpos($clean_name, '\\') !== false ? substr($clean_name, 0, strrpos($clean_name, '\\')) : '';
                        $this->nodes[$clean_name] = [
                            'namespace' => $namespace_part,
                            'alias' => substr($clean_name, strrpos($clean_name, '\\') + 1),
                            'actual_name' => $clean_name,
                            'type' => 'class'
                        ];
                    }
                }
            }
        }
        // Handle traits used within classes
        elseif ($node instanceof Node\Stmt\TraitUse) {
            foreach ($node->traits as $trait) {
                $trait_name = $trait->toString();
                if (str_contains($trait_name, '\\')) {
                    $clean_name = ltrim($trait_name, '\\');
                    $namespace_part = strrpos($clean_name, '\\') !== false ? substr($clean_name, 0, strrpos($clean_name, '\\')) : '';
                    $this->nodes[$clean_name] = [
                        'namespace' => $namespace_part,
                        'alias' => substr($clean_name, strrpos($clean_name, '\\') + 1),
                        'actual_name' => $clean_name,
                        'type' => 'class'
                    ];
                }
            }
        }
        // Handle inline constant references
        elseif ($node instanceof Node\Expr\ConstFetch) {
            $name = $node->name->toString();
            if (str_contains($name, '\\')) {
                $clean_name = ltrim($name, '\\');
                $parts = explode('\\', $clean_name);
                $first_part = $parts[0];

                if (isset($this->namespace_aliases[$first_part])) {
                    $full_name = $this->namespace_aliases[$first_part] . '\\' . implode('\\', array_slice($parts, 1));
                    if (!isset($this->local_constants[$full_name])) {
                        $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                        $this->nodes[$full_name] = [
                            'namespace' => $namespace_part,
                            'alias' => end($parts),
                            'actual_name' => $full_name,
                            'type' => 'constant'
                        ];
                    }
                } elseif (!isset($this->local_constants[$clean_name])) {
                    $namespace_part = strrpos($clean_name, '\\') !== false ? substr($clean_name, 0, strrpos($clean_name, '\\')) : '';
                    $this->nodes[$clean_name] = [
                        'namespace' => $namespace_part,
                        'alias' => substr($clean_name, strrpos($clean_name, '\\') + 1),
                        'actual_name' => $clean_name,
                        'type' => 'constant'
                    ];
                    if ($namespace_part !== $this->namespace) {
                        $parts = explode('\\', $namespace_part);
                        $this->nodes[$namespace_part] = [
                            'namespace' => strrpos($namespace_part, '\\') !== false ? substr($namespace_part, 0, strrpos($namespace_part, '\\')) : '',
                            'alias' => end($parts),
                            'actual_name' => $namespace_part,
                            'type' => 'class'
                        ];
                    }
                }
            }
        }
        // Handle function calls
        elseif ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = $node->name->toString();
            if (str_contains($name, '\\')) {
                $clean_name = ltrim($name, '\\');
                $parts = explode('\\', $clean_name);
                $first_part = $parts[0];

                if (isset($this->namespace_aliases[$first_part])) {
                    $full_name = $this->namespace_aliases[$first_part] . '\\' . implode('\\', array_slice($parts, 1));
                    if (!isset($this->local_functions[$full_name])) {
                        $namespace_part = strrpos($full_name, '\\') !== false ? substr($full_name, 0, strrpos($full_name, '\\')) : '';
                        $this->nodes[$full_name] = [
                            'namespace' => $namespace_part,
                            'alias' => end($parts),
                            'actual_name' => $full_name,
                            'type' => 'function'
                        ];
                    }
                } elseif (!isset($this->local_functions[$clean_name])) {
                    $namespace_part = strrpos($clean_name, '\\') !== false ? substr($clean_name, 0, strrpos($clean_name, '\\')) : '';
                    $this->nodes[$clean_name] = [
                        'namespace' => $namespace_part,
                        'alias' => substr($clean_name, strrpos($clean_name, '\\') + 1),
                        'actual_name' => $clean_name,
                        'type' => 'function'
                    ];
                    if ($namespace_part !== $this->namespace) {
                        $parts = explode('\\', $namespace_part);
                        $this->nodes[$namespace_part] = [
                            'namespace' => strrpos($namespace_part, '\\') !== false ? substr($namespace_part, 0, strrpos($namespace_part, '\\')) : '',
                            'alias' => end($parts),
                            'actual_name' => $namespace_part,
                            'type' => 'class'
                        ];
                    }
                }
            }
        }
        // Handle class instantiations and static calls
        elseif ($node instanceof Node\Expr\New_ || $node instanceof Node\Expr\StaticCall) {
            if ($node->class instanceof Node\Name) {
                $name = $node->class->toString();
                if (str_contains($name, '\\')) {
                    $clean_name = ltrim($name, '\\');
                    $namespace_part = strrpos($clean_name, '\\') !== false ? substr($clean_name, 0, strrpos($clean_name, '\\')) : '';
                    $this->nodes[$clean_name] = [
                        'namespace' => $namespace_part,
                        'alias' => substr($clean_name, strrpos($clean_name, '\\') + 1),
                        'actual_name' => $clean_name,
                        'type' => 'class'
                    ];
                }
            }
        }
        // Handle class constants
        elseif ($node instanceof Node\Expr\ClassConstFetch) {
            $class = $node->class instanceof Node\Name ? $node->class->toString() : null;
            $const_name = $node->name instanceof Node\Identifier ? $node->name->toString() : '';

            if ($const_name && $class && str_contains($class, '\\')) {
                $clean_class = ltrim($class, '\\');
                $full_name = $clean_class . '::' . $const_name;
                $namespace_part = strrpos($clean_class, '\\') !== false ? substr($clean_class, 0, strrpos($clean_class, '\\')) : '';
                $this->nodes[$full_name] = [
                    'namespace' => $namespace_part,
                    'alias' => $const_name,
                    'actual_name' => $full_name,
                    'type' => 'class'
                ];
            }
        }
        
        return null;
    }
}
