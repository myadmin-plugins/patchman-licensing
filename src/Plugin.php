<?php

namespace Detain\MyAdminPatchman;

//use Detain\Patchman\Patchman;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminPatchman
 */
class Plugin {

	public static $name = 'PatchMan Licensing';
	public static $description = 'Allows selling of PatchMan Server and VPS License Types.  More info at https://www.patchman.com/';
	public static $help = '';
	public static $module = 'licenses';
	public static $type = 'service';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			self::$module.'.activate' => [__CLASS__, 'getActivate'],
			self::$module.'.reactivate' => [__CLASS__, 'getActivate'],
			self::$module.'.deactivate' => [__CLASS__, 'getDeactivate'],
			self::$module.'.deactivate_ip' => [__CLASS__, 'getDeactivate'],
			'function.requirements' => [__CLASS__, 'getRequirements']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getActivate(GenericEvent $event) {
		$serviceClass = $event->getSubject();
		if ($event['category'] == get_service_define('DIRECTADMIN')) {
			myadmin_log(self::$module, 'info', 'Patchman Activation', __LINE__, __FILE__);
			function_requirements('patchman_get_best_type');
			function_requirements('activate_patchman');
			activate_patchman($serviceClass->getIp(), patchman_get_best_type(self::$module, $serviceClass->getType()), $event['email'], $event['email'], self::$module.$serviceClass->getId(), '');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getDeactivate(GenericEvent $event) {
		$serviceClass = $event->getSubject();
		if ($event['category'] == get_service_define('DIRECTADMIN')) {
			myadmin_log(self::$module, 'info', 'Patchman Deactivation', __LINE__, __FILE__);
			function_requirements('deactivate_patchman');
			deactivate_patchman($serviceClass->getIp());
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getChangeIp(GenericEvent $event) {
		if ($event['category'] == get_service_define('DIRECTADMIN')) {
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$patchman = new \Patchman(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
			myadmin_log(self::$module, 'info', 'IP Change - (OLD:'.$serviceClass->getIp().") (NEW:{$event['newip']})", __LINE__, __FILE__);
			$result = $patchman->editIp($serviceClass->getIp(), $event['newip']);
			if (isset($result['faultcode'])) {
				myadmin_log(self::$module, 'error', 'Patchman editIp('.$serviceClass->getIp().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__);
				$event['status'] = 'error';
				$event['status_text'] = 'Error Code '.$result['faultcode'].': '.$result['fault'];
			} else {
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_ip', $event['newip'], $serviceClass->getIp());
				$serviceClass->set_ip($event['newip'])->save();
				$event['status'] = 'ok';
				$event['status_text'] = 'The IP Address has been changed.';
			}
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			$menu->add_link(self::$module, 'choice=none.reusable_patchman', 'images/icons/database_warning_48.png', 'ReUsable Patchman Licenses');
			$menu->add_link(self::$module, 'choice=none.patchman_list', 'images/icons/database_warning_48.png', 'Patchman Licenses Breakdown');
			$menu->add_link(self::$module.'api', 'choice=none.patchman_licenses_list', '/images/whm/createacct.gif', 'List all Patchman Licenses');
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('get_patchman_license_types', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_page_requirement('patchman_get_best_type', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_page_requirement('patchman_req', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_requirement('get_patchman_licenses', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_requirement('get_patchman_license', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_requirement('get_patchman_license_by_ip', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_page_requirement('patchman_ip_to_lid', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_requirement('activate_patchman', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_requirement('deactivate_patchman', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_requirement('patchman_deactivate', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
		$loader->add_page_requirement('patchman_makepayment', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'PatchMan', 'patchman_username', 'Patchman Username:', 'Patchman Username', $settings->get_setting('DIRECTADMIN_USERNAME'));
		$settings->add_text_setting(self::$module, 'PatchMan', 'patchman_password', 'Patchman Password:', 'Patchman Password', $settings->get_setting('DIRECTADMIN_PASSWORD'));
		$settings->add_dropdown_setting(self::$module, 'PatchMan', 'outofstock_licenses_patchman', 'Out Of Stock PatchMan Licenses', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_LICENSES_DIRECTADMIN'), ['0', '1'], ['No', 'Yes']);
	}

}
