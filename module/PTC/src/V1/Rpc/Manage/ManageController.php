<?php
namespace PTC\V1\Rpc\Manage;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class ManageController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * PTC Table
     *
     * @var TableGateway $mPTCTbl
     * @since 1.0.0
     */
    protected $mPTCTbl;

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
     * ManageController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mPTCTbl = new TableGateway('ptc', $mapper);
    }

    /**
     * Manage PTC Ad
     *
     * @return int[]|ApiProblemResponse
     * @since 1.1.2
     */
    public function manageAction()
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

        # Get Data from Request Body
        $json = IndexController::loadJSONFromRequestBody(['ptc_id','action','value'],$this->getRequest()->getContent());
        if(!$json) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
        }

        $ptcId = filter_var($json->ptc_id, FILTER_SANITIZE_NUMBER_INT);

        $ptc = $this->mPTCTbl->select(['PTC_ID' => $ptcId]);
        if(count($ptc) == 0) {
            return new ApiProblemResponse(new ApiProblem(404, 'PTC Ad not found'));
        }
        $ptc = $ptc->current();

        if($ptc->created_by != $me->User_ID) {
            return new ApiProblemResponse(new ApiProblem(400, 'You are not the owner of this PTC Ad'));
        }

        if($request->isPost()) {
            $action = filter_var($json->action, FILTER_SANITIZE_STRING);
            if($action != 'status') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid action'));
            }
            $value = filter_var($json->value, FILTER_SANITIZE_STRING);
            if($value != 'play' && $value != 'pause') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid value for action'));
            }

            $aReturn = [];

            if($value == 'play' && $ptc->active == 0) {
                if($ptc->verified == 1) {
                    $this->mPTCTbl->update([
                        'active' => 1
                    ],['PTC_ID' => $ptc->PTC_ID]);

                    $aReturn['active'] = 1;
                } else {
                    return new ApiProblemResponse(new ApiProblem(400, 'PTC is not verified yet.'));
                }
            }

            if($value == 'pause' && $ptc->active == 1) {
                $this->mPTCTbl->update([
                    'active' => 0
                ],['PTC_ID' => $ptc->PTC_ID]);

                $aReturn['active'] = 0;
            }

            if(count($aReturn) > 0) {
                $myPTCAds = [];
                $ptcFound = $this->mPTCTbl->select(['created_by' => $me->User_ID]);
                if(count($ptcFound) > 0) {
                    foreach($ptcFound as $ptc) {
                        $myPTCAds[] = (object)[
                            'id' => $ptc->PTC_ID,
                            'title' => utf8_decode($ptc->title),
                            'description' => utf8_decode( $ptc->description),
                            'views' => $ptc->view_balance,
                            'redirect' => $ptc->redirect,
                            'nsfw' => $ptc->nsfw_warn,
                            'active' => $ptc->active,
                            'timer' => $ptc->timer,
                            'occurence' => $ptc->occur,
                            'website' => 'swissfaucet.io',
                            'url' => $ptc->url,
                            'verified' => $ptc->verified
                        ];
                    }
                }

                $aReturn['ptc_list'] = $myPTCAds;

                return $aReturn;
            }
        }

        if($request->isPut()) {
            $action = filter_var($json->action, FILTER_SANITIZE_STRING);
            if($action != 'topup') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid action'));
            }
            $value = filter_var($json->value, FILTER_SANITIZE_NUMBER_INT);
            if($value < 100 || empty($value) || !is_numeric($value)) {
                return new ApiProblemResponse(new ApiProblem(400, 'You need to add at least 100 Views'));
            }

            $ptcCost = 0;
            switch($ptc->timer) {
                case 15:
                    $ptcCost = 0.75;
                    break;
                case 30:
                    $ptcCost = 1;
                    break;
                case 60:
                    $ptcCost = 1.375;
                    break;
                case 90:
                    $ptcCost = 1.75;
                    break;
                default:
                    break;
            }

            $creditCost = $ptcCost*$value;
            if($me->credit_balance > $creditCost) {
                $newBalance = $this->mTransaction->executeCreditTransaction($creditCost, true, $me->User_ID, $ptc->PTC_ID, 'topup');
                if($newBalance !== false) {
                    $this->mPTCTbl->update([
                        'view_balance' => $ptc->view_balance + $value
                    ], ['PTC_ID' => $ptc->PTC_ID]);

                    return [
                        'credit_balance' => $newBalance
                    ];
                } else {
                    return new ApiProblemResponse(new ApiProblem(400, 'Credit Transaction Error'));
                }
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'Your Credit Balance is too low'));
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
