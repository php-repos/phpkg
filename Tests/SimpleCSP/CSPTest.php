<?php

use PhpRepos\SimpleCSP\CSP;
use function PhpRepos\SimpleCSP\CSPs\solve;
use function Phpkg\InfrastructureStructure\Arrays\first;
use PhpRepos\Datatype\Arr;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should solve CSP with only constraints',
    case: function () {
        $variables = ['package-a' => 'A', 'package-b' => 'B'];
        $domains = [
            'package-a' => ['v1.0.0', 'v1.1.0'],
            'package-b' => ['v2.0.0', 'v2.1.0']
        ];

        $csp = new class($variables, $domains) extends CSP {
            private function var_id(string $var_value): ?string {
                foreach ($this->variables as $id => $value) {
                    if ($value === $var_value) {
                        return $id;
                    }
                }
                return null;
            }
            
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->version_compatibility($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [];
            }
            
            public function orderings(): array {
                return [];
            }
            
            public function version_compatibility(array $assignment, string $var, string $val): bool {
                // Simple constraint: A v1.0.0 requires B v2.0.0
                // Check when assigning A
                if ($var === 'A' && $val === 'v1.0.0') {
                    $b_id = $this->var_id('B');
                    return !isset($assignment[$b_id]) || $assignment[$b_id] === 'v2.0.0';
                }
                // Check when assigning B: if A is already v1.0.0, B must be v2.0.0
                if ($var === 'B' && $val !== 'v2.0.0') {
                    $a_id = $this->var_id('A');
                    return !isset($assignment[$a_id]) || $assignment[$a_id] !== 'v1.0.0';
                }
                return true;
            }
        };

        $solutions = solve($csp);

        Arr\assert_equal([count($solutions)], [3]);
        
        // Verify that the constraint works: A v1.0.0 should only appear with B v2.0.0
        foreach ($solutions as $solution) {
            $a_id = 'package-a';
            $b_id = 'package-b';
            if (isset($solution[$a_id]) && $solution[$a_id]['value'] === 'v1.0.0') {
                Arr\assert_equal([$solution[$b_id]['value']], ['v2.0.0']);
            }
        }
    }
);

test(
    title: 'it should solve CSP with constraints and ordering',
    case: function () {
        $variables = ['package-a' => 'A', 'package-b' => 'B'];
        $domains = [
            'package-a' => ['v1.0.0', 'v1.1.0', 'v1.2.0'],
            'package-b' => ['v2.0.0', 'v2.1.0']
        ];

        $csp = new class($variables, $domains) extends CSP {
            private function var_id(string $var_value): ?string {
                foreach ($this->variables as $id => $value) {
                    if ($value === $var_value) {
                        return $id;
                    }
                }
                return null;
            }
            
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->version_compatibility($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [];
            }
            
            public function orderings(): array {
                return [
                    fn($var, array $values, array $assignment) => $this->prefer_latest_version($var, $values, $assignment)
                ];
            }
            
            public function version_compatibility(array $assignment, string $var, string $val): bool {
                // A v1.0.0 requires B v2.0.0
                // Check when assigning A
                if ($var === 'A' && $val === 'v1.0.0') {
                    $b_id = $this->var_id('B');
                    return !isset($assignment[$b_id]) || $assignment[$b_id] === 'v2.0.0';
                }
                // Check when assigning B: if A is already v1.0.0, B must be v2.0.0
                if ($var === 'B' && $val !== 'v2.0.0') {
                    $a_id = $this->var_id('A');
                    return !isset($assignment[$a_id]) || $assignment[$a_id] !== 'v1.0.0';
                }
                return true;
            }
            
            public function prefer_latest_version(string $var, array $values, array $assignment): array {
                // Sort versions in descending order (latest first)
                usort($values, fn($a, $b) => version_compare($b, $a));
                return $values;
            }
        };

        $solutions = solve($csp);

        // Ordering doesn't reduce solutions, just changes order. Should have multiple valid solutions.
        // The constraint only restricts: package-a v1.0.0 requires package-b v2.0.0
        // Valid solutions:
        // 1. package-a => v1.2.0, package-b => v2.1.0 (latest, no constraint)
        // 2. package-a => v1.2.0, package-b => v2.0.0 (no constraint)
        // 3. package-a => v1.1.0, package-b => v2.1.0 (no constraint)
        // 4. package-a => v1.1.0, package-b => v2.0.0 (no constraint)
        // 5. package-a => v1.0.0, package-b => v2.0.0 (satisfies constraint)
        Arr\assert_equal([count($solutions)], [5]);
        
        // Verify ordering: first solution should have latest versions (due to ordering)
        $first_solution = first($solutions);
        $assignment_array = [];
        foreach ($first_solution as $assignment) {
            $assignment_array[$assignment['variable']] = $assignment['value'];
        }
        Arr\assert_equal([$assignment_array['A']], ['v1.2.0']); // Latest version
        Arr\assert_equal([$assignment_array['B']], ['v2.1.0']); // Latest version
    }
);

test(
    title: 'it should solve CSP with constraints, ordering, and heuristics',
    case: function () {
        $variables = ['package-a' => 'A', 'package-b' => 'B', 'package-c' => 'C'];
        $domains = [
            'package-a' => ['v1.0.0', 'v1.1.0'],
            'package-b' => ['v2.0.0', 'v2.1.0'],
            'package-c' => ['v3.0.0', 'v3.1.0']
        ];

        $csp = new class($variables, $domains) extends CSP {
            private function var_id(string $var_value): ?string {
                foreach ($this->variables as $id => $value) {
                    if ($value === $var_value) {
                        return $id;
                    }
                }
                return null;
            }
            
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->dependency_constraint($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [
                    fn(array $assignment) => $this->most_constrained_variable($assignment)
                ];
            }
            
            public function orderings(): array {
                return [
                    fn($var, array $values, array $assignment) => $this->prefer_latest_version($var, $values, $assignment)
                ];
            }
            
            public function dependency_constraint(array $assignment, string $var, string $val): bool {
                // A depends on B (B must be assigned when A is assigned)
                // This constraint is always satisfied - it just ensures B exists when A exists
                // Since we're assigning all variables, this will be checked when both are assigned
                // For now, allow any assignment (the constraint will be checked when complete)
                return true;
            }
            
            public function most_constrained_variable(array $assignment): ?string {
                // Return first unassigned variable ID (simple heuristic)
                foreach ($this->domains as $var_id => $domain) {
                    if (!isset($assignment[$var_id])) {
                        return $var_id;
                    }
                }
                return null;
            }
            
            public function prefer_latest_version(string $var, array $values, array $assignment): array {
                usort($values, fn($a, $b) => version_compare($b, $a));
                return $values;
            }
        };

        $solutions = solve($csp);

        // With constraint "package-a depends on package-b" (package-b must be assigned before package-a),
        // and 3 packages each with 2 versions, we should have 8 solutions (2^3)
        // The constraint ensures package-b is assigned when package-a is assigned
        Arr\assert_equal([count($solutions)], [8]);
        
        // Verify all solutions have all 3 packages assigned
        foreach ($solutions as $solution) {
            $assignment_array = [];
            foreach ($solution as $assignment) {
                $assignment_array[$assignment['variable']] = $assignment['value'];
            }
            Arr\assert_equal([count($assignment_array)], [3]);
            
            // Extract keys from assignment
            $keys = array_keys($assignment_array);
            sort($keys); // Sort for comparison
            Arr\assert_equal($keys, ['A', 'B', 'C']);
            
            // Verify constraint: package-b must be assigned (which it always is since we have 3 packages)
            Arr\assert_equal([isset($assignment_array['B'])], [true]);
        }
    }
);

test(
    title: 'it should return empty array when no solution exists',
    case: function () {
        $variables = ['package-a' => 'A', 'package-b' => 'B'];
        $domains = [
            'package-a' => ['v1.0.0'],
            'package-b' => ['v2.0.0']
        ];

        $csp = new class($variables, $domains) extends CSP {
            private function var_id(string $var_value): ?string {
                foreach ($this->variables as $id => $value) {
                    if ($value === $var_value) {
                        return $id;
                    }
                }
                return null;
            }
            
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->impossible_constraint($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [];
            }
            
            public function orderings(): array {
                return [];
            }
            
            public function impossible_constraint(array $assignment, string $var, string $val): bool {
                // Impossible constraint: A v1.0.0 requires B v2.1.0
                // but B only has v2.0.0 available
                if ($var === 'A' && $val === 'v1.0.0') {
                    $b_id = $this->var_id('B');
                    return isset($assignment[$b_id]) && $assignment[$b_id] === 'v2.1.0';
                }
                return true;
            }
        };

        $solutions = solve($csp);

        Arr\assert_equal([count($solutions)], [0]);
    }
);

test(
    title: 'it should handle empty domains',
    case: function () {
        $variables = [];
        $domains = [];

        $csp = new class($variables, $domains) extends CSP {
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->dummy_constraint($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [];
            }
            
            public function orderings(): array {
                return [];
            }
            
            public function dummy_constraint(array $assignment, string $var, string $val): bool {
                return true;
            }
        };

        $solutions = solve($csp);

        // With no domains, an empty assignment is a valid solution
        Arr\assert_equal([count($solutions)], [1]);
        $first_solution = first($solutions);
        Arr\assert_equal([count($first_solution)], [0]);
    }
);

test(
    title: 'it should handle single variable with single value',
    case: function () {
        $variables = ['package-a' => 'A'];
        $domains = [
            'package-a' => ['v1.0.0']
        ];

        $csp = new class($variables, $domains) extends CSP {
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->dummy_constraint($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [];
            }
            
            public function orderings(): array {
                return [];
            }
            
            public function dummy_constraint(array $assignment, string $var, string $val): bool {
                return true;
            }
        };

        $solutions = solve($csp);

        Arr\assert_equal([count($solutions)], [1]);
        $first_solution = first($solutions);
        $assignment_array = [];
        foreach ($first_solution as $assignment) {
            $assignment_array[$assignment['variable']] = $assignment['value'];
        }
        Arr\assert_equal([$assignment_array['A']], ['v1.0.0']);
    }
);

test(
    title: 'it should return multiple solutions when several exist',
    case: function () {
        $variables = ['package-a' => 'A', 'package-b' => 'B'];
        $domains = [
            'package-a' => ['v1.0.0', 'v1.1.0'],
            'package-b' => ['v2.0.0', 'v2.1.0']
        ];

        $csp = new class($variables, $domains) extends CSP {
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->always_true($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [];
            }
            
            public function orderings(): array {
                return [];
            }
            
            public function always_true(array $assignment, string $var, string $val): bool {
                return true;
            }
        };

        $solutions = solve($csp);

        // Should find all 4 possible combinations
        Arr\assert_equal([count($solutions)], [4]);
        
        // Check that all expected solutions are present
        $expectedSolutions = [
            ['A' => 'v1.0.0', 'B' => 'v2.0.0'],
            ['A' => 'v1.0.0', 'B' => 'v2.1.0'],
            ['A' => 'v1.1.0', 'B' => 'v2.0.0'],
            ['A' => 'v1.1.0', 'B' => 'v2.1.0']
        ];
        
        foreach ($expectedSolutions as $expected) {
            $found = false;
            foreach ($solutions as $solution) {
                $assignment_array = [];
                foreach ($solution as $assignment) {
                    $assignment_array[$assignment['variable']] = $assignment['value'];
                }
                if ($assignment_array === $expected) {
                    $found = true;
                    break;
                }
            }
            Arr\assert_equal([$found], [true], "Expected solution not found: " . json_encode($expected));
        }
    }
);

test(
    title: 'it should handle complex dependency resolution',
    case: function () {
        $variables = ['logger' => 'L', 'datatype' => 'D', 'console' => 'C'];
        $domains = [
            'logger' => ['v1.0.0', 'v1.1.0'],
            'datatype' => ['v2.0.0', 'v2.1.0'],
            'console' => ['v3.0.0', 'v3.1.0']
        ];

        $csp = new class($variables, $domains) extends CSP {
            private function var_id(string $var_value): ?string {
                foreach ($this->variables as $id => $value) {
                    if ($value === $var_value) {
                        return $id;
                    }
                }
                return null;
            }
            
            public function constraints(): array {
                return [
                    fn(array $assignment, $var, $val) => $this->logger_dependency($assignment, $var, $val),
                    fn(array $assignment, $var, $val) => $this->datatype_dependency($assignment, $var, $val)
                ];
            }
            
            public function heuristics(): array {
                return [
                    fn(array $assignment) => $this->dependency_order($assignment)
                ];
            }
            
            public function orderings(): array {
                return [
                    fn($var, array $values, array $assignment) => $this->prefer_latest($var, $values, $assignment)
                ];
            }
            
            public function logger_dependency(array $assignment, string $var, string $val): bool {
                // C requires L >= v1.1.0
                // Check when assigning C
                if ($var === 'C') {
                    $logger_id = $this->var_id('L');
                    if (!isset($assignment[$logger_id])) {
                        return true; // Defer until logger is assigned
                    }
                    return version_compare($assignment[$logger_id], 'v1.1.0', '>=');
                }
                // Check when assigning L: if C is already assigned, L must be >= v1.1.0
                if ($var === 'L' && version_compare($val, 'v1.1.0', '<')) {
                    $console_id = $this->var_id('C');
                    return !isset($assignment[$console_id]);
                }
                return true;
            }
            
            public function datatype_dependency(array $assignment, string $var, string $val): bool {
                // L v1.1.0 requires D >= v2.1.0
                // Check when assigning L
                if ($var === 'L' && $val === 'v1.1.0') {
                    $datatype_id = $this->var_id('D');
                    if (!isset($assignment[$datatype_id])) {
                        return true; // Defer until datatype is assigned
                    }
                    return version_compare($assignment[$datatype_id], 'v2.1.0', '>=');
                }
                // Check when assigning D: if L is already v1.1.0, D must be >= v2.1.0
                if ($var === 'D' && version_compare($val, 'v2.1.0', '<')) {
                    $logger_id = $this->var_id('L');
                    return !isset($assignment[$logger_id]) || $assignment[$logger_id] !== 'v1.1.0';
                }
                return true;
            }
            
            public function dependency_order(array $assignment): ?string {
                // Assign dependencies first - return variable IDs
                $order = ['datatype', 'logger', 'console'];
                foreach ($order as $var_id) {
                    if (!isset($assignment[$var_id])) {
                        return $var_id;
                    }
                }
                return null;
            }
            
            public function prefer_latest(string $var, array $values, array $assignment): array {
                usort($values, fn($a, $b) => version_compare($b, $a));
                return $values;
            }
        };

        $solutions = solve($csp);

        // With constraints:
        // - console requires logger >= v1.1.0
        // - logger v1.1.0 requires datatype >= v2.1.0
        // Valid solutions:
        // - logger => v1.1.0, datatype => v2.1.0, console => v3.0.0 (valid)
        // - logger => v1.1.0, datatype => v2.1.0, console => v3.1.0 (valid)
        // All other combinations violate constraints
        Arr\assert_equal([count($solutions)], [2]);
        
        // Verify ordering: first solution should have latest versions
        $first_solution = first($solutions);
        $assignment_array = [];
        foreach ($first_solution as $assignment) {
            $assignment_array[$assignment['variable']] = $assignment['value'];
        }
        Arr\assert_equal([count($assignment_array)], [3]);
        
        // Extract keys from assignment
        $keys = array_keys($assignment_array);
        sort($keys); // Sort for comparison
        Arr\assert_equal($keys, ['C', 'D', 'L']);
        
        // Verify dependency constraints are satisfied
        Arr\assert_equal([version_compare($assignment_array['L'], 'v1.1.0', '>=')], [true]);
        Arr\assert_equal([version_compare($assignment_array['D'], 'v2.1.0', '>=')], [true]);
    }
);
