<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use Rector\PhpParser\Node\FileNode;

/**
 * Helpers for inserting top-level `use <fqcn>;` statements into a namespace
 * at the alphabetically-sorted position, skipping when the class is already
 * imported.
 *
 * Replaces Rector's default pending-imports path for this package because
 * UseAddingPostRector prepends new imports to the top of the use block
 * regardless of existing alphabetical order. See ROADMAP.md and the Finding A
 * entry in the 0.2.0 release notes for context.
 *
 * @internal
 */
trait ManagesNamespaceImports
{
    private function ensureUseImportInNamespace(Namespace_ $namespace, string $fqcn): void
    {
        $pendingNames = $this->pendingUseImportNames($namespace);

        if ($this->hasUseImport($namespace->stmts, $fqcn) || $this->containsNameIgnoringCase($pendingNames, $fqcn)) {
            return;
        }

        $insertAt = $this->findSortedUseInsertPosition($namespace->stmts, $fqcn, $pendingNames);
        $useStmt = new Use_([new UseItem(new Name($fqcn))]);

        array_splice($namespace->stmts, $insertAt, 0, [$useStmt]);
    }

    /**
     * @param  list<string>  $names
     */
    private function containsNameIgnoringCase(array $names, string $fqcn): bool
    {
        foreach ($names as $name) {
            if (strcasecmp($name, $fqcn) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Imports other rules queued on the file that will land in this
     * namespace. Rector's post rector prepends them to the use block of the
     * first namespace only, before or after this rule runs depending on the
     * Rector version. Rector 2.8+ adds them only after the rules stop
     * changing the file, so inserting a queued import here as well would
     * print it twice. Rector drops a queued import that the source file
     * already imports in any namespace, so those names do not count.
     *
     * @return list<string>
     */
    private function pendingUseImportNames(Namespace_ $namespace): array
    {
        $fileNode = $this->getFile()->getFileNode();

        if (! $fileNode instanceof FileNode || $this->firstNamespaceIn($fileNode) !== $namespace) {
            return [];
        }

        $sourceImports = $this->sourceUseImportsIn($fileNode);
        $names = [];

        foreach ($fileNode->getPendingImports()->getUseImports() as $pendingImport) {
            if (! isset($sourceImports[strtolower($pendingImport->getClassName())])) {
                $names[] = $pendingImport->getClassName();
            }
        }

        return $names;
    }

    /**
     * The class imports the parsed file had, in every namespace. Imports a
     * rule inserted have no start line.
     *
     * @return array<string, true>  keyed by lowercase name
     */
    private function sourceUseImportsIn(FileNode $fileNode): array
    {
        $imports = [];

        foreach ($fileNode->stmts as $stmt) {
            $stmts = $stmt instanceof Namespace_ ? $stmt->stmts : [$stmt];

            foreach ($stmts as $inner) {
                foreach ($this->sourceClassImportNames($inner) as $name) {
                    $imports[strtolower($name)] = true;
                }
            }
        }

        return $imports;
    }

    /**
     * Class names a parsed `use` statement imports, read the way Rector reads
     * them: a plain `use`, and a group use whose items carry their own type.
     *
     * @return list<string>
     */
    private function sourceClassImportNames(Stmt $stmt): array
    {
        if ($stmt->getStartLine() < 1) {
            return [];
        }

        if ($stmt instanceof Use_ && $stmt->type === Use_::TYPE_NORMAL) {
            return array_values(array_map(static fn (UseItem $useItem): string => $useItem->name->toString(), $stmt->uses));
        }

        if (! $stmt instanceof GroupUse || $stmt->type !== Use_::TYPE_UNKNOWN) {
            return [];
        }

        $names = [];

        foreach ($stmt->uses as $useItem) {
            if ($useItem->type === Use_::TYPE_NORMAL) {
                $names[] = $stmt->prefix->toString() . '\\' . $useItem->name->toString();
            }
        }

        return $names;
    }

    private function firstNamespaceIn(FileNode $fileNode): ?Namespace_
    {
        foreach ($fileNode->stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Remove a top-level `use <fqcn>;` statement from the namespace. Handles
     * grouped uses by peeling only the matching UseItem from the group (other
     * members preserved) and dropping the whole Use_ only when its UseItem
     * list is emptied. No-op when the import isn't present.
     */
    private function removeUseImportFromNamespace(Namespace_ $namespace, string $fqcn): void
    {
        $target = ltrim($fqcn, '\\');

        foreach ($namespace->stmts as $i => $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            $remaining = [];

            foreach ($stmt->uses as $useItem) {
                if ($useItem->name->toString() === $target) {
                    continue;
                }

                $remaining[] = $useItem;
            }

            if ($remaining === $stmt->uses) {
                continue;
            }

            if ($remaining === []) {
                unset($namespace->stmts[$i]);

                continue;
            }

            $stmt->uses = $remaining;
        }

        $namespace->stmts = array_values($namespace->stmts);
    }

    /**
     * The local name a FQCN is imported under in the namespace — its alias when
     * aliased (`use Foo\Bar as Baz;` → `Baz`), otherwise its short name — or
     * null when it isn't imported. A caller emitting a short type reference must
     * use this: `hasUseImport()` treats an aliased import as present, so a bare
     * short name would otherwise resolve to the current namespace instead of the
     * vendor class.
     */
    private function importedLocalName(Namespace_ $namespace, string $fqcn): ?string
    {
        $target = ltrim($fqcn, '\\');

        foreach ($namespace->stmts as $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $useItem) {
                if ($useItem->name->toString() !== $target) {
                    continue;
                }

                return $useItem->alias instanceof Identifier
                    ? $useItem->alias->toString()
                    : $useItem->name->getLast();
            }
        }

        return null;
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
     * @param  list<string>  $pendingNames  imports Rector will prepend later
     */
    private function findSortedUseInsertPosition(array $stmts, string $fqcn, array $pendingNames = []): int
    {
        $target = ltrim($fqcn, '\\');
        $lastUseIndex = -1;
        $existingOrder = [];
        $alreadyImported = [];

        foreach ($stmts as $key => $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            $lastUseIndex = $key;

            foreach ($stmt->uses as $useItem) {
                $existingOrder[] = ['index' => $key, 'name' => $useItem->name->toString()];
                $alreadyImported[strtolower($useItem->name->toString())] = true;
            }
        }

        if ($lastUseIndex === -1) {
            return $this->findNonHeaderInsertPosition($stmts);
        }

        // Rector prepends queued imports to the use block. Judge the order the
        // block will have then, so the result does not depend on whether the
        // post rector ran before this rule (Rector < 2.8) or after it.
        $prepended = [];

        foreach ($pendingNames as $pendingName) {
            if (! isset($alreadyImported[strtolower($pendingName)])) {
                $prepended[] = ['index' => $existingOrder[0]['index'], 'name' => $pendingName];
            }
        }

        if (! $this->areUsesSorted([...$prepended, ...$existingOrder])) {
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
