<?php
/**
 * WithdrawController.php - Withdraw Controller
 *
 * Main Controller for Withdraw
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rpc\Withdraw;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Laminas\Session\Container;

class WithdrawController extends AbstractActionController
{
    /**
     * User Withdrawal Table
     *
     * @var TableGateway $mWithdrawTbl
     * @since 1.0.0
     */
    protected $mWithdrawTbl;

    /**
     * Faucet Wallets Table
     *
     * @var TableGateway $mWalletTbl
     * @since 1.0.0
     */
    protected $mWalletTbl;

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

    protected $mLinkAccTbl;

    protected $mGachaWalletTbl;

    protected $mGachaDepositTbl;

    protected $mGachaUserTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper, $gachaMapper)
    {
        $this->mWithdrawTbl = new TableGateway('faucet_withdraw', $mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mLinkAccTbl = new TableGateway('user_linked_account', $mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);

        $this->mGachaWalletTbl = new TableGateway('user_wallet', $gachaMapper);
        $this->mGachaDepositTbl = new TableGateway('user_wallet_deposit', $gachaMapper);
        $this->mGachaUserTbl = new TableGateway('user', $gachaMapper);
    }

    public function withdrawAction()
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

        $tokenValue = $this->mTransaction->getTokenValue();

        $coinsWithdrawnToday = 0;
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->notLike('state', 'cancel');
        $oWh->notLike('currency', 'GAT');
        $oWh->like('date_requested', date('Y-m-d', time()) . '%');
        $oWithdrawsToday = $this->mWithdrawTbl->select($oWh);
        if (count($oWithdrawsToday) > 0) {
            foreach ($oWithdrawsToday as $oWth) {
                $coinsWithdrawnToday += $oWth->amount;
            }
        }

        if($request->isGet()) {
            $wallets = [];
            $walletsDB = $this->mWalletTbl->select(['status' => 'open']);
            foreach ($walletsDB as $wall) {
                $oWh = new Where();
                $oWh->equalTo('user_idfs', $me->User_ID);
                $oWh->like('currency', $wall->coin_sign);
                $oWh->like('state', 'done');
                $lastSel = new Select($this->mWithdrawTbl->getTable());
                $lastSel->where($oWh);
                $lastSel->order('date_sent DESC');
                $lastSel->limit(1);
                $lastWth = $this->mWithdrawTbl->selectWith($lastSel);
                $lastAddress = '';
                if (count($lastWth) > 0) {
                    $lastAddress = $lastWth->current()->wallet;
                }

                $balanceEst = $me->token_balance*$tokenValue;
                if($wall->dollar_val > 0) {
                    $balanceEst = $balanceEst/$wall->dollar_val;
                } else {
                    $balanceEst = $balanceEst*$wall->dollar_val;
                }

                $wallets[] = (object)[
                    'id' => $wall->Wallet_ID,
                    'name' => $wall->coin_label,
                    'sign' => $wall->coin_sign,
                    'url' => $wall->url,
                    'fee' => $wall->fee,
                    'withdraw_min' => $wall->withdraw_min,
                    'dollar_val' => $wall->dollar_val,
                    'change_24h' => $wall->change_24h,
                    'status' => $wall->status,
                    'bgcolor' => $wall->bgcolor,
                    'textcolor' => $wall->textcolor,
                    'blockexplorer_url' => $wall->blockexplorer_url,
                    'last_update' => $wall->last_update,
                    'user_recent_wallet' => $lastAddress,
                    'balance_est' => number_format($balanceEst,8,'.',''),
                ];
            }

            $withdrawLimit = 1000 + (200 * ($me->xp_level - 1));

            $withdrawBonus = 0;
            # check for active withdrawal buffs
            $activeBuffs = $this->mUserTools->getUserActiveBuffs('daily-withdraw-buff', date('Y-m-d', time()), $me->User_ID);
            if(count($activeBuffs) > 0) {
                foreach($activeBuffs as $buff) {
                    $withdrawBonus+=$buff->buff;
                }
            }

            $gachaLink = false;
            $links = $this->mLinkAccTbl->select(['user_idfs' => $me->User_ID]);
            if($links->count() > 0) {
                foreach ($links as $link) {
                    if($link->account == 'gachaminer') {
                        $gachaLink = true;
                    }
                }
            }
            if($gachaLink) {
                $balanceEst = $me->token_balance*$tokenValue;
                $balanceEst = $balanceEst/1;

                $wallets[] = (object)[
                    'id' => 99,
                    'name' => 'Gachatoken',
                    'sign' => 'GAT',
                    'url' => '',
                    'fee' => 0,
                    'withdraw_min' => 25000,
                    'dollar_val' => 1,
                    'change_24h' => 0,
                    'status' => 'open',
                    'bgcolor' => '#ffcc00',
                    'textcolor' => '#000',
                    'blockexplorer_url' => '',
                    'last_update' => date('Y-m-d H:i:s', time()),
                    'user_recent_wallet' => '',
                    'balance_est' => number_format($balanceEst,8,'.',''),
                ];
            }

            if($me->User_ID == 335875071) {
                //$withdrawLimit = 1000000;
            }

            $nextPay = $this->mSecTools->getCoreSetting('payment-next');

            $viewData = [
                '_links' => [],
                'wallet' => $wallets,
                'daily_limit_base' => $withdrawLimit,
                'daily_limit_bonus' => $withdrawBonus,
                'daily_limit' => $withdrawLimit+$withdrawBonus,
                'token_val' => $tokenValue,
                'daily_left' => (($withdrawLimit+$withdrawBonus) - $coinsWithdrawnToday),
                'next_payment' => $nextPay
            ];

            $hasMessage = $this->mSecTools->getCoreSetting('faucet-withdraw-msg-content');
            if($hasMessage) {
                $message = $hasMessage;
                $messageType = $this->mSecTools->getCoreSetting('faucet-withdraw-msg-level');
                $xpReq = $this->mSecTools->getCoreSetting('faucet-withdraw-msg-xplevel');
                $addMsg = false;
                if($xpReq) {
                    if($me->xp_level >= $xpReq) {
                        $addMsg = true;
                    }
                } else {
                    $addMsg = true;
                }

                if($addMsg && strlen($message) > 0) {
                    $viewData['message'] = [
                        'type' => $messageType,
                        'message' => $message
                    ];
                }
            }

            return $viewData;
        }

        if($request->isPost()) {
            $myWithdrawals = [];
            $pageSize = 25;
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;


            $stakingHistory = [];
            $historyWh = new Where();
            $historyWh->equalTo('user_idfs', $me->User_ID);
            $historyWh->notLike('currency', 'GAT');
            $historySel = new Select($this->mWithdrawTbl->getTable());
            $historySel->where($historyWh);
            $historySel->order('date_requested DESC');
            # Create a new pagination adapter object
            $oPaginatorAdapterStak = new DbSelect(
            # our configured select object
                $historySel,
                # the adapter to run it against
                $this->mWithdrawTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $stakPaginated = new Paginator($oPaginatorAdapterStak);
            $stakPaginated->setCurrentPageNumber($page);
            $stakPaginated->setItemCountPerPage($pageSize);
            foreach($stakPaginated as $wth) {
                $myWithdrawals[] = (object)[
                    'id' => $wth->Withdraw_ID,
                    'tx_id' => $wth->transaction_id,
                    'wallet' => $wth->wallet,
                    'date_requested' => $wth->date_requested,
                    'date_sent' => $wth->date_sent,
                    'amount_coin' => $wth->amount,
                    'amount' => $wth->amount_paid,
                    'currency' => $wth->currency,
                    'status' => $wth->state,
                ];
            }

            $allWth = $this->mWithdrawTbl->selectWith($historySel);
            $total = 0;
            $totalItems = 0;
            foreach($allWth as $tth) {
                if($tth->state != 'cancel') {
                    $total+=$tth->amount;
                }
                $totalItems++;
            }
            $total = round($total, 2);

            $nextPay = $this->mSecTools->getCoreSetting('payment-next');

            $viewData = [
                'history' => [
                    'items' => $myWithdrawals,
                    'total_items' => $totalItems,
                    'total_withdrawn' => $total,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'page_count' => (round($totalItems/$pageSize) > 0) ? round($totalItems/$pageSize) : 1,
                ],
                'next_payment' => $nextPay
            ];

            $hasMessage = $this->mSecTools->getCoreSetting('faucet-withdraw-msg-content');
            if($hasMessage) {
                $message = $hasMessage;
                $messageType = $this->mSecTools->getCoreSetting('faucet-withdraw-msg-level');
                $xpReq = $this->mSecTools->getCoreSetting('faucet-withdraw-msg-xplevel');
                $addMsg = false;
                if($xpReq) {
                    if($me->xp_level >= $xpReq) {
                        $addMsg = true;
                    }
                } else {
                    $addMsg = true;
                }

                if($addMsg && strlen($message) > 0) {
                    $viewData['message'] = [
                        'type' => $messageType,
                        'message' => $message
                    ];
                }
            }

            return $viewData;
        }

        if($request->isPut()) {
            /**
            if($me->User_ID != 335874987) {
                return new ApiProblemResponse(new ApiProblem(400, 'Withdrawals are currently disabled, we are doing an update. Please try again in '.date('H', strtotime('2022-04-19 15:00')-time()).' hours'));
            } **/

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
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Withdraw Request',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
            }

            # Check if user is verified
            if($me->email_verified == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before submitting Withdrawal Request.'));
            }

            /**
             * Get Coin Info
             */
            $coin = filter_var($json->coin, FILTER_SANITIZE_STRING);
            if(strtoupper($coin) == 'GAT') {
                $gachaLink = false;
                $gachaMail = "";
                $links = $this->mLinkAccTbl->select(['user_idfs' => $me->User_ID]);
                if($links->count() > 0) {
                    foreach ($links as $link) {
                        if($link->account == 'gachaminer') {
                            $gachaLink = true;
                            $gachaMail = $link->email;
                        }
                    }
                }
                if(!$gachaLink) {
                    return new ApiProblemResponse(new ApiProblem(404, 'You must link your Gachaminer Account to Withdraw in GAT'));
                }
                $gachaUser = $this->mGachaUserTbl->select(['email' => $gachaMail]);
                if($gachaUser->count() == 0) {
                    return new ApiProblemResponse(new ApiProblem(404, 'Could not find your Gachaminer Account'));
                }
                $gachaUser = $gachaUser->current();

                $gatWallet = $this->mGachaWalletTbl->select(['currency' => 'gat', 'user_idfs' => $gachaUser->User_ID]);

                /**
                 * Double check amount
                 */
                $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
                if($amount < 0 || empty($amount)) {
                    return new ApiProblemResponse(new ApiProblem(409, 'Invalid amount'));
                }

                /**
                 * Check if amount is below minimum withdrawal for coin
                 */
                if($amount < 25000) {
                    return new ApiProblemResponse(new ApiProblem(409, 'Amount is lower than GAT withdrawal minimum'));
                }

                if($amount > $me->token_balance) {
                    return new ApiProblemResponse(new ApiProblem(409, 'You cannot withdraw more than your balance'));
                }

                $tokenValue = $this->mTransaction->getTokenValue();

                /**
                 * Calculate Crypto Amount to Send
                 */
                $amountCrypto = $amount*$tokenValue;
                $amountFee = 0;
                $amountFee = $amountFee/1;
                $amountCrypto = number_format($amountCrypto/1,8,'.','');
                $amountCryptoToSend = number_format($amountCrypto-$amountFee,8,'.','');
                $timeNow = date('Y-m-d H:i:s', time());

                # double check if user has enough balance for withdrawal
                if($this->mTransaction->checkUserBalance($amount,$me->User_ID)) {
                    # insert to withdrawal history
                    if($this->mWithdrawTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'wallet' => 'internal',
                        'amount' => $amount,
                        'amount_coin' => $amountCrypto,
                        'amount_paid' => $amountCryptoToSend,
                        'dollarval_paid' => 1,
                        'date_requested' => $timeNow,
                        'date_sent' => $timeNow,
                        'currency' => 'GAT',
                        'state' => 'done',
                        'transaction_id' => '',
                        'hash' => password_hash($me->User_ID.$timeNow.$amount.$amountCrypto.$amountCrypto.'GAT', PASSWORD_DEFAULT)
                    ])) {
                        $this->mGachaDepositTbl->insert([
                            'user_idfs' => $gachaUser->User_ID,
                            'amount' => $amountCryptoToSend,
                            'date' => date('Y-m-d H:i:s', time()),
                            'currency' => 'gat',
                            'address' => 'internal',
                            'tx_id' => 'withdrawn from swissfaucet'
                        ]);

                        if($gatWallet->count() == 0) {
                            $this->mGachaWalletTbl->insert([
                                'user_idfs' => $gachaUser->User_ID,
                                'character_idfs' => 1,
                                'currency' => 'gat',
                                'address' => 'internal',
                                'balance' => $amountCryptoToSend
                            ]);
                        } else {
                            $this->mGachaWalletTbl->update([
                                'balance' => $gatWallet->current()->balance + $amountCryptoToSend,
                            ],['user_idfs' => $gachaUser->User_ID, 'currency' => 'gat']);
                        }

                        # execute transaction
                        $withdrawalID = $this->mWithdrawTbl->lastInsertValue;
                        $newBalance = $this->mTransaction->executeTransaction($amount, true, $me->User_ID, $withdrawalID, 'withdrawal', 'Requested Withdraw of '.$amount.' Coins in GAT');
                        if($newBalance !== false) {
                            $withdrawals = ['done' => [],'cancel' => [],'new' => [], 'total_items' => 0];
                            $userWithdrawals = $this->mWithdrawTbl->select(['user_idfs' => $me->User_ID]);
                            if(count($userWithdrawals) > 0) {
                                foreach($userWithdrawals as $wth) {
                                    $withdrawals[$wth->state][] = $wth;
                                }
                            }

                            # push new info to view
                            return new ViewModel([
                                'daily_left' => 0,
                                'token_balance' => $newBalance,
                                'withdrawals' => $withdrawals,
                            ]);
                        } else {
                            return new ApiProblemResponse(new ApiProblem(500, 'Transaction error'));
                        }
                    } else {
                        return new ApiProblemResponse(new ApiProblem(500, 'Could not confirm withdrawal request'));
                    }
                } else {
                    return new ApiProblemResponse(new ApiProblem(409, 'insufficient funds for transaction'));
                }
            } else {
                $coinInfo = $this->mWalletTbl->select(['coin_sign' => $coin]);
                if(count($coinInfo) == 0) {
                    return new ApiProblemResponse(new ApiProblem(404, 'Currency not found'));
                }
                $coinInfo = $coinInfo->current();

                /**
                 * Check if Wallet is a valid address
                 */
                $wallet = filter_var($json->wallet, FILTER_SANITIZE_STRING);
                switch($coinInfo->coin_sign) {
                    case 'BCH':
                        $addrCheck = str_replace(['bitcoincash:'],[''],$wallet);
                        if(strlen($addrCheck) < 42) {
                            if(substr($addrCheck,0,1) == 1) {
                                if(strlen($addrCheck) < 34) {
                                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid Bitcoin Cash Address. Make sure you have no typing errors and choose the correct currency'));
                                }
                            } else {
                                if(substr($addrCheck,0,1) == 3) {
                                    return new ApiProblemResponse(new ApiProblem(400, 'We do not support Segwit Addresses anymore. Please convert it on cashaddr.org'));
                                } else {
                                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid Bitcoin Cash Address. Make sure you have no typing errors and choose the correct currency'));
                                }
                            }
                        }
                        $firstLetter = strtolower(substr($addrCheck,0,1));
                        if($firstLetter != 'p' && $firstLetter != 'q') {
                            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Bitcoin Cash Address. Make sure you have no typing errors and choose the correct currency'));
                        }
                        break;
                    case 'LTC':
                        $addrCheck = str_replace(['litecoin:'],[''],$wallet);
                        if(strlen($addrCheck) < 34) {
                            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Litecoin Address. Make sure you have no typing errors and choose the correct currency'));
                        }
                        $firstLetter = strtolower(substr($addrCheck,0,1));
                        if($firstLetter != 'm' && $firstLetter != 'l') {
                            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Litecoin Address. Make sure you have no typing errors and choose the correct currency'));
                        }
                        break;
                    case 'BNB':
                        # Get Data from Request Body
                        $json = IndexController::loadJSONFromRequestBody(['amount','coin','wallet','memo','chain'],$this->getRequest()->getContent());
                        if(!$json) {
                            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
                        }
                        $memo = filter_var($json->memo, FILTER_SANITIZE_STRING);
                        $chain = filter_var($json->chain, FILTER_SANITIZE_STRING);
                        $addrCheck = str_replace([''],[''],$wallet);

                        if(strlen($addrCheck) < 34) {
                            return new ApiProblemResponse(new ApiProblem(400, 'Invalid BNB Address. Make sure you have no typing errors and choose the correct currency'));
                        }

                        if(strtolower($chain) == 'bsc') {
                            if(substr($addrCheck,0,1) != '0') {
                                return new ApiProblemResponse(new ApiProblem(400, 'Invalid BSC Address. Make sure you select the correct Network.'));
                            }
                        } else {
                            if(strtolower(substr($addrCheck,0,1)) != 'b') {
                                return new ApiProblemResponse(new ApiProblem(400, 'Invalid BNB Address. Make sure you select the correct Network.'));
                            }
                        }

                        if($memo != '') {
                            $wallet = $wallet.'-'.$memo;
                        }
                        break;
                    default:
                        break;
                }

                $tokenValue = $this->mTransaction->getTokenValue();

                /**
                 * Double check amount
                 */
                $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
                $withdrawLimit = 1000 + (200 * ($me->xp_level - 1));

                /**
                 * Add Buffs to Limit
                 */
                $withdrawBonus = 0;
                # check for active withdrawal buffs
                $activeBuffs = $this->mUserTools->getUserActiveBuffs('daily-withdraw-buff', date('Y-m-d', time()), $me->User_ID);
                if(count($activeBuffs) > 0) {
                    foreach($activeBuffs as $buff) {
                        $withdrawBonus+=$buff->buff;
                    }
                }

                /**
                 * Check Limits
                 */
                if($me->User_ID == 335875071) {
                    //$withdrawLimit = 1000000;
                }
                $withdrawLimit+=$withdrawBonus;
                if($amount > $withdrawLimit) {
                    return new ApiProblemResponse(new ApiProblem(409, 'Amount is bigger than daily withdrawal limit'));
                }
                if($amount < 0 || empty($amount)) {
                    return new ApiProblemResponse(new ApiProblem(409, 'Invalid amount'));
                }

                /**
                 * Check if amount is below minimum withdrawal for coin
                 */
                if($amount < $coinInfo->withdraw_min) {
                    return new ApiProblemResponse(new ApiProblem(409, 'Amount is lower than '.$coinInfo->coin_label.' withdrawal minimum'));
                }

                /**
                 * Check if there is no double withdrawal
                 */
                if($amount > ($withdrawLimit-$coinsWithdrawnToday)) {
                    return new ApiProblemResponse(new ApiProblem(409, 'You have already withdrawn your daily limit.'));
                }

                /**
                 * Calculate Crypto Amount to Send
                 */
                $amountCrypto = $amount*$tokenValue;
                $amountFee = $coinInfo->fee*$tokenValue;
                if($coinInfo->dollar_val > 0) {
                    $amountFee = $amountFee/$coinInfo->dollar_val;
                    $amountCrypto = number_format($amountCrypto/$coinInfo->dollar_val,8,'.','');
                } else {
                    $amountFee = $amountFee*$coinInfo->dollar_val;
                    $amountCrypto = number_format($amountCrypto*$coinInfo->dollar_val,8,'.','');
                }
                $amountCryptoToSend = number_format($amountCrypto-$amountFee,8,'.','');
                $timeNow = date('Y-m-d H:i:s', time());

                # double check if user has enough balance for withdrawal
                if($this->mTransaction->checkUserBalance($amount,$me->User_ID)) {
                    # insert to withdrawal history
                    if($this->mWithdrawTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'wallet' => $wallet,
                        'amount' => $amount,
                        'amount_coin' => $amountCrypto,
                        'amount_paid' => $amountCryptoToSend,
                        'dollarval_paid' => $coinInfo->dollar_val,
                        'date_requested' => $timeNow,
                        'date_sent' => '0000-00-00 00:00:00',
                        'currency' => $coinInfo->coin_sign,
                        'transaction_id' => '',
                        'hash' => password_hash($wallet.$me->User_ID.$timeNow.$amount.$amountCrypto.$amountCrypto.$coinInfo->dollar_val, PASSWORD_DEFAULT)
                    ])) {
                        # execute transaction
                        $withdrawalID = $this->mWithdrawTbl->lastInsertValue;
                        $newBalance = $this->mTransaction->executeTransaction($amount, true, $me->User_ID, $withdrawalID, 'withdrawal', 'Requested Withdraw of '.$amount.' Coins in '.$coinInfo->coin_sign);
                        if($newBalance !== false) {
                            $withdrawals = ['done' => [],'cancel' => [],'new' => [], 'total_items' => 0];
                            $userWithdrawals = $this->mWithdrawTbl->select(['user_idfs' => $me->User_ID]);
                            if(count($userWithdrawals) > 0) {
                                foreach($userWithdrawals as $wth) {
                                    $withdrawals[$wth->state][] = $wth;
                                }
                            }

                            # push new info to view
                            return new ViewModel([
                                'daily_left' => ($withdrawLimit - $json->amount - $coinsWithdrawnToday),
                                'token_balance' => $newBalance,
                                'withdrawals' => $withdrawals,
                            ]);
                        } else {
                            return new ApiProblemResponse(new ApiProblem(500, 'Transaction error'));
                        }
                    } else {
                        return new ApiProblemResponse(new ApiProblem(500, 'Could not confirm withdrawal request'));
                    }
                } else {
                    return new ApiProblemResponse(new ApiProblem(409, 'insufficient funds for transaction'));
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
