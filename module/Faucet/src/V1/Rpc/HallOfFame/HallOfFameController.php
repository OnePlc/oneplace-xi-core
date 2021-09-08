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
use Laminas\Db\Sql\Where;
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
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl;

     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Lottery Winners Table
     *
     * @var TableGateway $mLotteryWinTbl;

     * @since 1.0.0
     */
    protected $mLotteryWinTbl;

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
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mLotteryWinTbl = new TableGateway('faucet_lottery_winner', $mapper);
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
                            'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
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
                    'avatar' => ($top->avatar != '') ? $top->avatar : $top->username,
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
                    'avatar' => ($top->avatar != '') ? $top->avatar : $top->username,
                    'id' => $top->Guild_ID,
                    'icon' => $top->icon,
                    'is_vip' => $top->is_vip,
                    'rank' => $rank,
                ];
                $rank++;
            }
        }

        $detail = '';
        if(isset($_REQUEST['detail'])) {
            $detailSet = filter_var($_REQUEST['detail'], FILTER_SANITIZE_STRING);
            switch($detailSet) {
                case 'experience':
                case 'shortlinks':
                case 'mining':
                case 'referral':
                case 'contest':
                case 'cpumining':
                case 'offerwalls':
                case 'lottery':
                case 'faucet':
                    $detail = $detailSet;
                    break;
                default:
                    break;
            }
        }

        if($detail == "") {
            # Show Stats
            return new ViewModel([
                'date' => date('Y-m-d H:i:s'),
                'employees' => $employees,
                'top_guilds' => $topGuilds,
                'top_earners' => $topEarners,
                'top_players' => $topPlayers,
                'top_winners' => $topEarners,
            ]);
        } else {
            if($detail == 'experience') {
                $topPlayers = [];
                $statSel = new Select($this->mUserTbl->getTable());
                $statSel->order('xp_total DESC');
                //$statSel->limit(50);
                $topPlayerStats = $this->mUserTbl->selectWith($statSel);
                $rankMe = '-';
                if(count($topPlayerStats) > 0) {
                    $rank = 1;
                    foreach($topPlayerStats as $top) {
                        if($top->User_ID == $me->User_ID) {
                            $rankMe = $rank;
                        }
                        if($rank <= 50) {
                            $topPlayers[] = (object)[
                                'name' => $top->username,
                                'avatar' => ($top->avatar != '') ? $top->avatar : $top->username,
                                'id' => $top->User_ID,
                                'rank' => $rank,
                                'xp' => (float)$top->xp_total,
                                'xp_level' => (int)$top->xp_level,
                            ];
                        }
                        $rank++;
                    }
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topPlayers,
                        'all' => $topPlayers,
                    ],
                    'me_month' => ['xp_level' => (int)$me->xp_level,'rank' => $rankMe],
                    'me_all' => ['xp_level' => (int)$me->xp_level,'rank' => $rankMe]
                ]);
            }

            if($detail == 'referral') {
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'referral-toplist']);
                $statSel->limit(1);
                $statFound = $this->mStatsTbl->selectWith($statSel);

                $allTime = [];
                $month = [];

                $myRank = '> 50';

                if($statFound->count() > 0) {
                    $data = (array)$statFound->current();
                    $allTimeData = json_decode($data['stat-data']);
                    if(count($allTimeData) > 0) {
                        foreach($allTimeData as $dat) {
                            $userInfo = $this->mUserTbl->select(['User_ID' => $dat->id]);
                            if($userInfo->count() > 0) {
                                $userInfo = $userInfo->current();
                                $dat->avatar = ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username;
                                $allTime[] = $dat;
                            }
                        }
                    }
                }

                $myRefs = $this->mUserTbl->select(['ref_user_idfs' => $me->User_ID])->count();

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $month,
                        'all' => $allTime,
                    ],
                    'me_month' => ['count' => $myRefs, 'rank' => $myRank],
                    'me_all' => ['count' => $myRefs, 'rank' => $myRank]
                ]);
            }

            if($detail == 'contest') {
                $top3Level = [];
                $lvlSel = new Select($this->mUserTbl->getTable());
                $lvlSel->order('xp_total DESC');
                $lvlSel->limit(3);
                $top3User = $this->mUserTbl->selectWith($lvlSel);
                foreach($top3User as $toplvl) {
                    $top3Level[] = (object)[
                        'id' => $toplvl->User_ID,
                        'name' => $toplvl->username,
                        'xp_level' => $toplvl->xp_level,
                        'xp_total' => $toplvl->xp_total,
                    ];
                }

                $top3Sh = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'shmonth-top-09']);
                $statSel->limit(1);
                $statFound = $this->mStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $statFound = (array)$statFound->current();
                    $topList = json_decode($statFound['stat-data']);
                    $iCount = 0;
                    foreach($topList as $top) {
                        if($iCount == 3) {
                            break;
                        }
                        $top3Sh[] = $top;
                        $iCount++;
                    }
                }

                $top3SOf = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'ofmonth-top-09']);
                $statSel->limit(1);
                $statFound = $this->mStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $statFound = (array)$statFound->current();
                    $topList = json_decode($statFound['stat-data']);
                    $iCount = 0;
                    foreach($topList as $top) {
                        if($iCount == 3) {
                            break;
                        }
                        $top3SOf[] = $top;
                        $iCount++;
                    }
                }

                $top3Xmr = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'xmrshm-top-09']);
                $statSel->limit(1);
                $statFound = $this->mStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $statFound = (array)$statFound->current();
                    $topList = json_decode($statFound['stat-data']);
                    $iCount = 0;
                    foreach($topList as $top) {
                        if($iCount == 3) {
                            break;
                        }
                        $top3Xmr[] = $top;
                        $iCount++;
                    }
                }

                $top3Gpu = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'gpushm-top-09']);
                $statSel->limit(1);
                $statFound = $this->mStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $statFound = (array)$statFound->current();
                    $topList = json_decode($statFound['stat-data']);
                    $iCount = 0;
                    foreach($topList as $top) {
                        if($iCount == 3) {
                            break;
                        }
                        $top3Gpu[] = $top;
                        $iCount++;
                    }
                }

                $top3Guild = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'guild-top-09']);
                $statSel->limit(1);
                $statFound = $this->mStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $statFound = (array)$statFound->current();
                    $topList = json_decode($statFound['stat-data']);
                    $iCount = 0;
                    foreach($topList as $top) {
                        if($iCount == 3) {
                            break;
                        }
                        $top3Guild[] = $top;
                        $iCount++;
                    }
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_level' => $top3Level,
                    'player_shortlink' => $top3Sh,
                    'player_offerwall' => $top3SOf,
                    'player_cpushares' => $top3Xmr,
                    'player_gpushares' => $top3Gpu,
                    'guild_toplist' => $top3Guild
                ]);
            }

            if($detail == 'shortlinks') {
                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'shortlinks-total']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShorters[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'links' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'shortlinks-month']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                arsort($shortsByUser);
                $topShortersMonth = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShortersMonth[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'links' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersMonth,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['links' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['links' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'cpumining') {
                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'cpuminer-totalcoins']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShorters[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'coins' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-xmr-month']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                arsort($shortsByUser);
                $topShortersMonth = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShortersMonth[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'coins' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersMonth,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['coins' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['coins' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'mining') {
                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-etc-totalcoins']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totalcoins']);
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        if(!array_key_exists($shd->user_idfs,$shortsByUser)) {
                            $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                        } else {
                            $shortsByUser[$shd->user_idfs]+= (int)$shd->setting_value;
                        }
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShorters[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'coins' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-rvn-month']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-etc-month']);
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                arsort($shortsByUser);
                $topShortersMonth = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShortersMonth[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'coins' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersMonth,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['coins' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['coins' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'offerwalls') {
                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'totaloffers-count']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShorters[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'offers' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                $totalUserShorts = $this->mUserSetTbl->select(['setting_name' => 'totaloffers-month']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->setting_value;
                    }
                }
                arsort($shortsByUser);
                $topShortersMonth = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShortersMonth[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'offers' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersMonth,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['offers' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['offers' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'lottery') {
                $totalUserShorts = $this->mLotteryWinTbl->select();
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->coins_won] = (int)$shd->user_idfs;
                    }
                }
                krsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                $usersListed = [];
                foreach(array_keys($shortsByUser) as $claimWin) {
                    $claimUser = $shortsByUser[$claimWin];
                    if($claimUser == $me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $claimWin;
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            if(array_key_exists($claimUser, $usersListed)) {
                                continue;
                            }
                            $usersListed[$claimUser] = true;
                            $userInfo = $userInfo->current();
                            $topShorters[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'win' => (int)$claimWin,
                            ];
                        }

                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShorters,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['win' => $myShorts,'rank' => $myRank],
                    'me_all' => ['win' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'faucet') {
                $totalUserClaims = $this->mUserSetTbl->select(['setting_name' => 'faucet-claimtotal']);
                $claimsByUser = [];
                if(count($totalUserClaims) > 0) {
                    foreach($totalUserClaims as $cl) {
                        $claimsByUser[$cl->user_idfs] = (int)$cl->setting_value;
                    }
                }
                arsort($claimsByUser);
                $topClaimers = [];
                $rank = 1;
                $myRank = "-";
                $myClaims = 0;
                foreach(array_keys($claimsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRank = $rank;
                        $myClaims = $claimsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topClaimers[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rank,
                                'claims' => (int)$claimsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topClaimers,
                        'all' => $topClaimers,
                    ],
                    'me_month' => ['claims' => (int)$myClaims,'rank' => $myRank],
                    'me_all' => ['claims' => (int)$myClaims,'rank' => $myRank]
                ]);
            }
        }
    }
}
