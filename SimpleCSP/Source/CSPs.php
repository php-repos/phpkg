<?php

namespace PhpRepos\SimpleCSP\CSPs;

use PhpRepos\SimpleCSP\CSP;

/**
 * Solves a Constraint Satisfaction Problem using backtracking search.
 *
 * This function finds all valid solutions to the CSP by systematically exploring
 * the search space using backtracking. It applies variable ordering heuristics
 * and value ordering functions to optimize the search process.
 *
 * The algorithm:
 * 1. Selects the next unassigned variable using heuristics
 * 2. Orders the possible values for that variable
 * 3. Tries each value and checks constraints
 * 4. Recursively continues with valid assignments
 * 5. Backtracks when no valid values remain
 *
 * @param CSP $csp The CSP instance to solve. Must have variables, domains, constraints,
 *                 heuristics, and orderings properly defined.
 * @return array<int, array<string, array{variable: mixed, value: mixed}>> Array of all possible solutions.
 *                                                                         Each solution is an associative array
 *                                                                         mapping variable IDs (strings) to arrays containing
 *                                                                         'variable' (the variable object) and 'value' (the assigned value).
 *                                                                         Solutions are indexed sequentially starting from 0.
 *
 * @example
 * ```php
 * $csp = new MyCSP($variables, $domains);
 * $solutions = solve($csp);
 * foreach ($solutions as $solution) {
 *     foreach ($solution as $var_id => $assignment) {
 *         echo "Variable: {$assignment['variable']}, Value: {$assignment['value']}\n";
 *     }
 * }
 * ```
 */
function solve(CSP $csp): array
{
    $solutions = [];

    // Internal recursive backtracking function
    $backtrack = function($assignment) use (&$backtrack, $csp, &$solutions): void {
        // Check if all variables are assigned
        if (count($assignment) === count($csp->variables)) {
            $accepted_assignment = [];
            foreach ($assignment as $var_id => $value) {
                $accepted_assignment[$var_id] = ['variable' => $csp->variables[$var_id], 'value' => $value];
            }
            $solutions[] = $accepted_assignment;
            return;
        }

        // Select next variable using heuristics
        $var_id = null;
        foreach ($csp->heuristics() as $heuristic) {
            $var_id = $heuristic($assignment);
            if ($var_id !== null && !isset($assignment[$var_id])) {
                break;
            }
            $var_id = null;
        }

        // Fallback: first unassigned variable
        if ($var_id === null) {
            foreach ($csp->domains as $domain_var_id => $domain) {
                if (!isset($assignment[$domain_var_id])) {
                    $var_id = $domain_var_id;
                    break;
                }
            }
        }

        if ($var_id === null) {
            return;
        }

        // Order values for the selected variable
        $values = $csp->domains[$var_id];
        foreach ($csp->orderings() as $ordering) {
            $values = $ordering($var_id, $values, $assignment);
        }

        // Try each value
        foreach ($values as $val) {
            $next = $assignment + [$var_id => $val];

            // Check consistency with all constraints
            $consistent = true;
            foreach ($csp->constraints() as $constraint) {
                if (!$constraint($next, $csp->variables[$var_id], $val)) {
                    $consistent = false;
                    break;
                }
            }

            if ($consistent) {
                $backtrack($next);
            }
        }
    };

    $backtrack([]);
    return $solutions;
}
