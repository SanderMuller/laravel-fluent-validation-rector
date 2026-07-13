<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Concrete base whose rules() the SCHEMA rule would convert to schema()
 * (concrete, uses HasFluentRules, public rules() with FluentRule chains). Lives
 * under tests/ PSR-4 so a child fixture's `parent::rules()` resolves it via
 * reflection to a real autoloadable class — the rule then proves the parent
 * converts and rewrites the child's call to `parent::schema()`. Returns a
 * RuleSet so children can chain ->merge()/->modify()/->put() off it, the
 * canonical fluent-validation shape.
 */
class ConcreteBaseRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            'title' => FluentRule::string()->required()->max(255),
        ]);
    }
}
