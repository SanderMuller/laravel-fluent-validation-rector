<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class DynamicArgTupleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'a' => [
                'array',
                ['max', $this->isLandscape() ? 15 : 20],
            ],
            'b' => [
                'array',
                ['max', $this->getLimit()],
            ],
            'c' => [
                'array',
                ['max', config('app.limit')],
            ],
            'd' => [
                'array',
                ['max', $this->foo?->bar],
            ],
            'e' => [
                'array',
                ['max', $this->limit],
            ],
            'f' => [
                'array',
                ['max', match ($this->mode()) { 'strict' => 5, default => 10 }],
            ],
            'g' => [
                'array',
                ['max', \App\Config::get('limit')],
            ],
            'h' => [
                'array',
                ['max', $this->limits['max']],
            ],
            'i' => [
                'array',
                ['max', (int) $this->raw],
            ],
            'chapters' => [
                ['required_if', 'type', \App\Enums\InteractionType::CHAPTER->value],
                'array',
                ['max', $this->isLandscape() ? 15 : 20],
            ],
        ];
    }

    public function isLandscape(): bool
    {
        return true;
    }

    public function getLimit(): int
    {
        return 10;
    }

    public function mode(): string
    {
        return 'strict';
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Config;
use App\Enums\InteractionType;
use Illuminate\Foundation\Http\FormRequest;

class DynamicArgTupleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'a' => FluentRule::array()->max($this->isLandscape() ? 15 : 20),
            'b' => FluentRule::array()->max($this->getLimit()),
            'c' => FluentRule::array()->max(config('app.limit')),
            'd' => FluentRule::array()->max($this->foo?->bar),
            'e' => FluentRule::array()->max($this->limit),
            'f' => FluentRule::array()->max(match ($this->mode()) { 'strict' => 5, default => 10 }),
            'g' => FluentRule::array()->max(Config::get('limit')),
            'h' => FluentRule::array()->max($this->limits['max']),
            'i' => FluentRule::array()->max((int) $this->raw),
            'chapters' => FluentRule::array()->requiredIf('type', InteractionType::CHAPTER->value)->max($this->isLandscape() ? 15 : 20),
        ];
    }

    public function isLandscape(): bool
    {
        return true;
    }

    public function getLimit(): int
    {
        return 10;
    }

    public function mode(): string
    {
        return 'strict';
    }
}

?>
