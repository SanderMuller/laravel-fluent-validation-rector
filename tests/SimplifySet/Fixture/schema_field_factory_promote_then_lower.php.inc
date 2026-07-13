<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifySet\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaFieldFactoryPromoteThenLower
{
    public function schema(FluentSchema $rules): array
    {
        return [
            // The full SIMPLIFY set composes on the FluentSchema receiver:
            // PromoteFieldFactory ($rules->field() → $rules->string()),
            // SimplifyRuleWrappers (->rule() escape hatches → native methods),
            // and SimplifyFluentRule (string()->regex() factory shortcut) — all
            // preserving the `$rules->` receiver, never reverting to FluentRule::.
            'slug' => $rules->field()->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifySet\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaFieldFactoryPromoteThenLower
{
    public function schema(FluentSchema $rules): array
    {
        return [
            // The full SIMPLIFY set composes on the FluentSchema receiver:
            // PromoteFieldFactory ($rules->field() → $rules->string()),
            // SimplifyRuleWrappers (->rule() escape hatches → native methods),
            // and SimplifyFluentRule (string()->regex() factory shortcut) — all
            // preserving the `$rules->` receiver, never reverting to FluentRule::.
            'slug' => $rules->regex('/^[a-z]+$/')
                ->max(61),
        ];
    }
}

?>
