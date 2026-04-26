<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentValidationTraitRector;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;
use SanderMuller\FluentValidationRector\Rector\GroupWildcardRulesToEachRector;
use SanderMuller\FluentValidationRector\Rector\InlineMessageParamRector;
use SanderMuller\FluentValidationRector\Rector\InlineResolvableParentRulesRector;
use SanderMuller\FluentValidationRector\Rector\PromoteFieldFactoryRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;
use SplFileInfo;

/**
 * Audits every class / trait / interface under `src/` against the package's
 * public API boundary: it is either on the documented public list or carries
 * a class-level `@internal` PHPDoc tag. Catches the failure mode where a new
 * internal helper is added without the tag and silently appears public.
 *
 * Pairs with `PublicApiSurfaceTest` (constant-level + wire-key audits).
 */
final class InternalAuditTest extends TestCase
{
    /**
     * @var list<class-string>
     */
    private const array PUBLIC_CLASSES = [
        FluentValidationSetList::class,
        AddHasFluentRulesTraitRector::class,
        AddHasFluentValidationTraitRector::class,
        ConvertLivewireRuleAttributeRector::class,
        GroupWildcardRulesToEachRector::class,
        InlineMessageParamRector::class,
        InlineResolvableParentRulesRector::class,
        PromoteFieldFactoryRector::class,
        SimplifyFluentRuleRector::class,
        SimplifyRuleWrappersRector::class,
        UpdateRulesReturnTypeDocblockRector::class,
        ValidationArrayToFluentRuleRector::class,
        ValidationStringToFluentRuleRector::class,
    ];

    public function testEverySrcSymbolIsEitherPublicOrInternal(): void
    {
        $publicFlipped = array_flip(self::PUBLIC_CLASSES);
        $offenders = [];

        foreach ($this->discoverSrcSymbols() as $fqn) {
            if (isset($publicFlipped[$fqn])) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($fqn);
            } catch (ReflectionException $e) {
                $offenders[] = "{$fqn}: ReflectionException ({$e->getMessage()})";

                continue;
            }

            $docComment = $reflection->getDocComment();

            if ($docComment === false) {
                $offenders[] = "{$fqn}: missing class-level docblock (cannot mark @internal)";

                continue;
            }

            if (! preg_match('/^\s*\*\s*@internal\b/m', $docComment)) {
                $offenders[] = "{$fqn}: docblock does not contain @internal tag";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Each non-public symbol under src/ must carry a class-level @internal '
            . 'PHPDoc tag. Either add @internal or extend '
            . self::class . "::PUBLIC_CLASSES.\n\nOffenders:\n  - "
            . implode("\n  - ", $offenders),
        );
    }

    /**
     * @return list<class-string>
     */
    private function discoverSrcSymbols(): array
    {
        $srcRoot = dirname(__DIR__) . '/src';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
        );

        $fqns = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($srcRoot) + 1);
            $relative = str_replace(\DIRECTORY_SEPARATOR, '\\', $relative);
            $fqn = 'SanderMuller\\FluentValidationRector\\'
                . preg_replace('/\.php$/', '', $relative);
            if (! is_string($fqn)) {
                continue;
            }

            if ($fqn === '') {
                continue;
            }

            if (! class_exists($fqn) && ! trait_exists($fqn) && ! interface_exists($fqn) && ! enum_exists($fqn)) {
                continue;
            }

            $fqns[] = $fqn;
        }

        sort($fqns);

        /** @var list<class-string> $fqns */
        return $fqns;
    }
}
