<?php
namespace Backend\V1\Rest\Contest;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class ContestResource extends AbstractResourceListener
{
    /**
     * Contest Table
     *
     * @var TableGateway $mContestTbl
     * @since 1.0.0
     */
    protected $mContestTbl;

    /**
     * Contest Winner Table
     *
     * @var TableGateway $mWinnerTbl
     * @since 1.0.0
     */
    protected $mWinnerTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Contest Rewards Table
     *
     * @var TableGateway $mRewardTbl
     * @since 1.0.0
     */
    protected $mRewardTbl;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Inbox Table
     *
     * @var TableGateway $mInboxTbl
     * @since 1.0.0
     */
    protected $mInboxTbl;

    /**
     * User Inbox Attachment Table
     *
     * @var TableGateway $mInboxAttachTbl
     * @since 1.0.0
     */
    protected $mInboxAttachTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * User Stats Table V2
     *
     * @var TableGateway $mUsrStatsTbl
     * @since 1.0.0
     */
    protected $mUsrStatsTbl;

    /**
     * Stats Table
     *
     * @var TableGateway $mStatsTbl
     * @since 1.0.0
     */
    protected $mStatsTbl;

    /**
     * User Buff Table
     *
     * @var TableGateway $mBuffTbl
     * @since 1.0.0
     */
    protected $mBuffTbl;
    /**
     * @var TableGateway
     */
    private $mGuildStatsTbl;
    /**
     * @var TableGateway
     */
    private $mUserSetTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mContestTbl = new TableGateway('faucet_contest', $mapper);
        $this->mWinnerTbl = new TableGateway('faucet_contest_winner', $mapper);
        $this->mRewardTbl = new TableGateway('faucet_contest_reward', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);

        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mGuildStatsTbl = new TableGateway('faucet_guild_statistic', $mapper);

        $this->mInboxTbl = new TableGateway('user_inbox', $mapper);
        $this->mInboxAttachTbl = new TableGateway('user_inbox_item', $mapper);

        $this->mUsrStatsTbl = new TableGateway('user_faucet_stat', $mapper);
        $this->mStatsTbl = new TableGateway('faucet_statistic', $mapper);
        $this->mBuffTbl = new TableGateway('faucet_withdraw_buff', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        if(!isset($data->winners)) {
            return new ApiProblem(403, 'Missing Data');
        }
        if(!isset($data->date)) {
            return new ApiProblem(403, 'Missing Data');
        }
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }
        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $month = date('m', strtotime($data->date));
        $year = date('Y', strtotime($data->date));

        $check = $this->mWinnerTbl->select(['month' => $month,'year' => $year]);
        if($check->count() > 0) {
            return new ApiProblem(403, 'Contest Winners for '.$month.'-'.$year.' already set');
        }

        $winnerInfo = [];

        $date = filter_var($data->date, FILTER_SANITIZE_STRING);

        $contests = $this->getContestWinnersForMonth($date);

        $ids = [];

        foreach($contests as $contest) {
            $ids[] = $contest['id'];
            $contestRewards = $this->mRewardTbl->select(['contest_idfs' => $contest['id']]);
            $rewards = [];
            foreach($contestRewards as $rew) {
                $rewards[$rew->rank] = $rew->amount;
            }

            foreach($contest['winners'] as $winner) {
                if(array_key_exists($winner->rank, $rewards)) {
                    $this->mWinnerTbl->insert([
                        'contest_idfs' => $contest['id'],
                        'user_idfs' => $winner->id,
                        'rank' => $winner->rank,
                        'month' => $month,
                        'year' => $year,
                        'reward' => $rewards[$winner->rank],
                        'amount' => $winner->count
                    ]);
                }
            }
        }

        return [
            'dev' => $ids,
            'contests' => $contests,
            'winners' => $winnerInfo,
            'skipped' => []
        ];
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function delete($id)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for individual resources');
    }

    /**
     * Delete a collection, or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function deleteList($data)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for collections');
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function fetch($id)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }
        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $id = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

        if($id == 0) {
            $contests = [];
            $winSel = new Select($this->mWinnerTbl->getTable());
            $winSel->group(['year', 'month']);
            $contestList = $this->mWinnerTbl->selectWith($winSel);
            foreach($contestList as $contest) {
                $contests[] = [
                    'id' => $contest->year.$contest->month,
                    'name' => $contest->month.'.'.$contest->year,
                ];
            }
            return $contests;
        } else {
            $year = substr($id, 0, 4);
            $month = substr($id, 4, 2);

            $winInfo = [];
            $winSel = new Select($this->mWinnerTbl->getTable());
            $winSel->join(['u' => 'user'],'u.User_ID = faucet_contest_winner.user_idfs');
            $winSel->join(['fc' => 'faucet_contest'],'fc.Contest_ID = faucet_contest_winner.contest_idfs');
            $winSel->order('rank ASC');
            $winSel->where(['year' => $year, 'month' => $month,'contest_type' => 'player']);
            $winners = $this->mWinnerTbl->selectWith($winSel);
            foreach($winners as $win) {
                if(!array_key_exists($win->contest_idfs, $winInfo)) {
                    $contestInfo = $this->mContestTbl->select(['Contest_ID' => $win->contest_idfs]);
                    $name = '-';
                    if($contestInfo->count() > 0) {
                        $name = $contestInfo->current()->contest_label;
                    }
                    $winInfo[$win->contest_idfs] = [
                        'name' => $name,
                        'type' => 'user',
                        'unit_label' => $win->unit_label,
                        'winners' => []
                    ];
                }
                $winInfo[$win->contest_idfs]['winners'][] = [
                    'rank' => $win->rank,
                    'name' => $win->username,
                    'xp_level' => $win->xp_level,
                    'amount' => $win->amount
                ];
            }

            $winSel = new Select($this->mWinnerTbl->getTable());
            $winSel->join(['g' => 'faucet_guild'],'g.Guild_ID = faucet_contest_winner.user_idfs',['label']);
            $winSel->join(['fc' => 'faucet_contest'],'fc.Contest_ID = faucet_contest_winner.contest_idfs');
            $winSel->order('rank ASC');
            $winSel->where(['year' => $year, 'month' => $month,'contest_type' => 'guild']);
            $winners = $this->mWinnerTbl->selectWith($winSel);
            foreach($winners as $win) {
                if(!array_key_exists($win->contest_idfs, $winInfo)) {
                    $contestInfo = $this->mContestTbl->select(['Contest_ID' => $win->contest_idfs]);
                    $name = '-';
                    if($contestInfo->count() > 0) {
                        $name = $contestInfo->current()->contest_label;
                    }
                    $winInfo[$win->contest_idfs] = [
                        'name' => $name,
                        'type' => 'guild',
                        'unit_label' => $win->unit_label,
                        'winners' => []
                    ];
                }
                $winInfo[$win->contest_idfs]['winners'][] = [
                    'rank' => $win->rank,
                    'name' => $win->label,
                    'amount' => $win->amount
                ];
            }

            return $winInfo;
        }
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = [])
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }
        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $contests = [];

        $contestWinnersById = [];

        if(isset($_REQUEST['date'])) {
            $date = filter_var($_REQUEST['date'], FILTER_SANITIZE_STRING);

            $maxRank = 10;

            return $this->getContestWinnersForMonth($date);
        }
    }

    private function getContestWinnersForMonth($date) {
        $bannedUsersById = [];
        $bannedUsers = $this->mUserSetTbl->select(['setting_name' => 'user-tempban']);
        foreach($bannedUsers as $bannedUser) {
            if(!in_array($bannedUser->user_idfs,$bannedUsersById)) {
                $bannedUsersById[] = $bannedUser->user_idfs;
            }
        }

        $top10Contest = ['contest-10' => [], 'contest-5' => [], 'contest-6' => [], 'contest-11' => [], 'contest-12' => [], 'contest-13' => [], 'contest-1' => [], 'contest-14' => [], 'contest-15' => []];

        /**
         * Shortlinks
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $totalSel->where(['stat_key' => 'user-shortlink-m-' . date('n-Y', strtotime($date))]);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                $infoByUserId['user-' . $shd->user_idfs] = (object)[
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
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-10'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        /**
         * Daily Tasks
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $totalSel->where(['stat_key' => 'user-dailys-m-' . date('n-Y', strtotime($date))]);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                $infoByUserId['user-' . $shd->user_idfs] = (object)[
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
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-13'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        /**
         * Offerwalls BIG
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $totalSel->where(['stat_key' => 'user-offerbig-m-' . date('n-Y', strtotime($date))]);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                $infoByUserId['user-' . $shd->user_idfs] = (object)[
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
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-11'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        /**
         * Offerwalls Small
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $totalSel->where(['stat_key' => 'user-offersmall-m-' . date('n-Y', strtotime($date))]);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                $infoByUserId['user-' . $shd->user_idfs] = (object)[
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
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-12'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        /**
         * Offerwalls Tiny
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $totalSel->where(['stat_key' => 'user-offertiny-m-' . date('n-Y', strtotime($date))]);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                $infoByUserId['user-' . $shd->user_idfs] = (object)[
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
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-14'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        /**
         * Offerwalls Medium
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $totalSel->where(['stat_key' => 'user-offermed-m-' . date('n-Y', strtotime($date))]);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                $infoByUserId['user-' . $shd->user_idfs] = (object)[
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
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-15'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        // nano-coin-m-rvn-5-2022
        /**
         * GPU Miners
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $statWh = new Where();
        $statWh->NEST
            ->like('stat_key', 'user-nano-etc-coin-m-' . date('n-Y', strtotime($date)))
            ->OR
            ->like('stat_key', 'user-nano-rvn-coin-m-' . date('n-Y', strtotime($date)))
            ->OR
            ->like('stat_key', 'user-nano-ergo-coin-m-' . date('n-Y', strtotime($date)))
            ->UNNEST;
        $totalSel->where($statWh);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                if (!array_key_exists('user-' . $shd->user_idfs, $shortsByUser)) {
                    $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                    $infoByUserId['user-' . $shd->user_idfs] = (object)[
                        'name' => $shd->username,
                        'xp_level' => $shd->xp_level,
                        'avatar' => ($shd->avatar != '') ? $shd->avatar : $shd->username,
                        'id' => $shd->user_idfs,
                        'count' => (int)$shd->stat_data,
                    ];
                } else {
                    $shortsByUser['user-' . $shd->user_idfs] += (int)$shd->stat_data . $cLevel;
                    $infoByUserId['user-' . $shd->user_idfs]->count += (int)$shd->stat_data;
                }

            }
        }
        arsort($shortsByUser);
        $rank = 1;
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-5'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        /**
         * CPU Miners
         */
        $totalSel = new Select($this->mUsrStatsTbl->getTable());
        $totalSel->join(['u' => 'user'], 'u.User_ID = user_faucet_stat.user_idfs', ['username', 'avatar', 'xp_level']);
        $totalSel->where(['stat_key' => 'user-nano-xmr-coin-m-' . date('n-Y', strtotime($date))]);
        $totalSel->order('u.xp_level DESC');
        $totalUserShorts = $this->mUsrStatsTbl->selectWith($totalSel);
        $shortsByUser = [];
        $infoByUserId = [];
        if (count($totalUserShorts) > 0) {
            foreach ($totalUserShorts as $shd) {
                // skip banned users
                if (in_array($shd->user_idfs, $bannedUsersById)) {
                    continue;
                }
                $cLevel = $shd->xp_level;
                if ($cLevel < 10) {
                    $cLevel = '0' . $cLevel;
                }
                $shortsByUser['user-' . $shd->user_idfs] = (int)$shd->stat_data . $cLevel;
                $infoByUserId['user-' . $shd->user_idfs] = (object)[
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
        foreach (array_keys($shortsByUser) as $claimUser) {
            if ($rank <= 10) {
                if (array_key_exists($claimUser, $infoByUserId)) {
                    $infoByUserId[$claimUser]->rank = $rank;
                    $top10Contest['contest-6'][] = $infoByUserId[$claimUser];
                }
            }
            $rank++;
        }

        /**
         * Top Guilds
         */
        $statSel = new Select($this->mGuildStatsTbl->getTable());
        $statSel->join(['fg' => 'faucet_guild'], 'fg.Guild_ID = faucet_guild_statistic.guild_idfs', ['label', 'emblem_shield', 'emblem_icon']);
        $statSel->order('date DESC');
        $statSel->where(['stat_key' => 'guild-weeklys-m-' . date('n-Y', strtotime($date))]);
        $statFound = $this->mStatsTbl->selectWith($statSel);

        $tasksByGuild = [];
        $guildInfoByGuildId = [];
        foreach ($statFound as $guild) {
            $tasksByGuild['guild-' . $guild->guild_idfs] = (int)$guild->data;
            $guildInfoByGuildId['guild-' . $guild->guild_idfs] = (object)[
                'id' => $guild->guild_idfs,
                'name' => $guild->label,
                'shield' => $guild->emblem_shield,
                'icon' => $guild->emblem_icon,
                'count' => (int)$guild->data
            ];
        }

        arsort($tasksByGuild);

        $rank = 0;
        foreach ($tasksByGuild as $gKey => $gCount) {
            if ($rank == 10) {
                break;
            }
            $guildInfoByGuildId[$gKey]->rank = ($rank + 1);
            $top10Contest['contest-1'][] = $guildInfoByGuildId[$gKey];
            $rank++;
        }

        /**
         * if($statFound->count() > 0) {
         * $statFound = (array)$statFound->current();
         * $topList = json_decode($statFound['stat-data']);
         *
         * foreach($topList as $top) {
         * if($iCount == 5) {
         * break;
         * }
         * $gInfo = $this->mGuildTbl->select(['Guild_ID' => $top->id]);
         * if($gInfo->count() > 0) {
         * $gInfo = $gInfo->current();
         * $top10Contest['contest-1'][] = (object)[
         * 'id' => $top->id,
         * 'rank' => ($iCount+1),
         * 'count' => round($top->count),
         * 'name' => $gInfo->label,
         * 'shield' => $gInfo->emblem_shield,
         * 'icon' => $gInfo->emblem_icon,
         * ];
         * $iCount++;
         * }
         * }
         * } **/

        $conSel = new Select($this->mContestTbl->getTable());
        $conSel->join(['fcr' => 'faucet_contest_reward'], 'fcr.contest_idfs = faucet_contest.Contest_ID');
        $conSel->where(['fcr.month' => date('n', strtotime($date)), 'fcr.year' => date('Y',strtotime($date))]);
        $conSel->order('faucet_contest.sort_id');
        $conSel->group('faucet_contest.Contest_ID');

        $activeContests = $this->mContestTbl->selectWith($conSel);

        $contestsData = [];
        foreach ($activeContests as $con) {
            $winners = [];
            if (array_key_exists('contest-' . $con->Contest_ID, $top10Contest)) {
                $winners = $top10Contest['contest-' . $con->Contest_ID];
            }
            $contestsData[] = [
                'id' => $con->Contest_ID,
                'name' => $con->contest_name,
                'type' => $con->contest_type,
                'winners' => $winners,
                'unit_label' => $con->unit_label,
                'earn_label' => $con->earn_label
            ];
        }

        return $contestsData;
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patch($id, $data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for individual resources');
    }

    /**
     * Patch (partial in-place update) a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patchList($data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for collections');
    }

    /**
     * Replace a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function replaceList($data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }
        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $openPays = $this->mWinnerTbl->select(['status' => 'new']);

        if($openPays->count() > 0) {
            foreach($openPays as $op) {
                $contestInfo = $this->mContestTbl->select(['Contest_ID' => $op->contest_idfs]);
                if($contestInfo->count() == 0) {
                    continue;
                }
                $monthText = date('F', strtotime($op->year.'-'.$op->month.'-01'));
                $contestInfo = $contestInfo->current();
                if($contestInfo->contest_type == 'guild') {
                    $this->mTransaction->executeGuildTransaction($op->reward, false, $op->user_idfs, $op->contest_idfs, 'contestwin',$op->rank.'. Place in the '.$contestInfo->contest_label.' of '.$monthText, 1);
                } else {

                    # create message to buyer inbox
                    $this->mInboxTbl->insert([
                        'label' => $contestInfo->contest_label.' Contest '.$monthText,
                        'message' => 'Hi,<br/>You have ranked '.$op->rank.'. Place in the '.$contestInfo->contest_label.' Contest of '.$monthText.' - Congratulations! Attached is your Reward',
                        'credits' => $op->reward,
                        'from_idfs' => 1,
                        'to_idfs' => $op->user_idfs,
                        'date' => date('Y-m-d H:i:s', time()),
                        'is_read' => 0
                    ]);
                    $messageId = $this->mInboxTbl->lastInsertValue;

                    $now = date('Y-m-d H:i:s', time());
                    $bonusBuff = round($op->reward / 10);
                    $this->mBuffTbl->insert([
                        'ref_idfs' => $op->contest_idfs,
                        'ref_type' => 'contest',
                        'label' => 'Rank '.$op->rank.' '.$contestInfo->contest_label.' Contest '.$monthText,
                        'days_left' => 10,
                        'days_total' => 10,
                        'amount' => $bonusBuff,
                        'created_date' => $now,
                        'user_idfs' => $op->user_idfs
                    ]);
                }

                $this->mWinnerTbl->update([
                    'status' => 'paid'
                ],['contest_idfs' => $op->contest_idfs, 'user_idfs' => $op->user_idfs, 'month' => $op->month, 'year' => $op->year]);
            }
        }
        return new ViewModel([
            'state' => 'paid'
        ]);
        //return new ApiProblem(405, 'The PUT method has not been defined for collections');
    }

    /**
     * Update a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function update($id, $data)
    {
        if(!isset($data->username)) {
            return new ApiProblem(403, 'Missing Data');
        }
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }
        if($this->mSecTools->checkIpRestrictedAccess() !== true) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $username = filter_var($data->username, FILTER_SANITIZE_STRING);
        $contestId = filter_var($data->contest_id, FILTER_SANITIZE_NUMBER_INT);
        $contestInfo = $this->mContestTbl->select(['Contest_ID' => $contestId]);
        if($contestInfo->count() == 0) {
            return new ApiProblem(404, 'Contest not found');
        }
        $contestInfo = $contestInfo->current();
        if($contestInfo->contest_type == 'guild') {
            $winnerFound = $this->mGuildTbl->select(['label' => utf8_decode($username)]);
        } else {
            if(is_numeric($username)) {
                $winnerFound = $this->mUserTbl->select(['User_ID' => $username]);
            } else {
                $winnerFound = $this->mUserTbl->select(['username' => $username]);
            }
        }

        $winnersCount = $winnerFound->count();
        if($winnersCount == 1) {
            $winnerFound = $winnerFound->current();
            if($contestInfo->contest_type == 'guild') {
                return [
                    'user' => [
                        'id' => $winnerFound->Guild_ID,
                        'name' => utf8_encode($winnerFound->label)
                    ]
                ];
            } else {
                return [
                    'user' => [
                        'id' => $winnerFound->User_ID,
                        'name' => utf8_encode($winnerFound->username)
                    ]
                ];
            }
        } else {
            if($winnersCount == 0) {
                return new ApiProblem(404, 'User or Guild not found');
            } else {
                return new ApiProblem(400, 'Multiple Users / Guilds found with that Name');
            }
        }
    }
}
