<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// 0.19.1 peer-sourced fixture: consumer dogfood synthetic
// `Tests\Dogfood\SyntheticTagRequestPlainArray`
// (synthetic-plain-array dry-run
// truth-file). Bare-array variant — pins the canonical
// `'*' => FluentRule::array()->children([CONST => …])` fold AND
// the post-fold UpdateRulesReturnTypeDocblockRector narrow as a
// regression guard against the model →
// `*.NAME / *.SORT_ORDER` shape the consumer ships.
//
// Const host substituted per peer guidance; this fixture uses a synthetic
// `Sandbox\Tag` shape locally so the fixture is
// self-contained.
final class SyntheticTag
{
    public const string NAME = 'name';

    public const string SORT_ORDER = 'sort_order';
}

final class PeerSyntheticPlainArrayRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            '*.' . SyntheticTag::NAME => FluentRule::string()->nullable()->max(255),
            '*.' . SyntheticTag::SORT_ORDER => FluentRule::integer()->nullable()->min(0),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// 0.19.1 peer-sourced fixture: consumer dogfood synthetic
// `Tests\Dogfood\SyntheticTagRequestPlainArray`
// (synthetic-plain-array dry-run
// truth-file). Bare-array variant — pins the canonical
// `'*' => FluentRule::array()->children([CONST => …])` fold AND
// the post-fold UpdateRulesReturnTypeDocblockRector narrow as a
// regression guard against the model →
// `*.NAME / *.SORT_ORDER` shape the consumer ships.
//
// Const host substituted per peer guidance; this fixture uses a synthetic
// `Sandbox\Tag` shape locally so the fixture is
// self-contained.
final class SyntheticTag
{
    public const string NAME = 'name';

    public const string SORT_ORDER = 'sort_order';
}

final class PeerSyntheticPlainArrayRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            SyntheticTag::NAME => FluentRule::string()->nullable()->max(255),
            SyntheticTag::SORT_ORDER => FluentRule::integer()->nullable()->min(0),
        ])];
    }
}

?>
