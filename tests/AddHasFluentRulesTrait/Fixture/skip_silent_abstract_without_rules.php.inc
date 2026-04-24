<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

// Abstract classes with no rules() method should NOT emit a skip-log entry.
// Previously, every abstract class in the codebase (Events, Exceptions,
// DataObjects, Commands) would produce a skip-log line regardless of whether
// it had any validation surface. Now gated on hasMethod('rules').
abstract class AbstractEventWithoutRules
{
    public function __construct(public readonly int $userId)
    {
    }
}

?>
