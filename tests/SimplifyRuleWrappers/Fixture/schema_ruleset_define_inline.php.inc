<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;

// The FluentSchema builder is also reachable inline via
// RuleSet::define(fn (FluentSchema $rules) => [...]). Receiver inference
// resolves the seeds inside the closure, so the escape-hatch ->rule()
// hops lower natively there too.
class SchemaRuleSetDefineInline
{
    public function rules(): RuleSet
    {
        return RuleSet::define(fn (FluentSchema $rules): array => [
            'email' => $rules->email()->rule('max:255'),
            'level' => $rules->numeric()->rule('in:1,2,3'),
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;

// The FluentSchema builder is also reachable inline via
// RuleSet::define(fn (FluentSchema $rules) => [...]). Receiver inference
// resolves the seeds inside the closure, so the escape-hatch ->rule()
// hops lower natively there too.
class SchemaRuleSetDefineInline
{
    public function rules(): RuleSet
    {
        return RuleSet::define(fn (FluentSchema $rules): array => [
            'email' => $rules->email()
                ->max(255),
            'level' => $rules->numeric()
                ->in(['1', '2', '3']),
        ]);
    }
}

?>
