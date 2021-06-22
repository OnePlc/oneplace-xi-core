<?php
/**
 * HallOfFameController.php - Hall of Fame Controller
 *
 * Main Controller for Faucet Hall of Fame Page
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rpc\HallOfFame;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;

class HallOfFameController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Stats Table
     *
     * @var TableGateway $mStatsTbl
     * @since 1.0.0
     */
    protected $mStatsTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mStatsTbl = new TableGateway('faucet_statistic', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    public function hallOfFameAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $employees = [];
        $employeesDB = $this->mUserTbl->select(['is_employee' => 1]);
        foreach($employeesDB as $emp) {
            $employees[] = (object)[
                'name' => $emp->username,'id' => $emp->User_ID,
                'title' => $emp->employee_title,'isAdmin' => ($emp->User_ID == '335874987' || $emp->User_ID == '335874988') ? 1 : 0
            ];
        }

        $topEarners = [];
        $statSel = new Select($this->mStatsTbl->getTable());
        $statSel->where(['stat-key' => 'topearners-daily']);
        $statSel->order('date DESC');
        $statSel->limit(1);
        $topEarnerStats = $this->mStatsTbl->selectWith($statSel);
        if(count($topEarnerStats) > 0) {
            $sKey = 'stat-data';
            $earnInfo = (array)json_decode($topEarnerStats->current()->$sKey);
            if(count($earnInfo) > 0) {
                $rank = 1;
                foreach($earnInfo as $earn) {
                    $userInfo = $this->mUserTbl->select(['User_ID' => $earn->id]);
                    if(count($userInfo) > 0) {
                        $userInfo = $userInfo->current();
                        $topEarners[] = (object)[
                            'name' => $userInfo->username,
                            'id' => $userInfo->User_ID,
                            'rank' => $rank,
                            'coins' => $earn->coins,
                        ];
                    }
                    $rank++;
                }
            }
        }

        $topPlayers = [];
        $statSel = new Select($this->mUserTbl->getTable());
        $statSel->order('xp_total DESC');
        $statSel->limit(5);
        $topPlayerStats = $this->mUserTbl->selectWith($statSel);
        if(count($topPlayerStats) > 0) {
            $rank = 1;
            foreach($topPlayerStats as $top) {
                $topPlayers[] = (object)[
                    'name' => $top->username,
                    'id' => $top->User_ID,
                    'rank' => $rank,
                    'xp' => (float)$top->xp_total,
                ];
                $rank++;
            }
        }

        $topGuilds = [];
        $statSel = new Select($this->mGuildTbl->getTable());
        $statSel->order('token_balance DESC');
        $statSel->limit(6);
        $topPlayerStats = $this->mGuildTbl->selectWith($statSel);
        if(count($topPlayerStats) > 0) {
            $rank = 1;
            foreach($topPlayerStats as $top) {
                $topGuilds[] = (object)[
                    'name' => $top->label,
                    'id' => $top->Guild_ID,
                    'icon' => $top->icon,
                    'rank' => $rank,
                ];
                $rank++;
            }
        }

        # Show Stats
        return new ViewModel([
            'date' => date('Y-m-d H:i:s'),
            'employees' => $employees,
            'top_guilds' => $topGuilds,
            'top_earners' => $topEarners,
            'top_players' => $topPlayers,
            'top_winners' => $topEarners,
        ]);
    }
}
