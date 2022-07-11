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

        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);

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

        $month = date('m', strtotime($data->date));
        $year = date('Y', strtotime($data->date));

        $check = $this->mWinnerTbl->select(['month' => $month,'year' => $year]);
        if($check->count() > 0) {
            return new ApiProblem(403, 'Contest Winners for '.$month.'-'.$year.' already set');
        }

        $winnerInfo = [];
        $skipList = [];

        $contests = [];

        $contestWinnersById = [];

        $skipList = [
            335880436 => true,
            335875071 => true,
            335874987 => true,
            335902227 => true
        ];

        $date = strtotime(filter_var($data->date, FILTER_SANITIZE_STRING));

        $maxRank = 10;

        /**
         * User XP Level Contest Data
         */
        $statSel = new Select($this->mUsrStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where(['stat_key' => 'user-xp-'.date('n-Y', $date)]);
        $statFound = $this->mUsrStatsTbl->selectWith($statSel);

        $top3Level = [];
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
                ];
                if($iCount == $maxRank) {
                    break;
                }
                $iCount++;
            }
        }
        //$contestWinnersById[2] = $top3Level;

        /**
         * Shortlinks Done Contest Data
         */
        $statSel = new Select($this->mUsrStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where(['stat_key' => 'shdone-m-'.date('n-Y', $date)]);
        $statFound = $this->mUsrStatsTbl->selectWith($statSel);

        $top3Level = [];
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
                ];
                if($iCount == $maxRank) {
                    break;
                }
                $iCount++;
            }
        }
        $contestWinnersById[10] = $top3Level;

        /**
         * Offerwalls Done Contest Data
         */
        $statSel = new Select($this->mUsrStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where(['stat_key' => 'user-offerbig-m-'.date('n-Y', $date)]);
        $statFound = $this->mUsrStatsTbl->selectWith($statSel);

        $top3Level = [];
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
                ];
                if($iCount == $maxRank) {
                    break;
                }
                $iCount++;
            }
        }
        $contestWinnersById[11] = $top3Level;

        /**
         * Offerwalls Done Contest Data
         */
        $statSel = new Select($this->mUsrStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where(['stat_key' => 'user-offersmall-m-'.date('n-Y', $date)]);
        $statFound = $this->mUsrStatsTbl->selectWith($statSel);

        $top3Level = [];
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
                ];
                if($iCount == $maxRank) {
                    break;
                }
                $iCount++;
            }
        }
        $contestWinnersById[12] = $top3Level;

        /**
         * Daily Tasks Contest Data
         */
        $statSel = new Select($this->mUsrStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where(['stat_key' => 'user-dailys-m-'.date('n-Y', $date)]);
        $statFound = $this->mUsrStatsTbl->selectWith($statSel);

        $top3Level = [];
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
                ];
                if($iCount == $maxRank) {
                    break;
                }
                $iCount++;
            }
        }
        $contestWinnersById[13] = $top3Level;

        $statWh = new Where();
        $statWh->like('stat_key', 'nano-coin-m-xmr-'.date('n-Y',$date));
        $statSel = new Select($this->mUsrStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where($statWh);
        $statFound = $this->mUsrStatsTbl->selectWith($statSel);
        $top3ShById = [];
        $contestWinnersById[5] = [];
        if($statFound->count() > 0) {
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
                    $contestWinnersById[6][] = (object)[
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
        $statSel = new Select($this->mUsrStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where($statWh);
        $statFound = $this->mUsrStatsTbl->selectWith($statSel);
        $top3ShById = [];
        $contestWinnersById[5] = [];
        if($statFound->count() > 0) {
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
                    $contestWinnersById[5][] = (object)[
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

        /**
        $top3Ref = [];
        $statSel = new Select($this->mStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where(['stat-key' => 'conrefs-'.date('n-Y', $date)]);
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

        $contestWinnersById[7] = $top3Ref;
         **/
        $top3Guild = [];
        $statSel = new Select($this->mStatsTbl->getTable());
        $statSel->order('date DESC');
        $statSel->where(['stat-key' => 'guild-top-'.date('m', $date)]);
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
                $top3Guild[] = $top;
                $iCount++;
            }
        }

        $contestWinnersById[1] = $top3Guild;

        $conSel = new Select($this->mContestTbl->getTable());
        $conSel->join(['fcr' => 'faucet_contest_reward'], 'fcr.contest_idfs = faucet_contest.Contest_ID');
        if(isset($_REQUEST['date'])) {
            $date = filter_var($_REQUEST['date'], FILTER_SANITIZE_STRING);
            $conSel->where(['month' => date('n', strtotime($date)), 'year' => date('Y', strtotime($date))]);
        }
        $conSel->group('faucet_contest.Contest_ID');

        $contestList = $this->mContestTbl->selectWith($conSel);
        foreach($contestList as $contest) {
            if(array_key_exists($contest->Contest_ID, $contestWinnersById)) {
                $contests[] = [
                    'id' => $contest->Contest_ID,
                    'name' => $contest->contest_label,
                    'winners' => $contestWinnersById[$contest->Contest_ID]
                ];

                $contestRewards = $this->mRewardTbl->select(['contest_idfs' => $contest->Contest_ID]);
                if($contestRewards->count() < 3) {
                    $skipList[] = $contest->id;
                    continue;
                }
                $rewards = [];
                foreach($contestRewards as $rew) {
                    $rewards[$rew->rank] = $rew->amount;
                }

                if($contest->contest_type == 'guild') {
                    foreach($contestWinnersById[$contest->Contest_ID] as $winner) {
                        if(array_key_exists($winner->rank, $rewards)) {
                            $this->mWinnerTbl->insert([
                                'contest_idfs' => $contest->Contest_ID,
                                'user_idfs' => $winner->id,
                                'rank' => $winner->rank,
                                'month' => $month,
                                'year' => $year,
                                'reward' => $rewards[$winner->rank],
                                'amount' => $winner->count
                            ]);
                        }
                    }
                } else {
                    foreach($contestWinnersById[$contest->Contest_ID] as $winner) {
                        if(array_key_exists($winner->rank, $rewards)) {
                            $this->mWinnerTbl->insert([
                                'contest_idfs' => $contest->Contest_ID,
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
            }
        }

        /**
        foreach($data->winners as $contest) {
            $contest = (object)$contest;
            $contestInfo = $this->mContestTbl->select(['Contest_ID' => $contest->id]);
            $rewards = [];
            if($contestInfo->count() == 0) {
                $skipList[] = $contest->id;
                continue;
            }
            $contestInfo = $contestInfo->current();
            $contestRewards = $this->mRewardTbl->select(['contest_idfs' => $contest->id]);
            if($contestRewards->count() < 3) {
                $skipList[] = $contest->id;
                continue;
            }
            foreach($contestRewards as $rew) {
                $rewards[$rew->rank] = $rew->amount;
            }
            if(!isset($contest->winner_id) || !isset($contest->second_id) || !isset($contest->third_id)) {
                $skipList[] = $contest->id;
                continue;
            }
            // guild contest
            if($contestInfo->contest_type == 'guild') {
                $winnerInfo[] = [
                    'name' => $contestInfo->contest_label,
                    'winner' => ['name' => $contest->winner, 'amount' => $contest->winner_amount],
                    'second' => ['name' => $contest->second, 'amount' => $contest->second_amount],
                    'third' => ['name' => $contest->third, 'amount' => $contest->third_amount]
                ];

                $this->mWinnerTbl->insert([
                    'contest_idfs' => $contest->id,
                    'user_idfs' => $contest->winner_id,
                    'rank' => 1,
                    'month' => $month,
                    'year' => $year,
                    'reward' => $rewards[1],
                    'amount' => $contest->winner_amount
                ]);
                $this->mWinnerTbl->insert([
                    'contest_idfs' => $contest->id,
                    'user_idfs' => $contest->second_id,
                    'rank' => 2,
                    'month' => $month,
                    'year' => $year,
                    'reward' => $rewards[2],
                    'amount' => $contest->second_amount
                ]);
                $this->mWinnerTbl->insert([
                    'contest_idfs' => $contest->id,
                    'user_idfs' => $contest->third_id,
                    'rank' => 3,
                    'month' => $month,
                    'year' => $year,
                    'reward' => $rewards[3],
                    'amount' => $contest->third_amount
                ]);
            } else {
                $winnerInfo[] = [
                    'name' => $contestInfo->contest_label,
                    'winner' => ['name' => $contest->winner, 'amount' => $contest->winner_amount],
                    'second' => ['name' => $contest->second, 'amount' => $contest->second_amount],
                    'third' => ['name' => $contest->third, 'amount' => $contest->third_amount]
                ];
                // player contest
                $this->mWinnerTbl->insert([
                    'contest_idfs' => $contest->id,
                    'user_idfs' => $contest->winner_id,
                    'rank' => 1,
                    'month' => $month,
                    'year' => $year,
                    'reward' => $rewards[1],
                    'amount' => $contest->winner_amount
                ]);
                $this->mWinnerTbl->insert([
                    'contest_idfs' => $contest->id,
                    'user_idfs' => $contest->second_id,
                    'rank' => 2,
                    'month' => $month,
                    'year' => $year,
                    'reward' => $rewards[2],
                    'amount' => $contest->second_amount
                ]);
                $this->mWinnerTbl->insert([
                    'contest_idfs' => $contest->id,
                    'user_idfs' => $contest->third_id,
                    'rank' => 3,
                    'month' => $month,
                    'year' => $year,
                    'reward' => $rewards[3],
                    'amount' => $contest->third_amount
                ]);
            }
        }
         *
         * **/

        return [
            'winners' => $winnerInfo,
            'skipped' => $skipList
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
            $winSel->order('rank ASC');
            $winSel->where(['year' => $year, 'month' => $month]);
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
                        'winners' => []
                    ];
                }
                $winInfo[$win->contest_idfs]['winners'][] = [
                    'rank' => $win->rank,
                    'name' => $win->username,
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

        $contests = [];

        $contestWinnersById = [];

        if(isset($_REQUEST['date'])) {
            $skipList = [
                335880436 => true,
                335875071 => true,
                335874987 => true,
                335902227 => true
            ];

            $date = strtotime(filter_var($_REQUEST['date'], FILTER_SANITIZE_STRING));

            $maxRank = 10;

            /**
             * User XP Level Contest Data
             *
             * not active at the moment
             */
            /**
            $statSel = new Select($this->mUsrStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where(['stat_key' => 'user-xp-'.date('n-Y', $date)]);
            $statFound = $this->mUsrStatsTbl->selectWith($statSel);

            $top3Level = [];
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
                    ];
                    if($iCount == $maxRank) {
                        break;
                    }
                    $iCount++;
                }
            }
            $contestWinnersById[2] = $top3Level;
             * **/

            /**
             * Shortlinks Done Contest Data
             */
            $statSel = new Select($this->mUsrStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where(['stat_key' => 'shdone-m-'.date('n-Y', $date)]);
            $statFound = $this->mUsrStatsTbl->selectWith($statSel);

            $top3Level = [];
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
                    ];
                    if($iCount == $maxRank) {
                        break;
                    }
                    $iCount++;
                }
            }
            $contestWinnersById[10] = $top3Level;

            /**
             * Offerwalls Done Contest Data
             */
            $statSel = new Select($this->mUsrStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where(['stat_key' => 'user-offerbig-m-'.date('n-Y', $date)]);
            $statFound = $this->mUsrStatsTbl->selectWith($statSel);

            $top3Level = [];
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
                    ];
                    if($iCount == $maxRank) {
                        break;
                    }
                    $iCount++;
                }
            }
            $contestWinnersById[11] = $top3Level;

            /**
             * Offerwalls Done Contest Data
             */
            $statSel = new Select($this->mUsrStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where(['stat_key' => 'user-offersmall-m-'.date('n-Y', $date)]);
            $statFound = $this->mUsrStatsTbl->selectWith($statSel);

            $top3Level = [];
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
                    ];
                    if($iCount == $maxRank) {
                        break;
                    }
                    $iCount++;
                }
            }
            $contestWinnersById[12] = $top3Level;

            /**
             * Daily Tasks Contest Data
             */
            $statSel = new Select($this->mUsrStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where(['stat_key' => 'user-dailys-m-'.date('n-Y', $date)]);
            $statFound = $this->mUsrStatsTbl->selectWith($statSel);

            $top3Level = [];
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
                    ];
                    if($iCount == $maxRank) {
                        break;
                    }
                    $iCount++;
                }
            }
            $contestWinnersById[13] = $top3Level;

            $statWh = new Where();
            $statWh->like('stat_key', 'nano-coin-m-xmr-'.date('n-Y',$date));
            $statSel = new Select($this->mUsrStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where($statWh);
            $statFound = $this->mUsrStatsTbl->selectWith($statSel);
            $top3ShById = [];
            $contestWinnersById[5] = [];
            if($statFound->count() > 0) {
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
                        $contestWinnersById[6][] = (object)[
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
            $statSel = new Select($this->mUsrStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where($statWh);
            $statFound = $this->mUsrStatsTbl->selectWith($statSel);
            $top3ShById = [];
            $contestWinnersById[5] = [];
            if($statFound->count() > 0) {
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
                        $contestWinnersById[5][] = (object)[
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

            /**
            $top3Ref = [];
            $statSel = new Select($this->mStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where(['stat-key' => 'conrefs-'.date('n-Y', $date)]);
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

            $contestWinnersById[7] = $top3Ref;
            **/
            $top3Guild = [];
            $statSel = new Select($this->mStatsTbl->getTable());
            $statSel->order('date DESC');
            $statSel->where(['stat-key' => 'guild-top-'.date('m', $date)]);
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
                    $top3Guild[] = $top;
                    $iCount++;
                }
            }

            $contestWinnersById[1] = $top3Guild;

        }

        $conSel = new Select($this->mContestTbl->getTable());
        $conSel->join(['fcr' => 'faucet_contest_reward'], 'fcr.contest_idfs = faucet_contest.Contest_ID');
        if(isset($_REQUEST['date'])) {
            $date = filter_var($_REQUEST['date'], FILTER_SANITIZE_STRING);
            $conSel->where(['month' => date('n', strtotime($date)), 'year' => date('Y', strtotime($date))]);
        }
        $conSel->group('faucet_contest.Contest_ID');

        $contestList = $this->mContestTbl->selectWith($conSel);
        foreach($contestList as $contest) {
            if(array_key_exists($contest->Contest_ID, $contestWinnersById)) {
                $conData = [
                    'id' => $contest->Contest_ID,
                    'name' => $contest->contest_label,
                    'winners' => $contestWinnersById[$contest->Contest_ID]
                ];
                $contests[] = $conData;
            } else {
                $contests[] = [
                    'id' => $contest->Contest_ID,
                    'name' => $contest->contest_label,
                ];
            }
        }

        return $contests;
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
