<?php
/**
 * SecurityTools.php - Security Helper
 *
 * Main Helper for Faucet Basic Security and
 * Hacker Detection
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
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\Session\Container;

class SecurityTools extends AbstractResourceListener {
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Oauth User Table
     *
     * @var TableGateway $mOAuthTbl
     * @since 1.0.0
     */
    protected $mOAuthTbl;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

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
     * SecurityTools constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSession = new Container('webauth');
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
        $this->mOAuthTbl = new TableGateway('oauth_users', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
    }

    /**
     * Check string for potential xss attack
     *
     * @param array $aValsToCheck
     * @return bool|string
     * @since 1.0.0
     */
    private function xssCheck(array $aValsToCheck = [])
    {
        $aBlacklist = ['script>','src=','<script','</sc','=//'];
        foreach($aValsToCheck as $sVal) {
            foreach($aBlacklist as $sBlack) {
                $bHasBlack = stripos(strtolower($sVal),strtolower($sBlack));
                if($bHasBlack === false) {
                    # all good
                } else {
                    # found blacklisted needle in string
                    return $sBlack;
                }
            }
        }

        return false;
    }

    /**
     * Check string for potential sniffer attack
     *
     * @param array $aValsToCheck
     * @return bool|string
     * @since 1.0.0
     */
    private function snifferCheck(array $aValsToCheck = [])
    {
        $aBlacklist = ['http:','__import__',
            '.popen(','gethostbyname','localtime()','form-data',
            'java.lang','/bin/bash','cmd.exe','org.apache.commons','nginx','?xml','version=',
            'ping -n','WAITFOR DELAY','../','varchar(','exec(','%2F..','..%2F','multipart/'];
        foreach($aValsToCheck as $sVal) {
            foreach($aBlacklist as $sBlack) {
                $bHasBlack = stripos(strtolower($sVal),strtolower($sBlack));
                if($bHasBlack === false) {
                    # all good
                } else {
                    # found blacklisted needle in string
                    return $sBlack;
                }
            }
        }

        return false;
    }

    /**
     * Check string for potential sql inject attack
     *
     * @param array $aValsToCheck
     * @return bool|string
     * @since 1.0.0
     */
    private function sqlinjectCheck(array $aValsToCheck = [])
    {
        $aBlacklist = ['dblink_connect','user=','(SELECT','SELECT (','select *','union all','and 1','1=1','2=2','1 = 1', '2 = 2'];
        foreach($aValsToCheck as $sVal) {
            foreach($aBlacklist as $sBlack) {
                $bHasBlack = stripos(strtolower($sVal),strtolower($sBlack));
                if($bHasBlack === false) {
                    # all good
                } else {
                    # found blacklisted needle in string
                    return $sBlack;
                }
            }
        }

        return false;
    }

    /**
     * Perform basic security checks on string
     *
     * @param array $aValsToCheck
     * @return string
     * @since 1.0.0
     */
    public function basicInputCheck(array $aValsToCheck = [])
    {
        $xssCheck = $this->xssCheck($aValsToCheck);
        if($xssCheck !== false) {
            return 'xss - '.$xssCheck;
        }

        $snifCheck = $this->snifferCheck($aValsToCheck);
        if($snifCheck !== false) {
            return 'sniff - '.$snifCheck;
        }

        $sqlCheck = $this->sqlinjectCheck($aValsToCheck);
        if($sqlCheck !== false) {
            return 'sqlinject - '.$sqlCheck;
        }

        return 'ok';
    }

    /**
     * Get secured User Session on Server
     *
     * @return ApiProblem
     * @since 1.0.0
     */
    public function getSecuredUserSession($userId) {
        if(empty($userId) || $userId == 0) {
            return new ApiProblem(401, 'Not logged in');
        }
        # check for user bans
        $userTempBan = $this->mUserSetTbl->select([
            'user_idfs' => $userId,
            'setting_name' => 'user-tempban',
        ]);
        if(count($userTempBan) > 0) {
            return new ApiProblem(403, 'You are temporarily banned. Please contact support.');
        }

        # get user from db
        return $this->mUserTbl->select(['User_ID' => $userId])->current();
    }

    /**
     * Get Username Blacklist
     *
     * @return array
     * @since 1.0.0
     */
    public function getUsernameBlacklist() {
        $blacklist = $this->mSettingsTbl->select(['settings_key' => 'username_blacklist']);
        if(count($blacklist) == 0) {
            return [];
        }
        $blacklist = json_decode($blacklist->current()->settings_value);
        $blackIndex = [];
        foreach($blacklist as $bl) {
            $blackIndex[strtolower($bl)] = true;
        }
        return $blackIndex;
    }

    public function usernameBlacklistCheck($username) {
        $blacklist = $this->mSettingsTbl->select(['settings_key' => 'username-blacklist']);
        if(count($blacklist) == 0) {
            return true;
        }
        $blacklist = json_decode($blacklist->current()->settings_value);
        foreach($blacklist as $bl) {
            $check = stripos(strtolower($username), strtolower($bl));
            if($check !== false) {
                return false;
            }
        }

        return true;
    }

    public function getCoreSetting($key) {
        $settingFound = $this->mSettingsTbl->select(['settings_key' => $key]);
        if (count($settingFound) == 0) {
            return false;
        } else {
            return $settingFound->current()->settings_value;
        }
    }

    public function updatePassword($newPassword, $userId) {
        # some basic protection
        if($userId == 0 || $userId == null || !is_numeric($userId)) {
            return false;
        }
        $pwHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->mOAuthTbl->update([
            'password' => $pwHash
        ],['username' => $userId]);
        $this->mUserTbl->update([
            'password' => $pwHash
        ],['User_ID' => $userId]);

        return true;
    }

    /**
     * Get Week for Date
     * (Weeks are from wed-tue)
     *
     * @param $date
     * @return string
     */
    public function getWeek($date) {
        // update user stats (week)
        $week = date('W-Y', $date);
        $weekDay = date('w', $date);
        $weekCheck = date('W', $date);
        $monthCheck = date('n', $date);
        // fix php bug - wrong iso week for first week of the year
        //$dev = 1;
        $yearFixApplied = false;
        if($monthCheck == 1 && ($weekCheck > 10 || $weekCheck == 1)) {
            // last week of last year is extended to tuesday as our week begins at wednesday
            if($weekDay != 3 && $weekDay != 4 && $weekDay != 5) {
                //$dev = 5;
                try {
                    $stop_date = new \DateTime(date('Y-m-d', $date));
                    $stop_date->modify('-5 days');
                    $statDate = strtotime($stop_date->format('Y-m-d H:i:s'));

                    $week = date('W-Y', $statDate);
                    $yearFixApplied = true;
                } catch(\Exception $e) {

                }
            }
        }
        // dont mess with fixed date from year change
        if(!$yearFixApplied) {
            //$dev = 3;
            // monday and tuesday are counted to last weeks iso week
            if($weekDay == 1 || $weekDay == 2) {
                //$dev = 4;
                $week = ($weekCheck-1).'-'.date('Y', $date);
            }
        }

        return $week;
    }

    public function checkIpRestrictedAccess(): bool
    {
        $ipWhiteList = $this->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        $wthIp = filter_var($_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_STRING);
        $secResult = $this->basicInputCheck([$wthIp]);
        if($secResult !== 'ok') {
            return false;
        }
        if(empty($wthIp) || strlen($wthIp) < 10) {
            return false;
        }
        if(!in_array($wthIp, $ipWhiteList)) {
            return false;
        }
        return true;
    }
}