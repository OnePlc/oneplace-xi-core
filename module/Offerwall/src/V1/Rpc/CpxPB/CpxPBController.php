<?php
namespace Offerwall\V1\Rpc\CpxPB;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class CpxPBController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Log Table
     *
     * @var TableGateway $mLogTbl
     * @since 1.0.0
     */
    protected $mLogTbl;

    /**
     * Offerwall User Table
     *
     * @var TableGateway $mOfferDoneTbl
     * @since 1.0.0
     */
    protected $mOfferDoneTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * User Buff Table
     *
     * @var TableGateway $mBuffTbl
     * @since 1.0.0
     */
    protected $mBuffTbl;

    /**
     * User Tools Helper
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Constructor
     *
     * AyetstudiosController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mLogTbl = new TableGateway('faucet_log', $mapper);
        $this->mOfferDoneTbl = new TableGateway('offerwall_user', $mapper);
        $this->mBuffTbl = new TableGateway('faucet_withdraw_buff', $mapper);
        $this->mTransaction = new TransactionHelper($mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
    }

    public function cpxPBAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            if (isset($_REQUEST['authkey'])) {
                $ayetPBKey = $this->mSecTools->getCoreSetting('cpx-pb-key');
                if($ayetPBKey) {
                    $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                    if ($checkKey == $ayetPBKey) {
                        $offerWallId = 1;
                        /**
                         * Verify only Ayet is sending this requset
                         */

                        /**
                         * Check User ID
                         */
                        $iUserID = filter_var($_REQUEST['user_id'], FILTER_SANITIZE_NUMBER_INT);
                        if($iUserID <= 0 || empty($iUserID)) {
                            return [
                                'state' => 'error',
                                'message' => 'invalid user id',
                            ];
                        }

                        $aReturn = [
                            'state' => 'error',
                            'message' => 'unknown error',
                        ];

                        /**
                         * Get User
                         */
                        $oUser = $this->mUserTbl->select(['User_ID' => $iUserID]);
                        if($oUser->count() == 0) {
                            return [
                                'state' => 'error',
                                'message' => 'user not found',
                            ];
                        }

                        /**
                         * Check for existing offer
                         */
                        $txId = filter_var($_REQUEST['trans_id'], FILTER_SANITIZE_STRING);
                        $cWh = new Where();
                        $cWh->equalTo('user_idfs', $iUserID);
                        $cWh->equalTo('offerwall_idfs', $offerWallId);
                        $cWh->like('transaction_id', $txId);
                        $oCheck = $this->mOfferDoneTbl->select($cWh);

                        if($oCheck->count() == 0) {
                            $amount = (float)filter_var($_REQUEST['amount_local'], FILTER_SANITIZE_STRING);
                            $amountUsd = (float)filter_var($_REQUEST['amount_usd'], FILTER_SANITIZE_STRING);
                            $offerId = filter_var($_REQUEST['offer_id'], FILTER_SANITIZE_STRING);
                            $hash = filter_var($_REQUEST['hash'], FILTER_SANITIZE_STRING);
                            $offerName = filter_var($_REQUEST['offer_id'], FILTER_SANITIZE_STRING);

                            if($amount <= 0 || empty($amount) || $amountUsd <= 0 || empty($amountUsd)) {
                                return [
                                    'state' => 'error',
                                    'message' => 'invalid amount',
                                ];
                            }

                            $addBonus = true;
                            $now = date('Y-m-d H:i:s', time());

                            // log big offers
                            if($amountUsd >= 20) {
                                $this->mLogTbl->insert([
                                    'log_type' => 'offer-big',
                                    'log_level' => 'warning',
                                    'log_message' => 'Offer with Big Amount completed',
                                    'log_info' => '{"userId":'.$iUserID.',"offerId":'.$offerId.',"offerWall":'.$offerWallId.'}',
                                    'log_date' => $now
                                ]);
                            }

                            $checkRepeat = true;
                            $ayetWhiteList = $this->mSecTools->getCoreSetting('cpx-whitelist-offers');
                            if($ayetWhiteList) {
                                $ayetWhiteList = json_decode($ayetWhiteList);
                                if (in_array($offerId, $ayetWhiteList)) {
                                    $checkRepeat = false;
                                }
                            }

                            if($checkRepeat) {
                                $scamWh = new Where();
                                $scamWh->equalTo('offer_id', $offerId);
                                $scamWh->equalTo('offerwall_idfs', $offerWallId);
                                $scamWh->greaterThanOrEqualTo('date_completed', date('Y-m-d H:i:s', strtotime('-7 days')));

                                $oScamCheck = $this->mOfferDoneTbl->select($scamWh)->count();
                                if($oScamCheck >= 3) {
                                    // log same offer done again over and over
                                    $this->mLogTbl->insert([
                                        'log_type' => 'offer-repeat',
                                        'log_level' => 'error',
                                        'log_message' => 'Offer done more than 5 times a week',
                                        'log_info' => '{"userId":'.$iUserID.',"offerId":'.$offerId.',"offerWall":'.$offerWallId.'}',
                                        'log_date' => $now
                                    ]);

                                    // dont give withdrawal bonus yet
                                    $addBonus = false;
                                }
                            }

                            $this->mOfferDoneTbl->insert([
                                'user_idfs' => $iUserID,
                                'offerwall_idfs' => $offerWallId,
                                'transaction_id' => $txId,
                                'amount' => $amount,
                                'amount_usd' => $amountUsd,
                                'offer_id' => $offerId,
                                'hash' => $hash,
                                'label' => $offerName,
                                'date_started' => '0000-00-00 00:00:00',
                                'date_completed' => $now,
                                'date_claimed' => $now,
                                'api_response' => json_encode($_REQUEST),
                            ]);

                            $newBalance = $this->mTransaction->executeTransaction($amount, false, $iUserID, $offerWallId, 'offer-done', 'Offer '.$offerName.' completed', $iUserID);
                            if($newBalance) {
                                if($addBonus && $amount >= 5000) {
                                    $this->mUserTools->addXP('cpx-claim', $iUserID);

                                    $bonusBuff = round($amount / 14);

                                    $this->mBuffTbl->insert([
                                        'ref_idfs' => $offerWallId,
                                        'ref_type' => 'offerwall',
                                        'label' => $offerName,
                                        'days_left' => 14,
                                        'days_total' => 14,
                                        'amount' => $bonusBuff,
                                        'created_date' => date('Y-m-d H:i:s', time()),
                                        'user_idfs' => $iUserID
                                    ]);

                                    /**
                                    $this->mBuffTbl->insert([
                                        'source_idfs' => 44,
                                        'source_type' => 'item',
                                        'date' => $now,
                                        'expires' => date('Y-m-d H:i:s', time() + ((3600*24)*14)),
                                        'buff' => $bonusBuff,
                                        'buff_type' => 'daily-withdraw-buff',
                                        'user_idfs' => $iUserID
                                    ]); **/
                                } else {
                                    $this->mUserTools->addXP('cpx-claim-small', $iUserID);
                                }
                                return [
                                    'state' => 'success'
                                ];
                            } else {
                                return [
                                    'state' => 'error',
                                    'message' => 'payment error',
                                ];
                            }
                        } else {
                            return [
                                'state' => 'error',
                                'message' => 'already done',
                            ];
                        }
                    }
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));
    }
}
