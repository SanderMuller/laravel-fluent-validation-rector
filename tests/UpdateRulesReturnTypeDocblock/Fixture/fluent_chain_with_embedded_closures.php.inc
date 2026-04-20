<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FluentChainWithEmbeddedClosures extends FormRequest
{
    public function rules(): array
    {
        return [
            'field' => FluentRule::field()
                ->rule(fn (string $a, ?string $v, Closure $f): mixed => $f())
                ->requiredWithout('other')
                ->rule(fn (string $a, ?string $v, Closure $f): mixed => null),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FluentChainWithEmbeddedClosures extends FormRequest
{
    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'field' => FluentRule::field()
                ->rule(fn (string $a, ?string $v, Closure $f): mixed => $f())
                ->requiredWithout('other')
                ->rule(fn (string $a, ?string $v, Closure $f): mixed => null),
        ];
    }
}

?>
