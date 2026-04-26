<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Class-const keys on a method that otherwise looks rule-shaped.
// `STATUS_ACTIVE` resolves to an int at runtime, so the array is
// int-keyed — not a rules array, but a status → message lookup table.
// Auto-detect must ignore `ClassConstFetch` keys (Codex 2026-04-26
// catch). Without that guard, the value `'required|string'` would
// trick the shape check and the converter would rewrite the helper
// table.
class SkipClassConstKeyedMap extends FormRequest
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_PENDING = 2;

    public function rules(): array
    {
        return [];
    }

    public function statusMessages(): array
    {
        return [
            self::STATUS_ACTIVE => 'required|string',
            self::STATUS_PENDING => 'nullable|string',
        ];
    }
}

?>
