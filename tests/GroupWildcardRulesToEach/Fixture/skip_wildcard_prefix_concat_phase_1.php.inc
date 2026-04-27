<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 Phase 1: parser widening recognizes the
// `'*.' . CONST` shape and emits a coherent skip-log message
// pending the Phase 2 indexer/grouper + Phase 3 emitter that
// produce the `'*' => array()->children([CONST::FOO => …])` fold
// output. Until those phases land, the rule keys stay flat in
// the source and rector emits no transformation. Phase 1 ships
// only the parser branch + this skip-log diagnostic so consumers
// using the new shape get a non-misleading message ("not yet
// folded") rather than the generic "too complex" bail.
final class WildcardPrefixConcatRequest extends FormRequest
{
    public const string TITLE = 'title';

    public const string SORT_ORDER = 'sort_order';

    public function rules(): array
    {
        return [
            '*.' . self::TITLE => FluentRule::string()->required()->max(255),
            '*.' . self::SORT_ORDER => FluentRule::integer()->required()->min(0),
        ];
    }
}

?>
