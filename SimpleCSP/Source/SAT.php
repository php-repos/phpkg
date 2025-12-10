<?php

namespace PhpRepos\SimpleCSP;

/**
 * Abstract Boolean Satisfiability Problem (SAT) solver.
 *
 * A SAT problem consists of:
 * - Variables: Boolean variables that can be true or false
 * - Clauses: Disjunctions (OR) of literals (variables or their negations)
 * - Evaluation: A function to score/rank different solutions
 *
 * The goal is to find an assignment of truth values to variables that satisfies all clauses,
 * optionally optimizing for the best evaluation score.
 */
abstract class SAT
{
    /**
     * Array of variables in the SAT problem.
     *
     * Each element represents a variable that can be assigned true or false.
     * Variables are typically objects or values that need to be assigned.
     *
     * @var array<mixed>
     */
    public array $variables;

    /**
     * Array of clauses in the SAT problem.
     *
     * Each clause is an array of integers representing literals:
     * - Positive integers represent the variable at that index (1-based)
     * - Negative integers represent the negation of the variable at that index (1-based)
     * - Example: [1, -2, 3] means (var1 OR NOT var2 OR var3)
     *
     * @var array<array<int>>
     */
    public array $clauses;

    /**
     * Evaluates a solution and returns a numeric score.
     *
     * This function is used to rank different solutions when finding optimal assignments.
     * Higher scores are considered better when maximizing, lower scores when minimizing.
     *
     * @param array<int, array{variable: mixed, value: bool}> $solution The solution to evaluate.
     *                                                                  Format: [var_index => ['variable' => mixed, 'value' => bool]]
     *                                                                  where var_index is 1-based and matches the clause indices.
     * @return float The evaluation score for this solution. Higher values indicate better solutions when maximizing.
     */
    abstract public function evaluate(array $solution): float;
}
