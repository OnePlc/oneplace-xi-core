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
            $usrSel->where(['stats_key' => 'userstats-daily']);
            $usrSel->order('date DESC');
            $usrSel->limit(1);
            $usersStats = $this->mStatisticsTbl->selectWith($usrSel);
            if(count($usersStats) > 0) {
                $usersOnline = $usersStats->current()->data;
            }

            # get games
            $games = 0;

            # withdrawn total in usd
            $withdrawn = 0;
            $wthSel = new Select($this->mStatisticsTbl->getTable());
            $wthSel->where(['stats_key' => 'tokenmetrics-daily']);
            $wthSel->order('date DESC');
            $wthSel->limit(1);
            $withdrawnDB = $this->mStatisticsTbl->selectWith($wthSel);
            if(count($withdrawnDB) > 0) {
                $withdrawn = json_decode($withdrawnDB->current()->data)->withdraw_total*0.00004;
            }

            # get shortlinks done
            $shortDoneTbl = new TableGateway('shortlink_link_user', $this->mMapper);
            $shortlinks = $shortDoneTbl->select()->count();

            # get offerwalls done
            $offerDoneTbl = new TableGateway('offerwall_user', $this->mMapper);
            $offers = $offerDoneTbl->select()->count();

            # get faucet claims
            $claimDoneTbl = new TableGateway('faucet_claim', $this->mMapper);
            $claims = $claimDoneTbl->select()->count();

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
