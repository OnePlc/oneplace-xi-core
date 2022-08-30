<?php
namespace Backend\V1\Rpc\UserInfo;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class UserInfoController extends AbstractActionController
{
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
     * @var TableGateway
     */
    private $mTxTbl;

    /**
     * @var TableGateway
     */
    private $mOfTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mTxTbl = new TableGateway('faucet_transaction', $mapper);
        $this->mOfTbl = new TableGateway('offerwall_user', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function userInfoAction()
    {
        $request = $this->getRequest();

        /**
         * Load Shortlink Info
         *
         * @since 1.0.0
         */
        if($request->isGet()) {
            # Prevent 500 error
            if (!$this->getIdentity()) {
                return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
            }
            $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
            if (get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
                return new ApiProblemResponse($me);
            }
            if ($me->is_employee != 1) {
                return new ApiProblemResponse(new ApiProblem(400, 'You are not allowed this access this api'));
            }

            $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
            $ipWhiteList = json_decode($ipWhiteList);
            if (!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
                return new ApiProblemResponse(new ApiProblem(400, 'You are not allowed this access this api'));
            }

            $userId = filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT);
            if(!is_numeric($userId) || empty($userId) || $userId <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid User Id'));
            }

            $userInfo = $this->mUserTbl->select(['User_ID' => $userId]);
            if($userInfo->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'User not found'));
            }
            $userInfo = $userInfo->current();

            $userEarningsByType = [];
            $userSpendingsByType = [];

            $userTransactions = $this->mTxTbl->select(['user_idfs' => $userId]);
            foreach($userTransactions as $tx) {
                if($tx->is_output == 1) {
                    if(!array_key_exists($tx->ref_type, $userSpendingsByType)) {
                        $userSpendingsByType[$tx->ref_type] = 0;
                    }
                    $userSpendingsByType[$tx->ref_type]+=$tx->amount;
                } else {
                    if(!array_key_exists($tx->ref_type, $userEarningsByType)) {
                        $userEarningsByType[$tx->ref_type] = 0;
                    }
                    $userEarningsByType[$tx->ref_type]+=$tx->amount;
                }
            }

            $spendingChart = [
                'labels' => [],
                'data' => []
            ];
            $earningChart = [
                'labels' => [],
                'data' => []
            ];

            foreach($userSpendingsByType as $sKey => $sVal) {
                $spendingChart['labels'][] = $sKey;
                $spendingChart['data'][] = round($sVal);
            }


            foreach($userEarningsByType as $eKey => $eVal) {
                $earningChart['labels'][] = $eKey;
                $earningChart['data'][] = round($eVal);
            }

            $userOffers = ['small' => [],'big' => []];

            $ofSmallWh = new Where();
            $ofSmallWh->lessThanOrEqualTo('amount', 500);
            $ofSmallWh->equalTo('user_idfs', $userId);
            $ofSel = new Select($this->mOfTbl->getTable());
            $ofSel->join(['o' => 'offerwall'],'o.Offerwall_ID = offerwall_user.offerwall_idfs', ['wall_name']);
            $ofSel->where($ofSmallWh);
            $ofSel->order('date_completed DESC');
            $ofSel->limit(50);

            $smallOffers = $this->mOfTbl->selectWith($ofSel);
            foreach($smallOffers as $smallOffer) {
                $userOffers['small'][] = [
                    'id' => $smallOffer->id,
                    'amount' => $smallOffer->amount,
                    'usd' => $smallOffer->amount_usd,
                    'date' => date('Y-m-d H:i', strtotime($smallOffer->date_completed)),
                    'name' => $smallOffer->label,
                    'wall' => $smallOffer->wall_name,
                    'tx_id' => $smallOffer->transaction_id
                ];
            }

            $ofBigWh = new Where();
            $ofBigWh->greaterThanOrEqualTo('amount', 500);
            $ofBigWh->equalTo('user_idfs', $userId);
            $ofSel = new Select($this->mOfTbl->getTable());
            $ofSel->join(['o' => 'offerwall'],'o.Offerwall_ID = offerwall_user.offerwall_idfs', ['wall_name']);
            $ofSel->where($ofBigWh);
            $ofSel->order('date_completed DESC');
            $ofSel->limit(50);

            $bigOffers = $this->mOfTbl->selectWith($ofSel);
            foreach($bigOffers as $bigOffer) {
                $userOffers['big'][] = [
                    'id' => $bigOffer->id,
                    'amount' => $bigOffer->amount,
                    'usd' => $bigOffer->amount_usd,
                    'date' => date('Y-m-d H:i', strtotime($bigOffer->date_completed)),
                    'name' => $bigOffer->label,
                    'wall' => $bigOffer->wall_name,
                    'tx_id' => $bigOffer->transaction_id
                ];
            }

            return [
                'user' => [
                    'id' => $userInfo->User_ID,
                    'username' => $userInfo->username,
                    'token_balance' => $userInfo->token_balance,
                    'signup' => $userInfo->created_date,
                    'xp_level' => $userInfo->xp_level
                ],
                'offers' => $userOffers,
                'earning_chart' => $earningChart,
                'spending_chart' => $spendingChart
            ];
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not alloawed'));
    }
}
