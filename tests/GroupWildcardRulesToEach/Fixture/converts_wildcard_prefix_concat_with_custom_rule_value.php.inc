<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 fold preserves the value `Expr` verbatim — not just simple
// FluentRule chains. Custom rule objects in the value position
// (`->rule(new ColorRule())`-shape) round-trip cleanly through the
// fold's children([...]) emit. Pins "value AST is preserved
// verbatim regardless of internal shape" as a contract.
final class InlineCustomRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // body unimportant for the rector's perspective
    }
}

final class WildcardPrefixCustomRuleRequest extends FormRequest
{
    public const string COLOR = 'color';

    public const string PRIORITY = 'priority';

    public function rules(): array
    {
        return [
            '*.' . self::COLOR => FluentRule::string()->required()->rule(new InlineCustomRule()),
            '*.' . self::PRIORITY => FluentRule::integer()->min(0),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 fold preserves the value `Expr` verbatim — not just simple
// FluentRule chains. Custom rule objects in the value position
// (`->rule(new ColorRule())`-shape) round-trip cleanly through the
// fold's children([...]) emit. Pins "value AST is preserved
// verbatim regardless of internal shape" as a contract.
final class InlineCustomRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // body unimportant for the rector's perspective
    }
}

final class WildcardPrefixCustomRuleRequest extends FormRequest
{
    public const string COLOR = 'color';

    public const string PRIORITY = 'priority';

    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            self::COLOR => FluentRule::string()->required()->rule(new InlineCustomRule()),
            self::PRIORITY => FluentRule::integer()->min(0),
        ])];
    }
}

?>
