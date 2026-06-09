<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 Phase 6 — real-world-shape fixture mirroring the
// `JsonInteractionGroupImportValidator::rulesWithoutPrefix()` shape
// from production-dogfood code (consumer-committed
// `.dogfood-baselines/0.19-prep/fixture-source/`). Synthetic
// FormRequest framing per peer review's "don't try to bypass the
// Validator-subclass gate; write the same rule shape as a
// FormRequest" guidance.
//
// Pins the fold output against the consumer's hand-folded mental
// model. If 0.19's emit ever diverges from the consumer's
// expectation, this fixture surfaces the diff at every CI run
// instead of waiting for the next consumer dogfood cycle.
//
// Plain-class const-host variant. The Spatie LaravelData DTO
// variant (`JsonSubtitleImportValidator` / `ExternalSubtitle`) is
// structurally identical to this from the rector's perspective —
// the const-host class's parent doesn't affect ClassConstFetch
// resolution. Covered by `converts_wildcard_prefix_concat_mixed_const_host.php.inc`.
final class JsonInteractionGroupShape
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public const string SORT_ORDER = 'sort_order';

    public const string INTERACTIONS = 'interactions';
}

final class RealworldJsonInteractionGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            '*.' . JsonInteractionGroupShape::NAME => FluentRule::string()->nullable()->max(255),
            '*.' . JsonInteractionGroupShape::COLOR => FluentRule::string()->nullable()->rule('regex:/^#[0-9A-Fa-f]{6}$/'),
            '*.' . JsonInteractionGroupShape::SORT_ORDER => FluentRule::integer()->nullable()->min(0),
            '*.' . JsonInteractionGroupShape::INTERACTIONS => FluentRule::array()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 Phase 6 — real-world-shape fixture mirroring the
// `JsonInteractionGroupImportValidator::rulesWithoutPrefix()` shape
// from production-dogfood code (consumer-committed
// `.dogfood-baselines/0.19-prep/fixture-source/`). Synthetic
// FormRequest framing per peer review's "don't try to bypass the
// Validator-subclass gate; write the same rule shape as a
// FormRequest" guidance.
//
// Pins the fold output against the consumer's hand-folded mental
// model. If 0.19's emit ever diverges from the consumer's
// expectation, this fixture surfaces the diff at every CI run
// instead of waiting for the next consumer dogfood cycle.
//
// Plain-class const-host variant. The Spatie LaravelData DTO
// variant (`JsonSubtitleImportValidator` / `ExternalSubtitle`) is
// structurally identical to this from the rector's perspective —
// the const-host class's parent doesn't affect ClassConstFetch
// resolution. Covered by `converts_wildcard_prefix_concat_mixed_const_host.php.inc`.
final class JsonInteractionGroupShape
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    public const string SORT_ORDER = 'sort_order';

    public const string INTERACTIONS = 'interactions';
}

final class RealworldJsonInteractionGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            JsonInteractionGroupShape::NAME => FluentRule::string()->nullable()->max(255),
            JsonInteractionGroupShape::COLOR => FluentRule::string()->nullable()->rule('regex:/^#[0-9A-Fa-f]{6}$/'),
            JsonInteractionGroupShape::SORT_ORDER => FluentRule::integer()->nullable()->min(0),
            JsonInteractionGroupShape::INTERACTIONS => FluentRule::array()->required(),
        ])];
    }
}

?>
