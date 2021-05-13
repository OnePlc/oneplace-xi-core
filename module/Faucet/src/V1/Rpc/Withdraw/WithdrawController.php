<?php
namespace Faucet\V1\Rpc\Withdraw;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
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
    }

    public function withdrawAction()
    {
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSession->auth;

        $wallets = [];
        $walletsDB = $this->mWalletTbl->select();
        foreach($walletsDB as $wall) {
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
                'last_update' => $wall->last_update
            ];
        }

        $withdrawLimit = 1000*(1+(($me->xp_level-1)/6));

        $coinsWithdrawnToday = 0;
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->notLike('state', 'cancel');
        $oWh->like('date_requested', date('Y-m-d', time()).'%');
        $oWithdrawsToday = $this->mWithdrawTbl->select($oWh);
        if(count($oWithdrawsToday) > 0) {
            foreach($oWithdrawsToday as $oWth) {
                $coinsWithdrawnToday+=$oWth->amount;
            }
        }

        return [
            '_links' => [],
            'wallet' => $wallets,
            'daily_limit' => $withdrawLimit,
            'token_val' => 0.0004,
            'daily_left' => ($withdrawLimit-$coinsWithdrawnToday)
        ];
    }
}
