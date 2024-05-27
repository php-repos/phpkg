<?php


namespace Phpkg\Comparison;

use Closure;

function first_is_greater_or_equal(Closure $compare): bool
{
    return $compare() >= 0;
}
