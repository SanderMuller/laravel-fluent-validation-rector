<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Config;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Config\DocblockNarrowOptions;
use SanderMuller\FluentValidationRector\Config\Shared\AllowlistedFactories;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;

final class DocblockNarrowOptionsTest extends TestCase
{
    public function testDefaultProducesEmptyAllowlist(): void
    {
        $this->assertSame(
            [
                'treat_as_fluent_compatible' => [],
                'allow_chain_tail_on_allowlisted' => false,
            ],
            DocblockNarrowOptions::default()->toArray(),
        );
    }

    public function testToArrayProducesLiteralWireKeysForCustomAllowlist(): void
    {
        $dto = DocblockNarrowOptions::default()
            ->withAllowlistedFactories(
                AllowlistedFactories::none()
                    ->withFactories(['App\\Rules\\Custom'])
                    ->allowingChainTail(),
            );

        $this->assertSame(
            [
                'treat_as_fluent_compatible' => ['App\\Rules\\Custom'],
                'allow_chain_tail_on_allowlisted' => true,
            ],
            $dto->toArray(),
        );
    }

    public function testUpdateRulesReturnTypeDocblockRectorConstantsMatchWireKeys(): void
    {
        $this->assertSame('treat_as_fluent_compatible', UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE);
        $this->assertSame('allow_chain_tail_on_allowlisted', UpdateRulesReturnTypeDocblockRector::ALLOW_CHAIN_TAIL_ON_ALLOWLISTED);
    }

    public function testWithAllowlistedFactoriesReturnsNewInstance(): void
    {
        $dto = DocblockNarrowOptions::default();
        $next = $dto->withAllowlistedFactories(
            AllowlistedFactories::none()->withFactories(['App\\Rules\\X']),
        );

        $this->assertNotSame($dto, $next);
        $this->assertSame([], $dto->allowlistedFactories->factories);
        $this->assertSame(['App\\Rules\\X'], $next->allowlistedFactories->factories);
    }
}
