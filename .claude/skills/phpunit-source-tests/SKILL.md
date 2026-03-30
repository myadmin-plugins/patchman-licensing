---
name: phpunit-source-tests
description: Writes PHPUnit 9.6 tests using source-inspection (file_get_contents + assertStringContainsString / assertMatchesRegularExpression) and reflection (ReflectionClass) patterns. Use when adding tests for new functions in src/patchman.inc.php or new methods in src/Plugin.php. Trigger phrases: 'write test', 'add phpunit', 'test function', 'test plugin'. Do NOT use for integration tests requiring a live database or live cURL calls.
---
# phpunit-source-tests

## Critical

- Never instantiate `Plugin` or call `patchman_req()` at test time — both require live DB/cURL. All tests must be **static analysis only**: read the file or use `ReflectionClass`.
- All test files go in `tests/`, namespace `Detain\MyAdminPatchman\Tests`, extend `PHPUnit\Framework\TestCase`.
- Run tests with `composer test` (config: `phpunit.xml.dist`). Every new test file must pass before committing.
- Use `declare(strict_types=1)` at the top of every test file.

## Instructions

### Testing procedural functions in `src/patchman.inc.php`

1. **Create the test class** in `tests/` (e.g. alongside `tests/PatchmanFunctionsTest.php`):
   ```php
   <?php
   declare(strict_types=1);
   namespace Detain\MyAdminPatchman\Tests;
   use PHPUnit\Framework\TestCase;
   /**
    * @covers ::my_new_function
    */
   class MyNewFunctionTest extends TestCase
   {
       private static string $source;
       public static function setUpBeforeClass(): void {
           self::$source = file_get_contents(dirname(__DIR__) . '/src/patchman.inc.php');
       }
   }
   ```
   Verify `dirname(__DIR__) . '/src/patchman.inc.php'` resolves correctly from `tests/`.

2. **Add a file-exists guard** (always first test in a new class):
   ```php
   public function testIncludeFileExists(): void {
       $this->assertFileExists(dirname(__DIR__) . '/src/patchman.inc.php');
   }
   ```

3. **Assert function declaration** using `assertStringContainsString`:
   ```php
   public function testMyNewFunctionDeclared(): void {
       $this->assertStringContainsString('function my_new_function(', self::$source);
   }
   ```

4. **Assert parameter signature** using `assertMatchesRegularExpression` with `/s` flag for multi-line spans:
   ```php
   public function testMyNewFunctionSignature(): void {
       $this->assertMatchesRegularExpression(
           '/function\s+my_new_function\s*\(\s*\$param1\s*,\s*\$param2\s*=/',
           self::$source,
           'my_new_function should have $param1 and $param2 with default'
       );
   }
   ```

5. **Assert internal behaviour** (constants, literals, delegating calls) with `assertStringContainsString`:
   ```php
   public function testMyNewFunctionUsesExpectedConstant(): void {
       $this->assertStringContainsString('PATCHMAN_USERNAME', self::$source);
   }
   ```

6. **Assert return paths** with a cross-function regex (use `/s` dotall):
   ```php
   public function testMyNewFunctionReturnsFalseOnMiss(): void {
       $this->assertMatchesRegularExpression(
           '/function\s+my_new_function.*?return\s+false;/s',
           self::$source
       );
   }
   ```
   Verify the pattern matches by running the test via `composer test`.

### Testing class methods in `src/Plugin.php`

1. **Create the test class** with `ReflectionClass` setup (e.g. alongside `tests/PluginTest.php`):
   ```php
   <?php
   declare(strict_types=1);
   namespace Detain\MyAdminPatchman\Tests;
   use Detain\MyAdminPatchman\Plugin;
   use PHPUnit\Framework\TestCase;
   use ReflectionClass;
   use Symfony\Component\EventDispatcher\GenericEvent;
   /**
    * @covers \Detain\MyAdminPatchman\Plugin
    */
   class MyPluginMethodTest extends TestCase
   {
       private ReflectionClass $reflection;
       protected function setUp(): void {
           $this->reflection = new ReflectionClass(Plugin::class);
       }
   }
   ```

2. **Assert a new method exists and is public+static**:
   ```php
   public function testMyNewMethodExists(): void {
       $this->assertTrue($this->reflection->hasMethod('myNewMethod'));
   }
   public function testMyNewMethodIsPublicStatic(): void {
       $method = $this->reflection->getMethod('myNewMethod');
       $this->assertTrue($method->isPublic());
       $this->assertTrue($method->isStatic());
   }
   ```

3. **Assert event handler accepts exactly one `GenericEvent` parameter** (required for all hook handlers):
   ```php
   public function testMyNewMethodAcceptsGenericEvent(): void {
       $method = $this->reflection->getMethod('myNewMethod');
       $this->assertSame(1, $method->getNumberOfParameters());
       $type = $method->getParameters()[0]->getType();
       $this->assertSame(GenericEvent::class, (string) $type);
   }
   ```

4. **Assert a new hook is registered in `getHooks()`**:
   ```php
   public function testNewHookRegistered(): void {
       $hooks = Plugin::getHooks();
       $this->assertArrayHasKey('licenses.my_event', $hooks);
       $this->assertSame([Plugin::class, 'myNewMethod'], $hooks['licenses.my_event']);
   }
   ```
   Verify the hook key matches the format `Plugin::$module . '.event_name'`.

5. **Update the hook-count regression test** in `tests/PluginTest.php` when adding hooks:
   ```php
   // Change assertCount(6, $hooks) to assertCount(7, $hooks)
   ```

## Examples

**User says:** "Write tests for a new `get_patchman_server_count()` function I added to `src/patchman.inc.php`."

**Actions taken:**
1. Read `src/patchman.inc.php` to see the actual signature and internal literals.
2. Create a new test class in `tests/` with `setUpBeforeClass` loading `self::$source`.
3. Add tests: file exists, function declared, signature regex, internal constant/string assertions, return-value path regex.
4. Run `composer test` — all pass.

**Result:**
```php
public function testGetPatchmanServerCountDeclared(): void {
    $this->assertStringContainsString('function get_patchman_server_count(', self::$source);
}
public function testGetPatchmanServerCountSignature(): void {
    $this->assertMatchesRegularExpression(
        '/function\s+get_patchman_server_count\s*\(\s*\)/',
        self::$source
    );
}
```

## Common Issues

- **`assertMatchesRegularExpression` fails silently** — test passes even though pattern is wrong: add the third `$message` argument and run with `--verbose` to see the actual source snippet. Use `preg_match('/pattern/s', self::$source, $m); var_dump($m);` in a throwaway test to debug.
- **Cross-function regex matches wrong function**: if `/function\s+foo.*?bar/s` matches into the next function's body, tighten the pattern with a closing-brace anchor or use `assertStringContainsString` on a literal instead.
- **`ReflectionClass` throws `ReflectionException: Class not found`**: confirm `composer dump-autoload` has been run and the class file path matches the PSR-4 mapping `Detain\MyAdminPatchman\` → `src/` in `composer.json`.
- **`getType()->getName()` returns null on PHP < 8.0**: use `(string) $type` instead of `->getName()` when targeting PHP 7.4 compatibility.
- **Hook count assertion fails after adding a hook**: update `assertCount(N, $hooks)` in `tests/PluginTest.php::testGetHooksCount()` to the new total — this is an intentional regression guard.
