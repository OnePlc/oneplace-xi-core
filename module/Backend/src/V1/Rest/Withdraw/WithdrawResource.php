<?php
namespace Backend\V1\Rest\Withdraw;

use Faucet\Tools\SecurityTools;
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

        $this->mSecTools = new SecurityTools($mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        return new ApiProblem(405, 'The POST method has not been defined');
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

        $bannedUsers = $this->mUserSettingsTbl->select(['setting_name' => 'user-tempban']);
        $bannedUsersByUserId = [];
        foreach($bannedUsers as $ban) {
            $bannedUsersByUserId['ban-'.$ban->user_idfs] = 1;
        }

        $wthId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
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

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $bannedUsers = $this->mUserSettingsTbl->select(['setting_name' => 'user-tempban']);
        $bannedUsersByUserId = [];
        foreach($bannedUsers as $ban) {
            $bannedUsersByUserId['ban-'.$ban->user_idfs] = 1;
        }

        $wthSel = new Select($this->mWithdrawTbl->getTable());
        $wthSel->join(['u' => 'user'],'u.User_ID = faucet_withdraw.user_idfs', ['username']);
        $wthSel->where(['state' => 'new']);
        $openWithdraws = $this->mWithdrawTbl->selectWith($wthSel);
        $withdrawals = [];
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
                $withdrawals[] = [
                    'id' => $wth->Withdraw_ID,
                    'amount_coins' => $wth->amount,
                    'amount_crypto' => $wth->amount_paid,
                    'currency' => strtolower($wth->currency),
                    'wallet' => $wth->wallet,
                    'user' => [
                        'id' => $wth->user_idfs,
                        'name' => $wth->username
                    ],
                    'date' => date('Y-m-d H:i', strtotime($wth->date_requested))
                ];
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
        return new ApiProblem(405, 'The PUT method has not been defined for collections');
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
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
