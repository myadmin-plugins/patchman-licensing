---
name: patchman-api
description: Implements PatchMan API calls using the patchman_req() pattern in src/patchman.inc.php. Handles activate, deactivate, list, and IP lookup flows with curl and basic auth. Use when adding new PatchMan API endpoints, modifying activate_patchman() / deactivate_patchman(), or adding functions to src/patchman.inc.php. Trigger phrases: 'call patchman api', 'add patchman function', 'activate license', 'deactivate license'. Do NOT use for Plugin.php hook wiring.
---
# PatchMan API

## Critical

- **Never interpolate `$_GET`/`$_POST` directly** into queries — always `$db->real_escape()` or `make_insert_query()`.
- **Never call the PatchMan API without checking for an existing license first** — `activate_patchman()` must call `get_patchman_license_by_ip($ip)` before creating a new one.
- **Never deactivate unless `$license['active'] == 'Y'`** — check this guard in every deactivate path.
- All API functions live in `src/patchman.inc.php` as procedural functions — no classes.
- Constants `PATCHMAN_USERNAME` and `PATCHMAN_PASSWORD` must be defined before any `patchman_req()` call.

## Instructions

1. **Route all HTTP calls through `patchman_req()`.**
   ```php
   // GET-style (page name only — .php and path prefix auto-appended)
   $response = patchman_req('list');

   // POST with overrides
   $response = patchman_req($url, $postArray, $optionsArray);
   ```
   Default curl options applied automatically: `CURLOPT_HTTPAUTH => CURLAUTH_BASIC`, `CURLOPT_USERPWD => PATCHMAN_USERNAME.':'.PATCHMAN_PASSWORD`, SSL verify disabled.
   Verify `patchman_req()` exists in `src/patchman.inc.php` before adding a new wrapper.

2. **Use the correct base URLs.**
   - Read-only API: `https://www.patchman.co/` — page name resolved to `clients/api/{page}.php`
   - Mutating actions (create/delete): `https://www.patchman.com/cgi-bin/{action}` — pass as full URL
   - Always set `CURLOPT_REFERER` in `$options` for mutating calls (see `activate_patchman()` / `deactivate_patchman()`).

3. **Parse list responses with `parse_str()` per line.**
   ```php
   $lines = explode("\n", trim($response));
   foreach (array_values($lines) as $line) {
       parse_str($line, $entry);
       $results[$entry['lid']] = $entry;
   }
   ```
   Guard with `if (trim($response) == '') { return []; }` before parsing.

4. **Look up by IP via `get_patchman_license_by_ip()`.**
   ```php
   $license = get_patchman_license_by_ip($ipAddress);
   if ($license === false) { /* not found */ }
   ```
   Use `patchman_ip_to_lid($ip)` when you only need the `lid`.
   Verify these helpers exist before adding a new IP-lookup wrapper.

5. **Activate pattern** — check first, then POST to `cgi-bin/createlicense`.
   ```php
   function activate_patchman($ipAddress, $ostype, $pass, $email, $name, $domain = '', $custid = null)
   {
       myadmin_log('licenses', 'info', "Called activate_patchman($ipAddress,...)", __LINE__, __FILE__);
       $license = get_patchman_license_by_ip($ipAddress);
       if ($license === false) {
           $url = 'https://www.patchman.com/cgi-bin/createlicense';
           $post = ['uid' => PATCHMAN_USERNAME, 'password' => PATCHMAN_PASSWORD, 'api' => 1, ...];
           $options = [CURLOPT_REFERER => 'https://www.patchman.com/clients/createlicense.php'];
           $response = patchman_req($url, $post, $options);
           myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
           if (preg_match('/lid=(\d+)&/', $response, $matches)) {
               $lid = $matches[1];
               // optional follow-up call, e.g. patchman_makepayment($lid)
           }
       }
   }
   ```

6. **Deactivate pattern** — guard on `active == 'Y'`, POST to `cgi-bin/deletelicense`.
   ```php
   function deactivate_patchman($ipAddress)
   {
       $license = get_patchman_license_by_ip($ipAddress);
       if ($license['active'] == 'Y') {
           $url = 'https://www.patchman.com/cgi-bin/deletelicense';
           $post = ['uid' => PATCHMAN_USERNAME, 'password' => PATCHMAN_PASSWORD, 'api' => 1, 'lid' => $license['lid']];
           $options = [CURLOPT_REFERER => 'https://www.patchman.com/clients/license.php?lid='.$license['lid']];
           $response = patchman_req($url, $post, $options);
           myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
           return $response;
       }
   }
   ```

7. **Log every API call and response** with `myadmin_log('licenses', 'info', $message, __LINE__, __FILE__)`.

## Examples

**User says:** "Add a function to suspend a PatchMan license by IP"

**Actions taken:**
1. Check `get_patchman_license_by_ip()` exists in `src/patchman.inc.php`.
2. Add to `src/patchman.inc.php`:
```php
function suspend_patchman($ipAddress)
{
    $license = get_patchman_license_by_ip($ipAddress);
    if ($license === false) {
        myadmin_log('licenses', 'info', "No PatchMan license found for {$ipAddress}", __LINE__, __FILE__);
        return false;
    }
    if ($license['active'] == 'Y') {
        $url = 'https://www.patchman.com/cgi-bin/suspendlicense';
        $post = [
            'uid'      => PATCHMAN_USERNAME,
            'password' => PATCHMAN_PASSWORD,
            'api'      => 1,
            'lid'      => $license['lid']
        ];
        $options = [CURLOPT_REFERER => 'https://www.patchman.com/clients/license.php?lid='.$license['lid']];
        $response = patchman_req($url, $post, $options);
        myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
        return $response;
    }
    return null;
}
```
3. Run `vendor/bin/phpunit` to confirm no regressions.

## Common Issues

- **Empty string returned from `patchman_req('list')`:** `PATCHMAN_USERNAME` / `PATCHMAN_PASSWORD` constants are undefined. Check that `function_requirements('patchman_req')` has been called and the credentials constants are loaded.
- **`parse_str()` produces empty array:** Response line has no `=` delimiters — the API returned an error string. Log the raw `$response` with `myadmin_log` and inspect it.
- **`$license['active']` undefined / notice:** `get_patchman_license_by_ip()` returned `false`. Always check `$license === false` before accessing keys.
- **cgi-bin action returns HTML error page:** Missing or wrong `CURLOPT_REFERER`. Match the referer to the corresponding `clients/` page (e.g. `clients/createlicense.php` for create, `clients/license.php?lid=N` for delete/suspend).
- **Duplicate license created:** `activate_patchman()` was called without the `get_patchman_license_by_ip()` guard. Ensure the existence check wraps the entire POST block.