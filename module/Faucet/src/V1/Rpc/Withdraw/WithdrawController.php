<?php
namespace Faucet\V1\Rpc\Withdraw;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;
use Laminas\Mvc\Controller\AbstractActionController;
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

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mWithdrawTbl = new TableGateway('faucet_withdraw', $mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
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

        if($request->isGet()) {
            $wallets = [];
            $walletsDB = $this->mWalletTbl->select();
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

            $coinsWithdrawnToday = 0;
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->notLike('state', 'cancel');
            $oWh->like('date_requested', date('Y-m-d', time()) . '%');
            $oWithdrawsToday = $this->mWithdrawTbl->select($oWh);
            if (count($oWithdrawsToday) > 0) {
                foreach ($oWithdrawsToday as $oWth) {
                    $coinsWithdrawnToday += $oWth->amount;
                }
            }

            return [
                '_links' => [],
                'wallet' => $wallets,
                'daily_limit' => $withdrawLimit,
                'token_val' => $tokenValue,
                'daily_left' => ($withdrawLimit - $coinsWithdrawnToday)
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
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Withdraw Request',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
            }

            # Check if user is verified
            if($me->email_verified == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before submitting Withdrawal Request.'));
            }

            $tokenValue = $this->mTransaction->getTokenValue();

            /**
             * Double check amount
             */
            $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
            $withdrawLimit = 1000 * (1 + (($me->xp_level - 1) / 6));
            if($amount > $withdrawLimit) {
                return new ApiProblemResponse(new ApiProblem(409, 'Amount is bigger than daily withdrawal limit'));
            }
            if($amount < 0 || empty($amount)) {
                return new ApiProblemResponse(new ApiProblem(409, 'Invalid amount'));
            }

            /**
             * Get Coin Info
             */
            $coin = filter_var($json->coin, FILTER_SANITIZE_STRING);
            $coinInfo = $this->mWalletTbl->select(['coin_sign' => $coin]);
            if(count($coinInfo) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'Currency not found'));
            }
            $coinInfo = $coinInfo->current();

            /**
             * Check if amount is below minimum withdrawal for coin
             */
            if($amount < $coinInfo->withdraw_min) {
                return new ApiProblemResponse(new ApiProblem(409, 'Amount is lower than '.$coinInfo->coin_label.' withdrawal minimum'));
            }

            /**
             * Calculate Crypto Amount to Send
             */
            $wallet = filter_var($json->wallet, FILTER_SANITIZE_STRING);
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
                            'daily_left' => ($withdrawLimit - $json->amount),
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

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
