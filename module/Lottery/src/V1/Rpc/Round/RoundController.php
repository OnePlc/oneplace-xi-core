<?php
/**
 * RoundController.php - Lottery Round Controller
 *
 * Main Resource for Faucet Lottery Round
 *
 * @category Controller
 * @package Lottery
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Lottery\V1\Rpc\Round;

use Faucet\Tools\SecurityTools;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;
use Laminas\Db\Adapter\Adapter;
use Laminas\Session\Container;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;

class RoundController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

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
     * Lottery Winner Table
     *
     * @var TableGateway $mLotteryWInTbl
     * @since 1.0.0
     */
    protected $mLotteryWInTbl;

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
     * RoundController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        # Init Tables for this API
        $this->mLotteryTbl = new TableGateway('faucet_lottery_round', $mapper);
        $this->mLotteryTkTbl = new TableGateway('faucet_lottery_ticket', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mLotteryWInTbl = new TableGateway('faucet_lottery_winner', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    /**
     * Lottery Round Information
     *
     * @since 1.0.0
     */
    public function roundAction()
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

        $request = $this->getRequest();

        if($request->isGet()) {
            # Get Winners of last round
            $winnersLastRound = [];
            $winSel = new Select($this->mLotteryWInTbl->getTable());
            $winSel->order('rank ASC');
            $winSel->where(['round_idfs' => $roundID-1]);
            $lastWinners = $this->mLotteryWInTbl->selectWith($winSel);
            if(count($lastWinners) > 0) {
                foreach($lastWinners as $win) {
                    $winnerDB = $this->mUserTbl->select(['User_ID' => $win->user_idfs]);
                    # Skip deleted users
                    if(count($winnerDB) > 0) {
                        $winnerDB = $winnerDB->current();
                        $winner = (object)['name' => $winnerDB->username,'id' => $winnerDB->User_ID];
                        $winner->rank = $win->rank;
                        $winner->coins_won = $win->coins_won;
                        $winner->tickets = $win->tickets;
                        $winnersLastRound[] = $winner;
                    }
                }
            }

            # Get Tickets for current Round
            $totalTickets = 0;
            $myTickets = 0;
            $ticketsRound = $this->mLotteryTkTbl->select(['round_idfs' => $roundID]);
            if(count($ticketsRound) > 0) {
                foreach($ticketsRound as $tk) {
                    $totalTickets+=$tk->tickets;
                    if($tk->user_idfs == $me->User_ID) {
                        $myTickets = $tk->tickets;
                    }
                }
            }

            # Calculate Users Winning Chance
            $chanceWin = 0;
            if($myTickets > 0 && $totalTickets > 0) {
                $chanceWin = number_format(100/($totalTickets/$myTickets),8,'.','\'');
            }

            # Attach additional information to round
            $currentRound->winners_last_round = $winnersLastRound;
            $currentRound->my_tickets = $myTickets;
            $currentRound->my_chance = $chanceWin;
            $currentRound->tickets = $totalTickets;

            # Print Round Info
            return new ViewModel($currentRound);
        }
    }
}
