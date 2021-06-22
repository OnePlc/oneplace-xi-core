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
use Faucet\Tools\SecurityTools;
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
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

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
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    /**
     * Buy Lottery Tickets for current Round
     *
     * @since 1.0.0
     */
    public function ticketsAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # Get current lottery round
        $roundSel = new Select($this->mLotteryTbl->getTable());
        $roundSel->order('Round_ID DESC');
        $roundSel->limit(1);
        $currentRound = $this->mLotteryTbl->selectWith($roundSel)->current();
        $roundID = $currentRound->Round_ID;

        # get data from request
        $json = IndexController::loadJSONFromRequestBody(['round','tickets'],$this->getRequest()->getContent());
        # check for attack vendors
        $secResult = $this->mSecTools->basicInputCheck([$json->tickets,$json->round]);
        if($secResult !== 'ok') {
            # ban user and force logout on client
            $this->mUserSetTbl->insert([
                'user_idfs' => $me->User_ID,
                'setting_name' => 'user-tempban',
                'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Lottery Buy Tickets',
            ]);
            return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
        }

        $tickets = filter_var($json->tickets, FILTER_SANITIZE_NUMBER_INT);

        if($tickets <= 0) {
            return new ApiProblemResponse(new ApiProblem(400, 'You must provide a valid amount of tickets'));
        }
        $ticketPrice = 10;
        $myTotalTickets = 0;
        # Check if user has enough funds to buy the tickets
        if($this->mTransaction->checkUserBalance($tickets*$ticketPrice,$me->User_ID)) {
            # buy the tickets
            $newBalance = $this->mTransaction->executeTransaction($tickets*$ticketPrice,true,$me->User_ID,$roundID,'lottery-ticket','Bought '.$tickets.' lottery tickets');
            if($newBalance !== false) {
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

                # Get Tickets for current Round
                $totalTickets = 0;
                $ticketsRound = $this->mLotteryTkTbl->select(['round_idfs' => $roundID]);
                if(count($ticketsRound) > 0) {
                    foreach($ticketsRound as $tk) {
                        $totalTickets+=$tk->tickets;
                    }
                }

                # Calculate Users Winning Chance
                $chanceWin = 0;
                if($myTotalTickets > 0 && $totalTickets > 0) {
                    $chanceWin = number_format(100/($totalTickets/$myTotalTickets),8,'.','\'');
                }

                $jackpot = ($totalTickets*10)*.9;

                # success
                return new ViewModel([
                    'state' => 'success',
                    'message' => $tickets.' bought for round '.$roundID,
                    'all_tickets'=> $totalTickets,
                    'my_tickets' => $myTotalTickets,
                    'jackpot' => $jackpot,
                    'my_chance'=> $chanceWin,
                    'user' => (object)[
                        'id'=> $me->User_ID,
                        'name' => $me->username,
                        'token_balance' => $newBalance,
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
