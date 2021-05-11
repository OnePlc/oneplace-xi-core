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

use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Select;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
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
        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $pageSize = 25;
        # TODO: Rewrite to Batch to reduce DB load
        # TODO: Add paginated list of refs
        $myRefs = [];
        $memberSel = new Select($this->mUserTbl->getTable());
        $checkWh = new Where();
        $checkWh->equalTo('ref_user_idfs', $me->User_ID);
        $memberSel->where($checkWh);
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $memberSel,
            # the adapter to run it against
            $this->mUserTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $membersPaginated = new Paginator($oPaginatorAdapter);
        $membersPaginated->setCurrentPageNumber($page);
        $membersPaginated->setItemCountPerPage($pageSize);
        foreach($membersPaginated as $ref) {
            $myRefs[] = (object)[
                'id' => $ref->User_ID, 'name' => $ref->username,'xp_level' => $ref->xp_level,
                'signup' => $ref->created_date, 'withdrawn' => 0, 'bonus' => 0,
            ];
        }

        $myRefCount = $this->mUserTbl->select($checkWh)->count();

        $myRefWithdrawn = 0;

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
            'referrals' => $myRefs,
            'page' => $page,
            'page_count' => round($myRefCount/$pageSize),
            'page_size' => $pageSize,
        ]);
    }
}
