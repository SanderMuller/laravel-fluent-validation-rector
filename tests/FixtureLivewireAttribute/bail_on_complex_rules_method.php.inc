<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class ComplexRulesComponent extends Component
{
    #[Rule('required|string', as: 'name')]
    public string $name = '';

    protected function rules(): array
    {
        if (request()->routeIs('admin')) {
            return ['email' => 'required|email|max:512'];
        }

        return ['email' => 'required|email'];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class ComplexRulesComponent extends Component
{
    public string $name = '';

    protected function rules(): array
    {
        if (request()->routeIs('admin')) {
            return ['email' => 'required|email|max:512'];
        }

        return ['email' => 'required|email'];
    }
}

?>
