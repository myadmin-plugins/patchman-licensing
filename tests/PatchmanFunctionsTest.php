<?php

declare(strict_types=1);

namespace Detain\MyAdminPatchman\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for procedural functions in patchman.inc.php.
 *
 * Because these functions rely heavily on global state, database access, and
 * cURL calls, the tests focus on:
 *   - Function existence and signatures (static analysis)
 *   - Pure logic paths that can be exercised without external deps
 *   - Parameter type/count verification via reflection
 *
 * @covers ::patchman_req
 * @covers ::get_patchman_licenses
 * @covers ::get_patchman_license
 * @covers ::get_patchman_license_by_ip
 * @covers ::patchman_ip_to_lid
 * @covers ::activate_patchman
 * @covers ::deactivate_patchman
 * @covers ::patchman_deactivate
 * @covers ::add_patchman
 */
class PatchmanFunctionsTest extends TestCase
{
    /**
     * @var string Cached source contents of patchman.inc.php.
     */
    private static string $source;

    /**
     * Load the source file content once for all tests.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$source = file_get_contents(dirname(__DIR__) . '/src/patchman.inc.php');
    }

    // ---------------------------------------------------------------
    //  File-level checks
    // ---------------------------------------------------------------

    /**
     * Test that the patchman.inc.php source file exists.
     *
     * @return void
     */
    public function testIncludeFileExists(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/src/patchman.inc.php');
    }

    /**
     * Test that the source file is valid PHP that can be tokenized.
     *
     * @return void
     */
    public function testSourceFileIsTokenizable(): void
    {
        $tokens = @token_get_all(self::$source);
        $this->assertNotEmpty($tokens, 'patchman.inc.php should be parseable PHP');
        // First token should be T_OPEN_TAG
        $this->assertSame(T_OPEN_TAG, $tokens[0][0], 'File should start with a PHP open tag');
    }

    /**
     * Test that the source file contains the expected function declarations.
     *
     * @return void
     */
    public function testExpectedFunctionsDeclaredInSource(): void
    {
        $expectedFunctions = [
            'add_patchman',
            'patchman_req',
            'get_patchman_licenses',
            'get_patchman_license',
            'get_patchman_license_by_ip',
            'patchman_ip_to_lid',
            'activate_patchman',
            'deactivate_patchman',
            'patchman_deactivate',
        ];

        foreach ($expectedFunctions as $fn) {
            $this->assertStringContainsString(
                "function {$fn}(",
                self::$source,
                "Function {$fn}() should be declared in patchman.inc.php"
            );
        }
    }

    /**
     * Test that the source file declares the exact expected number of functions.
     *
     * @return void
     */
    public function testFunctionCountInSource(): void
    {
        preg_match_all('/^function\s+\w+\s*\(/m', self::$source, $matches);
        $this->assertCount(9, $matches[0], 'patchman.inc.php should declare exactly 9 functions');
    }

    // ---------------------------------------------------------------
    //  patchman_req URL building logic (static analysis of source)
    // ---------------------------------------------------------------

    /**
     * Test that patchman_req references the expected Patchman API base URL.
     *
     * @return void
     */
    public function testPatchmanReqContainsBaseUrl(): void
    {
        $this->assertStringContainsString('https://www.patchman.co/', self::$source);
    }

    /**
     * Test that patchman_req uses CURLOPT_USERPWD for authentication.
     *
     * @return void
     */
    public function testPatchmanReqUsesBasicAuth(): void
    {
        $this->assertStringContainsString('CURLOPT_USERPWD', self::$source);
        $this->assertStringContainsString('CURLAUTH_BASIC', self::$source);
    }

    /**
     * Test that patchman_req has the correct parameter signature.
     *
     * @return void
     */
    public function testPatchmanReqParameterSignature(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+patchman_req\s*\(\s*\$page\s*,\s*\$post\s*=/',
            self::$source,
            'patchman_req should have $page as first param and $post with default'
        );
    }

    // ---------------------------------------------------------------
    //  get_patchman_licenses (static analysis)
    // ---------------------------------------------------------------

    /**
     * Test that get_patchman_licenses initializes an empty array for licenses.
     *
     * @return void
     */
    public function testGetPatchmanLicensesInitializesEmptyArray(): void
    {
        $this->assertStringContainsString('$licenses = []', self::$source);
    }

    /**
     * Test that get_patchman_licenses uses parse_str for response parsing.
     *
     * @return void
     */
    public function testGetPatchmanLicensesUsesParseStr(): void
    {
        $this->assertStringContainsString('parse_str($line, $license)', self::$source);
    }

    // ---------------------------------------------------------------
    //  get_patchman_license_by_ip (static analysis)
    // ---------------------------------------------------------------

    /**
     * Test that get_patchman_license_by_ip returns false when no match found.
     *
     * @return void
     */
    public function testGetPatchmanLicenseByIpReturnsFalseOnNoMatch(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+get_patchman_license_by_ip.*?return\s+false;/s',
            self::$source,
            'get_patchman_license_by_ip should return false when no IP matches'
        );
    }

    // ---------------------------------------------------------------
    //  patchman_ip_to_lid (static analysis)
    // ---------------------------------------------------------------

    /**
     * Test that patchman_ip_to_lid delegates to get_patchman_license_by_ip.
     *
     * @return void
     */
    public function testPatchmanIpToLidDelegatesToGetByIp(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+patchman_ip_to_lid.*?get_patchman_license_by_ip/s',
            self::$source,
            'patchman_ip_to_lid should call get_patchman_license_by_ip'
        );
    }

    /**
     * Test that patchman_ip_to_lid returns false when license not found.
     *
     * @return void
     */
    public function testPatchmanIpToLidReturnsFalseOnMissing(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+patchman_ip_to_lid.*?return\s+false;/s',
            self::$source
        );
    }

    /**
     * Test that patchman_ip_to_lid returns the lid field on match.
     *
     * @return void
     */
    public function testPatchmanIpToLidReturnsLidField(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+patchman_ip_to_lid.*?return\s+\$license\[.lid.\]/s',
            self::$source
        );
    }

    // ---------------------------------------------------------------
    //  activate_patchman (static analysis)
    // ---------------------------------------------------------------

    /**
     * Test that activate_patchman accepts the expected parameters.
     *
     * @return void
     */
    public function testActivatePatchmanSignature(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+activate_patchman\s*\(\s*\$ipAddress\s*,\s*\$ostype\s*,\s*\$pass\s*,\s*\$email\s*,\s*\$name\s*,/',
            self::$source,
            'activate_patchman should accept $ipAddress, $ostype, $pass, $email, $name and more'
        );
    }

    /**
     * Test that activate_patchman uses the Patchman creation endpoint.
     *
     * @return void
     */
    public function testActivatePatchmanUsesCreateEndpoint(): void
    {
        $this->assertStringContainsString(
            'https://www.patchman.com/cgi-bin/createlicense',
            self::$source
        );
    }

    /**
     * Test that activate_patchman checks for existing license before creating.
     *
     * @return void
     */
    public function testActivatePatchmanChecksExistingLicense(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+activate_patchman.*?get_patchman_license_by_ip/s',
            self::$source,
            'activate_patchman should check for existing license by IP'
        );
    }

    // ---------------------------------------------------------------
    //  deactivate_patchman (static analysis)
    // ---------------------------------------------------------------

    /**
     * Test that deactivate_patchman uses the delete endpoint.
     *
     * @return void
     */
    public function testDeactivatePatchmanUsesDeleteEndpoint(): void
    {
        $this->assertStringContainsString(
            'https://www.patchman.com/cgi-bin/deletelicense',
            self::$source
        );
    }

    /**
     * Test that deactivate_patchman checks the active status.
     *
     * @return void
     */
    public function testDeactivatePatchmanChecksActiveStatus(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+deactivate_patchman.*?\$license\[.active.\]\s*==\s*.Y./s',
            self::$source,
            'deactivate_patchman should check if license active == Y'
        );
    }

    // ---------------------------------------------------------------
    //  patchman_deactivate (alias check)
    // ---------------------------------------------------------------

    /**
     * Test that patchman_deactivate is an alias for deactivate_patchman.
     *
     * @return void
     */
    public function testPatchmanDeactivateIsAlias(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+patchman_deactivate.*?return\s+deactivate_patchman\s*\(/s',
            self::$source,
            'patchman_deactivate should delegate to deactivate_patchman'
        );
    }

    // ---------------------------------------------------------------
    //  add_patchman (static analysis)
    // ---------------------------------------------------------------

    /**
     * Test that add_patchman references the expected package ID.
     *
     * @return void
     */
    public function testAddPatchmanUsesExpectedPackageId(): void
    {
        $this->assertStringContainsString('$package_id = 5081', self::$source);
    }

    /**
     * Test that add_patchman sets service cost to 20.
     *
     * @return void
     */
    public function testAddPatchmanServiceCost(): void
    {
        $this->assertStringContainsString('$service_cost = 20', self::$source);
    }

    /**
     * Test that add_patchman checks for existing license before adding.
     *
     * @return void
     */
    public function testAddPatchmanChecksExistingLicense(): void
    {
        $this->assertStringContainsString('Already Licensed for PatchMan', self::$source);
    }

    /**
     * Test that add_patchman uses Repeat_Invoice ORM class.
     *
     * @return void
     */
    public function testAddPatchmanUsesRepeatInvoice(): void
    {
        $this->assertStringContainsString('Repeat_Invoice', self::$source);
    }

    /**
     * Test that add_patchman has no required parameters.
     *
     * @return void
     */
    public function testAddPatchmanHasNoParameters(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+add_patchman\s*\(\s*\)/',
            self::$source,
            'add_patchman should have no parameters (reads from globals)'
        );
    }

    // ---------------------------------------------------------------
    //  Security: SSL verification settings
    // ---------------------------------------------------------------

    /**
     * Test that patchman_req configures SSL verification options.
     *
     * @return void
     */
    public function testPatchmanReqSslVerificationSettings(): void
    {
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST', self::$source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER', self::$source);
    }

    // ---------------------------------------------------------------
    //  URL path construction in patchman_req
    // ---------------------------------------------------------------

    /**
     * Test that patchman_req appends .php extension when missing.
     *
     * @return void
     */
    public function testPatchmanReqAppendsPhpExtension(): void
    {
        $this->assertStringContainsString("'.php'", self::$source);
        $this->assertStringContainsString("mb_strpos(\$page, '.php')", self::$source);
    }

    /**
     * Test that patchman_req prepends clients/api/ path prefix.
     *
     * @return void
     */
    public function testPatchmanReqPrependsApiPath(): void
    {
        $this->assertStringContainsString('clients/api/', self::$source);
    }

    // ---------------------------------------------------------------
    //  DNS configuration in activate_patchman
    // ---------------------------------------------------------------

    /**
     * Test that activate_patchman uses InterServer DNS servers.
     *
     * @return void
     */
    public function testActivatePatchmanUsesInterserverDns(): void
    {
        $this->assertStringContainsString('dns4.interserver.net', self::$source);
        $this->assertStringContainsString('dns5.interserver.net', self::$source);
    }

    /**
     * Test that activate_patchman uses payment method balance.
     *
     * @return void
     */
    public function testActivatePatchmanUsesBalancePayment(): void
    {
        $this->assertStringContainsString("'payment' => 'balance'", self::$source);
    }

    // ---------------------------------------------------------------
    //  get_patchman_license signature
    // ---------------------------------------------------------------

    /**
     * Test that get_patchman_license accepts a $lid parameter.
     *
     * @return void
     */
    public function testGetPatchmanLicenseAcceptsLid(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+get_patchman_license\s*\(\s*\$lid\s*\)/',
            self::$source,
            'get_patchman_license should accept exactly one $lid parameter'
        );
    }

    /**
     * Test that get_patchman_license calls patchman_req with license page.
     *
     * @return void
     */
    public function testGetPatchmanLicenseCallsPatchmanReq(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+get_patchman_license.*?patchman_req\s*\(\s*.license./s',
            self::$source
        );
    }
}
