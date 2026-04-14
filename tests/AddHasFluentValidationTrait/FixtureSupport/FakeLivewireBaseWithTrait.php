<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport;

use SanderMuller\FluentValidation\HasFluentValidation;

/**
 * Test-only support class that simulates a Livewire base class already
 * carrying HasFluentValidation — used by the "skip inherited trait" fixture
 * for AddHasFluentValidationTraitRector. The rector's DetectsInheritedTraits
 * concern walks this class's traits via PHP reflection, so it must be
 * autoloadable under tests/.
 */
abstract class FakeLivewireBaseWithTrait
{
    use HasFluentValidation;
}
