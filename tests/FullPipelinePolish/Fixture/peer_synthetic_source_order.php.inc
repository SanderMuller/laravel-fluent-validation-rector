<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// 0.19.1 peer-sourced fixture: hihaho dogfood synthetic
// `Tests\Dogfood\SyntheticReverseOrder` (children-ordering
// invariant pin).
// `.dogfood-baselines/0.19.0-hihaho-acceptance/dryrun-source-order-verified.json`.
//
// Deliberately non-alphabetical input order
// (SORT_ORDER, NAME, COLOR — alphabetical would be COLOR, NAME,
// SORT_ORDER). Catches any future refactor that introduces sorting
// in `applyGroups()` / the children emitter.
final class SyntheticOrderingHost
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public const string SORT_ORDER = 'sort_order';
}

final class PeerSyntheticSourceOrderRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            '*.' . SyntheticOrderingHost::SORT_ORDER => FluentRule::integer()->nullable()->min(0),
            '*.' . SyntheticOrderingHost::NAME => FluentRule::string()->nullable()->max(255),
            '*.' . SyntheticOrderingHost::COLOR => FluentRule::string()->nullable(),
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

// 0.19.1 peer-sourced fixture: hihaho dogfood synthetic
// `Tests\Dogfood\SyntheticReverseOrder` (children-ordering
// invariant pin).
// `.dogfood-baselines/0.19.0-hihaho-acceptance/dryrun-source-order-verified.json`.
//
// Deliberately non-alphabetical input order
// (SORT_ORDER, NAME, COLOR — alphabetical would be COLOR, NAME,
// SORT_ORDER). Catches any future refactor that introduces sorting
// in `applyGroups()` / the children emitter.
final class SyntheticOrderingHost
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public const string SORT_ORDER = 'sort_order';
}

final class PeerSyntheticSourceOrderRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            SyntheticOrderingHost::SORT_ORDER => FluentRule::integer()->nullable()->min(0),
            SyntheticOrderingHost::NAME => FluentRule::string()->nullable()->max(255),
            SyntheticOrderingHost::COLOR => FluentRule::string()->nullable(),
        ])];
    }
}

?>
