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

use Faucet\Tools\SecurityTools;
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
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * User Stats Table
     *
     * @var TableGateway $mUserStatsTbl
     * @since 1.0.0
     */
    protected $mUserStatsTbl;

    /**
     * Withdraw Table
     *
     * @var TableGateway $mWthTbl
     * @since 1.0.0
     */
    protected $mWthTbl;


    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

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
        $this->mUserStatsTbl = new TableGateway('user_faucet_stat', $mapper);
        $this->mWthTbl = new TableGateway('faucet_withdraw', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    /**
     * User Referral Statistics
     *
     * @return ApiProblem
     * @since 1.0.0
     */
    public function referralAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse(new ApiProblemResponse($me));
        }

        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $pageSize = 25;
        $myRefs = [];
        $myRefWithdrawn = 0;
        $memberSel = new Select($this->mUserTbl->getTable());
        $checkWh = new Where();
        $checkWh->equalTo('ref_user_idfs', $me->User_ID);
        $memberSel->where($checkWh);
        $memberSel->order('User_ID DESC');
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
            $withdrawn = 0;
            $bonus = 0;
            $refStat = $this->mUserStatsTbl->select(['stat_key' => 'user-wth-coins-total','user_idfs' => $ref->User_ID]);
            if($refStat->count() > 0) {
                $withdrawn = $refStat->current()->stat_data;
                $bonus = round($withdrawn*0.1, 0);
            }
            $lastAction = '-';
            if($ref->last_action != '0000-00-00 00:00:00') {
                $lastAction = date('Y-m-d', strtotime($ref->last_action));
            }
            $myRefs[] = (object)[
                'id' => $ref->User_ID,
                'name' => $ref->username,
                'xp_level' => $ref->xp_level,
                'signup' => date('Y-m-d', strtotime($ref->created_date)),
                'active' => $lastAction,
                'withdrawn' => $withdrawn,
                'bonus' => $bonus,
                'source' => $ref->ref_source
            ];
        }

        $myRefCount = 0;
        $refDate = "";
        $myRefStat = $this->mUserStatsTbl->select(['stat_key' => 'user-referral-total', 'user_idfs' => $me->User_ID]);
        if($myRefStat->count() > 0) {
            $myRefStat = $myRefStat->current();
            $myRefCount = $myRefStat->stat_data;
            $refDate = $myRefStat->date;
        }

        # get latest withdrawal stat
        $statSel = new Select($this->mUserStatsTbl->getTable());
        $statSel->where(['stat_key' => 'user-ref-bonus-total', 'user_idfs' => $me->User_ID]);
        $statSel->limit(1);
        $lastStat = $this->mUserStatsTbl->selectWith($statSel);

        $myRefBonus = 0;
        $bonusDate = "";
        if(count($lastStat) > 0) {
            $lastStat = $lastStat->current();
            $myRefBonus = round($lastStat->stat_data,2);
            $bonusDate = $lastStat->date;
        }

        $myRefLink = $this->mSecTools->getCoreSetting('app-url').'/ref/'.$me->User_ID;

        # Return referall info
        return new ViewModel([
            '_links' => [
                'self' => [
                    'href' => 'https://swissfaucet.io/ref/'.$me->User_ID,
                ]
            ],
            'total_items' => $myRefCount,
            'total_date' => $refDate,
            'withdrawn' => $myRefWithdrawn,
            'bonus' => $myRefBonus,
            'bonus_date' => $bonusDate,
            'referrals' => $myRefs,
            'link' => $myRefLink,
            'page' => $page,
            'page_count' => (ceil($myRefCount/$pageSize) > 0) ? ceil($myRefCount/$pageSize) : 1,
            'page_size' => $pageSize,
        ]);
    }
}
