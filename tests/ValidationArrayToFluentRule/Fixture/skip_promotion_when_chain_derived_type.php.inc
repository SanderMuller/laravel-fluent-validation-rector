<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkipPromotionWhenChainDerivedType extends FormRequest
{
    public function rules(): array
    {
        // Codex regression pin: when the type is detected from a
        // Rule:: factory chain (or a Password / Email rule object),
        // the chain ops at the type-index slot get applied in Pass 2
        // — superseding that index via sibling-token promotion would
        // silently drop the chain ops (`alpha()` here).
        // Promotion only fires when typeKind === 'string_token'.
        return [
            'addr' => [Rule::string()->alpha(), 'ipv4'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkipPromotionWhenChainDerivedType extends FormRequest
{
    public function rules(): array
    {
        // Codex regression pin: when the type is detected from a
        // Rule:: factory chain (or a Password / Email rule object),
        // the chain ops at the type-index slot get applied in Pass 2
        // — superseding that index via sibling-token promotion would
        // silently drop the chain ops (`alpha()` here).
        // Promotion only fires when typeKind === 'string_token'.
        return [
            'addr' => FluentRule::string()->alpha()->ipv4(),
        ];
    }
}

?>
