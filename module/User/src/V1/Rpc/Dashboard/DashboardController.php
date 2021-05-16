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
    }

    /**
     * Get User Dashboard Data
     *
     * @return ViewModel|ApiProblemResponse
     * @since 1.0.0
     */
    public function dashboardAction()
    {
        $me = $this->mSecTools->getSecuredUserSession();
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
            for($day = -7;$day <= 0;$day++) {
                $tasksDone = 0;
                $rewardsEarned = 0;
                # add date to labels
                $dayR = 0-$day;
                $date = ($dayR > 0) ? date('Y-m-d', strtotime('-'.$dayR.' days')) : date('Y-m-d', time());
                $chartLabels[] = $date;

                # Get Shortlinks done
                $shInfo = $this->getShortlinksDone($me->User_ID, $date);
                $tasksDone+=$shInfo['done'];
                $rewardsEarned+=$shInfo['reward'];

                # Get faucet claims
                $clInfo = $this->getClaimsDone($me->User_ID, $date);
                $tasksDone+=$clInfo['done'];
                $rewardsEarned+=$clInfo['reward'];

                # Get Offers done
                $owInfo = $this->getOffersDone($me->User_ID, $date);
                $tasksDone+=$owInfo['done'];
                $rewardsEarned+=$owInfo['reward'];

                if($coinsMax < ($rewardsEarned*1.2)) {
                    $coinsMax = ($rewardsEarned*1.2);
                }

                if($tasksMax < ($tasksDone*1.2)) {
                    $tasksMax = ($tasksDone*1.2);
                }

                $tasksDoneData[] = $tasksDone;
                $coinsEarnedData[] = $rewardsEarned;
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

    private function getShortlinksDone($userId, $date) {
        $shProviderRewards = [];
        $providers = $this->mShortTbl->select();
        foreach($providers as $prov) {
            $shProviderRewards[$prov->Shortlink_ID] = $prov->reward;
        }

        $reward = 0;
        $done = 0;

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $userId);
        $oWh->like('date_completed', $date.'%');
        $shortsDone = $this->mShortDoneTbl->select($oWh);
        if(count($shortsDone) > 0) {
            foreach($shortsDone as $shDone) {
                $reward+=$shProviderRewards[$shDone->shortlink_idfs];
                $done++;
            }
        }

        return [
            'done' => $done,
            'reward' => $reward
        ];
    }

    private function getClaimsDone($userId, $date) {
        $reward = 0;
        $done = 0;

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $userId);
        $oWh->like('date', $date.'%');
        $claimsDone = $this->mClaimTbl->select($oWh);
        if(count($claimsDone) > 0) {
            foreach($claimsDone as $cl) {
                $reward+=$cl->amount;
                $done++;
            }
        }

        return [
            'done' => $done,
            'reward' => $reward
        ];
    }

    private function getOffersDone($userId, $date) {
        $reward = 0;
        $done = 0;

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $userId);
        $oWh->like('date_completed', $date.'%');
        $claimsDone = $this->mOfferwallUserTbl->select($oWh);
        if(count($claimsDone) > 0) {
            foreach($claimsDone as $cl) {
                $reward+=$cl->amount;
                $done++;
            }
        }

        return [
            'done' => $done,
            'reward' => $reward
        ];
    }
}