<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewire;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

class AnvaAvantiAdminCredentials extends Component
{
    /**
     * @var array<string, string>
     */
    public array $credentials = [];

    public function rules(): array
    {
        return [
            'credentials.username' => FluentRule::string()->required(),
        ];
    }

    public function render()
    {
        return view('livewire.anva-avanti-admin-credentials');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewire;

use SanderMuller\FluentValidation\HasFluentValidation;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

class AnvaAvantiAdminCredentials extends Component
{
    use HasFluentValidation;

    /**
     * @var array<string, string>
     */
    public array $credentials = [];

    public function rules(): array
    {
        return [
            'credentials.username' => FluentRule::string()->required(),
        ];
    }

    public function render()
    {
        return view('livewire.anva-avanti-admin-credentials');
    }
}

?>
