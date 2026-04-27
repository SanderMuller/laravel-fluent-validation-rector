<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Config;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Config\HasFluentRulesTraitOptions;
use SanderMuller\FluentValidationRector\Config\Shared\BaseClassRegistry;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector;

final class HasFluentRulesTraitOptionsTest extends TestCase
{
    public function testDefaultProducesEmptyBaseClasses(): void
    {
        $this->assertSame(
            ['base_classes' => []],
            HasFluentRulesTraitOptions::default()->toArray(),
        );
    }

    public function testToArrayProducesLiteralWireKeyForCustomBaseClasses(): void
    {
        $dto = HasFluentRulesTraitOptions::default()
            ->withBaseClasses(BaseClassRegistry::of(['App\\Http\\Requests\\BaseRequest']));

        $this->assertSame(
            ['base_classes' => ['App\\Http\\Requests\\BaseRequest']],
            $dto->toArray(),
        );
    }

    public function testAddHasFluentRulesTraitRectorConstantMatchesWireKey(): void
    {
        $this->assertSame('base_classes', AddHasFluentRulesTraitRector::BASE_CLASSES);
    }

    public function testWithBaseClassesReturnsNewInstance(): void
    {
        $dto = HasFluentRulesTraitOptions::default();
        $next = $dto->withBaseClasses(BaseClassRegistry::of(['App\\Foo']));

        $this->assertNotSame($dto, $next);
        $this->assertSame([], $dto->baseClasses->baseClasses);
        $this->assertSame(['App\\Foo'], $next->baseClasses->baseClasses);
    }

    public function testWithNamedConstructorMatchesDefaultThenWithChain(): void
    {
        $registry = BaseClassRegistry::of(['App\\Http\\Requests\\BaseRequest']);

        $viaNamedConstructor = HasFluentRulesTraitOptions::with($registry);
        $viaBuilderChain = HasFluentRulesTraitOptions::default()->withBaseClasses($registry);

        $this->assertSame($viaNamedConstructor->toArray(), $viaBuilderChain->toArray());
    }
}
