<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Concrete base that already declares schema() alongside rules(). The converter
 * bails such a class (renaming rules() would declare a duplicate schema()), so
 * it keeps rules() at runtime — a child's parent::rules() must be left alone.
 * Reflection sees the pre-existing schema(), so the parent-conversion probe
 * returns false.
 */
class BaseWithSchemaRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            'title' => FluentRule::string()->required(),
        ]);
    }

    public function schema(FluentSchema $rules): RuleSet
    {
        return RuleSet::from([
            'body' => $rules->string()->nullable(),
        ]);
    }
}
