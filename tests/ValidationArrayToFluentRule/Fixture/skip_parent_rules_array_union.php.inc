<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Parent must NOT be converted: the child unions parent::rules() with `+`.
// `+` keeps the left operand's keys the same as array_merge, so a fluentized
// parent would leave the child holding a mixed FluentRule/plain-array result.
// The array-union operator isn't an `array_*()` call, so it needs its own
// detection alongside the existing manipulation ops.
class ArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}

class FeaturedArticleRequest extends ArticleRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'badge' => ['required', 'string'],
        ];
    }
}

?>
