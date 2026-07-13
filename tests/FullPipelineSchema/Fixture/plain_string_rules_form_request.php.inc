<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelineSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelineSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\FluentSchema;

class StoreArticleRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()
                ->required()
                ->max(255),
            'email' => $rules->email()
                ->required(),
        ];
    }
}

?>
