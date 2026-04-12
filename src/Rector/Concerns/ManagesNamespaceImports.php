<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;

/**
 * Helpers for inserting top-level `use <fqcn>;` statements into a namespace
 * at the alphabetically-sorted position, skipping when the class is already
 * imported.
 *
 * Replaces Rector's UseNodesToAddCollector path for this package because
 * UseAddingPostRector prepends new imports to the top of the use block
 * regardless of existing alphabetical order. See ROADMAP.md and the Finding A
 * entry in the 0.2.0 release notes for context.
 */
trait ManagesNamespaceImports
{
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
