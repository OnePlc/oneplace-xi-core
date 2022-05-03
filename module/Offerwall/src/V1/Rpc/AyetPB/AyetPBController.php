<?php
namespace Offerwall\V1\Rpc\AyetPB;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class AyetPBController extends AbstractActionController
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
        $this->mBuffTbl = new TableGateway('user_buff', $mapper);
        $this->mTransaction = new TransactionHelper($mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function ayetPBAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            if (isset($_REQUEST['authkey'])) {
                $ayetPBKey = $this->mSecTools->getCoreSetting('ayet-pb-key');
                if($ayetPBKey) {
                    $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                    if ($checkKey == $ayetPBKey) {
                        $offerWallId = 5;
                        /**
                         * Verify only Ayet is sending this requset
                         */
                        ksort($_REQUEST, SORT_STRING);
                        $sortedQueryString = http_build_query($_REQUEST, '', '&'); // "adslot_id=123&currency_amount=100&payout_usd=1.5...."
                        $securityHash = hash_hmac('sha256', $sortedQueryString, '987c27e2f3e96486837a5c9472576a8b');

                        /**
                        if($_SERVER['HTTP_X_AYETSTUDIOS_SECURITY_HASH']===$securityHash) { // actually sent as X-Ayetstudios-Security-Hash but converted by apache2 in this example
                        // success
                        } else {
                        $aReturn = [
                        'state' => 'error',
                        'message' => 'not allowed - only ayet server can access',
                        ];
                        echo json_encode($aReturn);
                        return false;
                        }**/

                        /**
                         * Check User ID
                         */
                        $iUserID = filter_var($_REQUEST['uid'], FILTER_SANITIZE_NUMBER_INT);
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
                        $txId = filter_var($_REQUEST['tx_id'], FILTER_SANITIZE_STRING);
                        $oCheck = $this->mOfferDoneTbl->select([
                            'user_idfs' => $iUserID,
                            'offerwall_idfs' => $offerWallId,
                            'transaction_id' => $txId,
                        ]);

                        if($oCheck->count() == 0) {
                            $amount = (float)filter_var($_REQUEST['currency_amount'], FILTER_SANITIZE_STRING);
                            $amountUsd = (float)filter_var($_REQUEST['payout_usd'], FILTER_SANITIZE_STRING);
                            $offerId = filter_var($_REQUEST['offer_id'], FILTER_SANITIZE_STRING);
                            $hash = filter_var($_REQUEST['sh1_imei'], FILTER_SANITIZE_STRING);
                            $offerName = filter_var($_REQUEST['offer_name'], FILTER_SANITIZE_STRING);

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

                            $scamWh = new Where();
                            $scamWh->equalTo('offer_id', $offerId);
                            $scamWh->equalTo('offerwall_idfs', $offerWallId);
                            $scamWh->greaterThanOrEqualTo('date_completed', date('Y-m-d H:i:s', strtotime('-7 days')));

                            $oScamCheck = $this->mOfferDoneTbl->select($scamWh)->count();
                            if($oScamCheck >= 5) {
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
                                    $bonusBuff = round(($amount - 250) / 14);
                                    $this->mBuffTbl->insert([
                                        'source_idfs' => 44,
                                        'source_type' => 'item',
                                        'date' => $now,
                                        'expires' => date('Y-m-d H:i:s', time() + ((3600*24)*14)),
                                        'buff' => $bonusBuff,
                                        'buff_type' => 'daily-withdraw-buff',
                                        'user_idfs' => $iUserID
                                    ]);
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