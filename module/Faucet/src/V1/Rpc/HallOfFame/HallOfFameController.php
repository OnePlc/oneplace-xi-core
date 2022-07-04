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
     * User Stats Table V2
     *
     * @var TableGateway $mUsrStatsTbl
     * @since 1.0.0
     */
    protected $mUsrStatsTbl;

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
     * Achievement User Table
     *
     * @var TableGateway $mAchievDoneTbl
     * @since 1.0.0
     */
    protected $mAchievDoneTbl;

    /**
     * Achievement Table
     *
     * @var TableGateway $mAchievTbl
     * @since 1.0.0
     */
    protected $mAchievTbl;

    /**
     * Contest Winners Table
     *
     * @var TableGateway $mContestWinners
     * @since 1.0.0
     */
    protected $mContestWinners;

    /**
     * Contest Table
     *
     * @var TableGateway $mContest
     * @since 1.0.0
     */
    protected $mContest;

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
        $this->mAchievDoneTbl = new TableGateway('faucet_achievement_user', $mapper);
        $this->mAchievTbl = new TableGateway('faucet_achievement', $mapper);
        $this->mUsrStatsTbl = new TableGateway('user_faucet_stat', $mapper);
        $this->mContestWinners = new TableGateway('faucet_contest_winner', $mapper);
        $this->mContest = new TableGateway('faucet_contest', $mapper);
        $this->mContestRewards = new TableGateway('faucet_contest_reward', $mapper);

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
                    'avatar' => '',
                    'id' => $top->Guild_ID,
                    'icon' => $top->icon,
                    'emblem_shield' => $top->emblem_shield,
                    'emblem_icon' => $top->emblem_icon,
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
                case 'winners':
                case 'achievement':
                case 'cpumining':
                case 'offerwalls':
                case 'loyalty':
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
            if($detail == 'loyalty') {
                $topPlayers = [];

                $loyalSel = new Select($this->mUserTbl->getTable());
                $loyalSel->order('created_date ASC');
                $loyalWh = new Where();
                $loyalWh->equalTo('is_employee', 0);
                $loyalWh->greaterThan('last_action', date('Y-m-d H:i:s', time()-((24*3600)*14)));
                $loyalSel->where($loyalWh);
                $loyalSel->limit(50);

                $allTimeLoyal = $this->mUserTbl->selectWith($loyalSel);
                $rank = 1;
                foreach($allTimeLoyal as $top) {
                    $topPlayers[] = (object)[
                        'name' => $top->username,
                        'avatar' => ($top->avatar != '') ? $top->avatar : $top->username,
                        'id' => $top->User_ID,
                        'rank' => $rank,
                        'xp' => date('Y-m-d', strtotime($top->created_date)),
                        'xp_level' => (int)$top->xp_level,
                    ];
                    $rank++;
                }

                $myShorts = 0;
                $myRank = 0;
                $rankMe = 0;
                $topShorters = [];
                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShorters,
                        'all' => $topPlayers,
                    ],
                    'me_month' => ['xp_total' => $myShorts,'rank' => $myRank],
                    'me_all' => ['xp_level' => date('Y-m-d', strtotime($me->created_date)),'rank' => $rankMe]
                ]);
            }

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

                $totalUserShorts = $this->mUsrStatsTbl->select(['stat_key' => 'user-xp-'.date('n-Y', time())]);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->stat_data;
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

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShorters,
                        'all' => $topPlayers,
                    ],
                    'me_month' => ['xp_total' => $myShorts,'rank' => $myRank],
                    'me_all' => ['xp_level' => (int)$me->xp_level,'rank' => $rankMe]
                ]);
            }

            if($detail == 'referral') {
                $aAdAcounts = [335875860 => true, 335877074 => true,335876060 => true,335880700 => true,335875071 => true,335880436 => true,335890616 => true,335898589 => true];

                $totalUserShorts = $this->mUsrStatsTbl->select(['stat_key' => 'ref-count-'.date('n-Y', time())]);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        if(!array_key_exists($shd->user_idfs,$aAdAcounts)) {
                            $shortsByUser[$shd->user_idfs] = (int)$shd->stat_data;
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
                                'count' => (int)$shortsByUser[$claimUser],
                            ];
                        }

                    }
                    $rank++;
                }

                $totalUserShortsA = $this->mUsrStatsTbl->select(['stat_key' => 'ref-count-total']);
                $shortsByUserA = [];
                if(count($totalUserShortsA) > 0) {
                    foreach($totalUserShortsA as $shda) {
                        if(!array_key_exists($shda->user_idfs,$aAdAcounts)) {
                            $shortsByUserA[$shda->user_idfs] = (int)$shda->stat_data;
                        }
                    }
                }
                arsort($shortsByUserA);
                $topShortersA = [];
                $rankA = 1;
                $myRankA = "-";
                $myShortsA = 0;
                foreach(array_keys($shortsByUserA) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRankA = $rankA;
                        $myShortsA = $shortsByUserA[$claimUser];
                    }
                    if($rankA <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topShortersA[] = (object)[
                                'name' => $userInfo->username,
                                'avatar' => ($userInfo->avatar != '') ? $userInfo->avatar : $userInfo->username,
                                'id' => $userInfo->User_ID,
                                'rank' => $rankA,
                                'count' => (int)$shortsByUserA[$claimUser],
                            ];
                        }

                    }
                    $rankA++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShorters,
                        'all' => $topShortersA,
                    ],
                    'me_month' => ['count' => $myShorts, 'rank' => $myRank],
                    'me_all' => ['count' => $myShortsA, 'rank' => $myRankA]
                ]);
            }

            if($detail == 'winners') {
                $top3Level = [];

                $contestFilter = filter_var($_REQUEST['date'], FILTER_SANITIZE_NUMBER_INT);
                if(strlen($contestFilter) != 6) {
                    return new ApiProblemResponse(new ApiProblem(403, 'Invalid Filter'));
                }

                if($contestFilter < 202109) {
                    return new ApiProblemResponse(new ApiProblem(403, 'There was no contests before september 2021'));
                }

                if($contestFilter > date('Ym', time())) {
                    return new ApiProblemResponse(new ApiProblem(403, 'You cannot view Winners of the future'));
                }

                $year = substr($contestFilter, 0, 4);
                $month = substr($contestFilter, 4, 2);

                if($year < 2021) {
                    return new ApiProblemResponse(new ApiProblem(403, 'Invalid year'));
                }
                if($month < 1 || $month > 12) {
                    return new ApiProblemResponse(new ApiProblem(403, 'Invalid month'));
                }

                $winnerSel = new Select($this->mContestWinners->getTable());
                $winnerSel->where(['year' => $year, 'month' => $month]);
                $winnerSel->order(['contest_idfs', 'rank']);
                $winners = $this->mContestWinners->selectWith($winnerSel);

                $winnersByContest = [];

                foreach($winners as $win) {
                    if(!array_key_exists($win->contest_idfs,$winnersByContest)) {
                        $winnersByContest[$win->contest_idfs] = [];
                    }
                    if($win->contest_idfs == 1) {
                        $winnerInfo = $this->mGuildTbl->select(['Guild_ID' => $win->user_idfs]);
                        if($winnerInfo->count() > 0) {
                            $winnerInfo = $winnerInfo->current();

                            $winnersByContest[$win->contest_idfs][] = [
                                'rank' => $win->rank,
                                'name' => $winnerInfo->label,
                                'reward' => $win->reward
                            ];
                        }
                    } else {
                        $winnerInfo = $this->mUserTbl->select(['User_ID' => $win->user_idfs]);
                        if($winnerInfo->count() > 0) {
                            $winnerInfo = $winnerInfo->current();

                            $winnersByContest[$win->contest_idfs][] = [
                                'rank' => $win->rank,
                                'name' => $winnerInfo->username,
                                'reward' => $win->reward
                            ];
                        }
                    }
                }

                $contestWinners = [];
                foreach(array_keys($winnersByContest) as $contestId) {
                    $contestInfo = $this->mContest->select(['Contest_ID' => $contestId]);
                    if($contestInfo->count() > 0) {
                        $contestWinners[] = [
                            'name' => $contestInfo->current()->contest_name,
                            'winners' => $winnersByContest[$contestId]
                        ];
                    }
                }

                return [
                    'winners' => $contestWinners,
                    'title' => $month.' '.$year
                ];
            }

            if($detail == 'contest') {
                $conMonth = date('n', time());
                $conYear = date('Y', time());

                $myContestStats = [];
                $top10Contest = ['contest-10' => [],'contest-5' => [],'contest-6' => [],'contest-11' => [],'contest-12' => [],'contest-13' => [],'contest-1' => []];

                /**
                 * Shortlinks
                 */
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'shdone-m-'.date('n-Y',time())]);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top10Contest['contest-10'][] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => $top3ShById[$topId],
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 10) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $myStatSel = new Select($this->mUsrStatsTbl->getTable());
                $myStatSel->where(['stat_key' => 'shdone-m-'.date('n-Y', $date), 'user_idfs' => $me->User_ID]);
                $myStat = $this->mUsrStatsTbl->selectWith($myStatSel);
                if($myStat->count() > 0) {
                    $myContestStats[10] = $myStat->current()->stat_data;
                } else {
                    $myContestStats[10] = 0;
                }

                /**
                 * Daily Tasks
                 */
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'user-dailys-m-'.date('n-Y',time())]);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top10Contest['contest-13'][] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => $top3ShById[$topId],
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 10) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $myStatSel = new Select($this->mUsrStatsTbl->getTable());
                $myStatSel->where(['stat_key' => 'user-dailys-m-'.date('n-Y', $date), 'user_idfs' => $me->User_ID]);
                $myStat = $this->mUsrStatsTbl->selectWith($myStatSel);
                if($myStat->count() > 0) {
                    $myContestStats[13] = $myStat->current()->stat_data;
                } else {
                    $myContestStats[13] = 0;
                }

                /**
                 * Offerwalls BIG
                 */
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'user-offerbig-m-'.date('n-Y',time())]);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top10Contest['contest-11'][] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => $top3ShById[$topId],
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 10) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $myStatSel = new Select($this->mUsrStatsTbl->getTable());
                $myStatSel->where(['stat_key' => 'user-offerbig-m-'.date('n-Y', $date), 'user_idfs' => $me->User_ID]);
                $myStat = $this->mUsrStatsTbl->selectWith($myStatSel);
                if($myStat->count() > 0) {
                    $myContestStats[11] = $myStat->current()->stat_data;
                } else {
                    $myContestStats[11] = 0;
                }

                /**
                 * Offerwalls Small
                 */
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'user-offersmall-m-'.date('n-Y',time())]);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top10Contest['contest-12'][] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => $top3ShById[$topId],
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 10) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $myStatSel = new Select($this->mUsrStatsTbl->getTable());
                $myStatSel->where(['stat_key' => 'user-offersmall-m-'.date('n-Y', $date), 'user_idfs' => $me->User_ID]);
                $myStat = $this->mUsrStatsTbl->selectWith($myStatSel);
                if($myStat->count() > 0) {
                    $myContestStats[12] = $myStat->current()->stat_data;
                } else {
                    $myContestStats[12] = 0;
                }

                // nano-coin-m-rvn-5-2022
                /**
                 * GPU Miners
                 */
                $statWh = new Where();
                $statWh->NEST
                    ->like('stat_key', 'nano-coin-m-rvn-'.date('n-Y',time()))
                    ->OR
                    ->like('stat_key', 'nano-coin-m-etc-'.date('n-Y',time()))
                    ->UNNEST;
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where($statWh);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        if(!array_key_exists($statUser->user_idfs, $top3ShById)) {
                            $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                        } else {
                            $top3ShById[$statUser->user_idfs] += $statUser->stat_data;
                        }
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top10Contest['contest-5'][] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => round($top3ShById[$topId]),
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 10) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }
                $statWh = new Where();
                $statWh->NEST
                    ->like('stat_key', 'nano-coin-m-rvn-'.date('n-Y',$date))
                    ->OR
                    ->like('stat_key', 'nano-coin-m-etc-'.date('n-Y',$date))
                    ->UNNEST;
                $statWh->equalTo('user_idfs', $me->User_ID);
                $myStatSel = new Select($this->mUsrStatsTbl->getTable());
                $myStatSel->where($statWh);
                $myStat = $this->mUsrStatsTbl->selectWith($myStatSel);
                if($myStat->count() > 0) {
                    $total = 0;
                    foreach($myStat as $ms) {
                        $total += (int)$ms->stat_data;
                    }
                    $myContestStats[5] = $total;
                } else {
                    $myContestStats[5] = 0;
                }


                /**
                 * CPU Miners
                 */
                $statWh = new Where();
                $statWh->like('stat_key', 'nano-coin-m-xmr-'.date('n-Y',time()));
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where($statWh);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top10Contest['contest-6'][] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => round($top3ShById[$topId]),
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 10) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $myStatSel = new Select($this->mUsrStatsTbl->getTable());
                $myStatSel->where(['stat_key' => 'nano-coin-m-'.date('n-Y', $date), 'user_idfs' => $me->User_ID]);
                $myStat = $this->mUsrStatsTbl->selectWith($myStatSel);
                if($myStat->count() > 0) {
                    $myContestStats[6] = $myStat->current()->stat_data;
                } else {
                    $myContestStats[6] = 0;
                }

                /**
                 * Top Guilds
                 */
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'guild-top-'.date('m', time())]);
                $statSel->limit(1);
                $statFound = $this->mStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $statFound = (array)$statFound->current();
                    $topList = json_decode($statFound['stat-data']);
                    $iCount = 0;
                    foreach($topList as $top) {
                        if($iCount == 5) {
                            break;
                        }
                        $gInfo = $this->mGuildTbl->select(['Guild_ID' => $top->id]);
                        if($gInfo->count() > 0) {
                            $gInfo = $gInfo->current();
                            $top10Contest['contest-1'][] = (object)[
                                'id' => $top->id,
                                'rank' => ($iCount+1),
                                'count' => round($top->count),
                                'name' => $gInfo->label,
                                'shield' => $gInfo->emblem_shield,
                                'icon' => $gInfo->emblem_icon,
                            ];
                            $iCount++;
                        }
                    }
                }

                $conSel = new Select($this->mContest->getTable());
                $conSel->join(['fcr' => 'faucet_contest_reward'], 'fcr.contest_idfs = faucet_contest.Contest_ID');
                $conSel->where(['fcr.month' => $conMonth, 'fcr.year' => $conYear]);
                $conSel->group('faucet_contest.Contest_ID');

                $activeContests = $this->mContest->selectWith($conSel);

                $contestsData = [];
                foreach($activeContests as $con) {
                    $rewSel = new Select($this->mContestRewards->getTable());
                    $rewSel->where(['contest_idfs' => $con->Contest_ID, 'month' => $conMonth, 'year' => $conYear]);
                    $rewSel->order('rank ASC');

                    $rewards = $this->mContestRewards->selectWith($rewSel);
                    $contestRewards = [];
                    foreach($rewards as $rew) {
                        $contestRewards[] = [
                            'rank' => $rew->rank,
                            'amount' => $rew->amount
                        ];
                    }
                    $winners = [];
                    if(array_key_exists('contest-'.$con->Contest_ID,$top10Contest)) {
                        $winners = $top10Contest['contest-'.$con->Contest_ID];
                    }
                    $me = 0;
                    if(array_key_exists($con->Contest_ID,$myContestStats)) {
                        $me = $myContestStats[$con->Contest_ID];
                    }
                    $contestsData[] = [
                        'id' => $con->Contest_ID,
                        'name' => $con->contest_name,
                        'type' => $con->contest_type,
                        'reward' => $contestRewards,
                        'winners' => $winners,
                        'me' => $me
                    ];
                }

                $viewData = [
                    'contest' => $contestsData,
                    'date' => date('Y-m-d H:i:s'),
                    'date_end' => date('Y-m-t', time()).' 23:59:59',
                ];
                
                $hasMessage = $this->mSecTools->getCoreSetting('faucet-contest-msg-content');
                if($hasMessage) {
                    $message = $hasMessage;
                    $messageType = $this->mSecTools->getCoreSetting('faucet-contest-msg-level');
                    $xpReq = $this->mSecTools->getCoreSetting('faucet-contest-msg-xplevel');
                    $addMsg = false;
                    if($xpReq) {
                        if($me->xp_level >= $xpReq) {
                            $addMsg = true;
                        }
                    } else {
                        $addMsg = true;
                    }

                    if($addMsg && strlen($message) > 0) {
                        $viewData['message'] = [
                            'type' => $messageType,
                            'message' => $message
                        ];
                    }
                }

                return $viewData;
            }

            if($detail == 'contestdep') {
                $skipList = [
                    335880436 => true,
                    335875071 => true,
                    335874987 => true,
                    335902227 => true
                ];
                $top3Level = [];
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'user-xp-'.date('n-Y', time())]);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top3Level[] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => $top3ShById[$topId],
                                'xp_level' => $top3ShById[$topId],
                                //'xp_level' => $topInfo->xp_level,
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 3) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $top3Sh = [];
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'shdone-m-'.date('n-Y',time())]);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top3Sh[] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => $top3ShById[$topId],
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 3) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $top3SOf = [];
                $statSel = new Select($this->mUsrStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'ofdone-m-'.date('n-Y',time())]);
                $statFound = $this->mUsrStatsTbl->selectWith($statSel);
                if($statFound->count() > 0) {
                    $top3ShById = [];
                    foreach($statFound as $statUser) {
                        $top3ShById[$statUser->user_idfs] = $statUser->stat_data;
                    }
                    arsort($top3ShById);
                    $iCount = 0;
                    foreach(array_keys($top3ShById) as $topId) {
                        $topInfo = $this->mUserTbl->select(['User_ID' => $topId]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top3SOf[] = (object)[
                                'id' => $topId,
                                'rank' => ($iCount+1),
                                'count' => $top3ShById[$topId],
                                'name' => $topInfo->username,
                                'avatar' => ($topInfo->avatar == '') ? $topInfo->username : $topInfo->avatar,
                            ];
                            if($iCount == 3) {
                                break;
                            }
                            $iCount++;
                        }
                    }
                }

                $top3Xmr = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'concpu-'.date('n-Y', time())]);
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
                        if(array_key_exists($top->id,$skipList)) {
                            continue;
                        }
                        $topInfo = $this->mUserTbl->select(['User_ID' => $top->id]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top->name = $topInfo->username;
                            $top->count = $top->coins;
                        }
                        $top3Xmr[] = $top;
                        $iCount++;
                    }
                }

                $top3Gpu = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'congpu-'.date('n-Y', time())]);
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
                        if(array_key_exists($top->id,$skipList)) {
                            continue;
                        }
                        $topInfo = $this->mUserTbl->select(['User_ID' => $top->id]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top->name = $topInfo->username;
                            $top->count = $top->coins;
                        }
                        $top3Gpu[] = $top;
                        $iCount++;
                    }
                }

                $top3Ref = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'conrefs-'.date('n-Y', time())]);
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
                        if(array_key_exists($top->id,$skipList)) {
                            continue;
                        }
                        $topInfo = $this->mUserTbl->select(['User_ID' => $top->id]);
                        if($topInfo->count() > 0) {
                            $topInfo = $topInfo->current();
                            $top->name = $topInfo->username;
                            $top->count = $top->refs;
                        }
                        $top3Ref[] = $top;
                        $iCount++;
                    }
                }

                $top3Guild = [];
                $statSel = new Select($this->mStatsTbl->getTable());
                $statSel->order('date DESC');
                $statSel->where(['stat-key' => 'guild-top-'.date('m', time())]);
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

                $top3Vets = [];
                $loyalSel = new Select($this->mUserTbl->getTable());
                $loyalSel->order('created_date ASC');
                $loyalWh = new Where();
                $loyalWh->equalTo('is_employee', 0);
                $loyalWh->greaterThan('last_action', date('Y-m-d H:i:s', time()-((24*3600)*14)));
                $loyalSel->where($loyalWh);
                $loyalSel->limit(50);

                $allTimeLoyal = $this->mUserTbl->selectWith($loyalSel);
                foreach($allTimeLoyal as $top) {
                    $top3Vets[] = (object)[
                        'name' => $top->username,
                        'id' => $top->User_ID,
                        'count' => date('Y-m-d', strtotime($top->created_date)),
                    ];
                }

                $viewData = [
                    'date' => date('Y-m-d H:i:s'),
                    'date_end' => date('Y-m-t', time()).' 23:59:59',
                    'player_level' => $top3Level,
                    'player_shortlink' => $top3Sh,
                    'player_offerwall' => $top3SOf,
                    'player_cpushares' => $top3Xmr,
                    'player_gpushares' => $top3Gpu,
                    'player_referral' => $top3Ref,
                    'player_veteran' => $top3Vets,
                    'guild_toplist' => $top3Guild
                ];

                $hasMessage = $this->mSecTools->getCoreSetting('faucet-contest-msg-content');
                if($hasMessage) {
                    $message = $hasMessage;
                    $messageType = $this->mSecTools->getCoreSetting('faucet-contest-msg-level');
                    $xpReq = $this->mSecTools->getCoreSetting('faucet-contest-msg-xplevel');
                    $addMsg = false;
                    if($xpReq) {
                        if($me->xp_level >= $xpReq) {
                            $addMsg = true;
                        }
                    } else {
                        $addMsg = true;
                    }

                    if($addMsg && strlen($message) > 0) {
                        $viewData['message'] = [
                            'type' => $messageType,
                            'message' => $message
                        ];
                    }
                }

                # Show Stats
                return new ViewModel($viewData);
            }

            if($detail == 'achievement') {
                $achievRewards = [];
                $achievs = $this->mAchievTbl->select();
                foreach($achievs as $ac) {
                    $achievRewards[$ac->Achievement_ID] = $ac->reward;
                }
                $achievSel = new Select($this->mAchievDoneTbl->getTable());
                $achievSel->join(['u' => 'user'],'faucet_achievement_user.user_idfs = u.User_ID');
                $achievsDone = $this->mAchievDoneTbl->selectWith($achievSel);
                $achievPointsByUser = [];
                $userNames = [];
                $userAvatars = [];
                if($achievsDone->count() > 0) {
                    foreach($achievsDone as $achiev) {
                        if(!array_key_exists($achiev->user_idfs, $achievPointsByUser)) {
                            $achievPointsByUser[$achiev->user_idfs] = 0;
                            $userNames[$achiev->user_idfs] = $achiev->username;
                            $userAvatars[$achiev->user_idfs] = ($achiev->avatar != '') ? $achiev->avatar : $achiev->username;
                        }
                        if(array_key_exists($achiev->achievement_idfs,$achievRewards)) {
                            $achievPointsByUser[$achiev->user_idfs]+=$achievRewards[$achiev->achievement_idfs];
                        }
                    }
                }
                arsort($achievPointsByUser);
                $topAchievUsers = [];
                $rank = 1;
                $myRank = "-";
                $myAchievs = 0;

                foreach(array_keys($achievPointsByUser) as $acUser) {
                    if($rank <= 50) {
                        if($acUser == $me->User_ID) {
                            $myRank = $rank;
                            $myAchievs = (int)$achievPointsByUser[$acUser];
                        }

                        $topAchievUsers[] = (object)[
                            'name' => $userNames[$acUser],
                            'id' => $acUser,
                            'rank' => $rank,
                            'avatar' => $userAvatars[$acUser],
                            'points' => (int)$achievPointsByUser[$acUser],
                        ];
                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topAchievUsers,
                        'all' => $topAchievUsers,
                    ],
                    'me_month' => ['points' => $myAchievs,'rank' => $myRank],
                    'me_all' => ['points' => $myAchievs,'rank' => $myRank]
                ]);
            }

            if($detail == 'shortlinks') {
                $totalUserShorts = $this->mUsrStatsTbl->select(['stat_key' => 'shdone-total']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->stat_data;
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

                $totalUserShorts = $this->mUsrStatsTbl->select(['stat_key' => 'shdone-m-'.date('n-Y',time())]);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->stat_data;
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
                $totalUserShorts = $this->mUsrStatsTbl->select(['stat_key' => 'ofdone-total']);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->stat_data;
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

                $totalUserShorts = $this->mUsrStatsTbl->select(['stat_key' => 'ofdone-m-'.date('n-Y',time())]);
                $shortsByUser = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser[$shd->user_idfs] = (int)$shd->stat_data;
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
