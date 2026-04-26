<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Source\SomeAppBaseComponent;

// Indirect-Livewire component: extends an intermediate base class
// (`SomeAppBaseComponent`) which extends `Livewire\Component`.
//
// In this test env Livewire isn't a dev dependency, so
// `class_exists('Livewire\Component')` returns false → the
// `isLivewireClass()` ancestry walk can't reach Livewire → the
// QualifiesForRulesProcessing gate's Livewire branch returns false →
// the rector silently skips this class.
//
// In a real consumer codebase Livewire IS installed, the ancestry walk
// reaches `Livewire\Component`, the gate passes, and the rector
// processes the class. The "before" state below stays unchanged in
// the test suite (no `-----` separator) but proves the gate's
// silent-skip behavior on unloadable ancestries.
class IndirectLivewireComponent extends SomeAppBaseComponent
{
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.name' => FluentRule::string()->required(),
        ];
    }

    public function render()
    {
        return view('foo');
    }
}

?>
