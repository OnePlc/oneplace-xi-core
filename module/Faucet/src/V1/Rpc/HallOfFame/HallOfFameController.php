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
     * @var TableGateway
     */
    private $mContestRewards;
    /**
     * @var TableGateway
     */
    private $mGuildStatsTbl;

    /**
     * @var TableGateway
     */
    private $mTokenTbl;

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
        $this->mGuildStatsTbl = new TableGateway('faucet_guild_statistic', $mapper);
        $this->mAchievDoneTbl = new TableGateway('faucet_achievement_user', $mapper);
        $this->mAchievTbl = new TableGateway('faucet_achievement', $mapper);
        $this->mUsrStatsTbl = new TableGateway('user_faucet_stat', $mapper);
        $this->mContestWinners = new TableGateway('faucet_contest_winner', $mapper);
        $this->mContest = new TableGateway('faucet_contest', $mapper);
        $this->mContestRewards = new TableGateway('faucet_contest_reward', $mapper);
        $this->mTokenTbl = new TableGateway('faucet_tokenbuy', $mapper);

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

        /**
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
        } **/

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
                case 'token':
                case 'rpsgame':
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
                //'employees' => $employees,
                'top_guilds' => $topGuilds,
                //'top_earners' => $topEarners,
                //'top_players' => $topPlayers,
                //'top_winners' => $topEarners,
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
                                'xp' => (int)$shortsByUser[$claimUser],
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
                    'me_month' => ['xp' => $myShorts,'rank' => $myRank],
                    'me_all' => ['xp_level' => (int)$me->xp_level,'rank' => $rankMe]
                ]);
            }

            if($detail == 'referral') {
                $aAdAcounts = [335875860 => true, 335877074 => true,335876060 => true,335880700 => true,335875071 => true,335880436 => true,335890616 => true,335898589 => true];

                $totalUserShorts = $this->mUsrStatsTbl->select(['stat_key' => 'user-referral-m-'.date('n-Y', time())]);
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

                $totalUserShortsA = $this->mUsrStatsTbl->select(['stat_key' => 'user-referral-total']);
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
                $bannedUsersById = [];
                $bannedUsers = $this->mUserSetTbl->select(['setting_name' => 'user-tempban']);
                foreach($bannedUsers as $bannedUser) {
                    if(!in_array($bannedUser->user_idfs,$bannedUsersById)) {
                        $bannedUsersById[] = $bannedUser->user_idfs;
                    }
                }

                $conMonth = date('n', time());
                $conYear = date('Y', time());

                $myContestStats = [];
                $myContestRanks = [];
                $top10Contest = ['contest-10' => [],'contest-5' => [],'contest-6' => [],'contest-11' => [],'contest-12' => [],'contest-13' => [],'contest-1' => [],'contest-14' => [],'contest-15' => []];

                /**
                 * Shortlinks
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $totalSel->where(['stat_key' => 'user-shortlink-m-'.date('n-Y', time())]);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'xp_level' => $shd->xp_level,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'count' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-10'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-10'] = $myShortsM;
                $myContestRanks['contest-10'] = $myRankM;

                /**
                 * Daily Tasks
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $totalSel->where(['stat_key' => 'user-dailys-m-'.date('n-Y', time())]);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'xp_level' => $shd->xp_level,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'count' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-13'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-13'] = $myShortsM;
                $myContestRanks['contest-13'] = $myRankM;

                /**
                 * Offerwalls BIG
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $totalSel->where(['stat_key' => 'user-offerbig-m-'.date('n-Y', time())]);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'xp_level' => $shd->xp_level,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'count' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankBM = "-";
                $myShortsBM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankBM = $rank;
                        $myShortsBM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-11'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-11'] = $myShortsBM;
                $myContestRanks['contest-11'] = $myRankBM;

                /**
                 * Offerwalls Small
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $totalSel->where(['stat_key' => 'user-offersmall-m-'.date('n-Y', time())]);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'xp_level' => $shd->xp_level,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'count' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-12'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-12'] = $myShortsM;
                $myContestRanks['contest-12'] = $myRankM;

                /**
                 * Offerwalls Tiny
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $totalSel->where(['stat_key' => 'user-offertiny-m-'.date('n-Y', time())]);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'xp_level' => $shd->xp_level,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'count' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-14'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-14'] = $myShortsM;
                $myContestRanks['contest-14'] = $myRankM;

                /**
                 * Offerwalls Medium
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $totalSel->where(['stat_key' => 'user-offermed-m-'.date('n-Y', time())]);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'xp_level' => $shd->xp_level,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'count' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-15'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-15'] = $myShortsM;
                $myContestRanks['contest-15'] = $myRankM;

                // nano-coin-m-rvn-5-2022
                /**
                 * GPU Miners
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $statWh = new Where();
                $statWh->NEST
                    ->like('stat_key', 'user-nano-etc-coin-m-'.date('n-Y',time()))
                    ->OR
                    ->like('stat_key', 'user-nano-rvn-coin-m-'.date('n-Y',time()))
                    ->UNNEST;
                $totalSel->where($statWh);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        if(!array_key_exists('user-'.$shd->user_idfs, $shortsByUser)) {
                            $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                            $infoByUserId['user-'.$shd->user_idfs] = (object)[
                                'name' => $shd->username,
                                'xp_level' => $shd->xp_level,
                                'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                                'id' => $shd->user_idfs,
                                'count' => (int)$shd->stat_data,
                            ];
                        } else {
                            $shortsByUser['user-'.$shd->user_idfs]+= (int)$shd->stat_data.$cLevel;
                            $infoByUserId['user-'.$shd->user_idfs]->count += (int)$shd->stat_data;
                        }

                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-5'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-5'] = $myShortsM;
                $myContestRanks['contest-5'] = $myRankM;

                /**
                 * CPU Miners
                 */
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar','xp_level']);
                $totalSel->where(['stat_key' => 'user-nano-xmr-coin-m-'.date('n-Y', time())]);
                $totalSel->order('u.xp_level DESC');
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        // skip banned users
                        if(in_array($shd->user_idfs, $bannedUsersById)) {
                            continue;
                        }
                        $cLevel = $shd->xp_level;
                        if($cLevel < 10) {
                            $cLevel = '0'.$cLevel;
                        }
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data.$cLevel;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'xp_level' => $shd->xp_level,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'count' => (int)$shd->stat_data,
                        ];
                    }
                }
                arsort($shortsByUser);
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $infoByUserId[$claimUser]->count;
                    }
                    if($rank <= 10) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $top10Contest['contest-6'][] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $myContestStats['contest-6'] = $myShortsM;
                $myContestRanks['contest-6'] = $myRankM;

                /**
                 * Top Guilds
                 */
                $statSel = new Select($this->mGuildStatsTbl->getTable());
                $statSel->join(['fg' => 'faucet_guild'],'fg.Guild_ID = faucet_guild_statistic.guild_idfs', ['label', 'emblem_shield', 'emblem_icon']);
                $statSel->order('date DESC');
                $statSel->where(['stat_key' => 'guild-weeklys-m-'.date('n-Y', time())]);
                $statFound = $this->mStatsTbl->selectWith($statSel);

                $tasksByGuild = [];
                $guildInfoByGuildId = [];
                foreach($statFound as $guild) {
                    $tasksByGuild['guild-'.$guild->guild_idfs] = (int)$guild->data;
                    $guildInfoByGuildId['guild-'.$guild->guild_idfs] = (object)[
                        'id' => $guild->guild_idfs,
                        'name' => $guild->label,
                        'shield' => $guild->emblem_shield,
                        'icon' => $guild->emblem_icon,
                        'count' => (int)$guild->data
                    ];
                }

                arsort($tasksByGuild);

                $rank = 1;
                foreach($tasksByGuild as $gKey => $gCount) {
                    if($rank == 5) {
                        break;
                    }
                    $guildInfoByGuildId[$gKey]->rank = $rank;
                    $top10Contest['contest-1'][] = $guildInfoByGuildId[$gKey];
                    $rank++;
                }

                /**
                if($statFound->count() > 0) {
                    $statFound = (array)$statFound->current();
                    $topList = json_decode($statFound['stat-data']);

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
                } **/

                $conSel = new Select($this->mContest->getTable());
                $conSel->join(['fcr' => 'faucet_contest_reward'], 'fcr.contest_idfs = faucet_contest.Contest_ID');
                $conSel->where(['fcr.month' => $conMonth, 'fcr.year' => $conYear]);
                $conSel->order('faucet_contest.sort_id');
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
                    if(array_key_exists('contest-'.$con->Contest_ID,$myContestStats)) {
                        $me = $myContestStats['contest-'.$con->Contest_ID];
                    }
                    $meRank = '-';
                    if(array_key_exists('contest-'.$con->Contest_ID,$myContestRanks)) {
                        $meRank = $myContestRanks['contest-'.$con->Contest_ID];
                    }
                    $contestsData[] = [
                        'id' => $con->Contest_ID,
                        'name' => $con->contest_name,
                        'type' => $con->contest_type,
                        'reward' => $contestRewards,
                        'winners' => $winners,
                        'me' => $me,
                        'me_rank' => $meRank,
                        'unit_label' => $con->unit_label,
                        'earn_label' => $con->earn_label
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

            if($detail == 'rpsgame') {
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-rps-game-total']);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'games' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShorters[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-rps-game-m-'.date('n-Y', time())]);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'games' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShortersM = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShortersM[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersM,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['games' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['games' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'shortlinks') {
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-shortlink-total']);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'links' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShorters[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-shortlink-m-'.date('n-Y', time())]);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'links' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShortersM = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShortersM[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersM,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['links' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['links' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'token') {

                $tkSel = new Select($this->mTokenTbl->getTable());
                $tkSel->join(['u' => 'user'],'u.User_ID = faucet_tokenbuy.user_idfs', ['username', 'avatar']);
                $tkSel->where(['sent' => 1]);
                $tokenHolders = $this->mTokenTbl->selectWith($tkSel);
                $tokensByUserId = [];
                $tokenUserInfoByUserId = [];
                foreach($tokenHolders as $tk) {
                    $tkKey = 'user-'.$tk->user_idfs;
                    if(!array_key_exists($tkKey, $tokensByUserId)) {
                        $tokensByUserId[$tkKey] = 0;
                    }
                    $tokensByUserId[$tkKey]+=$tk->amount;
                    if(!array_key_exists($tkKey,$tokenUserInfoByUserId)) {
                        $tokenUserInfoByUserId[$tkKey] = (object)[
                            'id' => $tk->user_idfs,
                            'name' => $tk->username,
                            'avatar' => ($tk->avatar != '') ? $tk->avatar : $tk->username,
                            'tokens' => 0,
                        ];
                    }
                }

                arsort($tokensByUserId);
                $rank = 1;
                $topShorters = [];
                $topShortersM = [];

                $myRank = 0;
                $myRankM = 0;
                $myShorts = 0;
                $myShortsM = 0;
                foreach($tokensByUserId as $tkKey => $tkUser) {
                    if($rank <= 50) {
                        $top = $tokenUserInfoByUserId[$tkKey];
                        $top->rank = $rank;
                        $top->tokens = $tkUser;
                        $topShorters[] = $top;
                    }
                    if($tkKey == 'user-'.$me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $tkUser;
                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersM,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['coins' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['coins' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'cpumining') {
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-nano-xmr-coin-total']);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'coins' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShorters[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-nano-xmr-coin-m-'.date('n-Y', time())]);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'coins' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShortersM = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShortersM[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersM,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['coins' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['coins' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'mining') {
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $gWh = new Where();
                $gWh->NEST
                    ->like('stat_key', 'user-nano-etc-coin-total')
                    ->OR
                    ->like('stat_key', 'user-nano-rvn-coin-total')
                    ->UNNEST;
                $totalSel->where($gWh);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        if(!array_key_exists('user-'.$shd->user_idfs, $shortsByUser)) {
                            $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                            $infoByUserId['user-'.$shd->user_idfs] = (object)[
                                'name' => $shd->username,
                                'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                                'id' => $shd->user_idfs,
                                'coins' => (int)$shd->stat_data
                            ];
                        } else {
                            $shortsByUser['user-'.$shd->user_idfs] += (int)$shd->stat_data;
                            $infoByUserId['user-'.$shd->user_idfs]->coins += (int)$shd->stat_data;
                        }
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShorters[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $gWh = new Where();
                $gWh->NEST
                    ->like('stat_key', 'user-nano-etc-coin-m-'.date('n-Y',time()))
                    ->OR
                    ->like('stat_key', 'user-nano-rvn-coin-m-'.date('n-Y',time()))
                    ->UNNEST;
                $totalSel->where($gWh);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        if(!array_key_exists('user-'.$shd->user_idfs, $shortsByUser)) {
                            $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                            $infoByUserId['user-'.$shd->user_idfs] = (object)[
                                'name' => $shd->username,
                                'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                                'id' => $shd->user_idfs,
                                'coins' => (int)$shd->stat_data
                            ];
                        } else {
                            $shortsByUser['user-'.$shd->user_idfs] += (int)$shd->stat_data;
                            $infoByUserId['user-'.$shd->user_idfs]->coins += (int)$shd->stat_data;
                        }
                    }
                }
                arsort($shortsByUser);
                $topShortersM = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShortersM[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersM,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['coins' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['coins' => (int)$myShorts,'rank' => $myRank]
                ]);
            }

            if($detail == 'offerwalls') {
                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-offerearned-total']);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'coins' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShorters = [];
                $rank = 1;
                $myRank = "-";
                $myShorts = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRank = $rank;
                        $myShorts = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShorters[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                $totalSel = new Select($this->mUsrStatsTbl->getTable());
                $totalSel->join(['u' => 'user'],'u.User_ID = user_faucet_stat.user_idfs', ['username','avatar']);
                $totalSel->where(['stat_key' => 'user-offerearned-m-'.date('n-Y', time())]);
                $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
                $shortsByUser = [];
                $infoByUserId = [];
                if(count($totalUserShorts) > 0) {
                    foreach($totalUserShorts as $shd) {
                        $shortsByUser['user-'.$shd->user_idfs] = (int)$shd->stat_data;
                        $infoByUserId['user-'.$shd->user_idfs] = (object)[
                            'name' => $shd->username,
                            'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                            'id' => $shd->user_idfs,
                            'coins' => (int)$shd->stat_data
                        ];
                    }
                }
                arsort($shortsByUser);
                $topShortersM = [];
                $rank = 1;
                $myRankM = "-";
                $myShortsM = 0;
                foreach(array_keys($shortsByUser) as $claimUser) {
                    if($claimUser == 'user-'.$me->User_ID) {
                        $myRankM = $rank;
                        $myShortsM = $shortsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        if(array_key_exists($claimUser,$infoByUserId)) {
                            $infoByUserId[$claimUser]->rank = $rank;
                            $topShortersM[] = $infoByUserId[$claimUser];
                        }
                    }
                    $rank++;
                }

                # Show Stats
                return new ViewModel([
                    'date' => date('Y-m-d H:i:s'),
                    'player_list' => [
                        'month' => $topShortersM,
                        'all' => $topShorters,
                    ],
                    'me_month' => ['coins' => $myShortsM,'rank' => $myRankM],
                    'me_all' => ['coins' => (int)$myShorts,'rank' => $myRank]
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
                $totalUserClaims = $this->mUsrStatsTbl->select(['stat_key' => 'user-claims-total']);
                $claimsByUser = [];
                if(count($totalUserClaims) > 0) {
                    foreach($totalUserClaims as $cl) {
                        $claimsByUser[$cl->user_idfs] = (int)$cl->stat_data;
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

                $totalUserClaims = $this->mUsrStatsTbl->select(['stat_key' => 'user-claims-m-'.date('n-Y', time())]);
                $claimsByUser = [];
                if(count($totalUserClaims) > 0) {
                    foreach($totalUserClaims as $cl) {
                        $claimsByUser[$cl->user_idfs] = (int)$cl->stat_data;
                    }
                }
                arsort($claimsByUser);
                $topClaimersM = [];
                $rank = 1;
                $myRankM = "-";
                $myClaimsM = 0;
                foreach(array_keys($claimsByUser) as $claimUser) {
                    if($claimUser == $me->User_ID) {
                        $myRankM = $rank;
                        $myClaimsM = $claimsByUser[$claimUser];
                    }
                    if($rank <= 50) {
                        $userInfo = $this->mUserTbl->select(['User_ID' => $claimUser]);
                        if(count($userInfo) > 0) {
                            $userInfo = $userInfo->current();
                            $topClaimersM[] = (object)[
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
                        'month' => $topClaimersM,
                        'all' => $topClaimers,
                    ],
                    'me_month' => ['claims' => (int)$myClaimsM,'rank' => $myRankM],
                    'me_all' => ['claims' => (int)$myClaims,'rank' => $myRank]
                ]);
            }
        }
    }
}
