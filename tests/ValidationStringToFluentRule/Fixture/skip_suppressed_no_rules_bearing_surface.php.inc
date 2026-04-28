<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// 0.20.2 mijntp dogfood: Filament-Page / Livewire-wrapper-shape
// classes qualify via Livewire ancestry but declare no validation
// rules. When such a class extends an unsafe parent (a parent
// whose other descendants do array_merge(parent::rules(), …)),
// pre-0.20.2 the unsafe-parent skip fired here too — generating
// pure noise on a class with nothing for the rector to convert
// in the first place.
//
// 0.20.2 pre-check: suppress the unsafe-parent skip when the
// class has no rules-bearing surface (no rules() method, no
// #[FluentRules]-attributed method, no auto-detect-qualified
// rules-shaped method). Skip becomes signal-only — fires only
// on classes the rector would have actually attempted to convert
// without the unsafe-parent flag.
//
// Synthetic shape mirroring mijntp's
// `App\Filament\Pages\ServiceProviderAdmin\ServiceProviderAdminPage`
// (which has 9 descendants, none with rules-bearing methods).
// Pre-0.20.2 this fixture would have emitted the unsafe-parent
// skip; post-0.20.2 it emits no skip and produces no diff.
class UnsafeParentBaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string',
        ];
    }
}

class UnsafeManipulatingChildRequest extends UnsafeParentBaseRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'extra' => 'required|string',
        ]);
    }
}

// This class extends `UnsafeParentBaseRequest` (which is now in
// the unsafe-parent set thanks to UnsafeManipulatingChildRequest)
// but declares no rules() method itself. Pre-0.20.2 the rector
// emitted an unsafe-parent skip here; post-0.20.2 the pre-check
// recognizes "nothing to convert anyway" and stays silent.
class UiScaffoldingChildRequest extends UnsafeParentBaseRequest
{
    public function authorize(): bool
    {
        return true;
    }
}

?>
