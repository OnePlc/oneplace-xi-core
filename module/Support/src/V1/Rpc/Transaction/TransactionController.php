<?php
/**
 * TransactionController.php - Transaction History Controller
 *
 * Main Controller for User Transaction History
 *
 * @category Controller
 * @package Support
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Support\V1\Rpc\Transaction;

use Application\Controller\IndexController;
use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class TransactionController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * User Basic Tools
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * Offers done Table
     *
     * @var TransactionHelper $mOffersDoneTbl
     * @since 1.0.0
     */
    protected $mOffersDoneTbl;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * User Transaction Table
     *
     * @var TableGateway $mTransTbl
     * @since 1.0.0
     */
    protected $mTransTbl;

    /**
     * Guild Table User Table
     *
     * Relation between Guild and User
     * to determine if user has a guild and
     * if yes what guild it is
     *
     * @var TableGateway $mGuildUserTbl
     * @since 1.0.0
     */
    protected $mGuildUserTbl;

    /**
     * Guild Activity Table
     *
     * Cached Activity of Guild Users
     *
     * @var TableGateway $mGuildActivityTbl
     * @since 1.0.0
     */
    protected $mGuildActivityTbl;

    /**
     * @var ApiTools
     */
    private $mApiTools;

    /**
     * ConstructormGuildActivityTbl
     *
     * TransactionController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        # Init Tables for this API
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mApiTools = new ApiTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mTransTbl = new TableGateway('faucet_transaction', $mapper);
        $this->mOffersDoneTbl = new TableGateway('offerwall_user', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mGuildActivityTbl = new TableGateway('faucet_guild_activity', $mapper);
    }

    /**
     * Get User Transaction Log (ONLY FOR MODS & ADMINS)
     *
     * @return array|ApiProblemResponse
     * @since 1.0.0
     */
    public function transactionAction()
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

        /**
         * Get User Transaction Log
         */
        if($request->isPost()) {
            # this is only for emplyoees ...
            if((int)$me->is_employee !== 1) {
                $userId = $me->User_ID;
            } else {
                # get data from body
                $json = IndexController::loadJSONFromRequestBody(['user_id'],$this->getRequest()->getContent());
                # check for attack vendors
                $secResult = $this->mSecTools->basicInputCheck([$json->user_id]);
                if($secResult !== 'ok') {
                    # ban user and force logout on client
                    $this->mUserSetTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'setting_name' => 'user-tempban',
                        'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' on Transaction History',
                    ]);
                    return new ApiProblemResponse(new ApiProblem(418, 'Potential XSS Attack - Goodbye'));
                }

                # check if user exists
                $userId = filter_var($json->user_id, FILTER_SANITIZE_NUMBER_INT);

                # if userId = 0, then employee wants to see his own history
                if($userId == 0) {
                    $userId = $me->User_ID;
                }
            }

            if($userId == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'User not does exist'));
            }

            $filter = (isset($_REQUEST['filter'])) ? filter_var($_REQUEST['filter'], FILTER_SANITIZE_STRING) : '';

            if($filter == 'guild') {
                $checkWh = new Where();
                $checkWh->equalTo('user_idfs', $me->User_ID);
                $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
                $userHasGuild = $this->mGuildUserTbl->select($checkWh);
                if($userHasGuild->count() > 0) {
                    $userId = $userHasGuild->current()->guild_idfs;

                    $transactionLog = [];
                    $gSel = new Select($this->mGuildActivityTbl->getTable());
                    $gSel->join(['u' => 'user'],'u.User_ID = faucet_guild_activity.user_idfs', ["username"]);
                    $gSel->where(['guild_idfs' => $userId]);
                    $gSel->order('date DESC');
                    $gTrans = $this->mGuildActivityTbl->selectWith($gSel);
                    if($gTrans->count() > 0) {
                        $i = 0;
                        foreach($gTrans as $gt) {
                            $transactionLog[] = (object)[
                                'id' => $i,
                                'amount' => $gt->reward,
                                'is_output' => 0,
                                'date' => $gt->date,
                                'time_ago' => $this->mApiTools->timeElapsedString($gt->date),
                                'ref_idfs' => $gt->ref_idfs,
                                'ref_type' => $gt->ref_type,
                                'comment' => '',
                                'member' => [
                                    'id' => $gt->user_idfs,
                                    'name' => $gt->username
                                ]
                            ];
                            $i++;
                        }
                    }
                    # api response
                    return [
                        'transactions' => $transactionLog,
                        'total_items' => 50,
                        'page' => 1,
                        'page_size' => 50,
                        'page_count' => 1
                    ];
                } else {
                    return new ApiProblemResponse(new ApiProblem(404, 'You are in no guild'));
                }
            } else {
                $logUser = $this->mUserTbl->select(['User_ID' => $userId]);
                if(count($logUser) == 0) {
                    return new ApiProblemResponse(new ApiProblem(404, 'User not does exist'));
                }

                /**
                 * Get open Support Tickets
                 */
                $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
                if($page <= 0) {
                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid Page'));
                }
                $pageSize = 25;
                $transactionLog = [];
                $transactionSel = new Select($this->mTransTbl->getTable());
                $checkWh = new Where();
                if($filter == 'shortlink') {
                    $checkWh->nest()
                        ->equalTo('ref_type', 'shortlink')
                        ->OR
                        ->equalTo('ref_type', 'shortlink-complete')
                        ->unnest();
                }
                $checkWh->equalTo('user_idfs', $userId);
                $transactionSel->where($checkWh);
                $transactionSel->order('date DESC');

                # Create a new pagination adapter object
                $oPaginatorAdapter = new DbSelect(
                # our configured select object
                    $transactionSel,
                    # the $ticketSel to run it against
                    $this->mTransTbl->getAdapter()
                );
                # Create Paginator with Adapter
                $transactionsPaginated = new Paginator($oPaginatorAdapter);
                $transactionsPaginated->setCurrentPageNumber($page);
                $transactionsPaginated->setItemCountPerPage($pageSize);
                foreach($transactionsPaginated as $trans) {
                    $transactionLog[] = (object)[
                        'id' => $trans->Transaction_ID,
                        'amount' => $trans->amount,
                        'is_output' => (boolean)$trans->is_output,
                        'date' => $trans->date,
                        'time_ago' => $this->mApiTools->timeElapsedString($trans->date),
                        'ref_idfs' => $trans->ref_idfs,
                        'ref_type' => $trans->ref_type,
                        'comment' => $trans->comment
                    ];
                }

                # count all transactions
                $totalTransactions = $this->mTransTbl->select($checkWh)->count();

                # api response
                return [
                    'transactions' => $transactionLog,
                    'total_items' => $totalTransactions,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'page_count' => (round($totalTransactions/$pageSize) > 0) ? round($totalTransactions/$pageSize) : 1
                ];
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
