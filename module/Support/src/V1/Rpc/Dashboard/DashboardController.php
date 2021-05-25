<?php
/**
 * DashboardController.php - Support Dashboard Controller
 *
 * Main Controller for User Support Dashboard
 * used by Moderators
 *
 * @category Controller
 * @package Support
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Support\V1\Rpc\Dashboard;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class DashboardController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Support Ticket Table
     *
     * @var TableGateway $mSupTicketTbl
     * @since 1.0.0
     */
    protected $mSupTicketTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Constructor
     *
     * SupportController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mSupTicketTbl = new TableGateway('user_request', $mapper);
    }

    /**
     * Get Support Ticket Dashboard Data
     *
     * @return ApiProblemResponse
     * @since 1.0.0
     */
    public function dashboardAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # this is only for emplyoees ...
        if((int)$me->is_employee !== 1) {
            return new ApiProblemResponse(new ApiProblem(418, 'Nice try - but no coffee for you.'));
        }

        /**
         * Get open Support Tickets
         */
        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $pageSize = 25;
        $openTickets = [];
        $ticketSel = new Select($this->mSupTicketTbl->getTable());
        $checkWh = new Where();
        $checkWh->like('state', 'new');
        $ticketSel->where($checkWh);
        $ticketSel->order('date ASC');

        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $ticketSel,
            # the $ticketSel to run it against
            $this->mSupTicketTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $ticketsPaginated = new Paginator($oPaginatorAdapter);
        $ticketsPaginated->setCurrentPageNumber($page);
        $ticketsPaginated->setItemCountPerPage($pageSize);
        foreach($ticketsPaginated as $openTicket) {
            $member = $this->mUserTbl->select(['User_ID' => $openTicket->user_idfs]);
            if(count($member) > 0) {
                $member = $member->current();
                $openTickets[] = (object)[
                    'id' => $openTicket->Request_ID,
                    'message' => $openTicket->message,
                    'user' => [
                        'id' => $member->User_ID,
                        'name' => $member->username
                    ],
                    'date'=> $openTicket->date,
                ];
            }
        }

        $totalOpenTickets = $this->mSupTicketTbl->select($checkWh)->count();

        $recentlyClosed = [];
        $recSel = new Select($this->mSupTicketTbl->getTable());
        $recSel->where(['state' => 'done']);
        $recSel->order('date_reply DESC');
        $recSel->limit(25);
        $recentTickets = $this->mSupTicketTbl->selectWith($recSel);
        foreach($recentTickets as $rec) {
            $member = $this->mUserTbl->select(['User_ID' => $rec->user_idfs]);
            if(count($member) == 0) {
                continue;
            }
            $member = $member->current();
            $moderator = $this->mUserTbl->select(['User_ID' => $rec->reply_user_idfs]);
            if(count($moderator) == 0) {
                continue;
            }
            $moderator = $moderator->current();
            $recentlyClosed[] = (object)[
                'id' => $rec->Request_ID,
                'message' => $rec->message,
                'reply' => $rec->reply,
                'user' => [
                    'id' => $member->User_ID,
                    'name' => $member->username
                ],
                'moderator' => [
                    'id' => $moderator->User_ID,
                    'name' => $moderator->username
                ],
                'date'=> $rec->date,
                'date_reply'=> ($rec->date_reply !== null) ? $rec->date_reply : '-',
            ];
        }

        $meDone24hWh = new Where();
        $meDone24hWh->equalTo('reply_user_idfs', $me->User_ID);
        $meDone24hWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-24 hours')));

        $meDone = $this->mSupTicketTbl->select($meDone24hWh)->count();

        $done24hWh = new Where();
        $done24hWh->like('state', 'done');
        $done24hWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-24 hours')));

        $done24h = $this->mSupTicketTbl->select($done24hWh)->count();

        $meDone30dWh = new Where();
        $meDone30dWh->equalTo('reply_user_idfs', $me->User_ID);
        $meDone30dWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-30 days')));

        $meDone30d = $this->mSupTicketTbl->select($meDone30dWh)->count();

        $done30dWh = new Where();
        $done24hWh->like('state', 'done');
        $done30dWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-30 days')));

        $done30d = $this->mSupTicketTbl->select($done30dWh)->count();

        return [
            'ticket' => [
                'total_items' => $totalOpenTickets,
                'page_size' => $pageSize,
                'page' => $page,
                'page_count' => (round($totalOpenTickets/$pageSize) > 0) ? round($totalOpenTickets/$pageSize) : 1,
                'items' => $openTickets
            ],
            'tickets_done_24h_me' => $meDone,
            'tickets_done_24h_total' => ($done24h-$meDone),
            'tickets_done_30d_me' => $meDone30d,
            'tickets_done_30d_total' => ($done30d-$meDone30d),
            'recent' => [
                'items' => $recentlyClosed,
            ]
        ];
    }
}
