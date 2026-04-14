<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipeline\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVideoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags' => ['array', ['max', 10]],
            'tags.*' => ['string', ['max', 128]],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipeline\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;

class UpdateVideoRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'tags' => FluentRule::array()->max(10)->each(FluentRule::string()->max(128)),
        ];
    }
}

?>
