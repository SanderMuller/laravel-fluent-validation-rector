<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Mixed-case method name (`Casts`). PHP method lookup is
// case-insensitive — at runtime this IS Eloquent's `casts()`. The
// denylist guard must lowercase the source-cased method name before
// the lookup, otherwise the literal-cased `Casts` slips past and the
// converter rewrites cast declarations as FluentRule chains. Codex
// 2026-04-26 catch.
class SkipCastsMethodMixedCase extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    protected function Casts(): array
    {
        return [
            'active' => 'boolean',
            'meta' => 'array',
        ];
    }
}

?>
