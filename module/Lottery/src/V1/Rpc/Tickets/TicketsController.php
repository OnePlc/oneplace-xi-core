<?php
/**
 * TicketsController.php - Lottery Ticket Controller
 *
 * Main Controller for Faucet Lottery Tickets
 *
 * @category Controller
 * @package Lottery
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Lottery\V1\Rpc\Tickets;

use Application\Controller\IndexController;
use Faucet\Transaction\TransactionHelper;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;
use Laminas\Db\Adapter\Adapter;
use Laminas\Session\Container;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;

class TicketsController extends AbstractActionController
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * Lottery Round Table
     *
     * @var TableGateway $mLotteryTbl
     * @since 1.0.0
     */
    protected $mLotteryTbl;

    /**
     * Lottery Ticket Table
     *
     * @var TableGateway $mLotteryTkTbl
     * @since 1.0.0
     */
    protected $mLotteryTkTbl;

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
     * TicketsController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        # Init Tables for this API
        $this->mLotteryTbl = new TableGateway('faucet_lottery_round', $mapper);
        $this->mLotteryTkTbl = new TableGateway('faucet_lottery_ticket', $mapper);
        $this->mSession = new Container('webauth');
        $this->mTransaction = new TransactionHelper($mapper);
    }

    /**
     * Buy Lottery Tickets for current Round
     *
     * @since 1.0.0
     */
    public function ticketsAction()
    {
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblemResponse(new ApiProblem(401, 'You are not logged in'));
        }
        $me = $this->mSession->auth;

        # Get current lottery round
        $roundSel = new Select($this->mLotteryTbl->getTable());
        $roundSel->order('Round_ID DESC');
        $roundSel->limit(1);
        $currentRound = $this->mLotteryTbl->selectWith($roundSel)->current();
        $roundID = $currentRound->Round_ID;

        # get data from request
        $json = IndexController::loadJSONFromRequestBody(['round','tickets'],$this->getRequest()->getContent());
        $tickets = filter_var($json->tickets, FILTER_SANITIZE_NUMBER_INT);
        $ticketPrice = 10;
        $myTotalTickets = 0;
        # Check if user has enough funds to buy the tickets
        if($this->mTransaction->checkUserBalance($tickets*$ticketPrice,$me->User_ID)) {
            # buy the tickets
            $newBalance = $this->mTransaction->executeTransaction($tickets*$ticketPrice,true,$me->User_ID,$roundID,'lottery-ticket','Bought '.$tickets.' lottery tickets');
            if($newBalance) {
                # Check if User already has tickets
                $userTickets = $this->mLotteryTkTbl->select([
                    'round_idfs' => $roundID,
                    'user_idfs' => $me->User_ID,
                ]);
                if(count($userTickets) == 0) {
                    # Add tickets
                    $this->mLotteryTkTbl->insert([
                        'round_idfs' => $roundID,
                        'user_idfs' => $me->User_ID,
                        'tickets' => $tickets,
                    ]);
                    $myTotalTickets = $tickets;
                } else {
                    # Get current tickets
                    $myTickets = $userTickets->current()->tickets;
                    $myTotalTickets = $myTickets+$tickets;
                    # Update Tickets
                    $this->mLotteryTkTbl->update([
                        'tickets' => $myTotalTickets,
                    ],[
                        'round_idfs' => $roundID,
                        'user_idfs' => $me->User_ID,
                    ]);
                }

                # success
                return new ViewModel([
                    'state' => 'success',
                    'message' => $tickets.' bought for round '.$roundID,
                    'user' => (object)[
                        'name' => $me->username,
                        'balance' => $newBalance,
                        'tickets' => $myTotalTickets
                    ],
                ]);
            }
        } else {
            return new ApiProblemResponse(new ApiProblem(409, 'Not enough funds to buy '.$tickets.' tickets'));
        }

        return new ViewModel([
            'state' => 'done'
        ]);
    }
}
