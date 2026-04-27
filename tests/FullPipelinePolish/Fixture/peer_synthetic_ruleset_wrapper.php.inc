<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 peer-sourced fixture: hihaho dogfood synthetic
// `Tests\Dogfood\SyntheticInteractionGroupRequest` (RuleSet::from
// wrapper variant) — the P0 silent-bail reproducer.
// `.dogfood-baselines/0.19.0-hihaho-acceptance/dryrun-synthetic-ruleset-wrapper.json`
// shows pre-0.19.1 `changed: 0` (silent skip, no log).
//
// This fixture is the canonical real-world hihaho FormRequest shape
// (matches `SaveWorkshopRequest`, `UpdateThemeRequest`,
// `AiTaskRequest`): `rules(): RuleSet` returning `RuleSet::from([…])`
// per the laravel-fluent-validation docs. Pins the post-0.19.1
// expected fold *inside* the wrapper — the wrapper itself stays
// intact, only the inner array argument folds.
//
// Const host + ColorRule substitutions per peer guidance: synthetic
// `Sandbox\InteractionGroup` const shape; ColorRule reference dropped
// for minimal fixture (peer-shipped synthetic referenced
// `App\Rules\ColorRule` for richness).
final class SyntheticInteractionGroupRich
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public const string SORT_ORDER = 'sort_order';

    public const string INTERACTIONS = 'interactions';
}

final class PeerSyntheticRuleSetWrapperRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from([
            '*.' . SyntheticInteractionGroupRich::NAME => FluentRule::string()
                ->nullable()
                ->max(255),
            '*.' . SyntheticInteractionGroupRich::COLOR => FluentRule::string()
                ->nullable(),
            '*.' . SyntheticInteractionGroupRich::SORT_ORDER => FluentRule::integer()
                ->nullable()
                ->min(0),
            '*.' . SyntheticInteractionGroupRich::INTERACTIONS => FluentRule::array()
                ->required(),
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 peer-sourced fixture: hihaho dogfood synthetic
// `Tests\Dogfood\SyntheticInteractionGroupRequest` (RuleSet::from
// wrapper variant) — the P0 silent-bail reproducer.
// `.dogfood-baselines/0.19.0-hihaho-acceptance/dryrun-synthetic-ruleset-wrapper.json`
// shows pre-0.19.1 `changed: 0` (silent skip, no log).
//
// This fixture is the canonical real-world hihaho FormRequest shape
// (matches `SaveWorkshopRequest`, `UpdateThemeRequest`,
// `AiTaskRequest`): `rules(): RuleSet` returning `RuleSet::from([…])`
// per the laravel-fluent-validation docs. Pins the post-0.19.1
// expected fold *inside* the wrapper — the wrapper itself stays
// intact, only the inner array argument folds.
//
// Const host + ColorRule substitutions per peer guidance: synthetic
// `Sandbox\InteractionGroup` const shape; ColorRule reference dropped
// for minimal fixture (peer-shipped synthetic referenced
// `App\Rules\ColorRule` for richness).
final class SyntheticInteractionGroupRich
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public const string SORT_ORDER = 'sort_order';

    public const string INTERACTIONS = 'interactions';
}

final class PeerSyntheticRuleSetWrapperRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from(['*' => FluentRule::array()->children([
            SyntheticInteractionGroupRich::NAME => FluentRule::string()
                ->nullable()
                ->max(255),
            SyntheticInteractionGroupRich::COLOR => FluentRule::string()
                ->nullable(),
            SyntheticInteractionGroupRich::SORT_ORDER => FluentRule::integer()
                ->nullable()
                ->min(0),
            SyntheticInteractionGroupRich::INTERACTIONS => FluentRule::array()
                ->required(),
        ])]);
    }
}

?>
