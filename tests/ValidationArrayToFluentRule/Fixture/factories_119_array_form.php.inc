<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class Factories119ArrayForm extends FormRequest
{
    public function rules(): array
    {
        return [
            // Array form: standalone tokens map to factory; sibling-token
            // promotion fires here too via the shared
            // ConvertsValidationRuleStrings concern (the array converter
            // routes each item through the string-conversion path).
            'addr' => ['string', 'ipv4', 'required'],
            'tags' => ['array', 'list'],
            'declined' => ['declined'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class Factories119ArrayForm extends FormRequest
{
    public function rules(): array
    {
        return [
            // Array form: standalone tokens map to factory; sibling-token
            // promotion fires here too via the shared
            // ConvertsValidationRuleStrings concern (the array converter
            // routes each item through the string-conversion path).
            'addr' => FluentRule::ipv4()->required(),
            'tags' => FluentRule::list(),
            'declined' => FluentRule::declined(),
        ];
    }
}

?>
