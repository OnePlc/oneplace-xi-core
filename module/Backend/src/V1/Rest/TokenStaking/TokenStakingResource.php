<?php
namespace Backend\V1\Rest\TokenStaking;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class TokenStakingResource extends AbstractResourceListener
{
    /**
     * Withdraw Table
     *
     * @var TableGateway $mTokenPayTbl
     * @since 1.0.0
     */
    protected $mTokenPayTbl;

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
     * @var TableGateway
     */
    private $mTokenPayHistoryTbl;

    /**
     * @var TableGateway
     */
    private $mTokenBuyTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mTokenPayTbl = new TableGateway('faucet_tokenpay', $mapper);
        $this->mTokenPayHistoryTbl = new TableGateway('faucet_tokenpay_history', $mapper);
        $this->mTokenBuyTbl = new TableGateway('faucet_tokenbuy', $mapper);

        $this->mUserStatsTbl = new TableGateway('user_faucet_stat', $mapper);

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

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $week = filter_var($data->week, FILTER_SANITIZE_NUMBER_INT);
        $year = filter_var($data->year, FILTER_SANITIZE_NUMBER_INT);
        $activeBonus = filter_var($data->active_bonus, FILTER_SANITIZE_NUMBER_INT);
        $totalIn = filter_var($data->total_in, FILTER_SANITIZE_NUMBER_INT);
        $totalOut = filter_var($data->total_out, FILTER_SANITIZE_NUMBER_INT);
        $totalProfit = filter_var($data->total_profit, FILTER_SANITIZE_NUMBER_INT);
        $paymentTotal = (float)filter_var($data->payment_total, FILTER_SANITIZE_STRING);
        $tokensCirculating = filter_var($data->tokens_circulating, FILTER_SANITIZE_NUMBER_INT);
        $paymentPerToken = (float)filter_var($data->payment_per_token, FILTER_SANITIZE_STRING);
        $coinsPerToken = (float)filter_var($data->coins_per_token, FILTER_SANITIZE_STRING);
        $adminBonus = filter_var($data->admin_bonus, FILTER_SANITIZE_NUMBER_INT);

        if($week <= 0 || $year <= 0) {
            return new ApiProblem(400, 'Invalid Date');
        }
        $check = $this->mTokenPayTbl->select(['year' => $year, 'week' => $week]);
        if($check->count() > 0) {
            return new ApiProblem(400, 'There is already a payment for that date');
        }

        if($activeBonus <= 0 || $totalIn <= 0 || $totalOut <= 0 || $paymentTotal <= 0 || $tokensCirculating <= 0 || $paymentPerToken <= 0 || $coinsPerToken <= 0) {
            return new ApiProblem(400, 'Invalid Payment Data');
        }

        if($adminBonus < 0) {
            return new ApiProblem(400, 'Invalid Admin Bonus');
        }

        $this->mTokenPayTbl->insert([
            'week' => $week,
            'year' => $year,
            'active_bonus' => $activeBonus,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'total_profit' => $totalProfit,
            'payment_total' => $paymentTotal,
            'tokens_circulating' => $tokensCirculating,
            'payment_per_token' => $paymentPerToken,
            'coins_per_token' => $coinsPerToken,
            'admin_bonus' => $adminBonus
        ]);

        return true;
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

        $payId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        // get information to prepare new payment
        if($payId == 0) {
            $tokensSold = $this->mTokenBuyTbl->select(['sent' => 1]);
            $totalTokenSold = 0;
            foreach($tokensSold as $tk) {
                $totalTokenSold+=$tk->amount;
            }

            return (object)[
                'info' => [
                    'year' => date('Y', time()),
                    'week' => date('W', time())-1,
                    'bonus' => 5,
                    'token_sold' => $totalTokenSold
                ]
            ];
        } else {
            // show information about existing payment

            $year = substr($payId, 0, 4);
            $week = substr($payId, 4);
            $tokenPayment = $this->mTokenPayTbl->select(['year' => $year, 'week' => $week]);
            if($tokenPayment->count() == 0) {
                return new ApiProblem(404, 'Payment not found');
            }
            $tokenPayment = $tokenPayment->current();

            $isPaid = 0;
            $paymentStaking = $this->mTokenPayHistoryTbl->select(['year' => $year, 'week' => $week]);
            if($paymentStaking->count() > 0) {
                $isPaid = 1;
            }

            return (object)[
                'info' => [
                    'year' => $tokenPayment->year,
                    'week' => $tokenPayment->week,
                    'bonus' => $tokenPayment->active_bonus,
                    'total_in' => $tokenPayment->total_in,
                    'total_out' => $tokenPayment->total_out,
                    'total_profit' => $tokenPayment->total_profit,
                    'payment_total' => $tokenPayment->payment_total,
                    'token_sold' => $tokenPayment->tokens_circulating,
                    'payment_per_token' => $tokenPayment->payment_per_token,
                    'coins_per_token' => $tokenPayment->coins_per_token,
                    'admin_bonus' => $tokenPayment->admin_bonus,
                    'total_coin_payment' => $tokenPayment->coins_per_token*$tokenPayment->tokens_circulating,
                    'is_paid' => $isPaid
                ]
            ];
        }
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

        $tpSel = new Select($this->mTokenPayTbl->getTable());
        $tpSel->where(['year' => date('Y', time())]);
        $tpSel->order('week DESC');
        $tokenPays = $this->mTokenPayTbl->selectWith($tpSel);

        $payments = [];
        foreach($tokenPays as $tp) {
            $payments[] = [
                'id' => $tp->year.''.$tp->week,
                'week' => $tp->week,
                'year' => $tp->year,
                'bonus' => $tp->active_bonus,
                'payment_total' => $tp->payment_total + $tp->admin_bonus,
                'payment_token' => $tp->coins_per_token
            ];
        }

        return (object)[
            'payments' => $payments
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

        $payId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        if($payId <= 0 || empty($payId)) {
            return new ApiProblem(400, 'Invalid Payment Id');
        }
        $year = substr($payId, 0, 4);
        if(strlen($year) != 4) {
            return new ApiProblem(400, 'Invalid Year');
        }
        $week = substr($payId, 4);

        // get payment
        $payment = $this->mTokenPayTbl->select(['year' => $year, 'week' => $week]);
        if($payment->count() == 0) {
            return new ApiProblem(400, 'Payment not found');
        }
        $payment = $payment->current();

        // double check its not already paid
        $isPaid = $this->mTokenPayHistoryTbl->select(['year' => $year, 'week' => $week]);
        if($isPaid->count() > 0) {
            return new ApiProblem(400, 'Staking Rewards are already sent for this Payment');
        }

        // get all token holders
        $tokensSold = $this->mTokenBuyTbl->select(['sent' => 1]);

        $totalCoinsSent = 0;
        $totalTokens = 0;
        $userIdCache = [];

        $tokensByWallets = [];

        foreach($tokensSold as $tkn) {
            $tknKey = trim($tkn->wallet).'-'.$tkn->user_idfs;
            if(!array_key_exists($tknKey, $tokensByWallets)) {
                $tokensByWallets[$tknKey] = (object)['amount' => 0, 'user_idfs' => $tkn->user_idfs, 'wallet' => trim($tkn->wallet)];
            }
            $tokensByWallets[$tknKey]->amount+=$tkn->amount;
        }

        foreach($tokensByWallets as $token) {
            $coinPaymentForTokens = $token->amount * $payment->coins_per_token;

            // add to totals for stats
            $totalTokens+=$token->amount;
            $totalCoinsSent+=$coinPaymentForTokens;

            if(!in_array($token->user_idfs, $userIdCache)) {
                $userIdCache[] = $token->user_idfs;
            }

            $this->mTokenPayHistoryTbl->insert([
                'week' => $payment->week,
                'year' => $payment->year,
                'wallet' => $token->wallet,
                'user_idfs' => $token->user_idfs,
                'coins' => $coinPaymentForTokens,
                'token' => $token->amount
            ]);

            $newBalance = $this->mTransHelper->executeTransaction($coinPaymentForTokens, false, $token->user_idfs, $payment->week, 'token-staking', 'Staking from Week '.$payment->week.'-'.$payment->year.' for '.$token->amount.' Token @ '.$token->wallet, 1, false);
        }

        $totalUsers = count($userIdCache);

        return [
            'state' => 'done',
            'paid' => $totalCoinsSent,
            'tokens' => $totalTokens,
            'users' => $totalUsers
        ];
    }
}
