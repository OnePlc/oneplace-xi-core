<?php
/**
 * ClaimController.php - Claim Controller
 *
 * Main Resource for Faucet Claim
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rpc\Claim;

use Faucet\Transaction\TransactionHelper;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Session\Container;

class ClaimController extends AbstractActionController
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * Claim Table
     *
     * @var TableGateway $mClaimTbl
     * @since 1.0.0
     */
    protected $mClaimTbl;

    /**
     * Database Connection
     *
     * @var $mMapper
     * @since 1.0.0
     */
    private $mMapper;

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
        $this->mSession = new Container('webauth');
        $this->mMapper = $mapper;
    }

    /**
     * Faucet Free Crypto Claim
     *
     * @return ApiProblem|ViewModel
     * @since 1.0.0
     */
    public function claimAction()
    {
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        # Set Timer for next claim
        $sTime = 0;

        # Lets check if there was a claim less than 60 minutes ago
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-1 hour')));
        $oClaimCheck = $this->mClaimTbl->select($oWh);
        if(count($oClaimCheck) > 0) {
            $oClaimCheck = $oClaimCheck->current();
            # override timer
            $sTime = strtotime($oClaimCheck->date_next)-time();
        }

        # Only show timer if GET
        $oRequest = $this->getRequest();
        if(!$oRequest->isPost()) {
            return new ViewModel([
                'status' => 'wait',
                'next_claim' => $sTime,
            ]);
        }

        # Prevent double claims
        if($sTime > 0) {
            return new ApiProblemResponse(new ApiProblem(409, 'Already claimed - wait '.$sTime.' more seconds before claiming again'));
        } else {
            # Default Claim
            $claimAmount = 10;

            # Set next claim date
            $nextDate = date('Y-m-d H:i:s', time()+3600);

            # Execute Claim Transaction
            $oTransHelper = new TransactionHelper($this->mMapper);
            if($oTransHelper->executeTransaction($claimAmount, false, $me->User_ID, 10, 'web-faucet', 'Website Faucet claimed')) {
                # Execute Claim
                $this->mClaimTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'date' => date('Y-m-d H:i:s', time()),
                    'date_next' => $nextDate,
                    'amount' => $claimAmount,
                    'mode' => 'coins',
                    'source' => 'website',
                ]);
            }

            # Show Timer
            return new ViewModel([
                'status' => 'done',
                'next' => strtotime($nextDate)-time()
            ]);
        }
    }
}
