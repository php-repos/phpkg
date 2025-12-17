<?php

namespace PhpRepos\SimpleCSP;

/**
 * Abstract Constraint Satisfaction Problem (CSP) solver.
 *
 * A CSP consists of:
 * - Variables: The entities that need to be assigned values
 * - Domains: The possible values each variable can take
 * - Constraints: Rules that restrict which combinations of values are valid
 *
 * Subclasses must implement:
 * - constraints(): Returns constraint functions that validate assignments
 * - heuristics(): Returns variable selection heuristics for optimization
 * - orderings(): Returns value ordering functions for optimization
 */
abstract class CSP
{
    /**
     * Constructs a new CSP instance.
     *
     * @param array<string, mixed> $variables Associative array mapping variable IDs to variable objects/values.
     *                                        Keys are variable identifiers (strings), values are the actual variable entities.
     * @param array<string, array<mixed>> $domains Associative array mapping variable IDs to their possible values.
     *                                             Keys must match variable IDs from $variables.
     *                                             Values are arrays of possible values for each variable.
     */
    public function __construct(public array $variables, public array $domains)
    {
    }

    /**
     * Returns the constraints for this CSP.
     *
     * Each constraint is a callable that validates whether an assignment is consistent.
     * The callable signature is: (array $assignment, mixed $var, mixed $val): bool
     * - $assignment: Current partial assignment (array of var_id => value)
     * - $var: The variable being assigned (from $this->variables)
     * - $val: The value being assigned to the variable
     * - Returns: true if the assignment is consistent with this constraint, false otherwise
     *
     * @return array<callable> Array of constraint callables that validate assignments.
     *                        Each callable accepts (array $assignment, mixed $var, mixed $val) and returns bool.
     */
    abstract public function constraints(): array;

    /**
     * Returns the variable ordering heuristics for this CSP.
     *
     * Heuristics determine which variable to assign next during backtracking search.
     * The callable signature is: (array $assignment): mixed|null
     * - $assignment: Current partial assignment (array of var_id => value)
     * - Returns: Variable ID (string) to assign next, or null if no preference
     *
     * Heuristics are applied in order until one returns a valid unassigned variable.
     *
     * @return array<callable> Array of heuristic callables for variable selection.
     *                        Each callable accepts (array $assignment) and returns mixed|null (variable ID).
     */
    abstract public function heuristics(): array;

    /**
     * Returns the value ordering functions for this CSP.
     *
     * Ordering functions determine the order in which values are tried for a variable.
     * The callable signature is: (mixed $var_id, array $values, array $assignment): array
     * - $var_id: The variable ID being assigned
     * - $values: Current list of possible values for this variable
     * - $assignment: Current partial assignment (array of var_id => value)
     * - Returns: Reordered array of values to try
     *
     * Ordering functions are applied in sequence, each receiving the output of the previous one.
     *
     * @return array<callable> Array of ordering callables for value selection.
     *                        Each callable accepts (mixed $var_id, array $values, array $assignment) and returns array.
     */
    abstract public function orderings(): array;
}
