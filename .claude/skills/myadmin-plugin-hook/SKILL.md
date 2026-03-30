---
name: myadmin-plugin-hook
description: Adds or modifies Symfony EventDispatcher hooks in src/Plugin.php following the getHooks()/GenericEvent pattern. Ensures correct guard (get_service_define), stopPropagation(), and myadmin_log() calls. Use when adding new event handlers to Plugin::getHooks(), wiring licenses.* events, or implementing getMenu/getSettings/getRequirements. Trigger phrases: 'add hook', 'new event', 'plugin method', 'register handler'. Do NOT use for src/patchman.inc.php API functions.
---
# myadmin-plugin-hook

## Critical

- Every service-scoped handler MUST guard with `$event['category'] == get_service_define('PATCHMAN')` before acting — skip this and the handler fires for ALL license types.
- ALWAYS call `$event->stopPropagation()` as the last statement inside the guard block.
- NEVER call `$event->stopPropagation()` outside the guard — it would block other plugins from handling the event.
- Only add hooks to `getHooks()` that have a corresponding `public static function` in the same class.

## Instructions

1. **Register the hook in `getHooks()`** (`src/Plugin.php`).
   Map the event name (using `self::$module` prefix for module events) to `[__CLASS__, 'methodName']`:
   ```php
   public static function getHooks()
   {
       return [
           self::$module.'.settings'      => [__CLASS__, 'getSettings'],
           self::$module.'.activate'      => [__CLASS__, 'getActivate'],
           self::$module.'.reactivate'    => [__CLASS__, 'getActivate'],
           self::$module.'.deactivate'    => [__CLASS__, 'getDeactivate'],
           self::$module.'.deactivate_ip' => [__CLASS__, 'getDeactivate'],
           self::$module.'.change_ip'     => [__CLASS__, 'getChangeIp'],
           'function.requirements'        => [__CLASS__, 'getRequirements'],
       ];
   }
   ```
   Verify the event name string matches what `run_event()` dispatches in the parent system before proceeding.

2. **Add the handler method** with the exact signature:
   ```php
   /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
   public static function getActivate(GenericEvent $event)
   {
       $serviceClass = $event->getSubject();
       if ($event['category'] == get_service_define('PATCHMAN')) {
           myadmin_log(self::$module, 'info', 'Patchman Activation', __LINE__, __FILE__, self::$module, $serviceClass->getId());
           // perform action here
           $event->stopPropagation();
       }
   }
   ```
   For error states, set `$event['status'] = 'error'` and `$event['status_text'] = '...'` before `stopPropagation()`.

3. **`getMenu` handler** — no category guard needed; use `$GLOBALS['tf']->ima == 'admin'` to restrict to admins:
   ```php
   public static function getMenu(GenericEvent $event)
   {
       $menu = $event->getSubject();
       if ($GLOBALS['tf']->ima == 'admin') {
           $menu->add_link(self::$module, 'choice=none.patchman_list', '/images/myadmin/to-do.png', _('Patchman Licenses Breakdown'));
       }
   }
   ```
   Verify `self::$module` matches the menu section where links should appear.

4. **`getRequirements` handler** — registers lazy-loaded function files:
   ```php
   public static function getRequirements(GenericEvent $event)
   {
       $loader = $event->getSubject();
       $loader->add_requirement('activate_patchman', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
       $loader->add_requirement('deactivate_patchman', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
   }
   ```
   Use `add_admin_page_requirement()` for admin-only pages; `add_requirement()` for general functions.

5. **`getSettings` handler** — adds settings fields to the admin settings page:
   ```php
   public static function getSettings(GenericEvent $event)
   {
       $settings = $event->getSubject();
       $settings->add_text_setting(self::$module, _('PatchMan'), 'patchman_username', _('Patchman Username'), _('Patchman Username'), $settings->get_setting('PATCHMAN_USERNAME'));
       $settings->add_password_setting(self::$module, _('PatchMan'), 'patchman_password', _('Patchman Password'), _('Patchman Password'), $settings->get_setting('PATCHMAN_PASSWORD'));
       $settings->add_dropdown_setting(self::$module, _('PatchMan'), 'outofstock_licenses_patchman', _('Out Of Stock'), _('Enable/Disable Sales'), $settings->get_setting('OUTOFSTOCK_LICENSES_PATCHMAN'), ['0', '1'], ['No', 'Yes']);
   }
   ```
   Setting key constant is the uppercase version of the field name (e.g. `patchman_username` → `PATCHMAN_USERNAME`).

6. **Run tests** to confirm registration is valid:
   ```bash
   vendor/bin/phpunit tests/PluginTest.php
   ```

## Examples

**User says:** "Add a change_ip hook to the patchman plugin"

**Actions taken:**
1. Add `self::$module.'.change_ip' => [__CLASS__, 'getChangeIp']` to `getHooks()`.
2. Add handler to `src/Plugin.php`:
```php
public static function getChangeIp(GenericEvent $event)
{
    if ($event['category'] == get_service_define('PATCHMAN')) {
        $serviceClass = $event->getSubject();
        $settings = get_module_settings(self::$module);
        myadmin_log(self::$module, 'info', 'IP Change - (OLD:'.$serviceClass->getIp().") (NEW:{$event['newip']})", __LINE__, __FILE__, self::$module, $serviceClass->getId());
        // call API, update DB, set $event['status']
        $serviceClass->set_ip($event['newip'])->save();
        $event['status'] = 'ok';
        $event['status_text'] = 'The IP Address has been changed.';
        $event->stopPropagation();
    }
}
```
3. Run `vendor/bin/phpunit tests/PluginTest.php` — all tests pass.

**Result:** `licenses.change_ip` events for PatchMan are now intercepted and handled.

## Common Issues

- **Handler fires for wrong license type:** Missing `$event['category'] == get_service_define('PATCHMAN')` guard. Add it as the outermost `if` in the method.
- **`get_service_define('PATCHMAN')` returns null / undefined constant:** The define isn't loaded yet. Add `function_requirements('get_service_define')` at the top of the method, or ensure it's registered in `getRequirements()`.
- **`stopPropagation()` blocks sibling plugins:** Ensure the call is inside the category guard block, not outside it.
- **PHPUnit: method not found on reflection:** The method exists but isn't listed in `getHooks()`. Add the mapping and re-run `vendor/bin/phpunit tests/PluginTest.php`.
- **Settings constant mismatch (e.g. `PATCHMAN_USER` vs `PATCHMAN_USERNAME`):** `get_setting()` argument must be the exact uppercase constant name. Check `src/patchman.inc.php` for the `define()` calls to confirm the constant name.