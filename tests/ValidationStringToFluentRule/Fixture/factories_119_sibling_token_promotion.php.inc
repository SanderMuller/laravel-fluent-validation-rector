<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class Factories119SiblingTokenPromotion extends FormRequest
{
    public function rules(): array
    {
        return [
            // 'string|ipv4' → ipv4 factory directly (NOT string()->ipv4()).
            // Promotion happens in convertStringToFluentRule before factory
            // build, so the rector emits the final form even when SIMPLIFY
            // isn't in the active set list.
            'addr' => 'string|ipv4|required',
            'mac' => 'required|string|mac_address',
            'tz' => 'string|timezone|nullable',
            // 'array|list' → list factory; sibling modifier 'required' rides through.
            'tags' => 'array|list|required',
            // 'string|max:255|json' — promotion happens regardless of modifier
            // ordering. max(255) attaches to the resolved StringRule receiver.
            'payload' => 'string|max:255|json',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class Factories119SiblingTokenPromotion extends FormRequest
{
    public function rules(): array
    {
        return [
            // 'string|ipv4' → ipv4 factory directly (NOT string()->ipv4()).
            // Promotion happens in convertStringToFluentRule before factory
            // build, so the rector emits the final form even when SIMPLIFY
            // isn't in the active set list.
            'addr' => FluentRule::ipv4()->required(),
            'mac' => FluentRule::macAddress()->required(),
            'tz' => FluentRule::timezone()->nullable(),
            // 'array|list' → list factory; sibling modifier 'required' rides through.
            'tags' => FluentRule::list()->required(),
            // 'string|max:255|json' — promotion happens regardless of modifier
            // ordering. max(255) attaches to the resolved StringRule receiver.
            'payload' => FluentRule::json()->max(255),
        ];
    }
}

?>
