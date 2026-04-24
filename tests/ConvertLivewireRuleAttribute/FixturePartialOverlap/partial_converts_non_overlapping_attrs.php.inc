<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// Collectiq shape: action-method `$this->validate([...])` with explicit
// keys overlaps SOME attrs, not others. Partial mode converts the
// non-overlapping attrs; overlapping attrs + the validate() call stay
// untouched.

final class ArticleEditorPage extends Component
{
    #[Validate('required|string|min:6|max:150')]
    public string $title = '';

    #[Validate('nullable|string')]
    public ?string $slug = null;

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|between:6,150',
            'content' => 'required|array',
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

// Collectiq shape: action-method `$this->validate([...])` with explicit
// keys overlaps SOME attrs, not others. Partial mode converts the
// non-overlapping attrs; overlapping attrs + the validate() call stay
// untouched.

final class ArticleEditorPage extends Component
{
    #[Validate('required|string|min:6|max:150')]
    public string $title = '';

    #[Validate]
    public ?string $slug = null;

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|between:6,150',
            'content' => 'required|array',
        ]);
    }

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'slug' => FluentRule::string()->nullable(),
        ];
    }
}

?>
