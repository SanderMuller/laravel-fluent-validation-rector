<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use SanderMuller\FluentValidation\FluentRule;

// Fortify-pattern Action: implements an interface, has its OWN
// `rules()` method (not Laravel's auto-resolved FormRequest one),
// invokes it via `Validator::make($data, $this->rules())`. The widened
// rules-shape detector matched on the internal `rules()` method, then
// the trait rector incorrectly added `HasFluentRules`. The trait
// overrides `createDefaultValidator(ValidationFactory)` — a
// FormRequest-internal hook never invoked on an Action class. Hihaho
// dogfood (2026-04-26) found this on 3 such Action classes.
final class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): void
    {
        Validator::make($input, $this->rules())->validate();
    }

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
