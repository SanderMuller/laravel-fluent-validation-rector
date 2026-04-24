<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns;

use Exception;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;
use SanderMuller\FluentValidationRector\Tests\Concerns\Support\IdentifiesLivewireClassesHarness;

final class IdentifiesLivewireClassesTest extends TestCase
{
    private IdentifiesLivewireClassesHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new IdentifiesLivewireClassesHarness();
    }

    public function testRenderMethodAloneDoesNotFlagAsLivewire(): void
    {
        // Regression: previous heuristic flagged every class with a render()
        // method as Livewire, misfiring on Exceptions implementing
        // CustomRenderInterface, PaginatorContract implementations, action
        // classes, Blade components, Filament Pages, Nova tools, etc.
        $class = $this->harness->buildClass(
            shortName: 'SomeException',
            extends: Exception::class,
            methodNames: ['render'],
        );

        $this->assertFalse($this->harness->isLivewireClass($class));
    }

    public function testDirectLivewireComponentParentIsLivewire(): void
    {
        $class = $this->harness->buildClass(
            shortName: 'MyComponent',
            extends: 'Livewire\Component',
        );

        $this->assertTrue($this->harness->isLivewireClass($class));
    }

    public function testDirectLivewireFormParentIsLivewire(): void
    {
        $class = $this->harness->buildClass(
            shortName: 'MyForm',
            extends: 'Livewire\Form',
        );

        $this->assertTrue($this->harness->isLivewireClass($class));
    }

    public function testDirectHasFluentValidationTraitIsLivewire(): void
    {
        $class = $this->harness->buildClass(
            shortName: 'AlreadyConverted',
            traitFqns: [HasFluentValidation::class],
        );

        $this->assertTrue($this->harness->isLivewireClass($class));
    }

    public function testDirectHasFluentValidationForFilamentTraitIsLivewire(): void
    {
        $class = $this->harness->buildClass(
            shortName: 'AlreadyConvertedFilament',
            traitFqns: [HasFluentValidationForFilament::class],
        );

        $this->assertTrue($this->harness->isLivewireClass($class));
    }

    public function testClassWithRenderMethodAndUnrelatedParentIsNotLivewire(): void
    {
        $class = $this->harness->buildClass(
            shortName: 'YouTubeCursorPaginator',
            extends: Collection::class,
            methodNames: ['render'],
        );

        $this->assertFalse($this->harness->isLivewireClass($class));
    }

    public function testParentlessClassIsNotLivewire(): void
    {
        $class = $this->harness->buildClass(shortName: 'Orphan');

        $this->assertFalse($this->harness->isLivewireClass($class));
    }
}
