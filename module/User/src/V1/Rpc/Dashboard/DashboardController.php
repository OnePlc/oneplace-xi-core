<?php
namespace User\V1\Rpc\Dashboard;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;

class DashboardController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Shortlink Provider Table
     *
     * @var TableGateway $mShortTbl
     * @since 1.0.0
     */
    protected $mShortTbl;

    /**
     * Shortlinks Table User Table
     *
     * Relation between shortlinks and User
     * to determine if user has completed a shortlinks
     *
     * @var TableGateway $mShortDoneTbl
     * @since 1.0.0
     */
    protected $mShortDoneTbl;

    /**
     * Offerwall Table User Table
     *
     * Relation between Offerwall and User
     * to determine if user has complete an offer
     * from an Offerwall
     *
     * @var TableGateway $mOfferwallUserTbl
     * @since 1.0.0
     */
    protected $mOfferwallUserTbl;

    /**
     * Faucet Claim Table
     *
     * @var TableGateway $mClaimTbl
     * @since 1.0.0
     */
    protected $mClaimTbl;

    /**
     * PTC Table
     *
     * @var TableGateway $mPTCTbl
     * @since 1.0.0
     */
    protected $mPTCTbl;

    /**
     * Transaction Table
     *
     * @var TableGateway $mTxTbl
     * @since 1.0.0
     */
    protected $mTxTbl;

    /**
     * Daily Task Done Table
     *
     * @var TableGateway $mDailyTbl
     * @since 1.0.0
     */
    protected $mDailyTbl;

    /**
     * PTC User (View) Table
     *
     * @var TableGateway $mPTCViewTbl
     * @since 1.0.0
     */
    protected $mPTCViewTbl;

    /**
     * Constructor
     *
     * DashboardController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mOfferwallUserTbl = new TableGateway('offerwall_user', $mapper);
        $this->mShortTbl = new TableGateway('shortlink', $mapper);
        $this->mClaimTbl = new TableGateway('faucet_claim', $mapper);
        $this->mDailyTbl = new TableGateway('faucet_dailytask_user', $mapper);
        $this->mTxTbl = new TableGateway('faucet_transaction', $mapper);
        $this->mPTCTbl = new TableGateway('ptc', $mapper);
        $this->mPTCViewTbl = new TableGateway('ptc_user', $mapper);
    }

    /**
     * Get User Dashboard Data
     *
     * @return ViewModel|ApiProblemResponse
     * @since 1.0.0
     */
    public function dashboardAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $request = $this->getRequest();

        if($request->isGet()) {
            $chartLabels = [];
            $tasksDoneData = [];
            $coinsEarnedData = [];
            $tasksMax = 0;
            $coinsMax = 0;

            $txByDate = $this->getTxEarned($me->User_ID);
            $shByDate = $this->getShortlinksDone($me->User_ID);
            $ofByDate = $this->getOffersDone($me->User_ID);
            $clByDate = $this->getClaimsDone($me->User_ID);
            $dlByDate = $this->getDailysDone($me->User_ID);

            for($day = -7;$day <= 0;$day++) {
                $tasksDone = 0;
                # add date to labels
                $dayR = 0-$day;
                $dateKey = date('Y-m-d', strtotime('-'.$dayR.' days'));
                $date = ($dayR > 0) ? date('Y-m-d', strtotime('-'.$dayR.' days')) : date('Y-m-d', time());
                $chartLabels[] = $date;

                if(array_key_exists($dateKey, $shByDate)) {
                    $tasksDone += $shByDate[$dateKey];
                }
                if(array_key_exists($dateKey, $clByDate)) {
                    $tasksDone += $clByDate[$dateKey];
                }
                if(array_key_exists($dateKey, $ofByDate)) {
                    $tasksDone += $ofByDate[$dateKey];
                }
                if(array_key_exists($dateKey, $dlByDate)) {
                    $tasksDone += $dlByDate[$dateKey];
                }

                if($tasksMax < ($tasksDone*1.2)) {
                    $tasksMax = ($tasksDone*1.2);
                }

                if(array_key_exists($dateKey, $txByDate)) {
                    if($coinsMax < ($txByDate[$dateKey]*1.2)) {
                        $coinsMax = ($txByDate[$dateKey]*1.2);
                    }
                    $coinsEarnedData[] = round($txByDate[$dateKey], 2);
                } else {
                    $coinsEarnedData[] = 0;
                }

                $tasksDoneData[] = $tasksDone;
            }

            return new ViewModel([
                'chart' => [
                    'task_done_7day' => [
                        'labels' => $chartLabels,
                        'data' => $tasksDoneData,
                        'max' => $tasksMax
                    ],
                    'coins_earned_7day' => [
                        'labels' => $chartLabels,
                        'data' => $coinsEarnedData,
                        'max' => $coinsMax,
                    ]
                ]
            ]);
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }

    private function getTxEarned($userId) {
        $txWh = new Where();
        $txWh->greaterThanOrEqualTo('date', strtotime('-7 days'));
        $txWh->equalTo('user_idfs', $userId);

        $recentTx = $this->mTxTbl->select($txWh);
        $txByDate = [];
        foreach($recentTx as $tx) {
            // skip outputs
            if($tx->is_output == 1) {
                continue;
            }
            $dateKey = date('Y-m-d', strtotime($tx->date));
            if(!array_key_exists($dateKey, $txByDate)) {
                $txByDate[$dateKey] = 0;
            }
            $txByDate[$dateKey]+=$tx->amount;
        }

        return $txByDate;
    }

    private function getShortlinksDone($userId) {
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $userId);
        $oWh->greaterThanOrEqualTo('date_completed', strtotime('-7 days'));
        $shortsDone = $this->mShortDoneTbl->select($oWh);
        $shDoneByDate = [];
        if(count($shortsDone) > 0) {
            foreach($shortsDone as $shDone) {
                $dateKey = date('Y-m-d', strtotime($shDone->date_completed));
                if(!array_key_exists($dateKey,$shDoneByDate)) {
                    $shDoneByDate[$dateKey] = 0;
                }
                $shDoneByDate[$dateKey]++;
            }
        }

        return $shDoneByDate;
    }

    private function getDailysDone($userId) {
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $userId);
        $oWh->greaterThanOrEqualTo('date', strtotime('-7 days'));

        $claimsDone = $this->mDailyTbl->select($oWh);
        $shDoneByDate = [];
        if(count($claimsDone) > 0) {
            foreach($claimsDone as $cl) {
                $dateKey = date('Y-m-d', strtotime($cl->date));
                if(!array_key_exists($dateKey,$shDoneByDate)) {
                    $shDoneByDate[$dateKey] = 0;
                }
                $shDoneByDate[$dateKey]++;
            }
        }

        return $shDoneByDate;
    }

    private function getClaimsDone($userId) {
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $userId);
        $oWh->greaterThanOrEqualTo('date', strtotime('-7 days'));

        $claimsDone = $this->mClaimTbl->select($oWh);
        $shDoneByDate = [];
        if(count($claimsDone) > 0) {
            foreach($claimsDone as $cl) {
                $dateKey = date('Y-m-d', strtotime($cl->date));
                if(!array_key_exists($dateKey,$shDoneByDate)) {
                    $shDoneByDate[$dateKey] = 0;
                }
                $shDoneByDate[$dateKey]++;
            }
        }

        return $shDoneByDate;
    }

    private function getOffersDone($userId) {
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $userId);
        $oWh->greaterThanOrEqualTo('date_completed', strtotime('-7 days'));
        $claimsDone = $this->mOfferwallUserTbl->select($oWh);
        $shDoneByDate = [];
        if(count($claimsDone) > 0) {
            foreach($claimsDone as $cl) {
                $dateKey = date('Y-m-d', strtotime($cl->date_completed));
                if(!array_key_exists($dateKey,$shDoneByDate)) {
                    $shDoneByDate[$dateKey] = 0;
                }
                $shDoneByDate[$dateKey]++;
            }
        }

        return $shDoneByDate;
    }
}
