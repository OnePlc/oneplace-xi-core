<?php
namespace Offerwall\V1\Rpc\BitlabsPB;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class BitlabsPBController extends AbstractActionController
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

    public function bitlabsPBAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            $this->layout('layout/json');
            if (isset($_REQUEST['authkey'])) {
                $ayetPBKey = $this->mSecTools->getCoreSetting('bitlabs-pb-key');
                if($ayetPBKey) {
                    $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                    if ($checkKey == $ayetPBKey) {
                        $offerWallId = 17;

                        $secret_key = $this->mSecTools->getCoreSetting('bitlabs-secret');
                        // Get the currently active http protocol
                        $protocol = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http";
                        // Build the full callback URL
                        // Example: https://url.com?param1=foo&param2=bar&hash=3171f6b78e06cadcec4c9c3b15f858b8400e8738
                        $url = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                        // Save all query parameters of the callback into the $params array
                        $url_components = parse_url($url);
                        parse_str($url_components["query"], $params);
                        // Get the callback URL without the "hash" query parameter
                        // Example: https://url.com?param1=foo&param2=bar
                        $url_val = substr($url, 0, -strlen("&hash=$params[hash]"));
                        // Generate a hash from the complete callback URL without the "hash" query parameter
                        $hash = hash_hmac("sha1", $url_val, $secret_key);

                        //Check if the generated hash is the same as the "hash" query parameter
                        if ($params["hash"] !== $hash) {
                            echo "SEC";
                            return false;
                        }

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
                        $hcheck = filter_var($_REQUEST['hash'], FILTER_SANITIZE_STRING);
                        $txId = filter_var($_REQUEST['tx'], FILTER_SANITIZE_STRING);
                        $cWh = new Where();
                        $cWh->equalTo('user_idfs', $iUserID);
                        $cWh->equalTo('offerwall_idfs', $offerWallId);
                        $cWh->like('hash', $hcheck);
                        $oCheck = $this->mOfferDoneTbl->select($cWh);

                        if($oCheck->count() == 0) {
                            $amount = (float)filter_var($_REQUEST['amount'], FILTER_SANITIZE_STRING);
                            $amountUsd = (float)filter_var($_REQUEST['payout'], FILTER_SANITIZE_STRING);
                            $offerId = filter_var($_REQUEST['tx'], FILTER_SANITIZE_STRING);
                            $hash = filter_var($_REQUEST['hash'], FILTER_SANITIZE_STRING);
                            $offerName = filter_var($_REQUEST['campaign_name'], FILTER_SANITIZE_STRING);

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
                                'offer_id' => $offerId.'-'.$oCheck->count(),
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
                                echo "OK";
                                return false;
                            } else {
                                echo "DUP";
                                return false;
                            }
                        } else {
                            echo "DUP";
                            return false;
                        }
                    }
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));
    }
}
