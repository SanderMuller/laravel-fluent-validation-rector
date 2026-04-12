<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TraitUse;

/**
 * Inserts a TraitUse into a Class_ with a blank-line Nop guard so the trait
 * doesn't sit flush against a docblocked next member. Composes with
 * ManagesNamespaceImports so consumers that add a class-level trait typically
 * also want the matching top-of-file `use` import.
 */
trait ManagesTraitInsertion
{
    use ManagesNamespaceImports;

    private function insertTraitUseInClass(Class_ $class, string $shortName): void
    {
        $traitUse = new TraitUse([new Name($shortName)]);

        $insertPosition = 0;

        foreach ($class->stmts as $i => $stmt) {
            if ($stmt instanceof TraitUse) {
                $insertPosition = $i + 1;
            }
        }

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
