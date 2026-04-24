<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use App\Validation\DutchPostcodeRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// Constructor-form allowlist entry: `DutchPostcodeRule::class` registered
// as a plain FQN. `new DutchPostcodeRule()` is recognized as allowlisted
// and the whole method silent-skips.

class SkipMixedWithAllowlistedConstructor extends FormRequest
{
    public function rules(): array
    {
        return [
            'city' => FluentRule::string()->required(),
            'zipcode' => new DutchPostcodeRule(),
        ];
    }
}

?>
