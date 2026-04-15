<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;

/**
 * Heuristic Livewire-class identification. A class counts as Livewire if it
 * either extends `Livewire\Component` / `Livewire\Form` directly, or declares
 * a `render()` method — the same signature Livewire uses to discover the
 * view template.
 *
 * Conservative on purpose: the rectors that gate on this don't want to touch
 * Actions, FormRequests, Controllers, or DataObjects that happen to have a
 * `validate()` method call. False negatives (missing a Livewire class) are
 * preferable to false positives (acting on a non-Livewire class).
 *
 * @phpstan-require-extends AbstractRector
 */
trait IdentifiesLivewireClasses
{
    private function isLivewireClass(Class_ $class): bool
    {
        if ($class->extends instanceof Name) {
            $parentName = $this->getName($class->extends);

            if (in_array($parentName, ['Livewire\Component', 'Livewire\Form'], true)) {
                return true;
            }
        }

        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'render')) {
                return true;
            }
        }

        return false;
    }
}
