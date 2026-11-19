<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\ValidatedInput as BaseValidatedInput;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast\String_ as StringCast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\ValidatedInput;

/**
 * Shared matching for the rules that rewrite reads of validated input to the
 * accessors on the package's `ValidatedInput`.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait MatchesValidatedInputReads
{
    private const string VALIDATED_INPUT_CLASS = ValidatedInput::class;

    private const array STRINGABLE_TERMINALS = ['value', 'tostring', '__tostring'];

    /** @var array<string, bool> */
    private static array $validatedInputMethodAvailable = [];

    /**
     * True when the installed package version has the accessor. An older
     * version would get a call to a method that does not exist.
     */
    private function validatedInputHasMethod(string $method): bool
    {
        return self::$validatedInputMethodAvailable[$method] ??= class_exists(self::VALIDATED_INPUT_CLASS)
            && method_exists(self::VALIDATED_INPUT_CLASS, $method);
    }

    /**
     * True when PHPStan types the expression as the package `ValidatedInput`.
     * Laravel's own `ValidatedInput` does not have the accessors.
     */
    private function isPackageValidatedInput(Expr $expr): bool
    {
        return (new ObjectType(ValidatedInput::class))->isSuperTypeOf($this->getType($expr))->yes();
    }

    /**
     * True when PHPStan types the expression as Laravel's `ValidatedInput`
     * (the package's one included) or a `Request`, and none of the given
     * methods is overridden below those classes. Both read input through
     * `InteractsWithData`, so `filled()`, `str()`, `date()` and `enum()`
     * agree on what a blank field is.
     *
     * @param  list<string>  $methods
     */
    private function isInputDataReceiver(Expr $expr, array $methods): bool
    {
        $type = $this->getType($expr);

        if (! (new ObjectType(BaseValidatedInput::class))->isSuperTypeOf($type)->yes()
            && ! (new ObjectType(Request::class))->isSuperTypeOf($type)->yes()) {
            return false;
        }

        $classReflections = $type->getObjectClassReflections();

        if ($classReflections === []) {
            return false;
        }

        foreach ($classReflections as $classReflection) {
            foreach ($methods as $method) {
                if (! $classReflection->hasNativeMethod($method)) {
                    return false;
                }

                $declaringClass = $classReflection->getNativeMethod($method)->getDeclaringClass()->getName();

                if ($declaringClass !== BaseValidatedInput::class && $declaringClass !== Request::class) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The `string('k')` / `str('k')` call inside a read that converts the
     * Stringable to a plain string, with no other Stringable call between.
     */
    private function matchStringRead(Expr $read): ?MethodCall
    {
        $stringable = null;

        if ($read instanceof StringCast) {
            $stringable = $read->expr;
        } elseif ($read instanceof FuncCall) {
            if ($read->name instanceof Name && strtolower((string) $this->getName($read)) === 'strval' && count($read->args) === 1 && $read->args[0] instanceof Arg && ! $read->args[0]->unpack && ! $read->args[0]->name instanceof Identifier) {
                $stringable = $read->args[0]->value;
            }
        } elseif ($read instanceof MethodCall && $read->args === []) {
            foreach (self::STRINGABLE_TERMINALS as $terminal) {
                if ($this->methodNameIs($read, $terminal)) {
                    $stringable = $read->var;

                    break;
                }
            }
        }

        if (! $stringable instanceof MethodCall) {
            return null;
        }

        if (! $this->methodNameIs($stringable, 'string') && ! $this->methodNameIs($stringable, 'str')) {
            return null;
        }

        return $stringable;
    }

    /**
     * Splits a ternary into the condition without a leading negation, the
     * branch taken when that condition is true, and the other branch.
     *
     * @return array{Expr, Expr, Expr}|null
     */
    private function normalizeTernary(Ternary $ternary): ?array
    {
        if (! $ternary->if instanceof Expr) {
            return null;
        }

        if ($ternary->cond instanceof BooleanNot) {
            return [$ternary->cond->expr, $ternary->else, $ternary->if];
        }

        return [$ternary->cond, $ternary->if, $ternary->else];
    }

    /**
     * The only argument of a call, when it is a plain positional argument.
     */
    private function soleArgument(MethodCall $call): ?Expr
    {
        if (count($call->args) !== 1) {
            return null;
        }

        $arg = $call->args[0];

        if ($arg instanceof VariadicPlaceholder || ! $arg instanceof Arg || $arg->unpack || $arg->byRef || $arg->name instanceof Identifier) {
            return null;
        }

        return $arg->value;
    }

    private function methodNameIs(MethodCall $call, string $lowercaseName): bool
    {
        return $call->name instanceof Identifier && $call->name->toLowerString() === $lowercaseName;
    }

    /**
     * True for an expression that gives the same value, with no side effects,
     * when it is read once instead of twice: a literal, a constant, a variable,
     * a static property, or a declared property on one of these that has no
     * hook and cannot get one in a subclass. A magic or hooked property can
     * run code on every read.
     */
    private function isStableRead(Expr $expr): bool
    {
        if ($expr instanceof String_ || $expr instanceof ConstFetch) {
            return true;
        }

        if ($expr instanceof ClassConstFetch || $expr instanceof StaticPropertyFetch) {
            return $expr->class instanceof Name && $expr->name instanceof Identifier;
        }

        if ($expr instanceof Variable) {
            return is_string($expr->name);
        }

        if ($expr instanceof PropertyFetch) {
            return $expr->name instanceof Identifier
                && $this->isStableRead($expr->var)
                && $this->isPlainNativeProperty($expr->var, $expr->name->toString());
        }

        return false;
    }

    private function isPlainNativeProperty(Expr $object, string $propertyName): bool
    {
        $classReflections = $this->getType($object)->getObjectClassReflections();

        if ($classReflections === []) {
            return false;
        }

        foreach ($classReflections as $classReflection) {
            if (! $classReflection->hasNativeProperty($propertyName)) {
                return false;
            }

            $property = $classReflection->getNativeProperty($propertyName);

            if ($property->isHooked()) {
                return false;
            }

            // On PHP 8.4 a subclass can redeclare the property with a get hook.
            $cannotBeOverridden = $classReflection->isFinal()
                || $property->isPrivate()
                || $property->isReadOnly()
                || $property->isFinal()->yes();

            if (! $cannotBeOverridden) {
                return false;
            }
        }

        return true;
    }

    /**
     * The ternary reads the fallback only when the field is blank; the call
     * reads it every time. A property read can throw (uninitialized, magic),
     * and so can a variable that may be undefined, so only a literal, a
     * constant or a certainly defined variable qualifies.
     */
    private function isSafeEagerFallback(Expr $fallback): bool
    {
        if ($fallback instanceof String_ || $fallback instanceof Int_ || $fallback instanceof ConstFetch) {
            return true;
        }

        if ($fallback instanceof ClassConstFetch) {
            return $fallback->class instanceof Name && $fallback->name instanceof Identifier;
        }

        if (! $fallback instanceof Variable || ! is_string($fallback->name)) {
            return false;
        }

        $scope = $fallback->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->hasVariableType($fallback->name)->yes();
    }

    private function isNullConstant(Expr $expr): bool
    {
        return $expr instanceof ConstFetch && $expr->name->toLowerString() === 'null';
    }

    /**
     * Receivers and keys of both reads match, and both are safe to read once
     * instead of twice.
     */
    private function sameReceiverAndKey(MethodCall $first, Expr $firstKey, MethodCall $second, Expr $secondKey): bool
    {
        return $this->isStableRead($first->var)
            && $this->isStableRead($firstKey)
            && $this->nodeComparator->areNodesEqual($first->var, $second->var)
            && $this->nodeComparator->areNodesEqual($firstKey, $secondKey)
            && $this->getType($firstKey)->isString()->yes();
    }
}
