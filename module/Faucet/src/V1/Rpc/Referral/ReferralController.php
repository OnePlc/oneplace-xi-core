<?php
/**
 * ReferralController.php - Referral Controller
 *
 * Main Resource for Faucet Referrals
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rpc\Referral;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Session\Container;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;

class ReferralController extends AbstractActionController
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Withdraw Table
     *
     * @var TableGateway $mWthTbl
     * @since 1.0.0
     */
    protected $mWthTbl;

    /**
     * Constructor
     *
     * ReferralController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mWthTbl = new TableGateway('faucet_withdraw', $mapper);
        $this->mSession = new Container('webauth');
    }

    /**
     * User Referral Statistics
     *
     * @return ApiProblem
     * @since 1.0.0
     */
    public function referralAction()
    {
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        # TODO: Rewrite to Batch to reduce DB load
        # TODO: Add paginated list of refs
        $myRefs = $this->mUserTbl->select(['ref_user_idfs' => $me->User_ID]);
        $myRefCount = 0;
        $myRefWithdrawn = 0;
        $myRefBalances = 0;
        if(count($myRefs) > 0) {
            foreach($myRefs as $ref) {
                $myRefCount++;
                $myRefBalances+=$ref->token_balance;
                $myRefWithdraws = $this->mWthTbl->select(['user_idfs' => $ref->User_ID]);
                # process withdrawals for user
                if(count($myRefWithdraws) > 0) {
                    foreach($myRefWithdraws as $wth) {
                        $myRefWithdrawn+=$wth->amount;
                    }
                }
            }
        }

        # Return referall info
        return new ViewModel([
            '_links' => [
                'self' => [
                    'href' => 'https://swissfaucet.io/ref/'.$me->User_ID,
                ]
            ],
            'total_items' => $myRefCount,
            'withdrawn' => $myRefWithdrawn,
            'bonus' => $myRefWithdrawn*.1,
            'balances' => $myRefBalances,
        ]);
    }
}
