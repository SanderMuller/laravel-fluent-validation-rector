<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class StringFormCommaSeparatedToMethods
{
    public function rules(): array
    {
        return [
            // Pure-field family — min arity 1, one or many fields.
            'a' => FluentRule::string()->rule('required_with:other'),
            'b' => FluentRule::string()->rule('required_with_all:first,second,third'),
            'c' => FluentRule::string()->rule('required_without:dependency'),
            'd' => FluentRule::string()->rule('prohibits:conflicting'),
            // Field-values family — min arity 2 (field + value).
            'e' => FluentRule::string()->rule('required_if:type,admin'),
            'f' => FluentRule::string()->rule('exclude_unless:status,active'),
            // ArrayRule-only — gated by per-class method availability.
            'g' => FluentRule::array()->rule('required_array_keys:host,port'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class StringFormCommaSeparatedToMethods
{
    public function rules(): array
    {
        return [
            // Pure-field family — min arity 1, one or many fields.
            'a' => FluentRule::string()
                ->requiredWith('other'),
            'b' => FluentRule::string()
                ->requiredWithAll('first', 'second', 'third'),
            'c' => FluentRule::string()
                ->requiredWithout('dependency'),
            'd' => FluentRule::string()
                ->prohibits('conflicting'),
            // Field-values family — min arity 2 (field + value).
            'e' => FluentRule::string()
                ->requiredIf('type', 'admin'),
            'f' => FluentRule::string()
                ->excludeUnless('status', 'active'),
            // ArrayRule-only — gated by per-class method availability.
            'g' => FluentRule::array()
                ->requiredArrayKeys('host', 'port'),
        ];
    }
}

?>
