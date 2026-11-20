<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Internal\DescendantMethodCalls;

/**
 * The grouping rector asks whether a subclass calls a method before it folds
 * keys in that method. The subclass can live in another file, so the index
 * scans the project from disk. These tests build a small project in a temp
 * dir and check the answers.
 */
final class DescendantMethodCallsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        DescendantMethodCalls::reset();
        $this->root = sys_get_temp_dir() . '/descendant-calls-test-' . uniqid('', true);
        mkdir($this->root . '/app/Http/Requests', 0777, true);
        mkdir($this->root . '/vendor/acme', 0777, true);
        file_put_contents($this->root . '/composer.json', '{}');

        $this->writeFile('app/Http/Requests/BasePostRequest.php', $this->source('App\\Http\\Requests', [FormRequest::class], 'BasePostRequest', 'FormRequest', "public function rules(): array { return ['summary.heading' => 'required']; }\n"
        . "protected function sharedRules(): array { return ['meta.title' => 'required']; }"));
        $this->writeFile('app/Http/Requests/StorePostRequest.php', $this->source('App\\Http\\Requests', [], 'StorePostRequest', 'BasePostRequest', "public function rules(): array { return [...parent::rules(), 'summary' => 'array']; }"));
        $this->writeFile('app/Http/Requests/Admin/UpdatePostRequest.php', $this->source('App\\Http\\Requests\\Admin', ['App\\Http\\Requests\\StorePostRequest as BaseStore'], 'UpdatePostRequest', 'BaseStore', "public function rules(): array { return ['meta' => 'array', ...\$this->sharedRules()]; }"));
        // A vendor subclass must not count: the scan skips vendor/.
        $this->writeFile('vendor/acme/VendorRequest.php', $this->source('Acme', [], 'VendorRequest', '\\App\\Http\\Requests\\BasePostRequest', 'public function extra(): array { return \$this->vendorOnly(); }'));
    }

    protected function tearDown(): void
    {
        DescendantMethodCalls::reset();
        $this->removeDir($this->root);

        parent::tearDown();
    }

    #[DataProvider('provideCases')]
    public function testIsCalledByDescendant(string $class, string $method, bool $expected): void
    {
        $this->assertSame($expected, DescendantMethodCalls::isCalledByDescendant($class, $method, $this->root));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideCases(): iterable
    {
        yield 'child calls parent::rules()' => ['App\Http\Requests\BasePostRequest', 'rules', true];
        yield 'method names are case-insensitive' => ['app\http\requests\basepostrequest', 'RULES', true];
        yield 'grandchild in another namespace, through an aliased import' => ['App\Http\Requests\BasePostRequest', 'sharedRules', true];
        yield 'direct child calls $this->sharedRules() only through the grandchild' => ['App\Http\Requests\StorePostRequest', 'sharedRules', true];
        yield 'no descendant calls the method' => ['App\Http\Requests\BasePostRequest', 'messages', false];
        yield 'a vendor subclass is not scanned' => ['App\Http\Requests\BasePostRequest', 'vendorOnly', false];
        yield 'a leaf class has no descendant' => ['App\Http\Requests\Admin\UpdatePostRequest', 'rules', false];
    }

    public function testFindProjectRootWalksUpToComposerJson(): void
    {
        $this->assertSame(
            $this->root,
            DescendantMethodCalls::findProjectRoot($this->root . '/app/Http/Requests/StorePostRequest.php'),
        );
    }

    /**
     * Builds a PHP class source. The parts are joined at runtime, so this
     * test file itself does not look like a subclass to the project scan.
     *
     * @param  list<string>  $imports
     */
    private function source(string $namespace, array $imports, string $class, string $parent, string $body): string
    {
        $uses = implode('', array_map(static fn (string $import): string => 'use ' . $import . ";\n", $imports));

        return "<?php\nnamespace {$namespace};\n{$uses}" . 'class ' . $class . ' extends ' . $parent . "\n{\n{$body}\n}\n";
    }

    private function writeFile(string $relativePath, string $content): void
    {
        $path = $this->root . '/' . $relativePath;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $content);
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
