<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source;

use Override;
use SanderMuller\FluentValidation\FluentRule;

/**
 * Middle of a chain whose ROOT (StringRulesBaseRequest) does NOT convert — its
 * rules() is string-only. This level calls parent::rules(), so it converts only
 * if the root does, and the root doesn't. The transitive walk must climb from a
 * child to this level, then up to the root, find the root non-converting, and
 * bail the whole chain.
 */
class ChainMiddleOnStringRootRequest extends StringRulesBaseRequest
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'body' => FluentRule::string()->required(),
        ]);
    }
}
