<?php
/**
 * ApiTools.php - Api Helper
 *
 * Main Helper for Api
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

class ApiTools extends AbstractResourceListener {

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
     * ApiTools constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
    }

    public function getSystemURL()
    {
        $url = $this->mSettingsTbl->select(['settings_key' => 'app-url']);
        if(count($url) == 0)
        {
            return false;
        }
        return $url->current()->settings_value;
    }

    public function getApiURL()
    {
        $url = $this->mSettingsTbl->select(['settings_key' => 'api-url']);
        if(count($url) == 0)
        {
            return false;
        }
        return $url->current()->settings_value;
    }

    public function getDashboardURL()
    {
        $url = $this->mSettingsTbl->select(['settings_key' => 'dashboard-url']);
        if(count($url) == 0)
        {
            return false;
        }
        return $url->current()->settings_value;
    }

    public function timeElapsedString($datetime, $full = false): string
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}