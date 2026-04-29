<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use SanderMuller\FluentValidation\HasFluentValidation;

// 1.2.0 coverage pin: the direct-trait shape must STILL convert, not
// bail. The class uses `HasFluentValidation` directly AND has a local
// `rules()` method — rector merges the attribute rule into the local
// rules array. No silent-ignore (trait's `getRules()` reads the local
// `rules()`); no parent-data-loss (no parent rules() to clobber).
//
// Pin guards against re-broadening the 1.2.0 compose-conflict bail.
// Codex review on the 1.2.0 candidate caught the original detection
// flagging this shape too — narrowing to ancestor-only use was the
// fix. Without this fixture a future regression would silently re-
// bail this convertible shape.
final class DirectTraitWithLocalRulesComponent
{
    use HasFluentValidation;

    #[Rule(['required', 'string', 'min:10'])]
    public ?string $phoneNumber = null;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'otherField' => ['required', 'string'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Rule;
use SanderMuller\FluentValidation\HasFluentValidation;

// 1.2.0 coverage pin: the direct-trait shape must STILL convert, not
// bail. The class uses `HasFluentValidation` directly AND has a local
// `rules()` method — rector merges the attribute rule into the local
// rules array. No silent-ignore (trait's `getRules()` reads the local
// `rules()`); no parent-data-loss (no parent rules() to clobber).
//
// Pin guards against re-broadening the 1.2.0 compose-conflict bail.
// Codex review on the 1.2.0 candidate caught the original detection
// flagging this shape too — narrowing to ancestor-only use was the
// fix. Without this fixture a future regression would silently re-
// bail this convertible shape.
final class DirectTraitWithLocalRulesComponent
{
    use HasFluentValidation;

    public ?string $phoneNumber = null;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'otherField' => ['required', 'string'], 'phoneNumber' => FluentRule::string()->required()->min(10),
        ];
    }
}

?>
