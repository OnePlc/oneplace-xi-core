<?php
namespace Backend\V1\Rest\TokenBuy;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class TokenBuyResource extends AbstractResourceListener
{
    /**
     * Withdraw Table
     *
     * @var TableGateway $mTokenBuyTbl
     * @since 1.0.0
     */
    protected $mTokenBuyTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * @var TableGateway
     */
    private $mWalletTbl;

    /**
     * @var TransactionHelper
     */
    private $mTransHelper;


    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mTokenBuyTbl = new TableGateway('faucet_tokenbuy', $mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransHelper = new TransactionHelper($mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $currency = strtoupper(filter_var($data->currency, FILTER_SANITIZE_STRING));
        if($currency !== 'COINS' && $currency !== 'USD') {
            return new ApiProblem(400, 'Invalid Currency');
        }

        $wthSel = new Select($this->mTokenBuyTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_tokenbuy.user_idfs', ['username']);
        $wthSel->where(['sent' => 0, 'cancelled' => 0, 'coin' => $currency]);
        $wthSel->order('Buy_ID ASC');
        $openWithdraws = $this->mTokenBuyTbl->selectWith($wthSel);
        $withdrawalsByWallet = [];
        $withdrawals = [];
        $duplicates = 0;
        $lastBuyId = 0;
        $totalCount = $openWithdraws->count();

        foreach($openWithdraws as $wth) {
            $wallet = $wth->wallet;
            if($wallet == 'rvninternalstorage') {
                $wallet .= '-'.$wth->Buy_ID;
            }
            $lastBuyId = $wth->Buy_ID;

            if(!in_array($wallet, $withdrawalsByWallet)) {

                $withdrawalsByWallet[] = $wallet;

                $withdrawals[$wallet] = [
                    'id' => $wth->Buy_ID,
                    'amount_token' => $wth->amount,
                    'tx_count' => 1,
                    'currency' => strtolower($wth->coin),
                    'wallet' => $wallet,
                    'payment_wallet' => $wth->wallet_receive,
                    'user' => [
                        'id' => $wth->user_idfs,
                        'name' => $wth->username
                    ],
                    'date' => date('Y-m-d H:i', strtotime($wth->date))
                ];
            } else {
                $withdrawals[$wallet]['amount_token'] += $wth->amount;
                $withdrawals[$wallet]['tx_count']++;
                //$duplicates++;
            }
        }

        $wthExport = [];
        foreach($withdrawals as $wthWall => $wthTx) {
            $wthExport[] = $wthTx;
        }

        return [
            'list' => $wthExport,
            'duplicates' => $duplicates,
            'last_buy_id' => $lastBuyId,
            'total_count' => $totalCount
        ];
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function delete($id)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for individual resources');
    }

    /**
     * Delete a collection, or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function deleteList($data)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for collections');
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function fetch($id)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $buyId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        if(empty($buyId) || $buyId <= 0) {
            return new ApiProblem(400, 'Invalid ID');
        }
        $wthSel = new Select($this->mTokenBuyTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_tokenbuy.user_idfs', ['username']);
        $wthSel->where(['Buy_ID' => $buyId]);
        $tokenBuy = $this->mTokenBuyTbl->selectWith($wthSel);
        if($tokenBuy->count() == 0) {
            return new ApiProblem(404, 'Token Buy Request not found');
        }
        $wth = $tokenBuy->current();
        if($tokenBuy->sent == 1 || $tokenBuy->cancelled == 1) {
            return new ApiProblem(404, 'Token Buy Request already processed');
        }

        $info = [
            'id' => $wth->Buy_ID,
            'amount_token' => $wth->amount,
            'currency' => strtolower($wth->coin),
            'wallet' => $wth->wallet,
            'payment_wallet' => $wth->wallet_receive,
            'user' => [
                'id' => $wth->user_idfs,
                'name' => $wth->username
            ],
            'date' => date('Y-m-d H:i', strtotime($wth->date))
        ];

        return [
            'info' => $info
        ];
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = [])
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $wthSel = new Select($this->mTokenBuyTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_tokenbuy.user_idfs', ['username']);
        $wthSel->where(['sent' => 0, 'cancelled' => 0]);
        $openWithdraws = $this->mTokenBuyTbl->selectWith($wthSel);
        $withdrawals = [];
        $totalOpenCoinsByCrypto = [];
        $totalOpenDollars = 0;
        $totalOpenTokens = 0;

        foreach($openWithdraws as $wth) {
            $totalOpenDollars+=($wth->amount*2500);
            $totalOpenTokens+=$wth->amount;
            $cryptoKey = 'coin-'.strtolower($wth->coin);
            if(!array_key_exists($cryptoKey, $totalOpenCoinsByCrypto)) {
                $totalOpenCoinsByCrypto[$cryptoKey] = 0;
            }
            $totalOpenCoinsByCrypto[$cryptoKey]+=($wth->amount*0.1);
            $withdrawals[] = [
                'id' => $wth->Buy_ID,
                'amount_token' => $wth->amount,
                'currency' => strtolower($wth->coin),
                'wallet' => $wth->wallet,
                'payment_wallet' => $wth->wallet_receive,
                'user' => [
                    'id' => $wth->user_idfs,
                    'name' => $wth->username
                ],
                'date' => date('Y-m-d H:i', strtotime($wth->date))
            ];
        }

        $cryptoInfo = $this->mWalletTbl->select();
        $colorsByCrypto = [];
        foreach($cryptoInfo as $c) {
            $colorsByCrypto['coin-'.strtolower($c->coin_sign)] = $c->chartcolor;
        }

        $openCryptos = [];
        foreach($totalOpenCoinsByCrypto as $cKey => $cVal) {
            $openCryptos[] = [
                'name' => substr($cKey, strlen('coin-')). ' - '.$cVal,
                'amount' => $cVal,
                'color' => $colorsByCrypto[$cKey]
            ];
        }

        return (object)[
            'total_usd' => round($totalOpenDollars*0.00004, 0),
            'total_crypto' => $openCryptos,
            'total_token' => $totalOpenTokens,
            'withdrawals' => $withdrawals
        ];
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patch($id, $data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for individual resources');
    }

    /**
     * Patch (partial in-place update) a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patchList($data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for collections');
    }

    /**
     * Replace a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function replaceList($data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $currency = strtoupper(filter_var($data[0]->currency, FILTER_SANITIZE_STRING));
        if($currency !== 'COINS' && $currency !== 'USD') {
            return new ApiProblem(400, 'Invalid Currency');
        }

        $lastId = filter_var($data[0]->last_id, FILTER_SANITIZE_NUMBER_INT);
        if(empty($lastId) || $lastId <= 0) {
            return new ApiProblem(400, 'Invalid Last ID');
        }

        $checkCount = filter_var($data[0]->check_count, FILTER_SANITIZE_NUMBER_INT);
        if(empty($checkCount) || $checkCount <= 0) {
            return new ApiProblem(400, 'Invalid Check Count');
        }

        # double check we got the same transactions
        $wthWh = new Where();
        $wthWh->lessThanOrEqualTo('Buy_ID', $lastId);
        $wthWh->equalTo('sent', 0);
        $wthWh->equalTo('cancelled', 0);
        $wthWh->equalTo('coin', $currency);

        $wthSel = new Select($this->mTokenBuyTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_tokenbuy.user_idfs', ['username']);
        $wthSel->where($wthWh);
        $checkListCount = $this->mTokenBuyTbl->selectWith($wthSel)->count();

        if((int)$checkCount !== $checkListCount) {
            return new ApiProblem(400, 'Invalid Check Count (c:'.$checkCount.'/s:'.$checkListCount.')');
        }

        $txId = filter_var($data[0]->tx_id, FILTER_SANITIZE_STRING);
        if(strlen($txId) < 10) {
            return new ApiProblem(400, 'Invalid TX ID');
        }

        $this->mTokenBuyTbl->update([
            'sent' => 1,
            'date_sent' => date('Y-m-d H:i:s', time()),
            'transaction_id' => $txId
        ],$wthWh);

        return true;
    }

    /**
     * Update a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function update($id, $data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $wthId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        if($wthId <= 0 || empty($wthId)) {
            return new ApiProblem(400, 'Invalid Token Buy ID');
        }
        $wthSel = new Select($this->mTokenBuyTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_tokenbuy.user_idfs', ['username']);
        $wthSel->where(['Buy_ID' => $wthId]);
        $wth = $this->mTokenBuyTbl->selectWith($wthSel);
        if($wth->count() == 0) {
            return new ApiProblem(404, 'Token Buy Request not found');
        }
        $wth = $wth->current();
        if($wth->sent == 1 || $wth->cancelled == 1) {
            return new ApiProblem(400, 'Token Buy Request is already processed');
        }

        $cmd = filter_var($data->cmd, FILTER_SANITIZE_STRING);
        if($cmd != 'send' && $cmd != 'cancel') {
            return new ApiProblem(400, 'Invalid Command');
        }

        if($cmd == 'send') {
            $txId = substr(filter_var($data->tx_id, FILTER_SANITIZE_STRING), 0, 255);
            if(strlen($txId) < 10) {
                return new ApiProblem(400, 'Please provide a valid transaction id');
            }

            $this->mTokenBuyTbl->update([
                'transaction_id' => $txId,
                'date_sent' => date('Y-m-d H:i:s', time()),
                'sent' => 1
            ],['Buy_ID' => $wth->Buy_ID]);
        } else {
            $comment = substr(filter_var($data->comment, FILTER_SANITIZE_STRING), 0, 255);
            if(strlen($comment) < 10) {
                return new ApiProblem(400, 'Please provide a comment when cancelling a token buy request');
            }
            if(strtolower($wth->coin) == 'coins') {
                # refund for token purchases paid with coins
                $newBalance = $this->mTransHelper->executeTransaction($wth->amount*2500, false, $wth->user_idfs, $wth->Buy_ID, 'tknrefund', 'Refund for cancelled Token Purchase');
            }

            $this->mTokenBuyTbl->update([
                'transaction_id' => $comment,
                'cancelled' => 1
            ],['Buy_ID' => $wth->Buy_ID]);

        }
        return [
            'state' => 'done'
        ];
    }
}
