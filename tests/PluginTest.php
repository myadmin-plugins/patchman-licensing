<?php

declare(strict_types=1);

namespace Detain\MyAdminPatchman\Tests;

use Detain\MyAdminPatchman\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Unit tests for the Detain\MyAdminPatchman\Plugin class.
 *
 * These tests verify class structure, static properties, hook registration,
 * and event handler signatures without invoking external dependencies.
 *
 * @covers \Detain\MyAdminPatchman\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass<Plugin>
     */
    private ReflectionClass $reflection;

    /**
     * Set up the reflection instance used across tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(Plugin::class);
    }

    // ---------------------------------------------------------------
    //  Class structure
    // ---------------------------------------------------------------

    /**
     * Test that the Plugin class can be instantiated.
     *
     * @return void
     */
    public function testClassIsInstantiable(): void
    {
        $this->assertTrue($this->reflection->isInstantiable());
    }

    /**
     * Test that the Plugin class resides in the expected namespace.
     *
     * @return void
     */
    public function testClassNamespace(): void
    {
        $this->assertSame('Detain\\MyAdminPatchman', $this->reflection->getNamespaceName());
    }

    /**
     * Test that the constructor accepts zero arguments.
     *
     * @return void
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertSame(0, $constructor->getNumberOfRequiredParameters());
    }

    // ---------------------------------------------------------------
    //  Static properties
    // ---------------------------------------------------------------

    /**
     * Test that the $name static property has the expected value.
     *
     * @return void
     */
    public function testStaticPropertyName(): void
    {
        $this->assertSame('PatchMan Licensing', Plugin::$name);
    }

    /**
     * Test that the $description static property is a non-empty string.
     *
     * @return void
     */
    public function testStaticPropertyDescription(): void
    {
        $this->assertIsString(Plugin::$description);
        $this->assertNotEmpty(Plugin::$description);
        $this->assertStringContainsString('PatchMan', Plugin::$description);
    }

    /**
     * Test that the $help static property is defined.
     *
     * @return void
     */
    public function testStaticPropertyHelp(): void
    {
        $this->assertIsString(Plugin::$help);
    }

    /**
     * Test that the $module static property equals 'licenses'.
     *
     * @return void
     */
    public function testStaticPropertyModule(): void
    {
        $this->assertSame('licenses', Plugin::$module);
    }

    /**
     * Test that the $type static property equals 'service'.
     *
     * @return void
     */
    public function testStaticPropertyType(): void
    {
        $this->assertSame('service', Plugin::$type);
    }

    /**
     * Test that all expected static properties exist on the class.
     *
     * @return void
     */
    public function testAllStaticPropertiesExist(): void
    {
        $expected = ['name', 'description', 'help', 'module', 'type'];
        foreach ($expected as $property) {
            $this->assertTrue(
                $this->reflection->hasProperty($property),
                "Static property \${$property} should exist"
            );
            $this->assertTrue(
                $this->reflection->getProperty($property)->isStatic(),
                "Property \${$property} should be static"
            );
            $this->assertTrue(
                $this->reflection->getProperty($property)->isPublic(),
                "Property \${$property} should be public"
            );
        }
    }

    // ---------------------------------------------------------------
    //  getHooks()
    // ---------------------------------------------------------------

    /**
     * Test that getHooks returns an array.
     *
     * @return void
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * Test that getHooks is a static method.
     *
     * @return void
     */
    public function testGetHooksIsStatic(): void
    {
        $method = $this->reflection->getMethod('getHooks');
        $this->assertTrue($method->isStatic());
    }

    /**
     * Test that the expected hook keys are registered.
     *
     * @return void
     */
    public function testGetHooksContainsExpectedKeys(): void
    {
        $hooks = Plugin::getHooks();
        $expectedKeys = [
            'licenses.settings',
            'licenses.activate',
            'licenses.reactivate',
            'licenses.deactivate',
            'licenses.deactivate_ip',
            'function.requirements',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $hooks, "Hook '{$key}' should be registered");
        }
    }

    /**
     * Test that hook keys use the module prefix from the $module static property.
     *
     * @return void
     */
    public function testHookKeysUseModulePrefix(): void
    {
        $hooks = Plugin::getHooks();
        $modulePrefix = Plugin::$module . '.';

        $moduleHooks = array_filter(
            array_keys($hooks),
            static fn (string $key): bool => str_starts_with($key, $modulePrefix)
        );

        $this->assertGreaterThanOrEqual(4, count($moduleHooks));
    }

    /**
     * Test that all hook values are valid callable arrays.
     *
     * @return void
     */
    public function testHookValuesAreCallableArrays(): void
    {
        $hooks = Plugin::getHooks();

        foreach ($hooks as $key => $callback) {
            $this->assertIsArray($callback, "Hook '{$key}' callback should be an array");
            $this->assertCount(2, $callback, "Hook '{$key}' callback should have two elements [class, method]");
            $this->assertSame(Plugin::class, $callback[0], "Hook '{$key}' first element should be the Plugin class");
            $this->assertIsString($callback[1], "Hook '{$key}' second element should be a method name string");
            $this->assertTrue(
                $this->reflection->hasMethod($callback[1]),
                "Hook '{$key}' references method '{$callback[1]}' which should exist on Plugin"
            );
        }
    }

    /**
     * Test that activate and reactivate both point to getActivate.
     *
     * @return void
     */
    public function testActivateAndReactivateShareHandler(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertSame($hooks['licenses.activate'], $hooks['licenses.reactivate']);
    }

    /**
     * Test that deactivate and deactivate_ip share the same handler.
     *
     * @return void
     */
    public function testDeactivateAndDeactivateIpShareHandler(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertSame($hooks['licenses.deactivate'], $hooks['licenses.deactivate_ip']);
    }

    // ---------------------------------------------------------------
    //  Event handler method signatures
    // ---------------------------------------------------------------

    /**
     * Provides method names that accept a GenericEvent parameter.
     *
     * @return array<string, array{string}>
     */
    public function eventHandlerMethodProvider(): array
    {
        return [
            'getActivate'    => ['getActivate'],
            'getDeactivate'  => ['getDeactivate'],
            'getChangeIp'    => ['getChangeIp'],
            'getMenu'        => ['getMenu'],
            'getRequirements' => ['getRequirements'],
            'getSettings'    => ['getSettings'],
        ];
    }

    /**
     * Test that each event handler method is public and static.
     *
     * @dataProvider eventHandlerMethodProvider
     *
     * @param string $methodName
     * @return void
     */
    public function testEventHandlerIsPublicStatic(string $methodName): void
    {
        $method = $this->reflection->getMethod($methodName);
        $this->assertTrue($method->isPublic(), "{$methodName} should be public");
        $this->assertTrue($method->isStatic(), "{$methodName} should be static");
    }

    /**
     * Test that each event handler method accepts exactly one parameter.
     *
     * @dataProvider eventHandlerMethodProvider
     *
     * @param string $methodName
     * @return void
     */
    public function testEventHandlerAcceptsOneParameter(string $methodName): void
    {
        $method = $this->reflection->getMethod($methodName);
        $this->assertSame(1, $method->getNumberOfParameters(), "{$methodName} should accept exactly 1 parameter");
    }

    /**
     * Test that each event handler's parameter is type-hinted to GenericEvent.
     *
     * @dataProvider eventHandlerMethodProvider
     *
     * @param string $methodName
     * @return void
     */
    public function testEventHandlerParameterIsGenericEvent(string $methodName): void
    {
        $method = $this->reflection->getMethod($methodName);
        $params = $method->getParameters();
        $type = $params[0]->getType();

        $this->assertNotNull($type, "{$methodName} parameter should have a type hint");
        $this->assertSame(
            GenericEvent::class,
            $type->getName(),
            "{$methodName} parameter should be type-hinted to GenericEvent"
        );
    }

    // ---------------------------------------------------------------
    //  getHooks() hook count (regression guard)
    // ---------------------------------------------------------------

    /**
     * Test that getHooks returns exactly the expected number of hooks.
     *
     * @return void
     */
    public function testGetHooksCount(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertCount(6, $hooks);
    }

    // ---------------------------------------------------------------
    //  Static analysis helpers: verify methods referenced by hooks exist
    // ---------------------------------------------------------------

    /**
     * Test that getSettings is listed in hooks and exists on the class.
     *
     * @return void
     */
    public function testGetSettingsMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('getSettings'));
    }

    /**
     * Test that getRequirements method exists on the class.
     *
     * @return void
     */
    public function testGetRequirementsMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('getRequirements'));
    }

    /**
     * Test that getMenu method exists on the class (not in hooks but present on class).
     *
     * @return void
     */
    public function testGetMenuMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('getMenu'));
    }

    /**
     * Test that getChangeIp method exists on the class (not in hooks but present on class).
     *
     * @return void
     */
    public function testGetChangeIpMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('getChangeIp'));
    }

    // ---------------------------------------------------------------
    //  Description content checks
    // ---------------------------------------------------------------

    /**
     * Test that the description includes the Patchman website URL.
     *
     * @return void
     */
    public function testDescriptionContainsPatchmanUrl(): void
    {
        $this->assertStringContainsString('https://www.patchman.com/', Plugin::$description);
    }

    /**
     * Test that the description mentions selling licenses.
     *
     * @return void
     */
    public function testDescriptionMentionsLicenseSelling(): void
    {
        $this->assertStringContainsString('selling', Plugin::$description);
    }
}
