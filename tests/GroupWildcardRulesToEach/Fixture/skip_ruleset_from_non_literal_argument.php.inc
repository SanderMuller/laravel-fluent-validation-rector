<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 P0 skip-log fixture: RuleSet::from(<non-Array_>) — the
// argument is a method call resolving an array at runtime. The
// rector can't statically determine the shape, so descent bails
// with a descriptive skip-log entry rather than silent-bail.
class RuleSetFromNonLiteralRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from($this->baseRules());
    }

    /** @return array<string, mixed> */
    private function baseRules(): array
    {
        return [];
    }
}

?>
