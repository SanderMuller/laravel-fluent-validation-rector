<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;

class CustomRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new \App\Rules\NoSwearWords()],
            'code' => ['required', new \App\Rules\ValidCode(), 'max:50'],
            'tags' => ['required', 'array', new \App\Rules\ValidTags(), new \App\Rules\MaxTags(10)],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use App\Rules\NoSwearWords;
use App\Rules\ValidCode;
use App\Rules\ValidTags;
use App\Rules\MaxTags;
use Illuminate\Foundation\Http\FormRequest;

class CustomRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->rule(new NoSwearWords()),
            'code' => FluentRule::field()->required()->rule(new ValidCode())->rule('max:50'),
            'tags' => FluentRule::array()->required()->rule(new ValidTags())->rule(new MaxTags(10)),
        ];
    }
}

?>
