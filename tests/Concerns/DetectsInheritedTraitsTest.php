<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidationRector\Tests\Concerns\Fixture\DirectFormRequestChild;
use SanderMuller\FluentValidationRector\Tests\Concerns\Fixture\NestedFormRequestChild;
use SanderMuller\FluentValidationRector\Tests\Concerns\Fixture\UsesHasFluentRulesOnly;
use SanderMuller\FluentValidationRector\Tests\Concerns\Support\DetectsInheritedTraitsHarness;

final class DetectsInheritedTraitsTest extends TestCase
{
    private DetectsInheritedTraitsHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new DetectsInheritedTraitsHarness();
    }

    public function testReflectedAncestryExtendsFindsDirectParent(): void
    {
        $this->assertTrue(
            $this->harness->reflectedAncestryExtends(
                DirectFormRequestChild::class,
                FormRequest::class,
            )
        );
    }

    public function testReflectedAncestryExtendsFindsIntermediateAncestor(): void
    {
        $this->assertTrue(
            $this->harness->reflectedAncestryExtends(
                NestedFormRequestChild::class,
                FormRequest::class,
            )
        );
    }

    public function testReflectedAncestryExtendsReturnsFalseWhenNoMatch(): void
    {
        $this->assertFalse(
            $this->harness->reflectedAncestryExtends(
                UsesHasFluentRulesOnly::class,
                FormRequest::class,
            )
        );
    }

    public function testReflectedAncestryExtendsAcceptsLeadingBackslashTarget(): void
    {
        $this->assertTrue(
            $this->harness->reflectedAncestryExtends(
                DirectFormRequestChild::class,
                '\\' . FormRequest::class,
            )
        );
    }

    public function testAnyAncestorExtendsResolvesFullyQualifiedName(): void
    {
        $class = $this->classWithExtends(new FullyQualified(NestedFormRequestChild::class));

        $this->assertTrue($this->harness->anyAncestorExtends($class, FormRequest::class));
    }

    public function testAnyAncestorExtendsReturnsFalseForClassWithoutExtends(): void
    {
        $class = $this->classWithExtends(null);

        $this->assertFalse($this->harness->anyAncestorExtends($class, FormRequest::class));
    }

    public function testAnyAncestorExtendsReturnsFalseWhenGetNameYieldsNull(): void
    {
        // Simulate the NodeNameResolver returning null (unresolvable aliased
        // import that never loaded): harness's getName maps unknown names to
        // null.
        $class = $this->classWithExtends(new Name('UnresolvableAlias'));

        $this->assertFalse($this->harness->anyAncestorExtends($class, FormRequest::class));
    }

    public function testCurrentOrAncestorUsesTraitFindsDirectUseOnParentlessClass(): void
    {
        $class = $this->classWithTraits([HasFluentRules::class]);

        $this->assertTrue(
            $this->harness->currentOrAncestorUsesTrait($class, HasFluentRules::class)
        );
    }

    public function testCurrentOrAncestorUsesTraitFindsHasFluentValidationDirectly(): void
    {
        $class = $this->classWithTraits([HasFluentValidation::class]);

        $this->assertTrue(
            $this->harness->currentOrAncestorUsesTrait($class, HasFluentValidation::class)
        );
    }

    public function testCurrentOrAncestorUsesTraitReturnsFalseForUnrelatedClass(): void
    {
        $class = $this->classWithTraits([]);

        $this->assertFalse(
            $this->harness->currentOrAncestorUsesTrait($class, HasFluentRules::class)
        );
    }

    public function testCurrentOrAncestorUsesTraitAcceptsLeadingBackslashTarget(): void
    {
        $class = $this->classWithTraits([HasFluentRules::class]);

        $this->assertTrue(
            $this->harness->currentOrAncestorUsesTrait($class, '\\' . HasFluentRules::class)
        );
    }

    private function classWithExtends(?Name $extends): Class_
    {
        $class = new Class_('StubClass');
        $class->extends = $extends;

        return $class;
    }

    /**
     * @param  list<class-string>  $traitFqns
     */
    private function classWithTraits(array $traitFqns): Class_
    {
        $class = new Class_('StubClass');

        if ($traitFqns !== []) {
            $class->stmts[] = new TraitUse(
                array_map(static fn (string $fqn): Name => new FullyQualified($fqn), $traitFqns)
            );
        }

        return $class;
    }
}
