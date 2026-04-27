<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach;

use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SanderMuller\FluentValidationRector\Rector\GroupWildcardRulesToEachRector;

/**
 * Pins the `parseConcatKey()` round-trip invariants directly at the
 * parser level, without going through the full rector pipeline.
 *
 * Two contracts the integration fixtures can't pin tightly:
 *
 *  - **ClassConstFetch identity preservation.** The parser MUST NOT
 *    clone, reconstruct, or otherwise produce a new `ClassConstFetch`
 *    instance for the new `'*.' . CONST` shape. The emit step (Phase 3)
 *    relies on the identity-equal node to preserve refactor safety
 *    + intent. A future refactor that adds e.g. `clone $parts[1]`
 *    would silently break the round-trip — caught only at Phase 3 emit
 *    if not pinned here.
 *  - **`kind` discriminator routing.** Each input shape produces the
 *    right `kind` field. Existing shape `<class-const> . '.<dotted>'`
 *    yields `'const_prefix_string_suffix'`; the new shape
 *    `String_('*.') . ClassConstFetch` yields
 *    `'string_prefix_const_suffix'`.
 */
final class ParseConcatKeyTest extends TestCase
{
    public function testNewShapePreservesClassConstFetchIdentity(): void
    {
        $literal = new String_('*.');
        $constFetch = new ClassConstFetch(new Name('App\\Models\\InteractionGroup'), new Identifier('NAME'));
        $concat = new Concat($literal, $constFetch);

        $result = $this->invokeParseConcatKey($concat);

        $this->assertNotNull($result);
        $this->assertSame('string_prefix_const_suffix', $result['kind']);
        $this->assertSame(
            $constFetch,
            $result['constExpr'],
            'parseConcatKey() must return the ORIGINAL ClassConstFetch '
            . 'instance, not a clone or reconstruction. Identity-equal '
            . '(===, not ==) is the round-trip invariant the Phase 3 '
            . 'emit depends on.',
        );
    }

    public function testNewShapePreservesPrefixLiteral(): void
    {
        $concat = new Concat(
            new String_('*.'),
            new ClassConstFetch(new Name('Foo\\Bar'), new Identifier('BAZ')),
        );

        $result = $this->invokeParseConcatKey($concat);

        $this->assertNotNull($result);
        $this->assertSame('string_prefix_const_suffix', $result['kind']);
        $this->assertSame('*.', $result['prefix']);
    }

    // The existing-shape happy path (`<class-const> . '.<dotted>'`) is
    // covered by the 25 integration fixtures under `Fixture/`. Direct
    // unit-test of that path requires NodeNameResolver via the rector's
    // container, which the `newInstanceWithoutConstructor()` workaround
    // can't provide. The new-shape path (this test file's focus) is
    // pure AST-only — no container calls — so reflection-without-init
    // is sufficient.

    public function testStrictPrefixRejectsTrailingSpace(): void
    {
        $concat = new Concat(
            new String_('*. '),
            new ClassConstFetch(new Name('Foo'), new Identifier('BAR')),
        );

        $result = $this->invokeParseConcatKey($concat);

        // Strict prefix value: '*. ' (trailing space) does NOT match the
        // new shape. Falls through to the existing parser path, which
        // sees String-before-ClassConstFetch and returns null.
        $this->assertNull($result);
    }

    public function testStrictPrefixRejectsNonWildcardLiteral(): void
    {
        $concat = new Concat(
            new String_('*.foo.'),
            new ClassConstFetch(new Name('Foo'), new Identifier('BAR')),
        );

        $result = $this->invokeParseConcatKey($concat);

        // '*.foo.' is not exactly '*.'; falls through to existing parser.
        $this->assertNull($result);
    }

    public function testStrictPrefixRejectsDoubleAsterisk(): void
    {
        $concat = new Concat(
            new String_('**.'),
            new ClassConstFetch(new Name('Foo'), new Identifier('BAR')),
        );

        $result = $this->invokeParseConcatKey($concat);

        // '**.' is not exactly '*.'; recursive-wildcard form is out of
        // 0.19 scope per Resolved Question #1.
        $this->assertNull($result);
    }

    public function testThreeOperandRejected(): void
    {
        // $foo . '*.' . CONST — three operands. The new shape requires
        // exactly two parts; three-operand stays out of scope per spec §2.
        $concat = new Concat(
            new Concat(
                new String_('prefix-'),
                new String_('*.'),
            ),
            new ClassConstFetch(new Name('Foo'), new Identifier('BAR')),
        );

        $result = $this->invokeParseConcatKey($concat);

        // Flatten yields 3 parts. New shape requires exactly 2; falls
        // through to existing parser which rejects (String before const).
        $this->assertNull($result);
    }

    /**
     * @return array{kind: 'const_prefix_string_suffix', prefixExpr: ClassConstFetch, prefix: string, suffix: string, prefixId: string}|array{kind: 'string_prefix_const_suffix', prefix: string, constExpr: ClassConstFetch}|null
     */
    private function invokeParseConcatKey(Concat $concat): ?array
    {
        // GroupWildcardRulesToEachRector is `final` so PHPUnit's
        // mock-class doubling rejects it. Bypass the constructor (which
        // would require Rector container deps) via Reflection — the
        // parser method is purely AST-driven and doesn't read instance
        // state from the rector class beyond what `getName()` provides.
        $reflection = new ReflectionClass(GroupWildcardRulesToEachRector::class);
        $rector = $reflection->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GroupWildcardRulesToEachRector::class, 'parseConcatKey');

        /** @var array{kind: 'const_prefix_string_suffix', prefixExpr: ClassConstFetch, prefix: string, suffix: string, prefixId: string}|array{kind: 'string_prefix_const_suffix', prefix: string, constExpr: ClassConstFetch}|null $result */
        $result = $method->invoke($rector, $concat);

        return $result;
    }
}
