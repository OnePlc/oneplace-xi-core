<?php
namespace Backend\V1\Rest\Withdraw;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ApiTools\ContentNegotiation\ViewModel;

class WithdrawResource extends AbstractResourceListener
{
    /**
     * Withdraw Table
     *
     * @var TableGateway $mWithdrawTbl
     * @since 1.0.0
     */
    protected $mWithdrawTbl;

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
    private $mUserSettingsTbl;
    /**
     * @var TableGateway
     */
    private $mWalletTbl;
    /**
     * @var TableGateway
     */
    private $mUserTbl;
    /**
     * @var TransactionHelper
     */
    private $mTransHelper;

    /**
     * @var TableGateway
     */
    private $mUserStatsTbl;

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
        $this->mUserSettingsTbl = new TableGateway('user_setting', $mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        $this->mUserStatsTbl = new TableGateway('user_faucet_stat', $mapper);

        $this->fixmapper = $mapper;

        $this->mUserTbl = new TableGateway('user', $mapper);

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

        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $currency = strtoupper(filter_var($data->currency, FILTER_SANITIZE_STRING));
        if(strlen($currency) != 3 && strlen($currency) != 4) {
            return new ApiProblem(400, 'Invalid Currency');
        }

        $bannedUsers = $this->mUserSettingsTbl->select(['setting_name' => 'user-tempban']);
        $bannedUsersByUserId = [];
        foreach($bannedUsers as $ban) {
            $bannedUsersByUserId['ban-'.$ban->user_idfs] = 1;
        }

        $wthSel = new Select($this->mWithdrawTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_withdraw.user_idfs', ['username', 'token_balance']);
        $wthSel->where(['state' => 'new', 'currency' => $currency]);
        $openWithdraws = $this->mWithdrawTbl->selectWith($wthSel);
        $withdrawalsByWallet = [];
        $withdrawals = [];
        $duplicates = 0;

        $userWthStats = $this->mUserStatsTbl->select(['stat_key' => 'user-wth-amount-total']);
        $wthAmountByUserId = [];
        foreach($userWthStats as $wthS) {
            $wthAmountByUserId['user-'.$wthS->user_idfs] = $wthS->stat_data;
        }
        foreach($openWithdraws as $wth) {
            if (!array_key_exists('ban-' . $wth->user_idfs, $bannedUsersByUserId)) {
                if(!in_array($wth->wallet, $withdrawalsByWallet)) {
                    $withdrawalsByWallet[] = $wth->wallet;

                    $totalWth = 0;
                    if(array_key_exists('user-'.$wth->user_idfs, $wthAmountByUserId)) {
                        $totalWth = $wthAmountByUserId['user-'.$wth->user_idfs];
                    }

                    $withdrawals[] = [
                        'id' => $wth->Withdraw_ID,
                        'amount_coins' => $wth->amount,
                        'amount_crypto' => $wth->amount_paid,
                        'currency' => strtolower($wth->currency),
                        'wallet' => $wth->wallet,
                        'user' => [
                            'id' => $wth->user_idfs,
                            'name' => $wth->username,
                            'balance' => $wth->token_balance,
                            'withdrawals' => $totalWth
                        ],
                        'date' => date('Y-m-d H:i', strtotime($wth->date_requested))
                    ];
                } else {
                    $duplicates++;
                }
            }
        }

        return [
            'list' => $withdrawals,
            'duplicates' => $duplicates
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

        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $bannedUsers = $this->mUserSettingsTbl->select(['setting_name' => 'user-tempban']);
        $bannedUsersByUserId = [];
        foreach($bannedUsers as $ban) {
            $bannedUsersByUserId['ban-'.$ban->user_idfs] = 1;
        }

        $wthId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        if(empty($wthId) || $wthId <= 0) {
            return new ApiProblem(400, 'Invalid ID');
        }
        $wthSel = new Select($this->mWithdrawTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_withdraw.user_idfs', ['username', 'token_balance']);
        $wthSel->where(['Withdraw_ID' => $wthId]);
        $wth = $this->mWithdrawTbl->selectWith($wthSel);
        if($wth->count() == 0) {
            return new ApiProblem(404, 'Withdrawal not found');
        }
        $wth = $wth->current();
        if($wth->state != 'new') {
            return new ApiProblem(400, 'Withdrawal is already processed');
        }

        return [
            'withdrawal' => [
                'id' => $wth->Withdraw_ID,
                'amount_coins' => $wth->amount,
                'amount_crypto' => $wth->amount_paid,
                'currency' => strtolower($wth->currency),
                'wallet' => $wth->wallet,
                'user' => [
                    'id' => $wth->user_idfs,
                    'name' => $wth->username,
                    'balance' => $wth->token_balance
                ],
                'date' => date('Y-m-d H:i', strtotime($wth->date_requested))
            ]
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

        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $bannedUsers = $this->mUserSettingsTbl->select(['setting_name' => 'user-tempban']);
        $bannedUsersByUserId = [];
        foreach($bannedUsers as $ban) {
            $bannedUsersByUserId['ban-'.$ban->user_idfs] = 1;
        }

        $wthSel = new Select($this->mWithdrawTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_withdraw.user_idfs', ['username','xp_level']);
        $wthSel->where(['state' => 'new']);
        $openWithdraws = $this->mWithdrawTbl->selectWith($wthSel);
        $withdrawals = [];
        $riskyWithdrawals = [];
        $totalOpenByCrypto = [];
        $totalOpenCoinsByCrypto = [];
        $totalOpenDollars = 0;
        foreach($openWithdraws as $wth) {
            if(!array_key_exists('ban-'.$wth->user_idfs, $bannedUsersByUserId)) {
                $totalOpenDollars+=$wth->amount;
                $cryptoKey = 'coin-'.strtolower($wth->currency);
                if(!array_key_exists($cryptoKey, $totalOpenByCrypto)) {
                    $totalOpenByCrypto[$cryptoKey] = 0;
                }
                $totalOpenByCrypto[$cryptoKey]+=$wth->amount_paid;
                if(!array_key_exists($cryptoKey, $totalOpenCoinsByCrypto)) {
                    $totalOpenCoinsByCrypto[$cryptoKey] = 0;
                }
                $totalOpenCoinsByCrypto[$cryptoKey]+=$wth->amount;

                if($wth->xp_level >= 10) {
                    $withdrawals[] = [
                        'id' => $wth->Withdraw_ID,
                        'amount_coins' => $wth->amount,
                        'amount_crypto' => $wth->amount_paid,
                        'currency' => strtolower($wth->currency),
                        'wallet' => $wth->wallet,
                        'user' => $wth->username,
                        'xp_level' => $wth->xp_level,
                        'lnk' => $wth->user_idfs,
                        'date' => date('Y-m-d H:i', strtotime($wth->date_requested))
                    ];
                } else {
                    $riskyWithdrawals[] = [
                        'id' => $wth->Withdraw_ID,
                        'amount_coins' => $wth->amount,
                        'amount_crypto' => $wth->amount_paid,
                        'currency' => strtolower($wth->currency),
                        'wallet' => $wth->wallet,
                        'user' => $wth->username,
                        'xp_level' => $wth->xp_level,
                        'lnk' => $wth->user_idfs,
                        'date' => date('Y-m-d H:i', strtotime($wth->date_requested))
                    ];
                }
            }
        }

        $cryptoInfo = $this->mWalletTbl->select();
        $colorsByCrypto = [];
        foreach($cryptoInfo as $c) {
            $colorsByCrypto['coin-'.strtolower($c->coin_sign)] = $c->chartcolor;
        }

        $openCryptos = [];
        foreach($totalOpenByCrypto as $cKey => $cVal) {
            $openCryptos[] = [
                'name' => substr($cKey, strlen('coin-')). ' - '.$cVal,
                'amount' => $totalOpenCoinsByCrypto[$cKey],
                'color' => $colorsByCrypto[$cKey]
            ];
        }

        return (object)[
            'total_usd' => round($totalOpenDollars*0.00004, 0),
            'total_crypto' => $openCryptos,
            'withdrawals' => $withdrawals,
            'risky_withdrawals' => $riskyWithdrawals
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

        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $bannedUsers = $this->mUserSettingsTbl->select(['setting_name' => 'user-tempban']);
        $bannedUsersByUserId = [];
        foreach($bannedUsers as $ban) {
            $bannedUsersByUserId['ban-'.$ban->user_idfs] = 1;
        }

        $currency = strtoupper(filter_var($data[0]->currency, FILTER_SANITIZE_STRING));
        if(strlen($currency) != 3 && strlen($currency) != 4) {
            return new ApiProblem(400, 'Invalid Currency');
        }

        $wthSel = new Select($this->mWithdrawTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_withdraw.user_idfs', ['username', 'ref_user_idfs']);
        $wthSel->where(['state' => 'new', 'currency' => strtoupper($currency)]);
        $openWithdraws = $this->mWithdrawTbl->selectWith($wthSel);

        $checkIdsTmp = (array)$data[0]->ids;
        $checkIds = [];
        foreach($checkIdsTmp as $cId) {
            $cIdFix = filter_var($cId, FILTER_SANITIZE_NUMBER_INT);
            if($cIdFix > 0 && !empty($cIdFix)) {
                $checkIds[] = $cIdFix;
            }
        }

        $txId = filter_var($data[0]->tx_id, FILTER_SANITIZE_STRING);
        if(strlen($txId) < 10) {
            return new ApiProblem(400, 'Invalid TX ID');
        }

        $withdrawalsByWallet = [];
        $processedWithdrawals = 0;
        foreach($openWithdraws as $wth) {
            if (!array_key_exists('ban-' . $wth->user_idfs, $bannedUsersByUserId)) {
                if(!in_array($wth->wallet, $withdrawalsByWallet)) {
                    $withdrawalsByWallet[] = $wth->wallet;

                    if(in_array($wth->Withdraw_ID, $checkIds)) {
                        $this->mWithdrawTbl->update([
                            'transaction_id' => $txId,
                            'date_sent' => date('Y-m-d H:i:s', time()),
                            'state' => 'done'
                        ],['Withdraw_ID' => $wth->Withdraw_ID]);

                        # referral bonus
                        if($wth->ref_user_idfs != 0) {
                            # make sure referral still exists and is not banned
                            $refInfo = $this->mUserTbl->select(['User_ID' => $wth->ref_user_idfs]);
                            if($refInfo->count() > 0) {
                                $refBonus = $wth->amount*0.1;
                                $refName = $wth->username;
                                $newBalance = $this->mTransHelper->executeTransaction($refBonus, false, $wth->ref_user_idfs, $wth->Withdraw_ID, 'ref-bonus', 'Referral Bonus for User '.$refName.' received');
                            }
                        }

                        $processedWithdrawals++;
                    }
                }
            }
        }

        if(count($checkIds) != $processedWithdrawals) {
            return new ApiProblem(400, 'Amount of transactions sent : '.count($checkIds). ' - tx processed: '.$processedWithdrawals);
        }

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

        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $wthId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        if($wthId <= 0 || empty($wthId)) {
            return new ApiProblem(400, 'Invalid Withdrawal ID');
        }
        $wthSel = new Select($this->mWithdrawTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_withdraw.user_idfs', ['ref_user_idfs', 'username']);
        $wthSel->where(['Withdraw_ID' => $wthId]);
        $wth = $this->mWithdrawTbl->selectWith($wthSel);
        if($wth->count() == 0) {
            return new ApiProblem(404, 'Withdrawal not found');
        }
        $wth = $wth->current();
        if($wth->state != 'new') {
            return new ApiProblem(400, 'Withdrawal is already processed');
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

            $this->mWithdrawTbl->update([
                'transaction_id' => $txId,
                'date_sent' => date('Y-m-d H:i:s', time()),
                'state' => 'done'
            ],['Withdraw_ID' => $wth->Withdraw_ID]);

            # referral bonus
            if($wth->ref_user_idfs != 0) {
                # make sure referral still exists and is not banned
                $refInfo = $this->mUserTbl->select(['User_ID' => $wth->ref_user_idfs]);
                if($refInfo->count() > 0) {
                    $isBanned = $this->mUserSettingsTbl->select(['setting_name' => 'user-tempban', 'user_idfs' => $wth->ref_user_idfs]);
                    if($isBanned->count() == 0) {
                        $refBonus = $wth->amount*0.1;
                        $refName = $wth->username;
                        $newBalance = $this->mTransHelper->executeTransaction($refBonus, false, $wth->ref_user_idfs, $wth->Withdraw_ID, 'ref-bonus', 'Referral Bonus for User '.$refName.' received');
                    }
                }
            }

        } else {
            $comment = substr(filter_var($data->comment, FILTER_SANITIZE_STRING), 0, 255);
            if(strlen($comment) < 10) {
                return new ApiProblem(400, 'Please provide a comment when cancelling a withdrawal');
            }
            $newBalance = $this->mTransHelper->executeTransaction($wth->amount, false, $wth->user_idfs, $wth->Withdraw_ID, 'wth-refund', 'Refund for cancelled Withdrawal');

            $this->mWithdrawTbl->update([
                'transaction_id' => $comment,
                'state' => 'cancel'
            ],['Withdraw_ID' => $wth->Withdraw_ID]);

        }
        return [
            'state' => 'done'
        ];
    }
}
