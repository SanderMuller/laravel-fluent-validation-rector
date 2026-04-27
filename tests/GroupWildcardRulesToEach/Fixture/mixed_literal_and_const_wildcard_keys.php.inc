<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 codex-review regression fixture: a rules array with BOTH
// literal `'*.foo'` keys (existing fold's territory) AND
// `'*.' . CONST` keys (new fold's territory) must NOT produce
// duplicate top-level `'*'` entries. Pre-fix the new fold's case
// (d) check used a stale `$entries` snapshot that didn't see the
// existing fold's synthesized `'*' =>` parent — both folds emitted
// `'*' => …`, last-write-wins silently dropped one branch of
// rules.
//
// Post-fix the new fold's case (d) live-scans `$array->items` for
// any String_('*') key (or self/static const resolving to '*'),
// so the synthesized parent from the existing fold is detected
// and the new fold bails-and-logs. The const-side rules stay flat
// — partial conversion, but no rule loss. Consumers wanting the
// mixed shape folded uniformly should hand-merge into a single
// `'*' => array()->children(['foo' => …, CONST => …])` shape.
final class MixedWildcardKeysRequest extends FormRequest
{
    public const string BAR = 'bar';

    public function rules(): array
    {
        return [
            '*.foo' => FluentRule::string()->required(),
            '*.' . self::BAR => FluentRule::integer()->min(0),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 codex-review regression fixture: a rules array with BOTH
// literal `'*.foo'` keys (existing fold's territory) AND
// `'*.' . CONST` keys (new fold's territory) must NOT produce
// duplicate top-level `'*'` entries. Pre-fix the new fold's case
// (d) check used a stale `$entries` snapshot that didn't see the
// existing fold's synthesized `'*' =>` parent — both folds emitted
// `'*' => …`, last-write-wins silently dropped one branch of
// rules.
//
// Post-fix the new fold's case (d) live-scans `$array->items` for
// any String_('*') key (or self/static const resolving to '*'),
// so the synthesized parent from the existing fold is detected
// and the new fold bails-and-logs. The const-side rules stay flat
// — partial conversion, but no rule loss. Consumers wanting the
// mixed shape folded uniformly should hand-merge into a single
// `'*' => array()->children(['foo' => …, CONST => …])` shape.
final class MixedWildcardKeysRequest extends FormRequest
{
    public const string BAR = 'bar';

    public function rules(): array
    {
        return [
            '*' => FluentRule::array()->children([
                'foo' => FluentRule::string()->required(),
            ]),
            '*.' . self::BAR => FluentRule::integer()->min(0),
        ];
    }
}

?>
