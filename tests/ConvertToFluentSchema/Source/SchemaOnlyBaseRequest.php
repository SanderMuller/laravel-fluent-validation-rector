<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

/**
 * A base ALREADY converted to the schema(FluentSchema) builder — it declares
 * schema() and no rules() (an earlier SCHEMA pass renamed rules() away). A child
 * calling parent::rules() must be rewritten to parent::schema(), since
 * parent::rules() no longer resolves on this base. The rules()-declarer probe
 * walk can't see a schema()-only base; nearestAncestorIsSchemaOnlyBuilder() does.
 */
class SchemaOnlyBaseRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): RuleSet
    {
        return RuleSet::from([
            'title' => $rules->string()->required(),
        ]);
    }
}
