<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// The child spreads parent::rules() and then sets its own 'summary' key.
// Folding 'summary.heading' in the parent would let that key replace the
// folded rule, so the heading rule would be lost.
class BaseSummaryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'summary.heading' => FluentRule::string()->required()->max(100),
        ];
    }
}

class StoreSummaryRequest extends BaseSummaryRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'summary' => FluentRule::array()->required(),
        ];
    }
}

?>
