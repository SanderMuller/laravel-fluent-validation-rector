<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TraitUse;
use Rector\Rector\AbstractRector;

/**
 * Inserts, removes, and queries `use Trait;` statements inside a class body.
 * Composes with `ManagesNamespaceImports` so consumers that add a class-level
 * trait typically also want the matching top-of-file `use` import.
 *
 * @phpstan-require-extends AbstractRector
 */
trait ManagesTraitInsertion
{
    use ManagesNamespaceImports;

    private function insertTraitUseInClass(Class_ $class, string $shortName): void
    {
        $traitUse = new TraitUse([new Name($shortName)]);

        $insertPosition = $this->resolveSortedTraitInsertPosition($class, $shortName);

        // Emit a blank line (Nop) between the trait and the next class member
        // when it would otherwise sit flush against a non-trait statement.
        // Skip the Nop when the original stmts already have a blank-line gap —
        // the format-preserving printer keeps that gap, so adding a Nop would
        // double it.
        $toInsert = $this->needsBlankLineAfterTrait($class, $insertPosition)
            ? [$traitUse, new Nop()]
            : [$traitUse];

        array_splice($class->stmts, $insertPosition, 0, $toInsert);
    }

    /**
     * Walk the class's existing `use` statements and return the index where
     * the new trait should be inserted to keep the block alphabetically
     * sorted. When the new trait sorts before an existing one, insert
     * immediately before it; when it sorts after all existing traits (or
     * when the class has no trait uses), fall through to "after the last
     * trait use" (or index 0 for a trait-less class).
     *
     * Pint's `ordered_traits` fixer resorts post-rector regardless, but
     * emitting at the sorted position means the rector's pre-Pint output
     * already matches final form — Pint doesn't have to touch converted
     * files just to reshuffle traits.
     */
    private function resolveSortedTraitInsertPosition(Class_ $class, string $shortName): int
    {
        $insertPosition = 0;

        foreach ($class->stmts as $i => $stmt) {
            if (! $stmt instanceof TraitUse) {
                continue;
            }

            $existingName = $this->firstTraitName($stmt);

            if ($existingName !== null && strcmp($shortName, $existingName) < 0) {
                return $i;
            }

            $insertPosition = $i + 1;
        }

        return $insertPosition;
    }

    private function firstTraitName(TraitUse $traitUse): ?string
    {
        $first = $traitUse->traits[0] ?? null;

        if (! $first instanceof Name) {
            return null;
        }

        return $first->getLast();
    }

    private function directlyUsesTrait(Class_ $class, string $traitFqn): bool
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                if ($this->getName($trait) === $traitFqn) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Remove a direct-trait reference (by short name or trailing-namespace
     * match) from the class. Handles both single-trait blocks (whole TraitUse
     * removed) and multi-trait blocks (only the target name removed from the
     * list, other traits preserved). No-op when the trait isn't directly used.
     */
    private function removeDirectTraitUse(Class_ $class, string $shortName): void
    {
        foreach ($class->stmts as $i => $stmt) {
            if (! $stmt instanceof TraitUse) {
                continue;
            }

            $remaining = [];
            $matched = false;

            foreach ($stmt->traits as $trait) {
                $name = $this->getName($trait);

                if ($name === $shortName || ($name !== null && str_ends_with($name, '\\' . $shortName))) {
                    $matched = true;

                    continue;
                }

                $remaining[] = $trait;
            }

            if (! $matched) {
                continue;
            }

            if ($remaining === []) {
                unset($class->stmts[$i]);

                continue;
            }

            $stmt->traits = $remaining;
        }

        $class->stmts = array_values($class->stmts);
    }

    private function anyClassInNamespaceUsesTrait(Namespace_ $namespace, string $shortName): bool
    {
        foreach ($namespace->stmts as $stmt) {
            if (! $stmt instanceof Class_) {
                continue;
            }

            foreach ($stmt->getTraitUses() as $traitUse) {
                foreach ($traitUse->traits as $trait) {
                    $name = $this->getName($trait);

                    if ($name === $shortName || ($name !== null && str_ends_with($name, '\\' . $shortName))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function needsBlankLineAfterTrait(Class_ $class, int $insertPosition): bool
    {
        $nextStmt = $class->stmts[$insertPosition] ?? null;

        if ($nextStmt === null) {
            return false;
        }

        if ($nextStmt instanceof TraitUse || $nextStmt instanceof Nop) {
            return false;
        }

        $prevStmt = $insertPosition > 0 ? $class->stmts[$insertPosition - 1] : null;

        if ($prevStmt === null) {
            return true;
        }

        $prevEnd = $prevStmt->getEndLine();
        $comments = $nextStmt->getComments();
        $nextStart = $comments === [] ? $nextStmt->getStartLine() : $comments[0]->getStartLine();

        return $nextStart - $prevEnd < 2;
    }
}
