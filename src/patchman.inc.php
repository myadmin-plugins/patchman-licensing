<?php
/**
* PatchMan Related Functionality
* @author Joe Huss <detain@interserver.net>
* @copyright 2017
* @package MyAdmin
* @category Licenses
*/


function add_patchman() {
	$module = $GLOBALS['tf']->variables->request['module'];
	$id = $GLOBALS['tf']->variables->request['id'];
	$serviceInfo = get_service($id, $module);
	$serviceSettings = get_module_settings($module);
	$settings = get_module_settings('licenses');
	$frequency = 1;
	$now = mysql_now();
	$custid = $serviceInfo[$serviceSettings['PREFIX'].'_custid'];
	$service_cost = 20;
	$package_id = 5081;
	$ip = $serviceInfo[$serviceSettings['PREFIX'].'_ip'];
	if (!isset($GLOBALS['tf']->variables->request['confirm'])) {
		$table = new TFTable;
		$table->set_title('Add Patchman');
		$table->add_hidden('id', $id);
		$table->add_hidden('module', $module);
		$table->add_field('Service');
		$table->add_field($id);
		$table->add_row();
		$table->add_field('Module');
		$table->add_field($module);
		$table->add_row();
		$table->add_field('IP');
		$table->add_field($ip);
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field($table->make_submit('Confirm', 'confirm'));
		$table->add_row();
		add_output($table->get_table());
	} else {
		$repeat_invoice = new \MyAdmin\Orm\Repeat_Invoice($db);
		$repeat_invoice->set_description('Patchman')
			->set_type(1)
			->set_custid($custid)
			->set_cost($service_cost)
			->setFrequency($frequency)
			->set_date($now)
			->set_module('licenses')
			->save();
		$rid = $repeat_invoice->get_id();
		$invoice = $repeat_invoice->invoice($now, $service_cost, FALSE);
		$iid = $invoice->get_id();
		$db->query(make_insert_query($settings['TABLE'], [
			$settings['PREFIX'].'_id' => null,
			$settings['PREFIX'].'_type' => $package_id,
			$settings['PREFIX'].'_custid' => $custid,
			$settings['PREFIX'].'_cost' => $service_cost,
			$settings['PREFIX'].'_frequency' => $frequency,
			$settings['PREFIX'].'_order_date' => $now,
			$settings['PREFIX'].'_ip' => $ip,
			$settings['PREFIX'].'_status' => 'active',
			$settings['PREFIX'].'_invoice' => $rid,
			$settings['PREFIX'].'_hostname' => ''
		]), __LINE__, __FILE__);
		$serviceid = $db->getLastInsertId($settings['TABLE'], $settings['PREFIX'].'_id');
		$repeat_invoice->set_service($serviceid)->save();
		$invoice->set_service($serviceid)->save();
		add_output('Patchman License Added');
	}


}

/**
 * @param string        $page
 * @param string        $post
 * @param bool|string[] $options
 * @return string
 */
function patchman_req($page, $post = '', $options = FALSE) {
	if ($options === FALSE)
		$options = [];
	$defaultOptions = [
		CURLOPT_USERPWD => PATCHMAN_USERNAME.':'.PATCHMAN_PASSWORD,
		CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
		CURLOPT_SSL_VERIFYHOST => FALSE,
		CURLOPT_SSL_VERIFYPEER => FALSE
	];
	foreach ($defaultOptions as $key => $value)
		if (!isset($options[$key]))
			$options[$key] = $value;
	if (!is_url($page)) {
		if (mb_strpos($page, '.php') === FALSE)
			$page .= '.php';
		if (mb_strpos($page, '/') === FALSE)
			$page = "clients/api/{$page}";
		elseif (mb_strpos($page, 'api/') === FALSE)
			$page = "api/{$page}";
		if (mb_strpos($page, 'clients/') === FALSE)
			$page == "clients/{$page}";
		if (!is_url($page))
			$page = "https://www.patchman.co/{$page}";
	}
	return trim(getcurlpage($page, $post, $options));
}

/**
 * @return array
 */
function get_patchman_licenses() {
	$response = patchman_req('list');
	$licenses = [];
	if (trim($response) == '')
		return $licenses;
	$lines = explode("\n", trim($response));
	$linesValues = array_values($lines);
	foreach ($linesValues as $line) {
		parse_str($line, $license);
		$licenses[$license['lid']] = $license;
	}
	return $licenses;
}

/**
 * @param $lid
 * @return string
 */
function get_patchman_license($lid) {
	$response = patchman_req('license', ['lid' => $lid]);
	_debug_array($response);
	return $response;
}

/**
 * @param $ipAddress
 * @return bool|mixed
 */
function get_patchman_license_by_ip($ipAddress) {
	$licenses = get_patchman_licenses();
	$licensesValues = array_values($licenses);
	foreach ($licensesValues as $license)
		if ($license['ip'] == $ipAddress)
			return $license;
	return FALSE;
}

/**
 * @param $ipAddress
 * @return bool
 */
function patchman_ip_to_lid($ipAddress) {
	$license = get_patchman_license_by_ip($ipAddress);
	if ($license === FALSE)
		return FALSE;
	else
		return $license['lid'];
}

/**
 * activate_patchman()
 *
 * @param $ipAddress
 * @param boolean|string $ostype
 * @param $pass
 * @param $email
 * @param string $name
 * @param string $domain
 */
function activate_patchman($ipAddress, $ostype, $pass, $email, $name, $domain = '') {
	myadmin_log('licenses', 'info', "Called activate_patchman($ipAddress, $ostype, $pass, $email, $name, $domain)", __LINE__, __FILE__);
	$settings = \get_module_settings('licenses');
	$license = get_patchman_license_by_ip($ipAddress);
	if ($license === FALSE) {
		$options = [
			CURLOPT_REFERER => 'https://www.patchman.com/clients/createlicense.php'
		];
		$post = [
			'uid' =>  PATCHMAN_USERNAME,
			'id' => PATCHMAN_USERNAME,
			'password' => PATCHMAN_PASSWORD,
			'api' => 1,
			'name' => $name,
			'pid' => 2712,
			'os' => $ostype,
			'payment' => 'balance',
			'ip' => $ipAddress,
			'pass1' => $pass,
			'pass2' => $pass,
			'username' => 'admin',
			'email' => $email,
			'admin_pass1' => $pass,
			'admin_pass2' => $pass,
			'ns1' => 'dns4.interserver.net',
			'ns2' => 'dns5.interserver.net',
			'ns_on_server' => 'yes',
			'ns1ip' => '66.45.228.78',
			'ns2ip' => '66.45.228.3'
		];
		if ($domain != '')
			$post['domain'] = $domain;
		else
			$post['domain'] = $post['ip'];
		$url = 'https://www.patchman.com/cgi-bin/createlicense';
		$response = patchman_req($url, $post, $options);
		myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
		if (preg_match('/lid=(\d+)&/', $response, $matches)) {
			$lid = $matches[1];
			$response = patchman_makepayment($lid);
			myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);

		}
		$GLOBALS['tf']->history->add($settings['TABLE'], 'add_patchman', 'ip', $ipAddress, $ostype);
	}
}

/**
 * deactivate_patchman()
 * @param mixed $ipAddress
 * @return string|null
 */
function deactivate_patchman($ipAddress) {
	$license = get_patchman_license_by_ip($ipAddress);
	if ($license['active'] == 'Y') {
		$url = 'https://www.patchman.com/cgi-bin/deletelicense';
		$post = [
			'uid' => PATCHMAN_USERNAME,
			'password' => PATCHMAN_PASSWORD,
			'api' => 1,
			'lid' => $license['lid']
		];
		$options = [
			//CURLOPT_REFERER => 'https://www.patchman.com/clients/license.php',
			CURLOPT_REFERER => 'https://www.patchman.com/clients/license.php?lid='.$license['lid']
		];
		$response = patchman_req($url, $post, $options);
		myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
		return $response;
	}
}

/**
 * @param $ipAddress
 * @return null|string
 */
function patchman_deactivate($ipAddress) {
	return deactivate_patchman($ipAddress);
}

