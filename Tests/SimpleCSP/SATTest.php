<?php

use PhpRepos\SimpleCSP\SAT;
use function PhpRepos\SimpleCSP\SATs\{solve, max, min};
use function PhpRepos\TestRunner\Runner\test;
use function PhpRepos\Datatype\Arr\assert_equal;
use function PhpRepos\TestRunner\Assertions\assert_true;

test(
    title: 'it should solve simple SAT problem with two variables',
    case: function () {
        $A = 'A';
        $B = 'B';
        
        $sat = new class($A, $B) extends SAT {
            public function __construct(private $A, private $B) {
                $this->variables = [$this->A, $this->B];
                // (A OR B) - at least one must be true
                $this->clauses = [
                    [1, 2]  // A OR B
                ];
            }
            
            public function evaluate(array $solution): float {
                $count = 0;
                foreach ($solution as $assignment) {
                    if ($assignment['value'] === true) {
                        $count++;
                    }
                }
                return $count;
            }
        };
        
        $solutions = solve($sat);
        
        // Should find exactly 3 solutions: (A=true, B=true), (A=true, B=false), (A=false, B=true)
        assert_equal($solutions, [
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => false],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
            ],
        ]);
    }
);

test(
    title: 'it should solve SAT problem with three variables and multiple clauses',
    case: function () {
        $A = 'A';
        $B = 'B';
        $C = 'C';
        
        $sat = new class($A, $B, $C) extends SAT {
            public function __construct(private $A, private $B, private $C) {
                $this->variables = [$this->A, $this->B, $this->C];
                // (A OR B) AND (NOT A OR C) AND (B OR NOT C)
                $this->clauses = [
                    [1, 2],      // A OR B
                    [-1, 3],     // NOT A OR C
                    [2, -3]      // B OR NOT C
                ];
            }
            
            public function evaluate(array $solution): float {
                $count = 0;
                foreach ($solution as $assignment) {
                    if ($assignment['value'] === true) {
                        $count++;
                    }
                }
                return $count;
            }
        };
        
        $solutions = solve($sat);
        
        // (A OR B) AND (NOT A OR C) AND (B OR NOT C) has exactly 3 solutions
        assert_equal($solutions, [
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => false],
            ],
        ]);
    }
);

test(
    title: 'it should find assignment with maximum evaluation',
    case: function () {
        $A = 'A';
        $B = 'B';
        
        $sat = new class($A, $B) extends SAT {
            public function __construct(private $A, private $B) {
                $this->variables = [$this->A, $this->B];
                // (A OR B) - at least one must be true
                $this->clauses = [
                    [1, 2]
                ];
            }
            
            public function evaluate(array $solution): float {
                // Prefer more variables to be true
                $count = 0;
                foreach ($solution as $item) {
                    if ($item['value'] === true) {
                        $count++;
                    }
                }
                return $count;
            }
        };
        
        $optimal = max($sat);
        
        // Should maximize evaluation (both variables true = evaluation 2)
        assert_equal($optimal, [
            1 => ['variable' => 'A', 'value' => true],
            2 => ['variable' => 'B', 'value' => true],
        ]);
        assert_equal([$sat->evaluate($optimal)], [2.0], 'Maximum evaluation should be 2');
    }
);

test(
    title: 'it should find assignment with minimum evaluation',
    case: function () {
        $A = 'A';
        $B = 'B';
        
        $sat = new class($A, $B) extends SAT {
            public function __construct(private $A, private $B) {
                $this->variables = [$this->A, $this->B];
                // (A OR B) - at least one must be true
                $this->clauses = [
                    [1, 2]
                ];
            }
            
            public function evaluate(array $solution): float {
                // Count true variables
                $count = 0;
                foreach ($solution as $assignment) {
                    if ($assignment['value'] === true) {
                        $count++;
                    }
                }
                return $count;
            }
        };
        
        $minimal = min($sat);
        
        // Should minimize evaluation (only one variable true = evaluation 1)
        // Either (A=true, B=false) or (A=false, B=true) - both have evaluation 1
        assert_true(
            ($minimal[1]['value'] === true && $minimal[2]['value'] === false) ||
            ($minimal[1]['value'] === false && $minimal[2]['value'] === true),
            'Exactly one variable should be true for minimum evaluation'
        );
        assert_equal([$sat->evaluate($minimal)], [1.0], 'Minimum evaluation should be 1');
    }
);

test(
    title: 'it should handle single variable constraint',
    case: function () {
        $A = 'A';
        
        $sat = new class($A) extends SAT {
            public function __construct(private $A) {
                $this->variables = [$this->A];
                // A must be true
                $this->clauses = [
                    [1]  // A
                ];
            }
            
            public function evaluate(array $solution): float {
                foreach ($solution as $assignment) {
                    return $assignment['value'] === true ? 1.0 : 0.0;
                }
                return 0.0;
            }
        };
        
        $solutions = solve($sat);
        $optimal = max($sat);
        $minimal = min($sat);
        
        // Should find exactly one solution: A = true
        assert_equal($solutions, [
            [
                1 => ['variable' => 'A', 'value' => true],
            ],
        ]);
        
        // Optimal and minimal should also have A = true
        assert_equal($optimal, [
            1 => ['variable' => 'A', 'value' => true],
        ]);
        assert_equal($minimal, [
            1 => ['variable' => 'A', 'value' => true],
        ]);
    }
);

test(
    title: 'it should return empty set when no solutions exist',
    case: function () {
        $A = 'A';
        
        $sat = new class($A) extends SAT {
            public function __construct(private $A) {
                $this->variables = [$this->A];
                // Impossible: (A) AND (NOT A)
                $this->clauses = [
                    [1],   // A
                    [-1]  // NOT A
                ];
            }
            
            public function evaluate(array $solution): float {
                return 0.0;
            }
        };
        
        $solutions = solve($sat);
        $optimal = max($sat);
        $minimal = min($sat);
        
        // Should return empty results for unsatisfiable problem
        assert_equal([count($solutions)], [0]);
        assert_true($optimal === null, 'Optimal should be null for unsatisfiable problem');
        assert_true($minimal === null, 'Minimal should be null for unsatisfiable problem');
    }
);

test(
    title: 'it should handle empty clauses (all assignments are valid)',
    case: function () {
        $A = 'A';
        $B = 'B';
        
        $sat = new class($A, $B) extends SAT {
            public function __construct(private $A, private $B) {
                $this->variables = [$this->A, $this->B];
                // No constraints - all assignments are valid
                $this->clauses = [];
            }
            
            public function evaluate(array $solution): float {
                $count = 0;
                foreach ($solution as $assignment) {
                    if ($assignment['value'] === true) {
                        $count++;
                    }
                }
                return $count;
            }
        };
        
        $solutions = solve($sat);
        $optimal = max($sat);
        $minimal = min($sat);
        
        // Should find all 4 possible assignments: (A=true, B=true), (A=true, B=false), (A=false, B=true), (A=false, B=false)
        assert_equal($solutions, [
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => false],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => false],
            ],
        ]);
        
        // Optimal should have both true (evaluation = 2)
        assert_equal($optimal, [
            1 => ['variable' => 'A', 'value' => true],
            2 => ['variable' => 'B', 'value' => true],
        ]);
        assert_equal([$sat->evaluate($optimal)], [2.0]);
        
        // Minimal should have both false (evaluation = 0)
        assert_equal($minimal, [
            1 => ['variable' => 'A', 'value' => false],
            2 => ['variable' => 'B', 'value' => false],
        ]);
        assert_equal([$sat->evaluate($minimal)], [0.0]);
    }
);

test(
    title: 'it should handle negative literals correctly',
    case: function () {
        $A = 'A';
        $B = 'B';
        
        $sat = new class($A, $B) extends SAT {
            public function __construct(private $A, private $B) {
                $this->variables = [$this->A, $this->B];
                // (NOT A OR B) - if A is true, then B must be true
                $this->clauses = [
                    [-1, 2]  // NOT A OR B
                ];
            }
            
            public function evaluate(array $solution): float {
                $count = 0;
                foreach ($solution as $assignment) {
                    if ($assignment['value'] === true) {
                        $count++;
                    }
                }
                return $count;
            }
        };
        
        $solutions = solve($sat);
        
        // (NOT A OR B) has exactly 3 solutions: (A=true, B=true), (A=false, B=true), (A=false, B=false)
        assert_equal($solutions, [
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => false],
            ],
        ]);
    }
);

test(
    title: 'it should handle complex CNF with four variables',
    case: function () {
        $A = 'A';
        $B = 'B';
        $C = 'C';
        $D = 'D';
        
        $sat = new class($A, $B, $C, $D) extends SAT {
            public function __construct(private $A, private $B, private $C, private $D) {
                $this->variables = [$this->A, $this->B, $this->C, $this->D];
                // (A OR B) AND (C OR D) - multiple valid combinations
                $this->clauses = [
                    [1, 2],  // A OR B
                    [3, 4]   // C OR D
                ];
            }
            
            public function evaluate(array $solution): float {
                $score = 0;
                foreach ($solution as $item) {
                    if ($item['variable'] === $this->A && $item['value'] === true) $score += 2;
                    if ($item['variable'] === $this->B && $item['value'] === true) $score += 1;
                    if ($item['variable'] === $this->C && $item['value'] === true) $score += 2;
                    if ($item['variable'] === $this->D && $item['value'] === true) $score += 1;
                }
                return $score;
            }
        };
        
        $solutions = solve($sat);
        $optimal = max($sat);
        $minimal = min($sat);
        
        // (A OR B) AND (C OR D) has exactly 9 solutions
        assert_equal($solutions, [
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => true],
                4 => ['variable' => 'D', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => true],
                4 => ['variable' => 'D', 'value' => false],
            ],
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => false],
                4 => ['variable' => 'D', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => false],
                3 => ['variable' => 'C', 'value' => true],
                4 => ['variable' => 'D', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => false],
                3 => ['variable' => 'C', 'value' => true],
                4 => ['variable' => 'D', 'value' => false],
            ],
            [
                1 => ['variable' => 'A', 'value' => true],
                2 => ['variable' => 'B', 'value' => false],
                3 => ['variable' => 'C', 'value' => false],
                4 => ['variable' => 'D', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => true],
                4 => ['variable' => 'D', 'value' => true],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => true],
                4 => ['variable' => 'D', 'value' => false],
            ],
            [
                1 => ['variable' => 'A', 'value' => false],
                2 => ['variable' => 'B', 'value' => true],
                3 => ['variable' => 'C', 'value' => false],
                4 => ['variable' => 'D', 'value' => true],
            ],
        ]);
        
        // Optimal should have all variables true (A=2, B=1, C=2, D=1 = 6)
        assert_equal($optimal, [
            1 => ['variable' => 'A', 'value' => true],
            2 => ['variable' => 'B', 'value' => true],
            3 => ['variable' => 'C', 'value' => true],
            4 => ['variable' => 'D', 'value' => true],
        ]);
        assert_equal([$sat->evaluate($optimal)], [6.0], 'Optimal evaluation should be 6 (A=2 + B=1 + C=2 + D=1)');
        
        // Minimal should be B=true, D=true (lowest score = 2)
        assert_equal($minimal, [
            1 => ['variable' => 'A', 'value' => false],
            2 => ['variable' => 'B', 'value' => true],
            3 => ['variable' => 'C', 'value' => false],
            4 => ['variable' => 'D', 'value' => true],
        ]);
        assert_equal([$sat->evaluate($minimal)], [2.0], 'Minimal evaluation should be 2');
    }
);

test(
    title: 'it should handle weighted evaluation correctly',
    case: function () {
        $A = 'A';
        $B = 'B';
        $C = 'C';
        $D = 'D';
        
        $sat = new class($A, $B, $C, $D) extends SAT {
            public function __construct(private $A, private $B, private $C, private $D) {
                $this->variables = [$this->A, $this->B, $this->C, $this->D];
                // (A OR B) AND (C OR D)
                $this->clauses = [
                    [1, 2],  // A OR B
                    [3, 4]   // C OR D
                ];
            }
            
            public function evaluate(array $solution): float {
                // Weighted evaluation: A=3 points, B=1 point, C=2 points, D=1 point
                $score = 0;
                foreach ($solution as $item) {
                    if ($item['variable'] === $this->A && $item['value'] === true) $score += 3;
                    if ($item['variable'] === $this->B && $item['value'] === true) $score += 1;
                    if ($item['variable'] === $this->C && $item['value'] === true) $score += 2;
                    if ($item['variable'] === $this->D && $item['value'] === true) $score += 1;
                }
                return $score;
            }
        };
        
        $solutions = solve($sat);
        $optimal = max($sat);
        $minimal = min($sat);
        
        // (A OR B) AND (C OR D) has exactly 9 solutions (same as previous test)
        assert_equal([count($solutions)], [9]);
        
        // Optimal should have all variables true (A=3, B=1, C=2, D=1 = 7)
        assert_equal($optimal, [
            1 => ['variable' => 'A', 'value' => true],
            2 => ['variable' => 'B', 'value' => true],
            3 => ['variable' => 'C', 'value' => true],
            4 => ['variable' => 'D', 'value' => true],
        ]);
        
        // Minimal should be B=true, D=true (lowest score = 2)
        assert_equal($minimal, [
            1 => ['variable' => 'A', 'value' => false],
            2 => ['variable' => 'B', 'value' => true],
            3 => ['variable' => 'C', 'value' => false],
            4 => ['variable' => 'D', 'value' => true],
        ]);
    }
);
