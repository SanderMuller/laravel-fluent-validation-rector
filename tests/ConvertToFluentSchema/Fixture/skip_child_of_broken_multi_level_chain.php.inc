<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ChainMiddleOnStringRootRequest;

// 3-level chain whose ROOT does not convert: this child extends
// ChainMiddleOnStringRootRequest, which calls parent::rules() into the
// string-only StringRulesBaseRequest root. The transitive walk climbs child →
// ChainMiddleOnStringRootRequest (defers) → StringRulesBaseRequest (string-only
// → does NOT convert), so the whole chain is non-converting and this child is
// skipped rather than rewritten to a stranded parent::schema().
class SkipChildOfBrokenMultiLevelChain extends ChainMiddleOnStringRootRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => FluentRule::string()->required(),
        ]);
    }
}

?>
