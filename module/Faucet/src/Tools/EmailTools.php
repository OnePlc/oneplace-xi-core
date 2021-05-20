<?php
/**
 * EmailTools.php - E-Mail Helper
 *
 * Main Helper for Faucet E-Mail Sending
 *
 * @category Helper
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\Tools;

use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mail;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;


class EmailTools extends AbstractResourceListener {
    /**
     * View Renderer for E-Mail Templates
     *
     * @var $view
     * @since 1.0.0
     */
    protected $view;

    /**
     * Settings Table
     *
     * @var TableGateway $mSettingsTbl
     * @since 1.0.0
     */
    protected $mSettingsTbl;

    /**
     * Constructor
     *
     * EmailTools constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper, $viewRenderer)
    {
        $this->view = $viewRenderer;
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
    }

    public function sendMail($tplName, $tplData, $from, $to, $subject)
    {
        # Get E-Mail html based on template
        $sBodyHtml = $this->view->render('email/'.$tplName, $tplData);

        # Build Mime-part
        $oHtml = new MimePart($sBodyHtml);
        $oHtml->type = "text/html";

        # Build Body
        $oBody = new MimeMessage();
        $oBody->addPart($oHtml);

        /**
        if($oAttachment != NULL) {
            $oBody->addPart($oAttachment);
        } **/

        # Build Message
        $oMail = new Mail\Message();
        //$oMail->setEncoding('UTF-8');
        $oMail->setBody($oBody);
        $oMail->setFrom($from);
        $oMail->addTo($to);
        $oMail->setSubject($subject);

        $server = $this->mSettingsTbl->select(['settings_key' => 'noreply-server']);
        if(count($server) == 0) {
            return false;
        }
        $user = $this->mSettingsTbl->select(['settings_key' => 'noreply-email']);
        if(count($user) == 0) {
            return false;
        }
        $pass = $this->mSettingsTbl->select(['settings_key' => 'noreply-pw']);
        if(count($pass) == 0) {
            return false;
        }
        $smtpServer = $server->current()->settings_value;
        $smtpUser = $user->current()->settings_value;
        $smtpPass = $pass->current()->settings_value;

        # Setup SMTP Transport for proper email sending
        $oTransport = new SmtpTransport();
        $aOptions   = new SmtpOptions([
            'name'              => $smtpServer,
            'host'              => $smtpServer,
            'port'              => 587,
            'connection_class'  => 'login',
            'connection_config' => [
                'username' => $smtpUser,
                'password' => $smtpPass,
                'ssl'      => 'tls',
            ],
        ]);
        $oTransport->setOptions($aOptions);
        $oTransport->send($oMail);

        return true;
    }

    public function getAdminEmail() {
        $mail = $this->mSettingsTbl->select(['settings_key' => 'admin_email']);
        if(count($mail) == 0)
        {
            return false;
        }
        return $mail->current()->settings_value;
    }

    public function getSystemURL() {
        $url = $this->mSettingsTbl->select(['settings_key' => 'app-url']);
        if(count($url) == 0)
        {
            return false;
        }
        return $url->current()->settings_value;
    }

    /**
     * Generate Secret Security Token for E-Mails
     *
     * @param $user
     * @return array|false|string|string[]|null
     * @since 1.0.0
     */
    public function generateSecurityToken($user) {
        /**
         * Get Token Base Fields from Config
         */
        $baseFields = $this->mSettingsTbl->select(['settings_key' => 'token-basefields']);
        $hashSalt = $this->mSettingsTbl->select(['settings_key' => 'token-salt']);
        if(count($baseFields) == 0 || count($hashSalt) == 0) {
            return false;
        }
        $baseFields = explode(',', $baseFields->current()->settings_value);
        $sHashBase = '';
        foreach($baseFields as $sField) {
            $sHashBase .= $user->$sField;
        }
        $sHashBase .= $hashSalt->current()->settings_value;
        $secToken = password_hash($sHashBase, PASSWORD_DEFAULT);
        $secToken = str_replace(['/','$','.'],[''],$secToken);

        return $secToken;
    }
}