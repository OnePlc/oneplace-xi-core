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
use Laminas\Db\Sql\Select;
use Laminas\Http\ClientStatic;
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
     * Quote Table
     *
     * @var TableGateway $mQuoteTbl
     * @since 1.0.0
     */
    protected $mQuoteTbl;

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
     * @var TableGateway
     */
    private $mTaskDoneTbl;
    /**
     * @var TableGateway
     */
    private $mTaskTbl;
    /**
     * @var TableGateway
     */
    private $mTokenTbl;

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
        $this->mQuoteTbl = new TableGateway('faucet_didyouknow', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mMapper = $mapper;
        $this->mTaskDoneTbl = new TableGateway('faucet_dailytask_user', $mapper);
        $this->mTaskTbl = new TableGateway('faucet_dailytask', $mapper);
        $this->mTokenTbl = new TableGateway('faucet_tokenbuy', $mapper);

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

        $claimAmount = 10;
        if($me->xp_level >= 20 && time() <= strtotime('2022-05-20')) {
            $claimAmount = 30;
        }

        # Set Timer for next claim
        $sTime = 0;
        $timeCheck = '-1 hour';
        # Lets check if there was a claim less than 60 minutes ago
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime($timeCheck)));
        $oClaimCheck = $this->mClaimTbl->select($oWh);
        if(count($oClaimCheck) > 0) {
            $oClaimCheck = $oClaimCheck->current();
            # override timer
            $sTime = strtotime($oClaimCheck->date_next)-time();
        }

        # Only show timer if GET
        $oRequest = $this->getRequest();

        /**
         * No captcha for token holders
         */
        $tokensBuys = $this->mTokenTbl->select(['sent' => 1, 'user_idfs' => $me->User_ID]);
        $tokens = 0;
        $disableCatpcha = 0;
        foreach($tokensBuys as $tb) {
            $tokens += $tb->amount;
        }
        if($tokens >= 50) {
            $disableCatpcha = 1;
        }

        if(!$oRequest->isPost()) {
            $oWhT = new Where();
            $oWhT->equalTo('user_idfs', $me->User_ID);
            $oWhT->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-24 hours')));
            $todayClaims = $this->mClaimTbl->select($oWhT)->count();

            # get some random satoshi quote
            $quotes = $this->mQuoteTbl->select(['page' => 'claim']);
            $quotesOrdered = [];
            foreach($quotes as $q) {
                $quotesOrdered[] = (object)['id' => $q->Tip_ID,'quote' => $q->description,'href' => $q->href];
            }
            $quoteRandom = rand(0, count($quotesOrdered)-1);
            $quote = $quotesOrdered[$quoteRandom];

            $viewData = [
                'status' => 'wait',
                'next_claim' => $sTime,
                'amount' => $claimAmount,
                'quote' => $quote,
                'captcha' => $disableCatpcha,
            ];

            $hasMessage = $this->mSecTools->getCoreSetting('faucet-claim-msg-content');
            if($hasMessage) {
                $message = $hasMessage;
                $messageType = $this->mSecTools->getCoreSetting('faucet-claim-msg-level');
                $xpReq = $this->mSecTools->getCoreSetting('faucet-claim-msg-xplevel');
                $addMsg = false;
                if($xpReq) {
                    if($me->xp_level >= $xpReq) {
                        $addMsg = true;
                    }
                } else {
                    $addMsg = true;
                }
                $countryReq = $this->mSecTools->getCoreSetting('faucet-claim-msg-country');
                if($countryReq) {
                    if($me->country == $countryReq) {
                        $addMsg = true;
                    } else {
                        $addMsg = false;
                    }
                }

                if($addMsg && strlen($message) > 0) {
                    $viewData['message'] = [
                        'type' => $messageType,
                        'message' => $message
                    ];
                }
            }

            return new ViewModel($viewData);
        }

        # Prevent double claims
        if($sTime > 0) {
            return new ApiProblemResponse(new ApiProblem(409, 'Already claimed - wait '.$sTime.' more seconds before claiming again'));
        } else {
            if(isset($_REQUEST['v2']) || isset($_REQUEST['v3'])) {
                if(isset($_REQUEST['v3'])) {
                    $json = IndexController::loadJSONFromRequestBody(['device','ad_id','advertiser', 'captcha', 'bonus'],$this->getRequest()->getContent());
                    if(!$json) {
                        return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
                    }
                } else {
                    $json = IndexController::loadJSONFromRequestBody(['device','ad_id','advertiser', 'captcha'],$this->getRequest()->getContent());
                    if(!$json) {
                        return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
                    }
                }

                if($disableCatpcha == 0) {
                    # Get Data from Request Body
                    $captchaToken = $json->captcha;
                    if(strlen($captchaToken) < 10) {
                        return new ApiProblemResponse(new ApiProblem(400, 'Please use the captcha'));
                    }
                    $captchaSecret = $this->mSecTools->getCoreSetting('hcaptcha-secret');
                    if($captchaSecret) {
                        $response = ClientStatic::post(
                            'https://hcaptcha.com/siteverify', [
                            'secret' => $captchaSecret,
                            'response' => $captchaToken
                        ]);

                        $status = $response->getStatusCode();
                        $googleResponse = $response->getBody();

                        $googleJson = json_decode($googleResponse);

                        if(!$googleJson->success) {
                            return new ApiProblemResponse(new ApiProblem(400, 'Captcha not valid. Please try again or contact support.'));
                        }
                    }
                }
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'You are using an old version of the faucet. Please reload the page or empty your browser cache so you have Version 2.0.5'));
            }

            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$json->device,$json->ad_id,$json->advertiser]);
            if($secResult !== 'ok') {
                /**
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Faucet Claim',
                ]); **/
                return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
            }

            $device = filter_var($json->device, FILTER_SANITIZE_STRING);
            $ad_id = filter_var($json->ad_id, FILTER_SANITIZE_STRING);
            $advertiser = filter_var($json->advertiser, FILTER_SANITIZE_STRING);
            $bonus = filter_var($json->bonus, FILTER_SANITIZE_NUMBER_INT);

            $nextTimer = 3600;
            if($platform == 'android' && $me->User_ID == 335874988) {
                $nextTimer = 60;
            }
            /**
            if($bonus == 1) {
                $claimAmount = $claimAmount * 3;
            }
             * **/

            # Set next claim date
            $nextDate = date('Y-m-d H:i:s', time()+$nextTimer);

            # Execute Claim Transaction
            $oTransHelper = new TransactionHelper($this->mMapper);
            $newBalance = $oTransHelper->executeTransaction($claimAmount, false, $me->User_ID, 10, 'web-faucet', 'Website Faucet claimed');
            if($newBalance) {
                # check if ip is blacklisted
                if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $sIpAddr = filter_var ($_SERVER['HTTP_CLIENT_IP'], FILTER_SANITIZE_STRING);
                } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $sIpAddr = filter_var ($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_SANITIZE_STRING);
                } else {
                    $sIpAddr = filter_var ($_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_STRING);
                }

                if($device == 'browser') {
                    $device = substr($_SERVER['HTTP_USER_AGENT'],0,100);
                }

                # Execute Claim
                $this->mClaimTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'date' => date('Y-m-d H:i:s', time()),
                    'date_next' => $nextDate,
                    'amount' => $claimAmount,
                    //'mode' => 'coins',
                    //'source' => ($platform != 'website') ? 'android' : 'website',
                    //'ad_id' => $ad_id,
                    //'advertiser' => $advertiser,
                    'device' => $device,
                    'claim_ip' => $sIpAddr
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

                # check for achievement completetion
                $currentClaimsDone = $this->mClaimTbl->select(['user_idfs' => $me->User_ID])->count();

                # check if user has completed an achievement
                if(array_key_exists($currentClaimsDone,$this->mAchievementPoints)) {
                    $this->mUserTools->completeAchievement($this->mAchievementPoints[$currentClaimsDone]->Achievement_ID, $me->User_ID);
                }

                if($me->client_version == '2.0') {
                    return new ApiProblemResponse(new ApiProblem(409, 'You are using an old version of the faucet. Please reload the website to update the site'));
                }

                # Show Timer
                return new ViewModel([
                    'status' => 'done',
                    'amount' => $claimAmount,
                    'next' => strtotime($nextDate)-time(),
                    'balance' => $newBalance,
                    'balance_crypto' => $oTransHelper->getCryptoBalance($newBalance, $me),
                    'xp_info' => [
                        'xp_level' => $me->xp_level,
                        'xp_current' => $me->xp_current,
                        'xp_percent' => $me->xp_percent,
                    ],
                    'achievement' => $achievDone,
                    'daily_claim_count' => $this->getDailyTasksReadyToClaim($me),
                ]);
            } else {
                return new ApiProblemResponse(new ApiProblem(409, 'Transaction Error Please contact admin'));
            }
        }
    }

    private function getDailyTasksReadyToClaim($me) {
        $sDate = date('Y-m-d', time());

        /**
         * Gather relevant data for progress
         */
        $shortlinksDone = 0;

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date', $sDate.'%');
        $claimsDone = $this->mClaimTbl->select($oWh)->count();

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date', $sDate.'%');
        $dailysToday = $this->mTaskDoneTbl->select($oWh);
        $dailyDoneById = [];
        foreach($dailysToday as $daily) {
            $dailyDoneById['task-'.$daily->task_idfs] = 1;
        }
        $dailysDone = $dailysToday->count();

        # Load Dailytasks
        $oWh = new Where();
        $oWh->NEST
            ->equalTo('mode', 'website')
            ->OR
            ->equalTo('mode', 'global')
            ->UNNEST;
        $dailySel = new Select($this->mTaskTbl->getTable());
        $dailySel->where($oWh);
        $dailySel->order('sort_id ASC');
        $achievementsDB = $this->mTaskTbl->selectWith($dailySel);
        $readyToClaim = 0;
        foreach($achievementsDB as $achiev) {
            switch($achiev->type) {
                case 'shortlink':
                    $progress = $shortlinksDone;
                    break;
                case 'claim':
                    $progress = $claimsDone;
                    break;
                case 'daily':
                    $progress = $dailysDone;
                    break;
                default:
                    $progress = 0;
                    break;
            }

            if($progress >= $achiev->goal && !array_key_exists('task-'.$achiev->Dailytask_ID, $dailyDoneById)) {
                $readyToClaim++;
            }
        }

        return $readyToClaim;
    }
}
