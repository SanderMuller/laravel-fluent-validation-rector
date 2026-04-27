<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Config\Shared;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Config\Shared\AllowlistedFactories;

final class AllowlistedFactoriesTest extends TestCase
{
    public function testNoneIsEmptyAndChainTailDisabled(): void
    {
        $dto = AllowlistedFactories::none();

        $this->assertSame([], $dto->factories);
        $this->assertFalse($dto->allowChainTail);
    }

    public function testWithFactoriesReturnsNewInstance(): void
    {
        $dto = AllowlistedFactories::none();
        $next = $dto->withFactories(['App\\Rules\\Custom']);

        $this->assertNotSame($dto, $next);
        $this->assertSame([], $dto->factories);
        $this->assertSame(['App\\Rules\\Custom'], $next->factories);
    }

    public function testAllowingChainTailReturnsNewInstance(): void
    {
        $dto = AllowlistedFactories::none();
        $next = $dto->allowingChainTail();

        $this->assertNotSame($dto, $next);
        $this->assertFalse($dto->allowChainTail);
        $this->assertTrue($next->allowChainTail);
    }

    public function testBuildersAreOrderIndependent(): void
    {
        $a = AllowlistedFactories::none()
            ->withFactories(['App\\Rules\\A', 'App\\Rules\\B'])
            ->allowingChainTail();

        $b = AllowlistedFactories::none()
            ->allowingChainTail()
            ->withFactories(['App\\Rules\\A', 'App\\Rules\\B']);

        $this->assertSame($a->factories, $b->factories);
        $this->assertSame($a->allowChainTail, $b->allowChainTail);
    }

    public function testWithFactoriesPreservesChainTailFlag(): void
    {
        $dto = AllowlistedFactories::none()
            ->allowingChainTail()
            ->withFactories(['App\\Rules\\C']);

        $this->assertTrue($dto->allowChainTail);
        $this->assertSame(['App\\Rules\\C'], $dto->factories);
    }

    public function testWithFactoriesAcceptsWildcardPatterns(): void
    {
        $dto = AllowlistedFactories::none()
            ->withFactories(['App\\Rules\\*', 'App\\Domain\\**\\Rule']);

        $this->assertSame(['App\\Rules\\*', 'App\\Domain\\**\\Rule'], $dto->factories);
    }

    public function testWithFactoriesAcceptsStaticCallTuples(): void
    {
        $dto = AllowlistedFactories::none()
            ->withFactories([
                'App\\Rules\\Custom',
                ['App\\Models\\Question', 'existsRule'],
            ]);

        $this->assertSame(
            [
                'App\\Rules\\Custom',
                ['App\\Models\\Question', 'existsRule'],
            ],
            $dto->factories,
        );
    }
}
