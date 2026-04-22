<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class SkipCombinedAllSixCategories
{
    /**
     * Single-class coverage of all six skip taxonomy categories.
     * Chain must stay bit-identical — all six predicates reject the
     * rewrite and emit skip-log entries (except the pre-existing-user-bug
     * variants which are silently left alone per spec §3).
     */
    public function rules(): array
    {
        return [
            // Category 1: variadic-trailing method.
            'a' => FluentRule::string()->requiredWith('b', 'c')->messageFor('required_with', 'Need one.'),

            // Category 2: composite method.
            'count' => FluentRule::numeric()->digitsBetween(1, 5)->messageFor('digits_between', 'Bad digits range.'),

            // Category 3: mode-modifier method.
            'email_strict' => FluentRule::email()->strict()->messageFor('strict', 'Must be strict.'),

            // Category 4: deferred-key factory (bare message() collapse).
            'starts' => FluentRule::date()->message('Bad date.'),

            // Category 5: L11/L12-divergent factory (bare message() AND rule-object form).
            'pw_factory' => FluentRule::password()->message('Bad password.'),
            'pw_object' => FluentRule::string()->rule(new Password(8))->messageFor('password', 'Strong password please.'),

            // Category 6: no-implicit-constraint factory.
            'custom' => FluentRule::field()->message('Bad field.'),
        ];
    }
}

?>
