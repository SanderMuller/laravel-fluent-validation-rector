<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// Concat key uses a Variable (`$prefix`) — `parseConcatKey` returns null
// because the prefix expression isn't a `ClassConstFetch`. Rector
// silently leaves the array alone (skip-log entry surfaces under
// FLUENT_VALIDATION_RECTOR_VERBOSE=actionable so consumers can see why
// their concat-keyed wildcard rule didn't fold).
class ComplexConcatKeyRequest extends FormRequest
{
    public function rules(): array
    {
        $prefix = 'items';

        return [
            $prefix . '.*.name' => FluentRule::string()->required(),
            $prefix . '.*.email' => FluentRule::email()->required(),
        ];
    }
}

?>
