<?php
namespace PTC\V1\Rpc\Deposit;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class DepositController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * PTC Deposit Table
     *
     * @var TableGateway $mDepositTbl
     * @since 1.0.0
     */
    protected $mDepositTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mDepositTbl = new TableGateway('ptc_deposit', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    /**
     * PTC Deposit History and Payment
     *
     * @return ApiProblemResponse|ViewModel
     * @since 1.0.0
     */
    public function depositAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $creditValue = (float)$this->mSecTools->getCoreSetting('ptc-credit-value');
        if($creditValue <= 0) {
            return new ApiProblemResponse(new ApiProblem(500, 'Could not load Credit Value'));
        }

        $request = $this->getRequest();

        if($request->isGet()) {
            return new ViewModel([
                'deposit' => [
                    'items' => [],
                    'total_items' => 0,
                    'page' => 1,
                    'page_size' => 25,
                    'page_count' => 1,
                    'show_info' => false,
                    'show_info_msg' => ""
                ],
            ]);
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
