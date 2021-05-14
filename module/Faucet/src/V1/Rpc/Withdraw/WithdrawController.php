<?php
namespace Faucet\V1\Rpc\Withdraw;

use Application\Controller\IndexController;
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
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

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
        $this->mSession = new Container('webauth');
        $this->mTransaction = new TransactionHelper($mapper);
    }

    public function withdrawAction()
    {
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSession->auth;

        $request = $this->getRequest();

        $tokenValue = 0.00004;

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
                    'test' =>  $me->token_balance.'*'.$tokenValue.'/'.$wall->dollar_val,
                ];
            }

            $withdrawLimit = 1000 * (1 + (($me->xp_level - 1) / 6));

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

            $tokenValue = 0.0004;

            /**
             * Double check amount
             */
            $withdrawLimit = 1000 * (1 + (($me->xp_level - 1) / 6));
            if($json->amount > $withdrawLimit) {
                return new ApiProblemResponse(new ApiProblem(409, 'Amount is bigger than daily withdrawal limit'));
            }
            if($json->amount < 0 || !is_numeric($json->amount) || empty($json->amount)) {
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
             * Calculate Crypto Amount to Send
             */
            $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
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
