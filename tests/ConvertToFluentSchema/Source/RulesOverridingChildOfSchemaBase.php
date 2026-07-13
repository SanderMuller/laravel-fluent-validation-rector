<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Override;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Extends a base that declares schema() (BaseWithSchemaRequest) but overrides
 * rules() with its own convertible FluentRule body. It only INHERITS schema()
 * — it does not declare one — so the converter renames its rules() to schema()
 * (`Class_::getMethod('schema')` sees no declared schema on this class). A
 * child's parent::rules() must therefore be rewritten to parent::schema().
 */
class RulesOverridingChildOfSchemaBase extends BaseWithSchemaRequest
{
    #[Override]
    public function rules(): RuleSet
    {
        return RuleSet::from([
            'title' => FluentRule::string()->required(),
        ]);
    }
}
