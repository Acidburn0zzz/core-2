<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Module\MailerModule\Form\Handler;

use Zikula_Form_View;
use SecurityUtil;
use LogUtil;
use DataUtil;
use ZLanguage;
use ModUtil;
use System;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zikula\Core\ModUrl;

/**
 * Form handler for the mailer modules modifyconfig form
 */
class ModifyConfigHandler extends \Zikula_Form_AbstractHandler
{
    /**
     * @var array values for this form
     */
    private $formValues;

    /**
     * initialise the form
     *
     * @param \Zikula_Form_view $view view object
     *
     * @return bool true if successful
     (
     * @throws AccessDeniedException Thrown if the user doesn't have admin access to the module
     */
    public function initialize(Zikula_Form_View $view)
    {
        if (!SecurityUtil::checkPermission('ZikulaMailerModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedException();
        }

        // assign the mail transport types
        $view->assign('transportItems', array(
            array('value' => 'mail', 'text' => DataUtil::formatForDisplay($this->__("Internal PHP `mail()` function"))),
            array('value' => 'sendmail', 'text' => DataUtil::formatForDisplay($this->__('Sendmail message transfer agent'))),
            array('value' => 'gmail', 'text' => DataUtil::formatForDisplay($this->__('Google gmail'))),
            array('value' => 'smtp', 'text' => DataUtil::formatForDisplay($this->__('SMTP mail transfer protocol'))),
            array('value' => 'null', 'text' => DataUtil::formatForDisplay($this->__('Development/debug mode (Redirect e-mails to LogUtil)')))
        ));

        $view->assign('encryptionItems', array(
            array('value' => 'null', 'text' => 'None'),
            array('value' => 'ssl', 'text' => 'SSL'),
            array('value' => 'tls', 'text' => 'TLS')
        ));

        $view->assign('auth_modeItems', array(
            array('value' => 'null', 'text' => 'None'),
            array('value' => 'plain', 'text' => 'Plain'),
            array('value' => 'login', 'text' => 'Login'),
            array('value' => 'cram-md5', 'text' => 'Cram-MD5'),
        ));

        $dumper = $this->view->getContainer()->get('zikula.dynamic_config_dumper');
        $params =  $dumper->getConfiguration('swiftmailer');
        $view->assign('swiftmailer_params', $params);

        // assign all config vars
        $this->view->assign($params);

        return true;
    }

    /**
     * Handle form commands
     *
     * @param \Zikula_Form_View $view view object
     * @param array $args
     *
     * @return bool|void false if the form to be saved isn't valid, void otherwise
     */
    public function handleCommand(Zikula_Form_View $view, &$args)
    {
        switch($args['commandName']) {
            case 'cancel':
                break;
            case 'save':
                if (!$view->isValid()) {
                    return false;
                }
                $this->formValues = $view->getValues();

                // set our new module variable values
                $vars = array();
                $vars['transport'] = (string)$this->getFormValue('transport', 'mail');
                $vars['html'] = (bool)$this->getFormValue('html', false);
                $vars['wordwrap'] = (int)$this->getFormValue('wordwrap', 50);
                $vars['auth_mode'] = $this->getFormValue('auth_mode', null);
                $vars['host'] = (string)$this->getFormValue('host', 'localhost');
                $vars['port'] = (int)$this->getFormValue('port', 25);
                $vars['smtptimeout'] = (int)$this->getFormValue('smtptimeout', 10);
                $vars['username'] = (string)$this->getFormValue('username', null);
                $vars['password'] = (string)$this->getFormValue('password', null);
                $vars['encryption'] = (string)$this->getFormValue('encryption', null);

                // write the config file
                // http://symfony.com/doc/current/reference/configuration/swiftmailer.html
                $config = array(
                    'transport' => $vars['transport'],
                    'username' => $vars['username'],
                    'password' => $vars['password'],
                    'host' => $vars['host'],
                    'port' => $vars['port'],
                    'encryption' => $vars['encryption'],
                    'auth_mode' => $vars['auth_mode'],
                    'spool' => array('type' => 'memory'),
                    'delivery_address' => null,
                    'disable_delivery' => false,
                );

                $configDumper = $this->view->getContainer()->get('zikula.dynamic_config_dumper');
                $configDumper->setConfiguration('swiftmailer', $config);

                // the module configuration has been updated successfully
                LogUtil::registerStatus($this->__('Done! Saved module configuration.'));
                break;
        }

        return $view->redirect(new ModUrl($this->name, 'admin', 'modifyconfig', ZLanguage::getLanguageCode()));
    }

    /**
     * Get the value of a form field
     *
     * @param string $key     the field key to query
     * @param string $default the default value for the query
     *
     * @return mixed the form value (or default otherwise)
     */
    private function getFormValue($key, $default)
    {
        return isset($this->formValues[$key]) ? $this->formValues[$key] : $default;
    }
}
