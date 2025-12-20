<?php

namespace PhpRepos\SimpleCSP\SATs;

use PhpRepos\SimpleCSP\SAT;

/**
 * Solves a Boolean Satisfiability Problem and returns all satisfying assignments.
 *
 * This function finds all assignments of truth values to variables that satisfy
 * all clauses in the SAT problem. It uses backtracking to explore all possible
 * assignments systematically.
 *
 * The algorithm:
 * 1. Tries assigning true to the next unassigned variable
 * 2. Recursively continues with the next variable
 * 3. When all variables are assigned, checks if all clauses are satisfied
 * 4. Backtracks and tries false for the variable
 * 5. Continues until all possibilities are explored
 *
 * @param SAT $sat The SAT instance to solve. Must have variables and clauses properly defined.
 * @return array<int, array<int, array{variable: mixed, value: bool}>> Array of all satisfying solutions.
 *                                                                     Each solution is an associative array
 *                                                                     mapping variable indices (1-based) to arrays
 *                                                                     containing 'variable' (the variable object)
 *                                                                     and 'value' (true or false).
 *                                                                     Solutions are indexed sequentially starting from 0.
 *
 * @example
 * ```php
 * $sat = new MySAT();
 * $solutions = solve($sat);
 * foreach ($solutions as $solution) {
 *     foreach ($solution as $var_index => $assignment) {
 *         echo "Variable: {$assignment['variable']}, Value: " . ($assignment['value'] ? 'true' : 'false') . "\n";
 *     }
 * }
 * ```
 */
function solve(SAT $sat): array
{
    $solutions = [];
    $assignment = [];

    $backtrack_all = function() use (&$backtrack_all, $sat, &$assignment, &$solutions): void {
        if (count($assignment) === count($sat->variables)) {
            // Check if assignment satisfies all clauses
            $satisfies = true;
            foreach ($sat->clauses as $clause) {
                $clause_satisfied = false;
                foreach ($clause as $literal) {
                    $var_index = abs($literal); // 1-based variable index
                    $value = $literal > 0;
                    if (isset($assignment[$var_index]) && $assignment[$var_index] === $value) {
                        $clause_satisfied = true;
                        break;
                    }
                }
                if (!$clause_satisfied) {
                    $satisfies = false;
                    break;
                }
            }

            if ($satisfies) {
                $solution = [];
                foreach ($assignment as $var_index => $value) {
                    // Convert 1-based variable index to 0-based array index
                    $variable = $sat->variables[$var_index - 1];
                    $solution[$var_index] = ['variable' => $variable, 'value' => $value];
                }
                $solutions[] = $solution;
            }
            return;
        }

        $next_var_index = null;
        $index = 1;
        foreach ($sat->variables as $var) {
            if (!isset($assignment[$index])) {
                $next_var_index = $index;
                break;
            }
            $index++;
        }

        if ($next_var_index === null) {
            return;
        }

        // Try true
        $assignment[$next_var_index] = true;
        $backtrack_all();

        // Try false
        $assignment[$next_var_index] = false;
        $backtrack_all();

        unset($assignment[$next_var_index]);
    };

    $backtrack_all();
    return $solutions;
}

/**
 * Finds the assignment that maximizes the evaluation function.
 *
 * This function solves the SAT problem and returns the solution with the highest
 * evaluation score. If multiple solutions have the same maximum score, one of them
 * is returned. If no satisfying solutions exist, null is returned.
 *
 * @param SAT $sat The SAT instance to solve. Must have variables, clauses, and
 *                evaluate() method properly defined.
 * @return array<int, array{variable: mixed, value: bool}>|null The optimal solution that maximizes the evaluation function.
 *                                                              Format: [var_index => ['variable' => mixed, 'value' => bool]]
 *                                                              where var_index is 1-based. Returns null if no solutions exist.
 *
 * @example
 * ```php
 * $sat = new MySAT();
 * $optimal = max($sat);
 * if ($optimal !== null) {
 *     foreach ($optimal as $var_index => $assignment) {
 *         echo "Variable: {$assignment['variable']}, Value: " . ($assignment['value'] ? 'true' : 'false') . "\n";
 *     }
 * }
 * ```
 */
function max(SAT $sat): ?array
{
    $solutions = solve($sat);

    if (empty($solutions)) {
        return null;
    }

    $best_solution = null;
    $best_value = -INF;

    foreach ($solutions as $solution) {
        $value = $sat->evaluate($solution);
        if ($value > $best_value) {
            $best_value = $value;
            $best_solution = $solution;
        }
    }

    return $best_solution;
}

/**
 * Finds the assignment that minimizes the evaluation function.
 *
 * This function solves the SAT problem and returns the solution with the lowest
 * evaluation score. If multiple solutions have the same minimum score, one of them
 * is returned. If no satisfying solutions exist, null is returned.
 *
 * @param SAT $sat The SAT instance to solve. Must have variables, clauses, and
 *                evaluate() method properly defined.
 * @return array<int, array{variable: mixed, value: bool}>|null The optimal solution that minimizes the evaluation function.
 *                                                              Format: [var_index => ['variable' => mixed, 'value' => bool]]
 *                                                              where var_index is 1-based. Returns null if no solutions exist.
 *
 * @example
 * ```php
 * $sat = new MySAT();
 * $minimal = min($sat);
 * if ($minimal !== null) {
 *     foreach ($minimal as $var_index => $assignment) {
 *         echo "Variable: {$assignment['variable']}, Value: " . ($assignment['value'] ? 'true' : 'false') . "\n";
 *     }
 * }
 * ```
 */
function min(SAT $sat): ?array
{
    $solutions = solve($sat);

    if (empty($solutions)) {
        return null;
    }

    $best_solution = null;
    $best_value = INF;

    foreach ($solutions as $solution) {
        $value = $sat->evaluate($solution);
        if ($value < $best_value) {
            $best_value = $value;
            $best_solution = $solution;
        }
    }

    return $best_solution;
}
