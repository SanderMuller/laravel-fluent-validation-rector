<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FlatWildcardWithoutParentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags.*' => FluentRule::string()->required()->max(50),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FlatWildcardWithoutParentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::array()->each(FluentRule::string()->required()->max(50)),
        ];
    }
}

?>
