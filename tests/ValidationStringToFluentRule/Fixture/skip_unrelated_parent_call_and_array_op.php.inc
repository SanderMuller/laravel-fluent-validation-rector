<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// 0.21.0 #38 false-positive-eliminated regression pin: a descendant
// that has a `parent::*()` call AND an unrelated array op in the
// same method body should NOT trigger the unsafe-parent skip — the
// array op doesn't operate on the parent's return value. Pre-0.21.0
// the loose coexistence heuristic flagged this as a false positive
// (mijntp 0.20.1 dogfood: ServiceProviderAdminPage with 9
// descendants generating noise skips). 0.21.0 data-flow trace
// tightens to direct-only: only flag when an array op's args
// contain a literal `parent::*()` call.
class UnrelatedCoexistenceParentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string',
        ];
    }
}

class UnrelatedCoexistenceChildRequest extends UnrelatedCoexistenceParentRequest
{
    public function authorize(): bool
    {
        // parent::*() call AND array op coexist in the same method
        // body — but the array op operates on a literal array,
        // NOT on parent::authorize()'s return value. Pre-0.21.0
        // false-positive trigger; 0.21.0 correctly inert.
        $allowed = array_keys(['admin' => true, 'editor' => true]);

        return parent::authorize() && in_array('admin', $allowed, true);
    }

    // Child's rules() stays as string-rule shape: fixture-defined
    // ancestor classes don't satisfy `class_exists()` so the child
    // doesn't qualify via FormRequest reflection. The regression
    // value is in proving the PARENT converts cleanly — pre-0.21.0
    // it would have been flagged unsafe via the unrelated array op
    // coexistence; post-0.21.0 it's not.
    public function rules(): array
    {
        return [
            'extra' => 'required|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

// 0.21.0 #38 false-positive-eliminated regression pin: a descendant
// that has a `parent::*()` call AND an unrelated array op in the
// same method body should NOT trigger the unsafe-parent skip — the
// array op doesn't operate on the parent's return value. Pre-0.21.0
// the loose coexistence heuristic flagged this as a false positive
// (mijntp 0.20.1 dogfood: ServiceProviderAdminPage with 9
// descendants generating noise skips). 0.21.0 data-flow trace
// tightens to direct-only: only flag when an array op's args
// contain a literal `parent::*()` call.
class UnrelatedCoexistenceParentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

class UnrelatedCoexistenceChildRequest extends UnrelatedCoexistenceParentRequest
{
    public function authorize(): bool
    {
        // parent::*() call AND array op coexist in the same method
        // body — but the array op operates on a literal array,
        // NOT on parent::authorize()'s return value. Pre-0.21.0
        // false-positive trigger; 0.21.0 correctly inert.
        $allowed = array_keys(['admin' => true, 'editor' => true]);

        return parent::authorize() && in_array('admin', $allowed, true);
    }

    // Child's rules() stays as string-rule shape: fixture-defined
    // ancestor classes don't satisfy `class_exists()` so the child
    // doesn't qualify via FormRequest reflection. The regression
    // value is in proving the PARENT converts cleanly — pre-0.21.0
    // it would have been flagged unsafe via the unrelated array op
    // coexistence; post-0.21.0 it's not.
    public function rules(): array
    {
        return [
            'extra' => 'required|string',
        ];
    }
}

?>
