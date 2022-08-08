<?php
/**
 * WithdrawController.php - Webstats Controller
 *
 * Main Controller for Faucet Public Webstats
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rpc\Webstats;

use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Mvc\Controller\AbstractActionController;

class WebstatsController extends AbstractActionController
{
    /**
     * Statistics Table
     *
     * @var TableGateway $mStatisticsTbl
     * @since 1.0.0
     */
    protected $mStatisticsTbl;

    private $mMapper;

    protected $mFaucetStatsTbl;

    /**
     * Constructor
     *
     * WebstatsController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mStatisticsTbl = new TableGateway('core_statistic', $mapper);
        $this->mFaucetStatsTbl = new TableGateway('faucet_statistic', $mapper);
        $this->mMapper = $mapper;
    }

    /**
     * Public Faucet Statistics
     *
     * @return ViewModel
     * @since 1.0.0
     */
    public function webstatsAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            # get users
            $usersOnline = 0;
            $usrSel = new Select($this->mStatisticsTbl->getTable());
            $usrSel->where(['stats_key' => 'users-total']);
            $usersStats = $this->mStatisticsTbl->selectWith($usrSel);
            if(count($usersStats) > 0) {
                $usersOnline = $usersStats->current()->data;
            }

            # withdrawn total in usd
            $withdrawn = 0;
            $wthSel = new Select($this->mStatisticsTbl->getTable());
            $wthSel->where(['stats_key' => 'wth-coins-total']);
            $withdrawnDB = $this->mStatisticsTbl->selectWith($wthSel);
            if(count($withdrawnDB) > 0) {
                $withdrawn = round($withdrawnDB->current()->data*0.00004,2);
            }

            # get shortlinks done
            $shortlinks = 0;
            $wthSel = new Select($this->mStatisticsTbl->getTable());
            $wthSel->where(['stats_key' => 'shortlinks-total-links']);
            $withdrawnDB = $this->mFaucetStatsTbl->selectWith($wthSel);
            if(count($withdrawnDB) > 0) {
                $shortlinks = $withdrawnDB->current()->data;
                if($shortlinks > 1000000) {
                    $shortlinks = substr($shortlinks, 0, 1).'.'.substr($shortlinks, 1, 1).'M';
                }
            }

            # get offerwalls done
            $offers = 0;
            $wthSel = new Select($this->mStatisticsTbl->getTable());
            $wthSel->where(['stats_key' => 'offerwalls-total-offers']);
            $withdrawnDB = $this->mFaucetStatsTbl->selectWith($wthSel);
            if(count($withdrawnDB) > 0) {
                $offers = $withdrawnDB->current()->data;
                if($offers > 100000 && $offers < 1000000) {
                    $offers = substr($offers, 0, 3).'K';
                }
            }

            # get faucet claims
            $claims = 0;
            $wthSel = new Select($this->mStatisticsTbl->getTable());
            $wthSel->where(['stats_key' => 'faucet-claims-total']);
            $withdrawnDB = $this->mFaucetStatsTbl->selectWith($wthSel);
            if(count($withdrawnDB) > 0) {
                $claims = $withdrawnDB->current()->data;
                if($claims > 1000000) {
                    $claims = substr($claims, 0, 1).'.'.substr($claims, 1, 1).'M';
                }
            }

            # get days online
            $now = time(); // or your date as well
            $your_date = strtotime("2021-04-03");
            $daysOnline = $now - $your_date;

            # return info
            return new ViewModel([
                'users' => $usersOnline,
                'withdrawn' => $withdrawn,
                'shortlinks' => $shortlinks,
                'offers' => $offers,
                'games' => 0,
                'claims' => $claims,
                'days_online' => round($daysOnline / (60 * 60 * 24)),
                'hashrate' => '41',
            ]);
        }
    }
}
