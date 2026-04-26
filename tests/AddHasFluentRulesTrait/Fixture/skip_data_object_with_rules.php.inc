<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Spatie\LaravelData\Data;

// Spatie Laravel Data DataObject with its own `rules()` method (Data
// has its own validation pipeline that calls `rules()` directly,
// independent of FormRequest's `createDefaultValidator()` hook).
// Hihaho dogfood (2026-04-26) found a `DataObjects\Video\Action\Action`
// got the trait inserted. The class extends `Data`, not FormRequest,
// so the trait override is dead code.
abstract class Action extends Data
{
    public static function rules(): array
    {
        return [
            'type' => FluentRule::string()->required(),
            'payload' => FluentRule::array()->required(),
        ];
    }
}

?>
