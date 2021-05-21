<?php
/**
 * ClaimController.php - Claim Controller
 *
 * Main Controller for Faucet Claim
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rpc\Claim;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Session\Container;

class ClaimController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Basic Tools
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Claim Table
     *
     * @var TableGateway $mClaimTbl
     * @since 1.0.0
     */
    protected $mClaimTbl;

    /**
     * Database Connection
     *
     * @var $mMapper
     * @since 1.0.0
     */
    private $mMapper;

    /**
     * Shortlink Achievements
     *
     * @var array $mAchievementPoints
     * @since 1.0.0
     */
    protected $mAchievementPoints;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mClaimTbl = new TableGateway('faucet_claim', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mMapper = $mapper;

        /**
         * Load Achievements to Cache
         */
        $achievTbl = new TableGateway('faucet_achievement', $mapper);
        $achievsXP = $achievTbl->select(['type' => 'faucetclaim', 'mode' => 'website']);
        $achievsFinal = [];
        if(count($achievsXP) > 0) {
            foreach($achievsXP as $achiev) {
                $achievsFinal[$achiev->goal] = $achiev;
            }
        }
        $this->mAchievementPoints = $achievsFinal;
    }

    /**
     * Faucet Free Crypto Claim
     *
     * @return ApiProblem|ViewModel
     * @since 1.0.0
     */
    public function claimAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $platform = (isset($_REQUEST['platform'])) ? filter_var($_REQUEST['platform'], FILTER_SANITIZE_STRING) : 'website';
        if($platform != 'website' && $platform != 'android') {
            return new ApiProblemResponse(new ApiProblem(400, 'invalid platform'));
        }

        # Set Timer for next claim
        $sTime = 0;

        # Lets check if there was a claim less than 60 minutes ago
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('source', $platform);
        $oWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-1 hour')));
        $oClaimCheck = $this->mClaimTbl->select($oWh);
        if(count($oClaimCheck) > 0) {
            $oClaimCheck = $oClaimCheck->current();
            # override timer
            $sTime = strtotime($oClaimCheck->date_next)-time();
        }

        # Only show timer if GET
        $oRequest = $this->getRequest();
        if(!$oRequest->isPost()) {
            if($platform == 'android') {
                $sTime = 60;
            }
            return new ViewModel([
                'status' => 'wait',
                'next_claim' => $sTime,
            ]);
        }

        # Prevent double claims
        if($sTime > 0) {
            return new ApiProblemResponse(new ApiProblem(409, 'Already claimed - wait '.$sTime.' more seconds before claiming again'));
        } else {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['device','ad_id','advertiser'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$json->device,$json->ad_id,$json->advertiser]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Faucet Claim',
                ]);
                return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
            }

            $device = filter_var($json->device, FILTER_SANITIZE_STRING);
            $ad_id = filter_var($json->ad_id, FILTER_SANITIZE_STRING);
            $advertiser = filter_var($json->advertiser, FILTER_SANITIZE_STRING);

            # Default Claim
            switch($advertiser) {
                case 'adcolony':
                    $claimAmount = rand(10,25);
                    break;
                default:
                    $claimAmount = 10;
                    break;
            }

            # Set next claim date
            $nextDate = date('Y-m-d H:i:s', time()+3600);

            # Execute Claim Transaction
            $oTransHelper = new TransactionHelper($this->mMapper);
            $newBalance = $oTransHelper->executeTransaction($claimAmount, false, $me->User_ID, 10, 'web-faucet', 'Website Faucet claimed');
            if($newBalance) {
                # Execute Claim
                $this->mClaimTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'date' => date('Y-m-d H:i:s', time()),
                    'date_next' => $nextDate,
                    'amount' => $claimAmount,
                    'mode' => 'coins',
                    'source' => ($platform != 'website') ? 'android' : 'website',
                    'ad_id' => $ad_id,
                    'advertiser' => $advertiser,
                    'device' => $device
                ]);

                # Add User XP
                $achievDone = (object)[];
                $newLevel = $this->mUserTools->addXP('faucet-claim', $me->User_ID);
                if($newLevel !== false) {
                    $me->xp_level = $newLevel['xp_level'];
                    $me->xp_current = $newLevel['xp_current'];
                    $me->xp_percent = $newLevel['xp_percent'];

                    # check if achievement got completed
                    if($newLevel['achievement']->id != 0) {
                        $achievDone = $newLevel['achievement'];
                    }
                }
                $tokenValue = $oTransHelper->getTokenValue();

                # check for achievement completetion
                $currentClaimsDone = $this->mClaimTbl->select(['user_idfs' => $me->User_ID,'source' => $platform])->count();

                # check if user has completed an achievement
                if(array_key_exists($currentClaimsDone,$this->mAchievementPoints)) {
                    $this->mUserTools->completeAchievement($this->mAchievementPoints[$currentClaimsDone]->Achievement_ID, $me->User_ID);
                }

                # Show Timer
                return new ViewModel([
                    'status' => 'done',
                    'amount' => $claimAmount,
                    'next' => strtotime($nextDate)-time(),
                    'balance' => $newBalance,
                    'balance_crypto' => $oTransHelper->getCryptoBalance($newBalance, $me),
                    'xp_level' => $me->xp_level,
                    'xp_current' => $me->xp_current,
                    'xp_percent' => $me->xp_percent,
                    'achievement' => $achievDone,
                ]);
            } else {
                return new ApiProblemResponse(new ApiProblem(409, 'Transaction Error Please contact admin'));
            }
        }
    }
}
