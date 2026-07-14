<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Declares a schema(FluentSchema) builder but does NOT declare rules() — it
 * INHERITS rules() from ConcreteBaseRequest. A child's parent::rules() still
 * resolves (to the inherited rules()) while parent::schema() would bind HERE,
 * so the two diverge: the child must NOT be rewritten (leaving parent::rules()
 * intact is behaviour-preserving), unlike a truly schema()-only base.
 */
class SchemaOverConcreteRulesRequest extends ConcreteBaseRequest
{
    public function schema(FluentSchema $rules): RuleSet
    {
        return RuleSet::from([
            'extra' => $rules->string()->nullable(),
        ]);
    }
}
