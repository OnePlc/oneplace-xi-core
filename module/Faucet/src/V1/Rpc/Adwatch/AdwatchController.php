<?php
namespace Faucet\V1\Rpc\Adwatch;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class AdwatchController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTrans
     * @since 1.0.0
     */
    protected $mTrans;

    /**
     * Claim Table
     *
     * @var TableGateway $mClaimTbl
     * @since 1.0.0
     */
    protected $mClaimTbl;


    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mClaimTbl = new TableGateway('faucet_claim', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTrans = new TransactionHelper($mapper);
    }

    public function adwatchAction()
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

        if($request->isGet()) {
            // check if there is extra claim available
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->like('source', 'adwatch');
            $oWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', time()-(3600*24)));
            $oClaimCheck = $this->mClaimTbl->select($oWh);
            if($oClaimCheck->count() <= 5) {
                return [
                    'numbers' => [15,20,25],
                    'available' => true
                ];
            } else {
                return [
                    'numbers' => [],
                    'available' => false
                ];
            }
        }

        if($request->isPost()) {
            // check if there is extra claim available
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->like('source', 'adwatch');
            $oWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', time()-(3600*24)));
            $oClaimCheck = $this->mClaimTbl->select($oWh);
            if($oClaimCheck->count() <= 10) {
                # Get Data from Request Body
                $json = IndexController::loadJSONFromRequestBody(['device','ad_id','advertiser'],$this->getRequest()->getContent());
                if(!$json) {
                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
                }

                $device = filter_var($json->device, FILTER_SANITIZE_STRING);
                $ad_id = filter_var($json->ad_id, FILTER_SANITIZE_STRING);
                $advertiser = filter_var($json->advertiser, FILTER_SANITIZE_STRING);
                $claimAmount = rand(0,2);
                $numbers = [15,20,25];
                $claimAmount = $numbers[$claimAmount];

                $this->mClaimTbl->insert([
                    'date' => date('Y-m-d H:i:s', time()),
                    'date_started' => date('Y-m-d H:i:s', time()),
                    'date_next' => date('Y-m-d H:i:s', time()),
                    'amount' => $claimAmount,
                    'mode' => 'coins',
                    'source' => 'adwatch',
                    'device' => $device,
                    'ad_id' => $ad_id,
                    'advertiser' => $advertiser
                ]);

                $newBalance = $this->mTrans->executeTransaction($claimAmount, false, $me->User_ID, $claimAmount, 'adwatch', 'Watched Android Ad ' . $ad_id);
                if($newBalance !== false) {
                    return [
                        'amount' => $claimAmount,
                        'token_balance' => $newBalance
                    ];
                } else {
                    return new ApiProblemResponse(new ApiProblem(500, 'transaction error - could not add coins to balance'));
                }
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'no more extra claim for today'));
            }
        }
    }
}
