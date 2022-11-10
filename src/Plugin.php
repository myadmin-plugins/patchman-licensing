<?php

namespace Detain\MyAdminPatchman;

//use Detain\Patchman\Patchman;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
* Class Plugin
*
* @package Detain\MyAdminPatchman
*/
class Plugin
{
    public static $name = 'PatchMan Licensing';
    public static $description = 'Allows selling of PatchMan Licenses.  More info at https://www.patchman.com/';
    public static $help = '';
    public static $module = 'licenses';
    public static $type = 'service';

    /**
    * Plugin constructor.
    */
    public function __construct()
    {
    }

    /**
    * @return array
    */
    public static function getHooks()
    {
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
    public static function getActivate(GenericEvent $event)
    {
        $serviceClass = $event->getSubject();
        if ($event['category'] == get_service_define('PATCHMAN')) {
            myadmin_log(self::$module, 'info', 'Patchman Activation', __LINE__, __FILE__, self::$module, $serviceClass->getId());
            //function_requirements('activate_patchman');
            //activate_patchman($serviceClass->getIp(), patchman_get_best_type(self::$module, $serviceClass->getType()), $event['email'], $event['email'], self::$module.$serviceClass->getId(), '');
            $event->stopPropagation();
        }
    }

    /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
    public static function getDeactivate(GenericEvent $event)
    {
        $serviceClass = $event->getSubject();
        if ($event['category'] == get_service_define('PATCHMAN')) {
            $subject = 'Patchman Deactivation for '.$serviceClass->getIp();
            myadmin_log(self::$module, 'info', $subject, __LINE__, __FILE__, self::$module, $serviceClass->getId());
            $body = $subject.PHP_EOL.PHP_EOL.'SUPPORT: This is a notification for billing. Please do not reply to or close this ticket. ';
            //function_requirements('deactivate_patchman');
            //deactivate_patchman($serviceClass->getIp());
            function_requirements('create_ky_ticket');
            $success = create_ky_ticket($fromEmail, $subject, $body, $fromName);
            if ($success == false) {
                (new \MyAdmin\Mail())->clientMail($subject, $body, 'support@interserver.net', '');
            }
            $event->stopPropagation();
        }
    }

    /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
    public static function getChangeIp(GenericEvent $event)
    {
        if ($event['category'] == get_service_define('PATCHMAN')) {
            $serviceClass = $event->getSubject();
            $settings = get_module_settings(self::$module);
            $patchman = new \Patchman(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
            myadmin_log(self::$module, 'info', 'IP Change - (OLD:'.$serviceClass->getIp().") (NEW:{$event['newip']})", __LINE__, __FILE__, self::$module, $serviceClass->getId());
            $result = $patchman->editIp($serviceClass->getIp(), $event['newip']);
            if (isset($result['faultcode'])) {
                myadmin_log(self::$module, 'error', 'Patchman editIp('.$serviceClass->getIp().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
                $event['status'] = 'error';
                $event['status_text'] = 'Error Code '.$result['faultcode'].': '.$result['fault'];
            } else {
                $GLOBALS['tf']->history->add($settings['TABLE'], 'change_ip', $event['newip'], $serviceClass->getId(), $serviceClass->getCustid());
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
    public static function getMenu(GenericEvent $event)
    {
        $menu = $event->getSubject();
        if ($GLOBALS['tf']->ima == 'admin') {
            $menu->add_link(self::$module, 'choice=none.reusable_patchman', '/images/myadmin/to-do.png', _('ReUsable Patchman Licenses'));
            $menu->add_link(self::$module, 'choice=none.patchman_list', '/images/myadmin/to-do.png', _('Patchman Licenses Breakdown'));
            $menu->add_link(self::$module.'api', 'choice=none.patchman_licenses_list', '/images/whm/createacct.gif', _('List all Patchman Licenses'));
        }
    }

    /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
    public static function getRequirements(GenericEvent $event)
    {
        /**
        * @var \MyAdmin\Plugins\Loader $this->loader
        */
        $loader = $event->getSubject();
        $loader->add_admin_page_requirement('add_patchman', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
        $loader->add_requirement('activate_patchman', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
        $loader->add_requirement('deactivate_patchman', '/../vendor/detain/myadmin-patchman-licensing/src/patchman.inc.php');
    }

    /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
    public static function getSettings(GenericEvent $event)
    {
        /**
        * @var \MyAdmin\Settings $settings
        **/
        $settings = $event->getSubject();
        $settings->add_text_setting(self::$module, _('PatchMan'), 'patchman_username', _('Patchman Username'), _('Patchman Username'), $settings->get_setting('PATCHMAN_USERNAME'));
        $settings->add_password_setting(self::$module, _('PatchMan'), 'patchman_password', _('Patchman Password'), _('Patchman Password'), $settings->get_setting('PATCHMAN_PASSWORD'));
        $settings->add_dropdown_setting(self::$module, _('PatchMan'), 'outofstock_licenses_patchman', _('Out Of Stock PatchMan Licenses'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_LICENSES_PATCHMAN'), ['0', '1'], ['No', 'Yes']);
    }
}
