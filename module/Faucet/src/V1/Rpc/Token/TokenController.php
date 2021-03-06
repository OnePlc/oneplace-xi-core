<?php
/**
 * TokenController.php - Token Controller
 *
 * Main Controller for Faucet Token
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Faucet\V1\Rpc\Token;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Http\ClientStatic;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Http\Client;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class TokenController extends AbstractActionController
{
    /**
     * User Basic Tools
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * Faucet Wallets Table
     *
     * @var TableGateway $mWalletTbl
     * @since 1.0.0
     */
    protected $mWalletTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Token Buy Requset Table
     *
     * @var TableGateway $mTokenBuyTbl
     * @since 1.0.0
     */
    protected $mTokenBuyTbl;

    /**
     * Token Pay Table
     *
     * @var TableGateway $mTokenPayTbl
     * @since 1.0.0
     */
    protected $mTokenPayTbl;

    /**
     * Token Pay History Table
     *
     * @var TableGateway $mTokenPayHistoryTbl
     * @since 1.0.0
     */
    protected $mTokenPayHistoryTbl;

    /**
     * Constructor
     *
     * TokenController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mTokenBuyTbl = new TableGateway('faucet_tokenbuy', $mapper);
        $this->mTokenPayTbl = new TableGateway('faucet_tokenpay', $mapper);
        $this->mTokenPayHistoryTbl = new TableGateway('faucet_tokenpay_history', $mapper);
    }

    /**
     * Token Buy Request and Statistics
     *
     * @return array|ApiProblemResponse
     * @since 1.0.0
     */
    public function tokenAction()
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
            # get all sent tokens for user
            $totalTokenDB = $this->mTokenBuyTbl->select(['user_idfs' => $me->User_ID, 'sent' => 1]);
            $totalToken = 0;
            if(count($totalTokenDB) > 0) {
                foreach($totalTokenDB as $tok) {
                    $totalToken+=$tok->amount;
                }
            }

            # get all pending tokens for user
            $pendingTokenDB = $this->mTokenBuyTbl->select(['user_idfs' => $me->User_ID, 'sent' => 0, 'cancelled' => 0]);
            $pendingToken = 0;
            if(count($pendingTokenDB) > 0) {
                foreach($pendingTokenDB as $tok) {
                    $pendingToken+=$tok->amount;
                }
            }

            # get all sent tokens
            $soldTokenDB = $this->mTokenBuyTbl->select(['sent' => 1]);
            $soldToken = 0;
            if(count($soldTokenDB) > 0) {
                foreach($soldTokenDB as $tok) {
                    $soldToken+=$tok->amount;
                }
            }

            # get price per token in crypto
            $tokenValue = $this->mTransaction->getTokenValue();
            $coinInfoBCH = $this->mWalletTbl->select(['coin_sign' => 'BCH'])->current();
            $amountCrypto = 2500*$tokenValue;
            if($coinInfoBCH->dollar_val > 0) {
                $amountCryptoBCH = number_format($amountCrypto/$coinInfoBCH->dollar_val,8,'.','');
            } else {
                $amountCryptoBCH = number_format($amountCrypto*$coinInfoBCH->dollar_val,8,'.','');
            }

            $coinInfoLTC = $this->mWalletTbl->select(['coin_sign' => 'LTC'])->current();
            if($coinInfoLTC->dollar_val > 0) {
                $amountCryptoLTC = number_format($amountCrypto/$coinInfoLTC->dollar_val,8,'.','');
            } else {
                $amountCryptoLTC = number_format($amountCrypto*$coinInfoLTC->dollar_val,8,'.','');
            }

            $coinInfoZEN = $this->mWalletTbl->select(['coin_sign' => 'ZEN'])->current();
            if($coinInfoZEN->dollar_val > 0) {
                $amountCryptoZEN = number_format($amountCrypto/$coinInfoZEN->dollar_val,8,'.','');
            } else {
                $amountCryptoZEN = number_format($amountCrypto*$coinInfoZEN->dollar_val,8,'.','');
            }

            $coinInfoDOGE = $this->mWalletTbl->select(['coin_sign' => 'DOGE'])->current();
            if($coinInfoDOGE->dollar_val > 0) {
                $amountCryptoDOGE = number_format($amountCrypto/$coinInfoDOGE->dollar_val,8,'.','');
            } else {
                $amountCryptoDOGE = number_format($amountCrypto*$coinInfoDOGE->dollar_val,8,'.','');
            }

            # get wallets for crypto payments
            $walletBCH = $this->mSecTools->getCoreSetting('tokenbuy-BCH');
            $walletLTC = $this->mSecTools->getCoreSetting('tokenbuy-LTC');
            $payWallets = [
                'COINS' => 'Swissfaucet.io Coin Burn',
                'BCH' => $walletBCH,
                'LTC' => $walletLTC,
            ];

            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $pageSize = 10;

            # user token history
            $tokenHistory = [];
            $buyHistorySel = new Select($this->mTokenBuyTbl->getTable());
            $buyHistorySel->where(['user_idfs' => $me->User_ID]);
            $buyHistorySel->order(['date DESC']);

            # Create a new pagination adapter object
            $oPaginatorAdapterBuy = new DbSelect(
            # our configured select object
                $buyHistorySel,
                # the adapter to run it against
                $this->mTokenBuyTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $buyPaginated = new Paginator($oPaginatorAdapterBuy);
            $buyPaginated->setCurrentPageNumber($page);
            $buyPaginated->setItemCountPerPage($pageSize);
            foreach($buyPaginated as $tk) {
                $tokenHistory[] = (object)[
                    'id' => $tk->Buy_ID,
                    'date' => $tk->date,
                    'coin' => $tk->coin,
                    'wallet' => $tk->wallet,
                    'amount' => $tk->amount,
                    'price' => $tk->price,
                    'wallet_pay' => ($tk->wallet_receive == null) ? $payWallets[$tk->coin] : $tk->wallet_receive,
                    'sent' => ($tk->sent == 1) ? true : false,
                    'cancelled' => ($tk->cancelled == 1) ? true : false,
                    'received' => ($tk->received == 1) ? true : false,
                ];
            }

            /**
            $tokenHistory = [];
            $myTokenTrans = $this->mTokenBuyTbl->select(['user_idfs' => $me->User_ID]);
            if(count($myTokenTrans) > 0) {
                foreach($myTokenTrans as $tk) {
                    $tokenHistory[] = (object)[
                        'id' => $tk->Buy_ID,
                        'date' => $tk->date,
                        'coin' => $tk->coin,
                        'wallet' => $tk->wallet,
                        'amount' => $tk->amount,
                        'price' => $tk->price,
                        'wallet_pay' => ($tk->wallet_receive == null) ? $payWallets[$tk->coin] : $tk->wallet_receive,
                        'sent' => ($tk->sent == 1) ? true : false,
                        'cancelled' => ($tk->cancelled == 1) ? true : false,
                        'received' => ($tk->received == 1) ? true : false,
                    ];
                }
            }
             * **/
            $totalBuyHistory = $this->mTokenBuyTbl->select(['user_idfs' => $me->User_ID])->count();


            # daily limit
            $todayWh = new Where();
            $todayWh->like('date', date('Y-m-d', time()).'%');
            $todayWh->equalTo('user_idfs', $me->User_ID);
            $tokenBuyToday = $this->mTokenBuyTbl->select($todayWh);
            $tokenBuyedToday = 0;
            if(count($tokenBuyToday) > 0) {
                foreach($tokenBuyToday as $tkbuy) {
                    $tokenBuyedToday+=$tkbuy->amount;
                }
            }
            $tokenLeft = 1000-$tokenBuyedToday;

            $paySel = new Select($this->mTokenPayTbl->getTable());
            $paySel->order('week DESC');
            $paySel->where(['year' => date('Y', time())]);
            $paySel->limit(1);
            $paymentInfo = $this->mTokenPayTbl->selectWith($paySel);
            $lastPayment = 0;
            $tokenValue = 0;
            $linkedTokens = 0;
            if(count($paymentInfo) > 0) {
                $paymentInfo = $paymentInfo->current();
                $lastPayment = $paymentInfo->payment_total + $paymentInfo->admin_bonus;
                $tokenValue = $paymentInfo->coins_per_token;
                $linkedTokens = $paymentInfo->tokens_circulating;
            }

            # Compile history
            $stakingHistory = [];
            $historySel = new Select($this->mTokenPayHistoryTbl->getTable());
            $historySel->where(['user_idfs' => $me->User_ID]);
            $historySel->order(['year DESC', 'week DESC']);
            # Create a new pagination adapter object
            $oPaginatorAdapterStak = new DbSelect(
            # our configured select object
                $historySel,
                # the adapter to run it against
                $this->mTokenPayHistoryTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $stakPaginated = new Paginator($oPaginatorAdapterStak);
            $stakPaginated->setCurrentPageNumber($page);
            $stakPaginated->setItemCountPerPage($pageSize);
            foreach($stakPaginated as $stak) {
                $stakingHistory[] = (object)[
                    'week' => $stak->week,
                    'year' => $stak->year,
                    'wallet' => $stak->wallet,
                    'coins' => $stak->coins,
                    'token' => $stak->token,
                ];
            }

            $totalStakingHistory = $this->mTokenPayHistoryTbl->select(['user_idfs' => $me->User_ID])->count();

            $chartValues = [];
            $chartIncome = [];
            $chartCost = [];
            $chartProfit = [];
            $chartPercent = [];
            $chartLabels = [];
            $chartBonus = [];
            $chartTokens = [];
            $totalPay = 0;

            $paySel = new Select($this->mTokenPayTbl->getTable());
            $paySel->order(['week DESC']);
            $paySel->where(['year' => date('Y', time())]);
            $lastPayments = $this->mTokenPayTbl->selectWith($paySel);
            if($lastPayments->count() > 0) {
                foreach($lastPayments as $lp) {
                    $chartLabels[$lp->week] = 'Week '.$lp->week.' '.$lp->year;
                    $chartValues[$lp->week] = $lp->coins_per_token;
                    $chartIncome[$lp->week] = $lp->total_in;
                    $chartCost[$lp->week] = $lp->total_out;
                    $chartPercent[$lp->week] = $lp->active_bonus;
                    $chartProfit[$lp->week] = $lp->total_profit;
                    $chartBonus[$lp->week] = $lp->admin_bonus;
                    $chartTokens[$lp->week] = $lp->tokens_circulating;
                    $totalPay+=$lp->coins_per_token;
                }
            }

            ksort($chartLabels);
            ksort($chartValues);
            ksort($chartIncome);
            ksort($chartCost);
            ksort($chartPercent);
            ksort($chartProfit);
            ksort($chartBonus);
            ksort($chartTokens);

            $finLabels = [];
            foreach($chartLabels as $chartLabel) {
                $finLabels[] = $chartLabel;
            }
            $chartLabels = $finLabels;

            $finLabels = [];
            foreach($chartValues as $chartValue) {
                $finLabels[] = $chartValue;
            }
            $chartValues = $finLabels;

            $finLabels = [];
            foreach($chartIncome as $chartIncom) {
                $finLabels[] = $chartIncom;
            }
            $chartIncome = $finLabels;

            $finLabels = [];
            foreach($chartCost as $chartCos) {
                $finLabels[] = $chartCos;
            }
            $chartCost = $finLabels;

            $finLabels = [];
            foreach($chartPercent as $chartPercen) {
                $finLabels[] = $chartPercen;
            }
            $chartPercent = $finLabels;

            $finLabels = [];
            foreach($chartProfit as $chartProfi) {
                $finLabels[] = $chartProfi;
            }
            $chartProfit = $finLabels;

            $finLabels = [];
            foreach($chartBonus as $chartBoni) {
                $finLabels[] = $chartBoni;
            }
            $chartBonus = $finLabels;

            $finLabels = [];
            foreach($chartTokens as $chartToken) {
                $finLabels[] = $chartToken;
            }
            $chartTokens = $finLabels;

            return [
                '_links' => [],
                '_embedded' => [
                    'user' => [
                        'total' => (int)$totalToken,
                        'pending' => (int)$pendingToken,
                        'history' => $tokenHistory,
                        'page_count' => (round($totalBuyHistory/$pageSize) > 0) ? round($totalBuyHistory/$pageSize) : 1,
                        'today_left' => $tokenLeft,
                    ],
                    'token' => [
                        'history' => [
                          'total_items' => $totalStakingHistory,
                          'items' => $stakingHistory,
                          'page' => $page,
                          'page_size' => $pageSize,
                          'page_count' => (round($totalStakingHistory/$pageSize) > 0) ? round($totalStakingHistory/$pageSize) : 1,
                        ],
                        'active_bonus' => 5,
                        'next_bonus' => 10,
                        'next_bonus_percent' => round(($soldToken / 100000) * 100 ,2),
                        'price' => 2500,
                        'wallet_bch' => $walletBCH,
                        'wallet_ltc' => $walletLTC,
                        'bch_price' => number_format($amountCryptoBCH, 8),
                        'ltc_price' => number_format($amountCryptoLTC,8),
                        'zen_price' => number_format($amountCryptoZEN,8),
                        'doge_price' => number_format($amountCryptoDOGE,8),
                        'total' => 21000000,
                        'sold' => (int)$soldToken,
                        'linked' => $linkedTokens,
                        'last_payment' => number_format($lastPayment,2),
                        'value' => $tokenValue,
                    ],
                    'history' => [
                        'chart' => [
                            'data' => $chartValues,
                            'labels' => $chartLabels,
                            'income' => $chartIncome,
                            'cost' => $chartCost,
                            'profit' => $chartProfit,
                            'bonus' => $chartBonus,
                            'percent' => $chartPercent,
                            'tokens' => $chartTokens
                        ],
                        'total' => $totalPay
                    ]
                ]
            ];
        }

        if($request->isPost()) {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['amount','coin','wallet'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$json->amount,$json->coin,$json->wallet]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Buy Token',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
            }

            # Check if user is verified
            if($me->email_verified == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before buying tokens'));
            }

            $wallet = filter_var($json->wallet, FILTER_SANITIZE_STRING);
            if(strtolower(substr($wallet,0,1)) != 'r') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Ravencoin address'));
            }

            $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
            if($amount <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Amount'));
            }

            $coin = filter_var($json->coin, FILTER_SANITIZE_STRING);
            if($coin != 'COINS' && $coin != 'USD') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Payment Method'));
            }

            $todayWh = new Where();
            $todayWh->like('date', date('Y-m-d', time()).'%');
            $todayWh->equalTo('user_idfs', $me->User_ID);
            $tokenBuyToday = $this->mTokenBuyTbl->select($todayWh);
            $tokenBuyedToday = 0;
            if(count($tokenBuyToday) > 0) {
                foreach($tokenBuyToday as $tkbuy) {
                    $tokenBuyedToday+=$tkbuy->amount;
                }
            }
            $tokenLeft = 1000-$tokenBuyedToday;
            if($tokenLeft-$amount < 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'You have already reached the daily limit of 1000 token. You can buy more tomorrow.'));
            }

            $walletReceive = "";

            $tokenPrice = 2500;
            # get price per token in crypto
            $tokenValue = $this->mTransaction->getTokenValue();
            $amountCrypto = ($amount*$tokenPrice)*$tokenValue;
            $price = 0;

            switch(strtolower($coin)) {
                /**
                case 'doge':
                    $sBCHNodeUrl = $this->mSecTools->getCoreSetting('dogenode-rpcurl');
                    if($sBCHNodeUrl) {
                        $client = new Client();
                        $client->setUri($sBCHNodeUrl);
                        $client->setMethod('POST');
                        $client->setRawBody('{"jsonrpc":"2.0","id":"curltext","method":"getnewaddress","params":[]}');
                        $response = $client->send();
                        $googleResponse = json_decode($response->getBody());
                        $walletReceive = $googleResponse->result;
                    }
                    $coinInfoBCH = $this->mWalletTbl->select(['coin_sign' => 'DOGE'])->current();
                    if($coinInfoBCH->dollar_val > 0) {
                        $price = number_format($amountCrypto/$coinInfoBCH->dollar_val,8,'.','');
                    } else {
                        $price = number_format($amountCrypto*$coinInfoBCH->dollar_val,8,'.','');
                    }
                    break;
                case 'bch':
                    $sBCHNodeUrl = $this->mSecTools->getCoreSetting('bchnode-rpcurl');
                    if($sBCHNodeUrl) {
                        $client = new Client();
                        $client->setUri($sBCHNodeUrl);
                        $client->setMethod('POST');
                        $client->setRawBody('{"jsonrpc":"2.0","id":"curltext","method":"getnewaddress","params":[]}');
                        $response = $client->send();
                        $googleResponse = json_decode($response->getBody());
                        $walletReceive = $googleResponse->result;
                    }
                    $coinInfoBCH = $this->mWalletTbl->select(['coin_sign' => 'BCH'])->current();
                    if($coinInfoBCH->dollar_val > 0) {
                        $price = number_format($amountCrypto/$coinInfoBCH->dollar_val,8,'.','');
                    } else {
                        $price = number_format($amountCrypto*$coinInfoBCH->dollar_val,8,'.','');
                    }
                    break;
                case 'ltc':
                    $sLTCNodeUrl = $this->mSecTools->getCoreSetting('ltcnode-rpcurl');
                    if($sLTCNodeUrl) {
                        $client = new Client();
                        $client->setUri($sLTCNodeUrl);
                        $client->setMethod('POST');
                        $client->setRawBody('{"jsonrpc":"2.0","id":"curltext","method":"getnewaddress","params":[]}');
                        $response = $client->send();
                        $googleResponse = json_decode($response->getBody());
                        $walletReceive = $googleResponse->result;
                    }
                    $coinInfoLTC = $this->mWalletTbl->select(['coin_sign' => 'LTC'])->current();
                    if($coinInfoLTC->dollar_val > 0) {
                        $price = number_format($amountCrypto/$coinInfoLTC->dollar_val,8,'.','');
                    } else {
                        $price = number_format($amountCrypto*$coinInfoLTC->dollar_val,8,'.','');
                    }
                    break;
                case 'zen':
                    $sZENNodeUrl = $this->mSecTools->getCoreSetting('zennode-rpcurl');
                    if($sZENNodeUrl) {
                        $client = new Client();
                        $client->setUri($sZENNodeUrl);
                        $client->setMethod('POST');
                        $client->setRawBody('{"jsonrpc":"2.0","id":"curltext","method":"getnewaddress","params":[]}');
                        $response = $client->send();
                        $googleResponse = json_decode($response->getBody());
                        $walletReceive = $googleResponse->result;
                    }
                    $coinInfoZEN = $this->mWalletTbl->select(['coin_sign' => 'ZEN'])->current();
                    if($coinInfoZEN->dollar_val > 0) {
                        $price = number_format($amountCrypto/$coinInfoZEN->dollar_val,8,'.','');
                    } else {
                        $price = number_format($amountCrypto*$coinInfoZEN->dollar_val,8,'.','');
                    }
                    break;
                 **/
                case 'usd':
                    $cWh = new Where();
                    $cWh->equalTo('user_idfs', $me->User_ID);
                    $cWh->like('coin', $coin);
                    $cWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-24 hours')));
                    $check = $this->mTokenBuyTbl->select($cWh);
                    if($check->count() > 0) {
                        return new ApiProblemResponse(new ApiProblem(400, 'You can buy tokens with crypto only once every 24 hours'));
                    }
                    $merchKey = $this->mSecTools->getCoreSetting('cu-merchant-key');
                    $secKey = $this->mSecTools->getCoreSetting('cu-secret-key');

                    $response = ClientStatic::post(
                        'https://cryptounifier.io/api/v1/merchant/create-invoice', [
                        'cryptocurrencies' => json_encode(["bch", "ltc", "doge", "zen"]),
                        'currency' => 'usd',
                        'target_value' => $amount * 0.1,
                        'title' => 'Buy Swissfaucet Token',
                        'description' => 'Purchase of '.$amount.' Swissfaucet Token'
                    ], [
                        'X-Merchant-Key' => $merchKey,
                        'X-Secret-Key' => $secKey
                    ]);
                    $status = $response->getStatusCode();
                    if($status == 200) {
                        $responseBody = $response->getBody();

                        $responseJson = json_decode($responseBody);
                        if(isset($responseJson->message->hash)) {
                            $hash = $responseJson->message->hash;
                            $walletReceive = $hash;
                        } else {
                            return new ApiProblemResponse(new ApiProblem(400, 'Could not generate Invoice (2). Please try again later'));
                        }
                    } else {
                        return new ApiProblemResponse(new ApiProblem(400, 'Could not generate Invoice. Please try again later'));
                    }
                    break;
                case 'coins':
                    if (!$this->mTransaction->checkUserBalance(($amount * $tokenPrice), $me->User_ID)) {
                        return new ApiProblemResponse(new ApiProblem(400, 'Your balance is too low to buy ' . $amount . ' tokens'));
                    }
                    $newBalance = $this->mTransaction->executeTransaction(($amount * $tokenPrice), true, $me->User_ID, $amount, 'token-buy', 'Bought '.$amount.' Tokens with COINS');
                    /**
                     * Send Coins to Admins - do not Burn
                     */
                    if($newBalance !== false) {
                        $me->token_balance = $newBalance;
                        /**
                         * just burn that shit
                         *
                        $adminUserIds = explode(',',$this->mSecTools->getCoreSetting('admin-user-ids'));
                        foreach($adminUserIds as $adminid) {
                            $newBalanceAdmin = $this->mTransaction->executeTransaction((($amount * $tokenPrice)/count($adminUserIds)), false, $adminid, $amount, 'token-buy', 'User Bought '.$amount.' Tokens with COINS');
                        }
                         * **/
                    }
                    $walletReceive = $me->User_ID;
                    break;
                default:
                    break;
            }

            $this->mTokenBuyTbl->insert([
                'user_idfs' => $me->User_ID,
                'date' => date('Y-m-d H:i:s', time()),
                'coin' => $coin,
                'wallet' => $wallet,
                'wallet_receive' => $walletReceive,
                'amount' => $amount,
                'price' => $price,
                'received' => ($coin == 'COINS') ? 1 : 0,
                'sent' => 0,
            ]);

            return [
                'coin' => $coin,
                'wallet' => $walletReceive,
                'token_balance' => $me->token_balance,
                'price' => $price,
            ];
        }

        if($request->isPut()) {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['amount','coin','wallet'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$json->amount,$json->coin,$json->wallet]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Buy Token',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
            }

            # Check if user is verified
            if($me->email_verified == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before buying tokens'));
            }

            $wallet = filter_var($json->wallet, FILTER_SANITIZE_STRING);
            if(strtolower(substr($wallet,0,1)) != 'r') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Ravencoin address'));
            }

            $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
            if($amount <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Amount'));
            }

            $coin = filter_var($json->coin, FILTER_SANITIZE_STRING);
            if($coin != 'COINS' && $coin != 'BCH' && $coin != 'LTC') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Coin'));
            }

            $todayWh = new Where();
            $todayWh->like('date', date('Y-m-d', time()).'%');
            $todayWh->equalTo('user_idfs', $me->User_ID);
            $tokenBuyToday = $this->mTokenBuyTbl->select($todayWh);
            $tokenBuyedToday = 0;
            if(count($tokenBuyToday) > 0) {
                foreach($tokenBuyToday as $tkbuy) {
                    $tokenBuyedToday+=$tkbuy->amount;
                }
            }
            $tokenLeft = 1000-$tokenBuyedToday;
            if($tokenLeft-$amount < 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'You have already reached the daily limit of 100 token. You can buy more tomorrow.'));
            }

            $newBalance = false;
            $tokenPrice = 2500;
            if($coin == 'COINS') {
                if (!$this->mTransaction->checkUserBalance(($amount * $tokenPrice), $me->User_ID)) {
                    return new ApiProblemResponse(new ApiProblem(400, 'Your balance is too low to buy ' . $amount . ' tokens'));
                }
                $newBalance = $this->mTransaction->executeTransaction(($amount * $tokenPrice), true, $me->User_ID, $amount, 'token-buy', 'Bought '.$amount.' Tokens with COINS');
                /**
                 * Send Coins to Admins - do not Burn
                 */
                if($newBalance !== false) {
                    $adminUserIds = explode(',',$this->mSecTools->getCoreSetting('admin-user-ids'));
                    foreach($adminUserIds as $adminid) {
                        $newBalanceAdmin = $this->mTransaction->executeTransaction((($amount * $tokenPrice)/count($adminUserIds)), false, $adminid, $amount, 'token-buy', 'User Bought '.$amount.' Tokens with COINS');
                    }
                }
            } else {
                $newBalance = $me->token_balance;
            }

            $totalTokenDB = $this->mTokenBuyTbl->select(['user_idfs' => $me->User_ID, 'sent' => 1]);
            $totalToken = 0;
            if(count($totalTokenDB) > 0) {
                foreach($totalTokenDB as $tok) {
                    $totalToken+=$tok->amount;
                }
            }

            $pendingTokenDB = $this->mTokenBuyTbl->select(['user_idfs' => $me->User_ID, 'sent' => 0]);
            $pendingToken = 0;
            if(count($pendingTokenDB) > 0) {
                foreach($pendingTokenDB as $tok) {
                    $pendingToken+=$tok->amount;
                }
            }

            $tokenValue = $this->mTransaction->getTokenValue();
            $pricePaid = 0;

            switch($coin) {
                case 'COINS':
                    $pricePaid = $amount*$tokenPrice;
                    break;
                case 'BCH':
                    $coinInfoBCH = $this->mWalletTbl->select(['coin_sign' => 'BCH'])->current();
                    $amountCrypto = ($amount*$tokenPrice)*$tokenValue;
                    if($coinInfoBCH->dollar_val > 0) {
                        $pricePaid = number_format($amountCrypto/$coinInfoBCH->dollar_val,8,'.','');
                    } else {
                        $pricePaid = number_format($amountCrypto*$coinInfoBCH->dollar_val,8,'.','');
                    }
                    break;
                case 'LTC':
                    $coinInfoLTC = $this->mWalletTbl->select(['coin_sign' => 'LTC'])->current();
                    $amountCrypto = ($amount*$tokenPrice)*$tokenValue;
                    if($coinInfoLTC->dollar_val > 0) {
                        $pricePaid = number_format($amountCrypto/$coinInfoLTC->dollar_val,8,'.','');
                    } else {
                        $pricePaid = number_format($amountCrypto*$coinInfoLTC->dollar_val,8,'.','');
                    }
                    break;
                default:
                    break;
            }

            if($newBalance !== false) {
                $this->mTokenBuyTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'date' => date('Y-m-d H:i:s', time()),
                    'coin' => $coin,
                    'wallet' => $wallet,
                    'amount' => $amount,
                    'price' => $pricePaid,
                    'received' => ($coin == 'COINS') ? 1 : 0,
                    'sent' => 0,
                ]);
                return [
                    '_links' => [],
                    '_embedded' => [
                        'user' => [
                            'token_balance' => $newBalance,
                        ],
                        'token' => [
                            'total' => (int)$totalToken,
                            'pending' => (int)$pendingToken
                        ]
                    ]
                ];
            } else {
                return new ApiProblemResponse(new ApiProblem(500, 'Transaction Error. Please contact support'));
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
