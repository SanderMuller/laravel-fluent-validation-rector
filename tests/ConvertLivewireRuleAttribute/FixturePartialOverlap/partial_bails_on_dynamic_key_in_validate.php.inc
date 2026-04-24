<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// validate() arg has a dynamic `[$key => ...]` item. The extracted key
// set is incomplete (can't know what $key resolves to); classwide bail.

final class DynamicKeyPage extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    public string $dynamicKey = 'title';

    public function save(): void
    {
        $this->validate([
            $this->dynamicKey => 'required|max:60',
        ]);
    }
}

?>
