<?php
namespace Backend\V1\Rpc\UserStats;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class UserStatsController extends AbstractActionController
{
    /**
     * Page Statistics Table
     *
     * @var TableGateway $mCoreStatsTbl
     * @since 1.0.0
     */
    protected $mCoreStatsTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * @var TableGateway
     */
    private $mUserTbl;

    /**
     * @var TableGateway
     */
    private $mWthBuffTbl;

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
        $this->mCoreStatsTbl = new TableGateway('core_statistic', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mWthBuffTbl = new TableGateway('faucet_withdraw_buff', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function userStatsAction()
    {
        $request = $this->getRequest();

        /**
         * Load Shortlink Info
         *
         * @since 1.0.0
         */
        if($request->isGet()) {
            # Prevent 500 error
            if(!$this->getIdentity()) {
                return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
            }
            $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
            if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
                return new ApiProblemResponse($me);
            }
            if($me->is_employee != 1) {
                return new ApiProblemResponse(new ApiProblem(400, 'You are not allowed this access this api'));
            }

            $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
            $ipWhiteList = json_decode($ipWhiteList);
            if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
                return new ApiProblemResponse(new ApiProblem(400, 'You are not allowed this access this api'));
            }

            if(isset($_REQUEST['richlist'])) {
                return $this->getUserRichList();
            }

            /**
             * Daily Active Users
             */
            $dauWh = new Where();
            $dauWh->like('stats_key', 'users-active-d-%');
            $dauSel = new Select($this->mCoreStatsTbl->getTable());
            $dauSel->where($dauWh);
            $dauSel->order('date DESC');
            $dauSel->limit(30);
            $dailyActiveUsers = $this->mCoreStatsTbl->selectWith($dauSel);

            $dauStats = [
                'labels' => [],
                'data' => []
            ];
            foreach($dailyActiveUsers as $dau) {
                $dateFromKey = substr($dau->stats_key, strlen('users-active-d-'));
                $dauStats['labels'][] = date('Y-m-d', strtotime($dateFromKey));
                $dauStats['data'][] = $dau->data;
            }

            $dauStats['labels'] = array_reverse($dauStats['labels']);
            $dauStats['data'] = array_reverse($dauStats['data']);

            /**
             * Monthly Active Users
             */
            $dauWh = new Where();
            $dauWh->like('stats_key', 'users-active-m-%');
            $dauSel = new Select($this->mCoreStatsTbl->getTable());
            $dauSel->where($dauWh);
            $dauSel->order('date DESC');
            $dauSel->limit(30);
            $dailyActiveUsers = $this->mCoreStatsTbl->selectWith($dauSel);

            $mauStats = [
                'labels' => [],
                'data' => []
            ];
            foreach($dailyActiveUsers as $dau) {
                $mauStats['labels'][] = date('Y-m-d', strtotime($dau->date));
                $mauStats['data'][] = $dau->data;
            }

            $mauStats['labels'] = array_reverse($mauStats['labels']);
            $mauStats['data'] = array_reverse($mauStats['data']);

            /**
             * Monthly Unique Users
             */
            $dauWh = new Where();
            $dauWh->like('stats_key', 'users-unique-m-%');
            $dauSel = new Select($this->mCoreStatsTbl->getTable());
            $dauSel->where($dauWh);
            $dauSel->order('date DESC');
            $dauSel->limit(30);
            $dailyActiveUsers = $this->mCoreStatsTbl->selectWith($dauSel);

            $muuStats = [
                'labels' => [],
                'data' => []
            ];
            foreach($dailyActiveUsers as $dau) {
                $dateFromKey = substr($dau->stats_key, strlen('users-unique-m-'));
                $muuStats['labels'][] = date('Y-m-d', strtotime($dateFromKey));
                $muuStats['data'][] = $dau->data;
            }

            $muuStats['labels'] = array_reverse($muuStats['labels']);
            $muuStats['data'] = array_reverse($muuStats['data']);

            /**
             * Client Version and Country for Monthly Unique Users
             */
            $clientWh = new Where();
            $clientWh->like('last_action', date('Y-m', time()).'%');
            $mauClients = $this->mUserTbl->select($clientWh);

            $clientStats = [
                'labels' => [],
                'data' => []
            ];
            $labelsTmp = [];
            $dataTmp = [];

            $countryStats = [
                'labels' => [],
                'data' => []
            ];
            $countryLabelsTmp = [];
            $countryDataTmp = [];

            $clientDayStats = [
                'labels' => [],
                'data' => []
            ];
            $clientDayLabelsTmp = [];
            $clientDayDataTmp = [];

            foreach($mauClients as $cl) {
                if(date('Y-m-d', strtotime($cl->last_action)) == date('Y-m-d', time())) {
                    if(!array_key_exists('v-'.$cl->client_version,$clientDayLabelsTmp)) {
                        $clientDayLabelsTmp['v-'.$cl->client_version] = $cl->client_version;
                    }
                    if(!array_key_exists('v-'.$cl->client_version,$clientDayDataTmp)) {
                        $clientDayDataTmp['v-'.$cl->client_version] = 0;
                    }
                    $clientDayDataTmp['v-'.$cl->client_version]++;
                }
                if(!array_key_exists('v-'.$cl->client_version,$labelsTmp)) {
                    $labelsTmp['v-'.$cl->client_version] = $cl->client_version;
                }
                if(!array_key_exists('v-'.$cl->client_version,$dataTmp)) {
                    $dataTmp['v-'.$cl->client_version] = 0;
                }
                $dataTmp['v-'.$cl->client_version]++;

                if(!array_key_exists('c-'.$cl->country,$countryLabelsTmp)) {
                    $countryLabelsTmp['c-'.$cl->country] = $cl->country;
                }
                if(!array_key_exists('c-'.$cl->country,$countryDataTmp)) {
                    $countryDataTmp['c-'.$cl->country] = 0;
                }
                $countryDataTmp['c-'.$cl->country]++;
            }

            foreach($labelsTmp as $vKey => $vName) {
                $clientStats['labels'][] = $vName;
                $clientStats['data'][] = $dataTmp[$vKey];
            }

            foreach($countryLabelsTmp as $vKey => $vName) {
                $countryStats['labels'][] = $vName;
                $countryStats['data'][] = $countryDataTmp[$vKey];
            }

            foreach($clientDayLabelsTmp as $cKey => $cName) {
                $clientDayStats['labels'][] = $cName;
                $clientDayStats['data'][] = $clientDayDataTmp[$cKey];
            }

            /**
             * Daily Created Users
             */
            $dauWh = new Where();
            $dauWh->like('stats_key', 'users-created-d-%');
            $dauSel = new Select($this->mCoreStatsTbl->getTable());
            $dauSel->where($dauWh);
            $dauSel->order('stats_key DESC');
            $dauSel->limit(30);
            $dailyActiveUsers = $this->mCoreStatsTbl->selectWith($dauSel);

            $dcuStats = [
                'labels' => [],
                'data' => []
            ];
            foreach($dailyActiveUsers as $dau) {
                $dateFromKey = substr($dau->stats_key, strlen('users-created-d-'));
                $dcuStats['labels'][] = date('Y-m-d', strtotime($dateFromKey));
                $dcuStats['data'][] = $dau->data;
            }

            $dcuStats['labels'] = array_reverse($dcuStats['labels']);
            $dcuStats['data'] = array_reverse($dcuStats['data']);


            return [
                'dau' => $dauStats,
                'mau' => $mauStats,
                'muu' => $muuStats,
                'clv' => $clientStats,
                'cld' => $clientDayStats,
                'dcu' => $dcuStats,
                'mcd' => $countryStats
            ];
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not alloawed'));
    }

    private function getUserRichList(): array
    {
        $rlSel = new Select($this->mUserTbl->getTable());
        $rlSel->order('token_balance DESC');
        $rlSel->limit(51);

        $richList = $this->mUserTbl->selectWith($rlSel);
        $users = [];

        $bannedUsersById = [];
        $bannedUsers = $this->mUserSetTbl->select(['setting_name' => 'user-tempban']);
        foreach($bannedUsers as $bannedUser) {
            if(!in_array($bannedUser->user_idfs,$bannedUsersById)) {
                $bannedUsersById[] = $bannedUser->user_idfs;
            }
        }

        foreach($richList as $user) {
            if($user->User_ID == 335874987) {
                continue;
            }
            if(in_array($user->User_ID, $bannedUsersById)) {
                continue;
            }
            $bfWth = new Where();
            $bfWth->equalTo('user_idfs', $user->User_ID);
            $bfWth->greaterThanOrEqualTo('days_left', 1);
            $buffs = $this->mWthBuffTbl->select($bfWth);
            $buffCoin = 0;
            if($buffs->count() > 0) {
                foreach($buffs as $bf) {
                    $buffCoin+=$bf->amount;
                }
            }
            $users[] = [
                'id' => $user->User_ID,
                'name' => $user->username,
                'token_balance' => $user->token_balance,
                'wth_buff' => $buffCoin
            ];
        }

        return [
            'richlist' => $users
        ];
    }
}
