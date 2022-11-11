<?php

namespace Tests\DataType\Collection\ValuesTest;

use Saeghe\Saeghe\DataType\Collection;

test(
    title: 'it should run collection values',
    case: function () {
        $collection = new class([1 => 'foo', 2 => 'bar']) extends Collection {
            public function key_is_valid(mixed $key): bool
            {
                return true;
            }

            public function value_is_valid(mixed $value): bool
            {
                return true;
            }
        };

        assert_true(['foo', 'bar'] === $collection->values());
    }
);
