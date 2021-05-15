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

use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class SecurityTools {
    /**
     * Constructor
     *
     * LoginController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
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
        foreach($aValsToCheck as $sVal) {
            $bHasScript = stripos(strtolower($sVal),'script>');
            if($bHasScript === false) {
                $bHasScript = stripos(strtolower($sVal),'src=');
                if($bHasScript === false) {

                } else {
                    # found xss attack
                    return 'src=';
                }
            } else {
                # found xss attack
                return 'script>';
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
        $aBlacklist = ['dblink_connect','user=','(SELECT','SELECT (','select *','union all','and 1',
            'or 1','1=1','2=2'];
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
}