<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;

/**
 * Shared helpers for the trait-insertion rectors.
 *
 * Handles:
 *  - inserting a TraitUse into a Class_ with a blank-line Nop guard so the
 *    trait doesn't sit flush against a docblocked next member
 *  - inserting a top-level `use <fqcn>;` statement into a Namespace_ at the
 *    alphabetically-sorted position, skipping when the class is already
 *    imported
 *
 * The manual-import approach here replaces Rector's UseNodesToAddCollector
 * path because UseAddingPostRector prepends new imports to the top of the
 * use block regardless of existing alphabetical order, which produced an
 * unintentional regression in 0.1.1 compared to 0.1.0 and what Pint's
 * ordered_imports fixer would produce.
 */
trait ManagesTraitInsertion
{
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

    private function ensureUseImportInNamespace(Namespace_ $namespace, string $fqcn): void
    {
        if ($this->hasUseImport($namespace->stmts, $fqcn)) {
            return;
        }

        $insertAt = $this->findSortedUseInsertPosition($namespace->stmts, $fqcn);
        $useStmt = new Use_([new UseItem(new Name($fqcn))]);

        array_splice($namespace->stmts, $insertAt, 0, [$useStmt]);
    }

    /**
     * @param  array<Node\Stmt>  $stmts
     */
    private function hasUseImport(array $stmts, string $fqcn): bool
    {
        $target = ltrim($fqcn, '\\');

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $useItem) {
                if ($useItem->name->toString() === $target) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find the index to insert a new Use_ so the top-level use block stays
     * alphabetically sorted by FQN. Falls back to "after the last existing
     * Use_" when the existing imports aren't already sorted, preserving the
     * user's custom order without forcibly rewriting it.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function findSortedUseInsertPosition(array $stmts, string $fqcn): int
    {
        $target = ltrim($fqcn, '\\');
        $lastUseIndex = -1;
        $existingOrder = [];

        foreach ($stmts as $key => $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            $lastUseIndex = $key;

            foreach ($stmt->uses as $useItem) {
                $existingOrder[] = ['index' => $key, 'name' => $useItem->name->toString()];
            }
        }

        if ($lastUseIndex === -1) {
            return $this->findNonHeaderInsertPosition($stmts);
        }

        if (! $this->areUsesSorted($existingOrder)) {
            return $lastUseIndex + 1;
        }

        foreach ($existingOrder as $entry) {
            if (strcasecmp($target, $entry['name']) < 0) {
                return $entry['index'];
            }
        }

        return $lastUseIndex + 1;
    }

    /**
     * @param  array<Node\Stmt>  $stmts
     */
    private function findNonHeaderInsertPosition(array $stmts): int
    {
        foreach ($stmts as $key => $stmt) {
            if ($stmt instanceof Declare_) {
                continue;
            }

            return $key;
        }

        return count($stmts);
    }

    /**
     * @param  list<array{index: int, name: string}>  $entries
     */
    private function areUsesSorted(array $entries): bool
    {
        $names = array_column($entries, 'name');
        $sorted = $names;
        usort($sorted, strcasecmp(...));

        return $names === $sorted;
    }
}
