<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// A protected helper of the parent, spread by the child next to its own
// 'meta' key. No parent:: call is involved.
class BaseMetaRequest extends FormRequest
{
    protected function sharedRules(): array
    {
        return [
            'meta.title' => FluentRule::string()->required()->max(60),
        ];
    }
}

class StoreMetaRequest extends BaseMetaRequest
{
    public function rules(): array
    {
        return [
            'meta' => FluentRule::array()->required(),
            ...$this->sharedRules(),
        ];
    }
}

?>
