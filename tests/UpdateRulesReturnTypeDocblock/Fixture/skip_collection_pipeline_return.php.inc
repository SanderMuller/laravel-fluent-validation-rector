<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipCollectionPipelineReturn extends FormRequest
{
    public function rules(): array
    {
        return collect(parent::rules())
            ->mergeRecursive([
                'title' => FluentRule::string()->required(),
            ])
            ->all();
    }
}

?>
