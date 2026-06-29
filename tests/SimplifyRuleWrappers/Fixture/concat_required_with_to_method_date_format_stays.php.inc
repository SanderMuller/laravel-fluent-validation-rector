<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class ConcatRequiredWithToMethodDateFormatStays
{
    private const string START_DATE = 'start_date';

    private const string END_DATE = 'end_date';

    public function rules(): array
    {
        // `->rule('required_with:' . self::END_DATE)` — a Concat payload on the
        // pure-field family. The fluent `requiredWith(...$fields)` rebuilds
        // `'required_with:' . implode(',', $fields)`, so passing the whole
        // post-colon expression as one field arg is provably equivalent.
        //
        // `->rule(Rule::date()->format(...))` stays an escape hatch: rewriting
        // it would require flipping the root factory from string() to date()
        // (a receiver-type change outside this rector) and would change the
        // validated type.
        return [
            self::START_DATE => FluentRule::string()
                ->bail()
                ->nullable()
                ->rule('required_with:' . self::END_DATE)
                ->rule(Rule::date()->format('Y-m-d H:i:s')),
            self::END_DATE => FluentRule::string()
                ->bail()
                ->nullable()
                ->rule('required_with:' . self::START_DATE)
                ->rule(Rule::date()->format('Y-m-d H:i:s')),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class ConcatRequiredWithToMethodDateFormatStays
{
    private const string START_DATE = 'start_date';

    private const string END_DATE = 'end_date';

    public function rules(): array
    {
        // `->rule('required_with:' . self::END_DATE)` — a Concat payload on the
        // pure-field family. The fluent `requiredWith(...$fields)` rebuilds
        // `'required_with:' . implode(',', $fields)`, so passing the whole
        // post-colon expression as one field arg is provably equivalent.
        //
        // `->rule(Rule::date()->format(...))` stays an escape hatch: rewriting
        // it would require flipping the root factory from string() to date()
        // (a receiver-type change outside this rector) and would change the
        // validated type.
        return [
            self::START_DATE => FluentRule::string()
                ->bail()
                ->nullable()
                ->requiredWith(self::END_DATE)
                ->rule(Rule::date()->format('Y-m-d H:i:s')),
            self::END_DATE => FluentRule::string()
                ->bail()
                ->nullable()
                ->requiredWith(self::START_DATE)
                ->rule(Rule::date()->format('Y-m-d H:i:s')),
        ];
    }
}

?>
