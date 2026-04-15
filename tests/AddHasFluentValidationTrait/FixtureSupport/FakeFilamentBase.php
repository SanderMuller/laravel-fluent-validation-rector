<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport;

/**
 * Test-only support class simulating a shared Livewire base class that pulls
 * in Filament's `InteractsWithForms`. Used by the ancestor-Filament-detection
 * fixture — the rector walks this class via ReflectionClass so it must be
 * autoloadable under tests/.
 */
abstract class FakeFilamentBase
{
    use InteractsWithForms;
}
