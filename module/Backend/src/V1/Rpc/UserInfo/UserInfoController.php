<?php
namespace Backend\V1\Rpc\UserInfo;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
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

            return [
                'user' => [
                    'id' => $userInfo->User_ID,
                    'username' => $userInfo->username,
                    'token_balance' => $userInfo->token_balance,
                    'signup' => $userInfo->created_date,
                    'xp_level' => $userInfo->xp_level
                ],
                'earning_chart' => $earningChart,
                'spending_chart' => $spendingChart
            ];
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not alloawed'));
    }
}
