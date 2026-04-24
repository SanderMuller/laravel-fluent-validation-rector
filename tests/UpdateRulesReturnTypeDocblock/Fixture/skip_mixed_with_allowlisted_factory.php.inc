<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// `Question::existsRule` is allowlisted (see rector config under
// tests/UpdateRulesReturnTypeDocblock/config/...allowlist.php). Array mixes
// a FluentRule chain with an allowlisted static-factory call; POLISH
// silent-skips — narrowing to FluentRuleContract would lie about the
// allowlisted item's type, but logging would spam (allowlisted entries are
// known escape-hatch use).

class SkipMixedWithAllowlistedFactory extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'question_id' => Question::existsRule(),
        ];
    }
}

?>
