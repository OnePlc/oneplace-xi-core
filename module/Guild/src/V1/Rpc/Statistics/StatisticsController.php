<?php
namespace Guild\V1\Rpc\Statistics;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class StatisticsController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Guild Table User Table
     *
     * Relation between Guild and User
     * to determine if user has a guild and
     * if yes what guild it is
     *
     * @var TableGateway $mGuildUserTbl
     * @since 1.0.0
     */
    protected $mGuildUserTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * @var TableGateway
     */
    private $mGuildRankTbl;

    /**
     * Constructor
     *
     * BankController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mGuildRankTbl = new TableGateway('faucet_guild_rank', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function statisticsAction()
    {
        # Get Request Data
        $request = $this->getRequest();
        if($request->isGet()) {
            # Prevent 500 error
            if(!$this->getIdentity()) {
                return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
            }
            $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
            if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
                return new ApiProblemResponse($me);
            }

            $mode = 'month';
            if(isset($_REQUEST['mode'])) {
                $modeSet = filter_var($_REQUEST['mode'], FILTER_SANITIZE_STRING);
                switch($modeSet) {
                    case 'lastmonth':
                        $mode = 'lastmonth';
                        break;
                    case 'week':
                        $mode = 'week';
                        break;
                    case 'total':
                        $mode = 'total';
                        break;
                    default:
                        break;
                }
            }

            # check if user already has joined or created a guild
            $checkWh = new Where();
            $checkWh->equalTo('user_idfs', $me->User_ID);
            $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
            $userHasGuild = $this->mGuildUserTbl->select($checkWh);
            if(count($userHasGuild) == 0) {
                return new ApiProblemResponse(new ApiProblem(409, 'You are not part of a guild'));
            }
            $isGuildMaster = false;
            $userHasGuild = $userHasGuild->current();
            if($userHasGuild->rank == 0) {
                $isGuildMaster = true;
            }
            $guildId = $userHasGuild->guild_idfs;
            $ranks = [];
            $guildRanks = $this->mGuildRankTbl->select(['guild_idfs' => $guildId]);
            if(count($guildRanks) > 0) {
                foreach($guildRanks as $rank) {
                    $ranks['rank-'.$rank->level] = (object)[
                        'id' => $rank->level,
                        'name' => $rank->label,
                    ];
                }
            }

            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-shortlink-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-shortlink-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-shortlink-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-shortlink-total');
            }

            /**
             * Get Top 10 Shortlinks
             */
            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar','last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $shortsByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }

                $shortsByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($shortsByUserId);

            $top10SH = [];
            $count = 1;
            foreach($shortsByUserId as $topSh) {
                $top10SH[] = $topSh;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 Offerwalls
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-offerbig-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-offerbig-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-offerbig-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-offerbig-total');
            }
            //$gWh->like('ufs.stat_key', 'ofdone-m-'.date('n-Y', time()));

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar','last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }

                $offersByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($offersByUserId);

            $top10OF = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OF[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 Offerwalls Medium
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-offermed-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-offermed-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-offermed-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-offermed-total');
            }

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar','last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }

                $offersByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($offersByUserId);

            $top10OFM = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OFM[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 Offerwalls Small
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-offersmall-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-offersmall-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-offersmall-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-offersmall-total');
            }

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar','last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }

                $offersByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($offersByUserId);

            $top10OFS = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OFS[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 Offerwalls Tiny
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-offertiny-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-offertiny-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-offertiny-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-offertiny-total');
            }

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar','last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }

                $offersByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($offersByUserId);

            $top10OFT = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OFT[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 Daily Tasks
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-dailys-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-dailys-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-dailys-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-dailys-total');
            }

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar', 'last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }

                $offersByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($offersByUserId);

            $top10OD = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OD[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 CPU miners
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-nano-xmr-coin-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-nano-xmr-coin-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-nano-xmr-coin-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-nano-xmr-coin-total');
            }

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar', 'last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }

                $offersByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($offersByUserId);

            $top10OCPU = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OCPU[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 GPU miners
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            if($mode == 'month') {
                $gWh->NEST
                    ->like('ufs.stat_key', 'user-nano-etc-coin-m-'.date('n-Y',time()))
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-rvn-coin-m-'.date('n-Y',time()))
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-ergo-coin-m-'.date('n-Y',time()))
                    ->UNNEST;
            }
            if($mode == 'lastmonth') {
                $gWh->NEST
                    ->like('ufs.stat_key', 'user-nano-etc-coin-m-'.date('n-Y',strtotime('-1 month')))
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-rvn-coin-m-'.date('n-Y',strtotime('-1 month')))
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-ergo-coin-m-'.date('n-Y',strtotime('-1 month')))
                    ->UNNEST;
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->NEST
                    ->like('ufs.stat_key', 'user-nano-etc-coin-w-'.$week)
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-rvn-coin-w-'.$week)
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-ergo-coin-w-'.$week)
                    ->UNNEST;
            }
            if($mode == 'total') {
                $gWh->NEST
                    ->like('ufs.stat_key', 'user-nano-etc-coin-total')
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-rvn-coin-total')
                    ->OR
                    ->like('ufs.stat_key', 'user-nano-ergo-coin-total')
                    ->UNNEST;
            }

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                if(!array_key_exists($sh->user_idfs, $offersByUserId)) {
                    $uInfo = [
                        'amount' => $sh->stat_data,
                        'id' => $sh->user_idfs,
                        'name' => $sh->username,
                        'avatar' => $sh->avatar
                    ];
                    if($isGuildMaster) {
                        $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                        $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                        if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                            $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                        }
                    }
                    $offersByUserId[$sh->user_idfs] = $uInfo;
                } else {
                    $offersByUserId[$sh->user_idfs]['amount'] += $sh->stat_data;
                }
            }
            arsort($offersByUserId);

            $top10OGPU = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OGPU[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }


            /**
             * Get Top 10 Claimers
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            //$gWh->like('ufs.stat_key', 'fclaim-m-web-'.date('n-Y', time()));
            if($mode == 'month') {
                $gWh->like('ufs.stat_key', 'user-claims-m-'.date('n-Y', time()));
            }
            if($mode == 'lastmonth') {
                $gWh->like('ufs.stat_key', 'user-claims-m-'.date('n-Y', strtotime('-1 month')));
            }
            if($mode == 'week') {
                $week = $this->getCurrentWeekNumber();
                $gWh->like('ufs.stat_key', 'user-claims-w-'.$week);
            }
            if($mode == 'total') {
                $gWh->like('ufs.stat_key', 'user-claims-total');
            }

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username','avatar','last_action']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            //$gSel->join(['fgr' => 'faucet_guild_rank'], 'faucet_guild_user.level = fgr.level', ['label']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $claimsByUserId = [];
            foreach($shortStats as $sh) {
                $uInfo = [
                    'amount' => $sh->stat_data,
                    'id' => $sh->user_idfs,
                    'name' => $sh->username,
                    'avatar' => $sh->avatar
                ];
                if($isGuildMaster) {
                    $uInfo['joined'] = date('Y-m-d', strtotime($sh->date_joined));
                    $uInfo['active'] = date('Y-m-d', strtotime($sh->last_action));
                    if(array_key_exists('rank-'.$sh->rank, $ranks)) {
                        $uInfo['rank'] = $ranks['rank-'.$sh->rank];
                    }
                }
                $claimsByUserId[$sh->user_idfs] = $uInfo;
            }
            arsort($claimsByUserId);

            $top10Cl = [];
            $count = 1;
            foreach($claimsByUserId as $topCl) {
                $top10Cl[] = $topCl;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            return [
                'shortlink' => $top10SH,
                'offerwall' => $top10OF,
                'offermed' => $top10OFM,
                'offersmall' => $top10OFS,
                'offertiny' => $top10OFT,
                'faucet' => $top10Cl,
                'dailys' => $top10OD,
                'miner_cpu' => $top10OCPU,
                'miner_gpu' => $top10OGPU,
            ];
        }
        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));
    }

    private function getCurrentWeekNumber() {
        $week = date('W-Y',time());
        $weekDay = date('w', time());
        // if monday or tuesday, show stats from last week
        if($weekDay == 1 || $weekDay == 2) {
            $week = (date('W', time())-1).'-'.date('Y', time());
        }
        return $week;
    }
}
