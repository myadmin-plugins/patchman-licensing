# MyAdmin PatchMan Licensing Plugin

Composer package for event-driven PatchMan license provisioning for the MyAdmin control panel. Handles activate, deactivate, IP change, and admin menu integration.

## Commands

```bash
composer install                        # install deps including phpunit/phpunit ^9.6
vendor/bin/phpunit                      # run all tests (phpunit.xml.dist config)
vendor/bin/phpunit tests/PluginTest.php # run single test file
```

## Architecture

- **Plugin class**: `src/Plugin.php` — registers all Symfony EventDispatcher hooks via `getHooks()`
- **API functions**: `src/patchman.inc.php` — procedural functions loaded via `function_requirements()`
- **CLI scripts**: `bin/create_patchman_license.php` · `bin/patchman_licenses.php` — require parent `include/functions.inc.php`
- **Tests**: `tests/PatchmanFunctionsTest.php` (source inspection) · `tests/PluginTest.php` (reflection-based)
- **CI/CD**: `.github/` contains workflows (e.g. `.github/workflows/tests.yml`) for automated test runs on push
- **IDE config**: `.idea/` holds inspectionProfiles, deployment.xml, and encodings.xml for PhpStorm project settings
- **Namespace**: `Detain\MyAdminPatchman\` → `src/` · `Detain\MyAdminPatchman\Tests\` → `tests/`

## Event Hook Pattern

All hooks registered in `Plugin::getHooks()` as `['module.event' => [ClassName, 'methodName']]`:

```php
public static function getHooks() {
    return [
        self::$module.'.settings'     => [__CLASS__, 'getSettings'],
        self::$module.'.activate'     => [__CLASS__, 'getActivate'],
        self::$module.'.reactivate'   => [__CLASS__, 'getActivate'],
        self::$module.'.deactivate'   => [__CLASS__, 'getDeactivate'],
        self::$module.'.deactivate_ip'=> [__CLASS__, 'getDeactivate'],
        'function.requirements'       => [__CLASS__, 'getRequirements'],
    ];
}
```

- Handler methods accept `GenericEvent $event`, call `$event->stopPropagation()` after handling
- Guard with `$event['category'] == get_service_define('PATCHMAN')` before acting
- Log with `myadmin_log(self::$module, 'info', $message, __LINE__, __FILE__, self::$module, $serviceClass->getId())`

## PatchMan API Pattern

All API calls go through `patchman_req($page, $post, $options)` in `src/patchman.inc.php`:

```php
// Reads PATCHMAN_USERNAME / PATCHMAN_PASSWORD constants
$response = patchman_req('list');                          // GET-style
$response = patchman_req($url, $postArray, $curlOptions); // POST with overrides
```

- Base URL: `https://www.patchman.co/` for API · `https://www.patchman.com/cgi-bin/` for actions
- Always uses `CURLOPT_HTTPAUTH => CURLAUTH_BASIC` with `PATCHMAN_USERNAME:PATCHMAN_PASSWORD`
- `activate_patchman($ipAddress, $ostype, $pass, $email, $name, $domain, $custid)` — checks existing license first
- `deactivate_patchman($ipAddress)` — only acts when `$license['active'] == 'Y'`

## Database / Billing Pattern

```php
$db = get_module_db($module);
$settings = get_module_settings('licenses'); // returns PREFIX, TABLE, TBLNAME
$db->query(make_insert_query($settings['TABLE'], [
    $settings['PREFIX'].'_type'   => $package_id,
    $settings['PREFIX'].'_custid' => $custid,
    // ...
]), __LINE__, __FILE__);
$id = $db->getLastInsertId($settings['TABLE'], $settings['PREFIX'].'_id');
```

- Create billing with `new \MyAdmin\Orm\Repeat_Invoice($db)` → `->invoice($now, $cost, false)`
- Never use PDO — always `get_module_db($module)`

## Conventions

- Module: `licenses` · Service define: `PATCHMAN` · Package ID: `5081` · Cost: `$20`
- Requirements registered via `$loader->add_requirement('func_name', __DIR__.'/../src/patchman.inc.php')`
- Settings added via `$settings->add_text_setting()` / `->add_password_setting()` / `->add_dropdown_setting()`
- Commit messages: lowercase descriptive (`patchman updates`, `fix ip change handler`)
- Tabs for indentation (see `.scrutinizer.yml` coding style)

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
