<?php
/**
 * GuildResource.php - Guild Resource
 *
 * Main Resource for Faucet Guilds
 *
 * @category Resource
 * @package Guild
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Guild\V1\Rest\Guild;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Http\ClientStatic;
use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Session\Container;
use Faucet\Transaction\TransactionHelper;

class GuildResource extends AbstractResourceListener
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Focus Table
     *
     * @var TableGateway $mFocusTbl
     * @since 1.0.0
     */
    protected $mFocusTbl;

    /**
     * Guild Focus Table
     *
     * @var TableGateway $mGuildFocusTbl
     * @since 1.0.0
     */
    protected $mGuildFocusTbl;

    /**
     * Guild Rank Table
     *
     * @var TableGateway $mGuildRankTbl
     * @since 1.0.0
     */
    protected $mGuildRankTbl;

    /**
     * Guild Task Table
     *
     * @var TableGateway $mGuildTaskTbl
     * @since 1.0.0
     */
    protected $mGuildTaskTbl;

    /**
     * Guild Achievement Table
     *
     * @var TableGateway $mGuildAchievTbl
     * @since 1.0.0
     */
    protected $mGuildAchievTbl;

    /**
     * Guild Statistics Table
     *
     * @var TableGateway $mGuildWeeklyStatusTbl
     * @since 1.0.0
     */
    protected $mGuildWeeklyStatusTbl;

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
     * Guild Chat Ban Table
     *
     * @var TableGateway $mGuildChatBanTbl
     * @since 1.0.0
     */
    protected $mGuildChatBanTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

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
     * Guild XP Level Table
     *
     * @var TableGateway $mXPLvlTbl
     * @since 1.0.0
     */
    protected $mXPLvlTbl;

    /**
     * Guild Rank Permission Table
     *
     * @var TableGateway $mRankPermTbl
     * @since 1.0.0
     */
    protected $mRankPermTbl;

    /**
     * @var TableGateway
     */
    private $mWeeklyDoneTbl;
    /**
     * @var TableGateway
     */
    private $mContestWinnerTbl;

    /**
     * Constructor
     *
     * AchievementResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mGuildRankTbl = new TableGateway('faucet_guild_rank', $mapper);
        $this->mFocusTbl = new TableGateway('faucet_guild_focus', $mapper);
        $this->mGuildFocusTbl = new TableGateway('faucet_guild_focus_guild', $mapper);
        $this->mGuildTaskTbl = new TableGateway('faucet_guild_weekly', $mapper);
        $this->mGuildAchievTbl = new TableGateway('faucet_guild_achievement', $mapper);
        $this->mGuildWeeklyStatusTbl = new TableGateway('faucet_guild_weekly_status', $mapper);
        $this->mGuildChatBanTbl = new TableGateway('faucet_guild_chat_ban', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mXPLvlTbl = new TableGateway('user_xp_level', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mRankPermTbl = new TableGateway('faucet_guild_rank_permission', $mapper);
        $this->mWeeklyDoneTbl = new TableGateway('faucet_guild_weekly_claim', $mapper);
        $this->mContestWinnerTbl = new TableGateway('faucet_contest_winner', $mapper);

        $this->mSession = new Container('webauth');
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function create($data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) == 0) {
            # check if user has enough funds to create a guild
            $guildPrice = 5000;
            if($this->mTransaction->checkUserBalance($guildPrice,$me->User_ID)) {
                # create guild
                $guildName = $data->name;
                $guildMessage = $data->welcome_message;
                $guildDescription = $data->description;
                $guildLanguage = $data->language;
                $guildShield = $data->shield;
                $guildIcon = $data->icon;

                $secResult = $this->mSecTools->basicInputCheck([$guildName,$guildIcon,$guildMessage,$guildDescription,$guildShield,$guildLanguage]);
                if($secResult !== 'ok') {
                    return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                }

                $guildName = substr(filter_var($guildName, FILTER_SANITIZE_STRING),0,150);
                $guildMessage = substr(filter_var($guildMessage, FILTER_SANITIZE_STRING),0,250);
                $guildDescription = substr(filter_var($guildDescription, FILTER_SANITIZE_STRING),0,250);
                $guildIcon = filter_var($guildIcon, FILTER_SANITIZE_NUMBER_INT);
                $guildShield = filter_var($guildShield, FILTER_SANITIZE_NUMBER_INT);
                $guildLanguage = substr(filter_var($guildLanguage, FILTER_SANITIZE_STRING),0,2);

                # remove all join requests
                if($me->User_ID != 0) {
                    $this->mGuildUserTbl->delete(['user_idfs' => $me->User_ID]);
                }

                $fFaucet = filter_var($data->focus_faucet, FILTER_SANITIZE_NUMBER_INT);
                $fSH = filter_var($data->focus_shortlinks, FILTER_SANITIZE_NUMBER_INT);
                $fOF = filter_var($data->focus_offerwalls, FILTER_SANITIZE_NUMBER_INT);
                $fLot = filter_var($data->focus_lottery, FILTER_SANITIZE_NUMBER_INT);
                $fMin = filter_var($data->focus_mining, FILTER_SANITIZE_NUMBER_INT);

                // enforce 1 and 0
                $focus = [
                    'f' => ($fFaucet == 1) ? 1 : 0,
                    'sl' => ($fSH == 1) ? 1 : 0,
                    'of' => ($fOF == 1) ? 1 : 0,
                    'lt' => ($fLot == 1) ? 1 : 0,
                    'm' => ($fMin == 1) ? 1 : 0,
                ];

                $guildData = [
                    'label' => $guildName,
                    'description' => $guildDescription,
                    'welcome_message' => $guildMessage,
                    'focus' => json_encode($focus),
                    'owner_idfs' => $me->User_ID,
                    'created_date' => date('Y-m-d H:i:s', time()),
                    'xp_level' => 1,
                    'xp_current' => 0,
                    'xp_total' => 0,
                    'members' => 1,
                    'icon' => '',
                    'emblem_shield' => $guildShield,
                    'emblem_icon' => $guildIcon,
                    'is_vip' => 0,
                    'token_balance' => 0,
                    'main_language' => $guildLanguage,
                    'sort_id' => 99
                ];
                if($this->mGuildTbl->insert($guildData)) {
                    # get id of new guild
                    $newGuildId = $this->mGuildTbl->lastInsertValue;

                    # join the new guild as guildmaster
                    $this->mGuildUserTbl->insert([
                        'guild_idfs' => $newGuildId,
                        'user_idfs' => $me->User_ID,
                        'rank' => 0,
                        'date_requested' => date('Y-m-d H:i:s', time()),
                        'date_joined' => date('Y-m-d H:i:s', time()),
                        'date_declined' => '0000-00-00 00:00:00',
                    ]);

                    # set guild on user table for joins
                    $this->mUserTbl->update([
                        'user_guild_idfs' => $newGuildId
                    ],['User_ID' => $me->User_ID]);

                    # create guild ranks
                    $guildRanks = [0 => 'Guildmaster',1 => 'Officer',2 => 'Veteran', 3 => 'Member',9 => 'Newbie'];
                    foreach(array_keys($guildRanks) as $rankLevel) {
                        $isDefault = 0;
                        if($rankLevel == 9) {
                            $isDefault = 1;
                        }
                        $this->mGuildRankTbl->insert([
                            'guild_idfs' => $newGuildId,
                            'level' => $rankLevel,
                            'label' => $guildRanks[$rankLevel],
                            'is_default' => $isDefault
                        ]);
                    }

                    # deduct cost from user balance
                    $newBalance = $this->mTransaction->executeTransaction($guildPrice, 1, $me->User_ID, $newGuildId, 'create-guild', 'Guild '.$guildName.' created');
                    if($newBalance) {
                        $guildInfo = (object)$guildData;
                        unset($guildInfo->owner_idfs);
                        $guild = (object)[
                            'id' => $newGuildId,
                            'name' => $guildName,
                            'icon' => $guildIcon,
                            'xp_level' => 1,
                            'xp_total' => 0,
                            'xp_current' => 0,
                            'xp_percent' => 0,
                            'token_balance' => 0,
                            'rank' => (object)['id' => 0, 'name' => 'Guildmaster'],
                        ];

                        return [
                            'guild' => $guild,
                        ];
                        //$guildInfo->owner = (object)['id' => $me->User_ID, 'name' => $me->username,'token_balance' => $newBalance];
                        //return $guildInfo;
                    } else {
                        return new ApiProblem(409, 'There was an error in your coin transaction. Please contact admin');
                    }
                } else {
                    return new ApiProblem(500, 'There was an error while creating the guild (db error). Please contact admin');
                }
            }
        } else {
            # load existing guild info
            $guild = $this->mGuildTbl->select(['Guild_ID' => $userHasGuild->current()->guild_idfs]);
            # make sure guild does still exist
            if(count($guild) > 0) {
                $guild = $guild->current();
                return new ApiProblem(409, 'User is already member of the guild '.$guild->label.'. Please leave guild before creating one');
            } else {
                return new ApiProblem(409, 'User is already member of a removed guild. please contact admin.');
            }
        }
    }

    /**
     * Leave the current guild
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function delete($id)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) == 0) {
            return new ApiProblem(404, 'User is not part of any guild.');
        } else {
            /**
             * Leave Guild
             */
            if($id == 'leave') {
                $captcha = filter_var($_REQUEST['captcha'], FILTER_SANITIZE_STRING);
                # Check which captcha secret key we should load
                $captchaKey = 'recaptcha-secret-login';
                # check captcha (google v2)
                $captchaSecret = $this->mSecTools->getCoreSetting($captchaKey);
                if($captchaSecret) {
                    $response = ClientStatic::post(
                        'https://www.google.com/recaptcha/api/siteverify', [
                        'secret' => $captchaSecret,
                        'response' => $captcha
                    ]);

                    $status = $response->getStatusCode();
                    $googleResponse = $response->getBody();

                    $googleJson = json_decode($googleResponse);

                    if(!$googleJson->success) {
                        return new ApiProblem(400, 'Captcha not valid. Please try again or contact support.');
                    }
                }

                $userGuildInfo = $userHasGuild->current();
                if($userGuildInfo->rank == 0) {
                    $gmCount = $this->mGuildUserTbl->select(['guild_idfs' => $userGuildInfo->guild_idfs,'rank' => 0])->count();
                    if($gmCount == 1) {
                        $guildInfo = $this->mGuildTbl->select(['Guild_ID' => $userGuildInfo->guild_idfs]);
                        if($guildInfo->count() > 0) {
                            $guildInfo = $guildInfo->current();
                        }
                        $memberCount = $this->mGuildUserTbl->select(['guild_idfs' => $userGuildInfo->guild_idfs])->count();
                        if($memberCount > 1) {
                            return new ApiProblem(403, 'You cannot leave the guild as guildmaster. Please promote a new guildmaster first or remove all members so the guild is empty.');
                        }
                        if($guildInfo->token_balance > 0) {
                            return new ApiProblem(403, 'You still have Coins in your Guild Bank. Please withraw them all before deleting the guild');
                        }
                    }
                }

                # make sure user id is not zero before delete
                if($me->User_ID == 0) {
                    return new ApiProblem(400, 'invalid user id');
                }

                # leave guild
                $this->mGuildUserTbl->delete([
                    'user_idfs' => $me->User_ID,
                    'guild_idfs' => $userGuildInfo->guild_idfs
                ]);

                # remove guild if empty
                $membersLeft = $this->mGuildUserTbl->select([
                    'guild_idfs' => $userGuildInfo->guild_idfs
                ])->count();
                if($membersLeft == 0) {
                    $this->mGuildTbl->delete(['Guild_ID' => $userGuildInfo->guild_idfs]);
                }

                return true;
            } elseif($id == 'remove') {
                /**
                 * Remove Guild
                 */
                $userGuildInfo = $userHasGuild->current();
                if($userGuildInfo->rank == 0) {
                    # make sure user is is not zero before delete
                    if($me->User_ID == 0) {
                        return new ApiProblem(400, 'invalid user id');
                    }

                    # leave guild
                    $this->mGuildUserTbl->delete([
                        'user_idfs' => $me->User_ID,
                        'guild_idfs' => $userGuildInfo->guild_idfs
                    ]);

                    # delete guild
                    $this->mGuildTbl->delete([
                        'Guild_ID' => $userGuildInfo->guild_idfs
                    ]);

                    return true;
                } else {
                    return new ApiProblem(403, 'You must own a guild to delete it.');
                }
            }
            return new ApiProblem(400, 'Invalid request');
        }
    }

    /**
     * Delete a collection, or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
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
     * @since 1.0.0
     */
    public function fetch($id)
    {
        $guildId = 0;
        if(isset($_REQUEST['mode'])) {
            $secResult = $this->mSecTools->basicInputCheck([$id]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
            }

            $guildUrl = filter_var($id, FILTER_SANITIZE_STRING);

            $guildFound = $this->mGuildTbl->select(['page_url' => $guildUrl]);
            if($guildFound->count() > 0) {
                $guild = $guildFound->current();
                $guildId = $guild->Guild_ID;

                return [
                    'guild' => [
                        'id' => $guildId,
                        'name' => $guild->label,
                        'emblem_shield' => $guild->emblem_shield,
                        'emblem_icon' => $guild->emblem_icon,
                    ]
                ];
            } else {
                return new ApiProblem(404, 'Invalid Guild Invite Link');
            }
        } else {
            # Prevent 500 error
            if (!$this->getIdentity()) {
                return new ApiProblem(401, 'Not logged in');
            }
            $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
            if (get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
                return $me;
            }

            $guildId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

            $guild = $this->mGuildTbl->select(['Guild_ID' => $guildId]);
            if (count($guild) == 0) {
                return new ApiProblem(404, 'Guild not found');
            }
            $guild = $guild->current();
        }

        if(isset($_REQUEST['weekly'])) {
            $gTaskWeek = $this->mSecTools->getWeek(time());
            $weekInfo = explode('-', $gTaskWeek);
            $weekNo = $weekInfo[0];
            $yearNo = $weekInfo[1];

            /**
             * Get Weekly Tasks Progress
             */
            $weeklyStatus = [];
            $statCheckWh = new Where();
            $statCheckWh->equalTo('guild_idfs', $guild->Guild_ID);
            $statCheckWh->equalTo('week', $weekNo);
            $statCheckWh->equalTo('year', $yearNo);
            $statCheck = $this->mGuildWeeklyStatusTbl->select($statCheckWh);
            foreach($statCheck as $stat) {
                $weeklyStatus[$stat->weekly_key] = $stat->progress;
            }

            /**
             * Load Guild Tasks List (Weeklys)
             */
            $weeklyTasks = [];
            $weeklysDB = $this->mGuildTaskTbl->select(['series' => 0, 'active' => 1]);
            $tasksDone = 0;
            $taskEarnings = 0;
            foreach($weeklysDB as $weekly) {
                $progress = 0;
                if(array_key_exists($weekly->target_mode, $weeklyStatus)) {
                    $progress = $weeklyStatus[$weekly->target_mode];
                }
                if($progress >= $weekly->target) {
                    $tasksDone++;
                    $taskEarnings+=$weekly->reward;
                    $nextLvl = $this->mGuildTaskTbl->select(['series' => $weekly->Weekly_ID, 'active' => 1]);
                    if($nextLvl->count() == 0) {
                        $weeklyTasks[] = (object)[
                            'id' => $weekly->Weekly_ID,
                            'name' => $weekly->label,
                            'description' => $weekly->description,
                            'target' => $weekly->target,
                            'target_mode' => $weekly->target_mode,
                            'reward' => $weekly->reward,
                            'current' => $progress,
                        ];
                    } else {
                        $nextLvl = $nextLvl->current();
                        if($progress >= $nextLvl->target) {
                            $tasksDone++;
                            $taskEarnings+=$weekly->reward;
                            $nextLvl2 = $this->mGuildTaskTbl->select(['series' => $nextLvl->Weekly_ID, 'active' => 1]);
                            if($nextLvl2->count() == 0) {
                                $weeklyTasks[] = (object)[
                                    'id' => $nextLvl->Weekly_ID,
                                    'name' => $nextLvl->label,
                                    'description' => $nextLvl->description,
                                    'target' => $nextLvl->target,
                                    'target_mode' => $nextLvl->target_mode,
                                    'reward' => $nextLvl->reward,
                                    'current' => $progress,
                                ];
                            } else {
                                $nextLvl2 = $nextLvl2->current();
                                $weeklyTasks[] = (object)[
                                    'id' => $nextLvl2->Weekly_ID,
                                    'name' => $nextLvl2->label,
                                    'description' => $nextLvl2->description,
                                    'target' => $nextLvl2->target,
                                    'target_mode' => $nextLvl2->target_mode,
                                    'reward' => $nextLvl2->reward,
                                    'current' => $progress,
                                ];
                            }
                        } else {
                            $weeklyTasks[] = (object)[
                                'id' => $nextLvl->Weekly_ID,
                                'name' => $nextLvl->label,
                                'description' => $nextLvl->description,
                                'target' => $nextLvl->target,
                                'target_mode' => $nextLvl->target_mode,
                                'reward' => $nextLvl->reward,
                                'current' => $progress,
                            ];
                        }
                    }
                } else {
                    $weeklyTasks[] = (object)[
                        'id' => $weekly->Weekly_ID,
                        'name' => $weekly->label,
                        'description' => $weekly->description,
                        'target' => $weekly->target,
                        'target_mode' => $weekly->target_mode,
                        'reward' => $weekly->reward,
                        'current' => $progress,
                    ];
                }
            }

            return [
                'tasks' => $weeklyTasks,
                'done' => $tasksDone,
                'earned' => $taskEarnings
            ];
        }

        /**
         * Get Guild Ranks
         */
        $ranks = [];
        $guildRanks = [];
        $guildRanksDB = $this->mGuildRankTbl->select(['guild_idfs' => $guild->Guild_ID]);
        if(count($guildRanksDB) > 0) {
            foreach($guildRanksDB as $rank) {
                $ranks[] = (object)[
                    'id' => $rank->level,
                    'name' => $rank->label,
                    'is_default' => $rank->is_default
                ];
                $guildRanks[$rank->level] = $rank->label;
            }
        }

        // user-xp-m-
        $xpStatSel = new Select($this->mGuildUserTbl->getTable());
        $xpStatSel->join(['ufc' => 'user_faucet_stat'], 'ufc.user_idfs = faucet_guild_user.user_idfs', ['stat_data']);
        $xpStatSel->where(['guild_idfs' => $guildId, 'ufc.stat_key' => 'user-xp-m-'.date('n-Y', time())]);
        $guildUserXp = $this->mGuildUserTbl->selectWith($xpStatSel);
        $gUserXpCache = [];
        foreach($guildUserXp as $gXp) {
            $gUserXpCache['user-'.$gXp->user_idfs] = $gXp->stat_data;
        }

        /**
         * Load Guild Members List (paginated)
         */
        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        if($page <= 0) {
            return new ApiProblem(400, 'Invalid Page');
        }
        $guildMembers = [];
        $memberSel = new Select($this->mGuildUserTbl->getTable());
        $memberSel->join(['user' => 'user'], 'user.User_ID = faucet_guild_user.user_idfs');
        $checkWh = new Where();
        $checkWh->equalTo('guild_idfs', $guildId);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $memberSel->where($checkWh);
        $memberSel->order(['rank ASC','user.username ASC']);
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $memberSel,
            # the adapter to run it against
            $this->mGuildUserTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $membersPaginated = new Paginator($oPaginatorAdapter);
        $membersPaginated->setCurrentPageNumber($page);
        $membersPaginated->setItemCountPerPage(25);
        foreach($membersPaginated as $guildMember) {
            $xpGained = 0;
            if(array_key_exists('user-'.$guildMember->User_ID, $gUserXpCache)) {
                $xpGained = $gUserXpCache['user-'.$guildMember->User_ID];
            }
            $guildMembers[] = (object)[
                'id' => $guildMember->User_ID,
                'name' => $guildMember->username,
                'avatar' => ($guildMember->avatar != '') ? $guildMember->avatar : $guildMember->username,
                'xp_level' => $guildMember->xp_level,
                'join_level' => $guildMember->join_level,
                'xp_gained' => $xpGained,
                'last_action' => $guildMember->last_action,
                'rank' => (object)[
                    'id' => $guildMember->rank,
                    'name'=> $guildRanks[$guildMember->rank]
                ]
            ];
        }
        $totalMembers = $this->mGuildUserTbl->select($checkWh)->count();

        $gTaskWeek = $this->mSecTools->getWeek(time());
        $weekInfo = explode('-', $gTaskWeek);
        $weekNo = $weekInfo[0];
        $yearNo = $weekInfo[1];

        /**
         * Get Weekly Tasks Progress
         */
        $weeklyStatus = [];
        $statCheckWh = new Where();
        $statCheckWh->equalTo('guild_idfs', $guild->Guild_ID);
        $statCheckWh->equalTo('week', $weekNo);
        $statCheckWh->equalTo('year', $yearNo);
        $statCheck = $this->mGuildWeeklyStatusTbl->select($statCheckWh);
        foreach($statCheck as $stat) {
            $weeklyStatus[$stat->weekly_key] = $stat->progress;
        }

        /**
         * Load Guild Tasks List (Weeklys)
         */
        $weeklyTasks = [];
        $weeklysDB = $this->mGuildTaskTbl->select(['series' => 0, 'active' => 1]);
        foreach($weeklysDB as $weekly) {
            $progress = 0;
            if(array_key_exists($weekly->target_mode, $weeklyStatus)) {
                $progress = $weeklyStatus[$weekly->target_mode];
            }
            if($progress >= $weekly->target) {
                $nextLvl = $this->mGuildTaskTbl->select(['series' => $weekly->Weekly_ID, 'active' => 1]);
                if($nextLvl->count() == 0) {
                    $weeklyTasks[] = (object)[
                        'id' => $weekly->Weekly_ID,
                        'name' => $weekly->label,
                        'description' => $weekly->description,
                        'target' => $weekly->target,
                        'target_mode' => $weekly->target_mode,
                        'reward' => $weekly->reward,
                        'current' => $progress,
                    ];
                } else {
                    $nextLvl = $nextLvl->current();
                    if($progress >= $nextLvl->target) {
                        $nextLvl2 = $this->mGuildTaskTbl->select(['series' => $nextLvl->Weekly_ID, 'active' => 1]);
                        if($nextLvl2->count() == 0) {
                            $weeklyTasks[] = (object)[
                                'id' => $nextLvl->Weekly_ID,
                                'name' => $nextLvl->label,
                                'description' => $nextLvl->description,
                                'target' => $nextLvl->target,
                                'target_mode' => $nextLvl->target_mode,
                                'reward' => $nextLvl->reward,
                                'current' => $progress,
                            ];
                        } else {
                            $nextLvl2 = $nextLvl2->current();
                            $weeklyTasks[] = (object)[
                                'id' => $nextLvl2->Weekly_ID,
                                'name' => $nextLvl2->label,
                                'description' => $nextLvl2->description,
                                'target' => $nextLvl2->target,
                                'target_mode' => $nextLvl2->target_mode,
                                'reward' => $nextLvl2->reward,
                                'current' => $progress,
                            ];
                        }
                    } else {
                        $weeklyTasks[] = (object)[
                            'id' => $nextLvl->Weekly_ID,
                            'name' => $nextLvl->label,
                            'description' => $nextLvl->description,
                            'target' => $nextLvl->target,
                            'target_mode' => $nextLvl->target_mode,
                            'reward' => $nextLvl->reward,
                            'current' => $progress,
                        ];
                    }
                }
            } else {
                $weeklyTasks[] = (object)[
                    'id' => $weekly->Weekly_ID,
                    'name' => $weekly->label,
                    'description' => $weekly->description,
                    'target' => $weekly->target,
                    'target_mode' => $weekly->target_mode,
                    'reward' => $weekly->reward,
                    'current' => $progress,
                ];
            }
        }

        /**
         * Load Guild Achievements
         */
        $achievements = [];
        $achievementsDB = $this->mGuildAchievTbl->select();
        foreach($achievementsDB as $achiev) {
            $achievements[] = (object)[
                'id' => $achiev->Achievement_ID,
                'name' => $achiev->label,
                'description' => $achiev->description,
                'target' => $achiev->target,
                'target_mode' => $achiev->target_mode,
                'reward' => $achiev->reward,
                'current' => 0,
            ];
        }

        # calculate guild xp percent
        $guildXPPercent = 0;
        if ($guild->xp_current != 0) {
            $guildNextLvl = $this->mXPLvlTbl->select(['Level_ID' => ($guild->xp_level + 1)])->current();
            $guildXPPercent = round((100 / ($guildNextLvl->xp_total / $guild->xp_current)), 2);
        }

        /**
         * Get open requests
         */
        $checkWh = new Where();
        $checkWh->equalTo('guild_idfs', $guildId);
        $checkWh->like('date_joined', '0000-00-00 00:00:00');
        $checkWh->like('date_declined', '0000-00-00 00:00:00');
        $totalRequests = $this->mGuildUserTbl->select($checkWh)->count();

        /**
         * Check if user is part of guild
         */
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->equalTo('guild_idfs', $guild->Guild_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userJoinedGuild = $this->mGuildUserTbl->select($checkWh);

        $myRank = (object)[];
        $myChatBans = [];
        $myBansFound = $this->mGuildChatBanTbl->select(['user_idfs' => $me->User_ID]);
        if(count($myBansFound) > 0) {
            foreach($myBansFound as $ban) {
                $myChatBans[] = (int)$ban->ban_user_idfs;
            }
        }

        $requestOpen = false;
        if(count($userJoinedGuild) > 0) {
            $guildRank = $userJoinedGuild->current();
            $rankDB = $this->mGuildRankTbl->select([
                'guild_idfs' => $guild->Guild_ID,
                'level' => $guildRank->rank,
            ]);
            if(count($rankDB) > 0) {
                $rank = $rankDB->current()->label;
                $canInvite = false;
                $invitePerm = $this->mRankPermTbl->select(['rank_idfs' => $guildRank->rank,'guild_idfs' => $guild->Guild_ID,'permission' => 'invite']);
                if($invitePerm->count() > 0) {
                    $canInvite = true;
                }

                $myRank = (object)['id' => (int)$guildRank->rank, 'name' => $rank,'can_invite' => $canInvite];
            }
        } else {
            /**
             * Check if user is part of guild
             */
            $checkWh = new Where();
            $checkWh->equalTo('user_idfs', $me->User_ID);
            $checkWh->equalTo('guild_idfs', $guild->Guild_ID);
            $checkWh->like('date_joined', '0000-00-00 00:00:00');
            $userRequestedJoin = $this->mGuildUserTbl->select($checkWh);
            if($userRequestedJoin->count() > 0) {
                $requestOpen = true;
            }
        }


        $weeklyTotal = 0;
        $weeklyEarnings = 0;
        $weeklysDone = $this->mWeeklyDoneTbl->select(['guild_idfs' => $guild->Guild_ID]);
        foreach($weeklysDone as $weekly) {
            $weeklyTotal++;
            $weeklyEarnings+=$weekly->reward;
        }

        $contestEarnings = 0;
        $wonContests = $this->mContestWinnerTbl->select(['contest_idfs' => 1, 'user_idfs'  => $guild->Guild_ID]);
        foreach($wonContests as $win) {
            $contestEarnings+=$win->reward;
        }

        return (object)[
            'guild' => (object)[
                'id' => $guild->Guild_ID,
                'name'=> $guild->label,
                'description'=> $guild->description,
                'icon' => $guild->icon,
                'language' => $guild->main_language,
                'emblem_shield' => $guild->emblem_shield,
                'emblem_icon' => $guild->emblem_icon,
                'link' => $guild->page_url,
                'week_no' => $weekNo,
                'weeklys_total' => $weeklyTotal,
                'weeklys_earnings' => $weeklyEarnings,
                'contest_earnings' => $contestEarnings,
                'is_vip' => ($guild->is_vip == 1) ? true : false,
                'token_balance' => $guild->token_balance,
                'xp_level' => $guild->xp_level,
                'xp_percent' => $guildXPPercent,
                'focus' => json_decode($guild->focus),
                'members' => $guildMembers,
                'chat_banlist' => $myChatBans,
                'welcome_message' => $guild->welcome_message,
                'tasks' => $weeklyTasks,
                'ranks' => $ranks,
                'my_rank' => $myRank,
                'request_open' => $requestOpen,
                'achievements' => $achievements,
                'total_members' => $totalMembers,
                'total_requests' => $totalRequests,
                'page_count' => round($totalMembers/25),
                'page_size' => 25,
                'page' => $page,
            ],
        ];
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     * @since 1.0.0
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

        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $focusFilter = (isset($_REQUEST['focus'])) ? filter_var($_REQUEST['focus'], FILTER_SANITIZE_NUMBER_INT) : 0;
        $sizeFilter = (isset($_REQUEST['size'])) ? filter_var($_REQUEST['size'], FILTER_SANITIZE_STRING) : 'all';
        $langFilter = (isset($_REQUEST['lang'])) ? filter_var($_REQUEST['lang'], FILTER_SANITIZE_STRING) : 'all';

        $guildWh = new Where();
        # Compile list of all guilds
        $guilds = [];
        $guildSel = new Select($this->mGuildTbl->getTable());
        if($focusFilter != 0) {
            $guildSel->join(['gf' => 'faucet_guild_focus_guild'], 'gf.guild_idfs = faucet_guild.Guild_ID');
            $guildWh->equalTo('gf.focus_idfs', $focusFilter);
        }
        if($sizeFilter != 'all') {
            switch($sizeFilter) {
                case 's':
                    $guildWh->lessThanOrEqualTo('members', 50);
                    break;
                case 'm':
                    $guildWh->greaterThan('members', 50);
                    $guildWh->lessThanOrEqualTo('members', 250);
                    break;
                case 'l':
                    $guildWh->greaterThan('members', 250);
                    break;
                default:
                    break;
            }
        }
        if($langFilter != 'all') {
            $guildWh->like('main_language', $langFilter);
        }
        $guildSel->where($guildWh);
        $guildSel->order(['sort_id ASC']);
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $guildSel,
            # the adapter to run it against
            $this->mGuildTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $guildsPaginated = new Paginator($oPaginatorAdapter);
        $guildsPaginated->setCurrentPageNumber($page);
        $guildsPaginated->setItemCountPerPage(4);

        $totalGuilds = $this->mGuildTbl->selectWith($guildSel)->count();

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->like('date_joined', '0000-00-00 00:00:00');
        $userRequestsDB = $this->mGuildUserTbl->select($checkWh);
        $userRequests = [];
        if(count($userRequestsDB) > 0){
            foreach($userRequestsDB as $req) {
                $userRequests[$req->guild_idfs] = $req->date_requested;
            }
        }

        foreach($guildsPaginated as $guild) {
            # count guild members
            $guild->members = $this->mGuildUserTbl->select(['guild_idfs' => $guild->Guild_ID])->count();
            $gFocus = [];
            $focusSel = new Select($this->mGuildFocusTbl->getTable());
            $focusSel->join(['f' => 'faucet_guild_focus'],'f.Focus_ID = faucet_guild_focus_guild.focus_idfs');
            $focusSel->where(['faucet_guild_focus_guild.guild_idfs' => $guild->Guild_ID]);
            $focusGuild = $this->mGuildFocusTbl->selectWith($focusSel);
            if($focusGuild->count() > 0) {
                foreach($focusGuild as $f) {
                    $gFocus[] = (object)[
                        'id' => $f->focus_idfs,
                        'name' => utf8_encode($f->label),
                        'icon' => $f->icon
                    ];
                }
            }
            $guildAPI = (object)[
                'id' => $guild->Guild_ID,
                'name' => $guild->label,
                'focus' => json_decode($guild->focus),
                'guild_focus' => $gFocus,
                'description' => $guild->description,
                'members' => $guild->members,
                'xp_level' => $guild->xp_level,
                'xp_current' => $guild->xp_current,
                'xp_total' => $guild->xp_total,
                'icon' => $guild->icon,'is_vip' => ($guild->is_vip == 1) ? true : false,
                'emblem_shield' => $guild->emblem_shield,
                'emblem_icon' => $guild->emblem_icon,
            ];
            if(array_key_exists($guild->Guild_ID,$userRequests)) {
                $guildAPI->userHasRequestOpen = 1;
            } else {
                $guildAPI->userHasRequestOpen = 0;
            }
            $guilds[] =$guildAPI;
        }

        if(isset($_REQUEST['v2'])) {
            $topSel = new Select($this->mGuildTbl->getTable());
            $topSel->order('members DESC');
            $topSel->limit(5);
            $bigGuilds = $this->mGuildTbl->selectWith($topSel);
            $guildsBig = [];
            foreach($bigGuilds as $bg) {
                $guildsBig[] = (object)[
                    'id' => $bg->Guild_ID,
                    'name' => utf8_decode($bg->label),
                    'focus' => json_decode($bg->focus),
                    'description' => utf8_decode($bg->description),
                    'members' => $bg->members,
                    'xp_level' => $bg->xp_level,
                    'xp_current' => $bg->xp_current,
                    'xp_total' => $bg->xp_total,
                    'icon' => $bg->icon,'is_vip' => ($bg->is_vip == 1) ? true : false];
            }

            $newSel = new Select($this->mGuildTbl->getTable());
            $newSel->order('created_date DESC');
            $newSel->limit(4);
            $newGuilds = $this->mGuildTbl->selectWith($newSel);
            $guildsNew = [];
            foreach($newGuilds as $ng) {
                if(array_key_exists($ng->Guild_ID,$userRequests)) {
                    $ng->userHasRequestOpen = 1;
                } else {
                    $ng->userHasRequestOpen = 0;
                }
                $guildsNew[] = (object)[
                    'id' => $ng->Guild_ID,
                    'name' => $ng->label,
                    'focus' => json_decode(filter_var($ng->focus, FILTER_SANITIZE_STRING)),
                    'description' => filter_var($ng->description, FILTER_SANITIZE_STRING),
                    'members' => $ng->members,
                    'xp_level' => $ng->xp_level,
                    'xp_current' => $ng->xp_current,
                    'emblem_shield' => $ng->emblem_shield,
                    'emblem_icon' => $ng->emblem_icon,
                    'xp_total' => $ng->xp_total,
                    'userHasRequestOpen' => $ng->userHasRequestOpen,
                    'icon' => $ng->icon,'is_vip' => ($ng->is_vip == 1) ? true : false];
            }

            $guildsV2 = [
                'big' => $guildsBig,
                'new' => $guildsNew,
                'list' => $guilds
            ];

            $guilds = $guildsV2;
        }

        $focus = [];
        $guildFocus = $this->mFocusTbl->select();
        foreach($guildFocus as $gf) {
            $focus[] = [
                'id' => $gf->Focus_ID,
                'name' => $gf->label
            ];
        }

        // TODO: Remove static URL
        return (object)[
            '_links' => (object)['self' => (object)['href' => 'https://xi.api.swissfaucet.io/guild']],
            '_embedded' => (object)['guild' => $guilds],
            'total_items' => $totalGuilds,
            'page_count' => round($totalGuilds/4),
            'page_size' => 4,
            'page' => $page,
            'focus' => $focus
        ];
    }

    /**
     * Update Guild Information
     *
     * Only available for Guildmaster
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
     */
    public function replaceList($data)
    {
        return new ApiProblem(405, 'The PUT method has not been defined for collections');
    }

    /**
     * Join a Guild
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function update($id, $data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if($id == 'update') {
            # check if user is guildmaster of a guild
            $checkWh = new Where();
            $checkWh->equalTo('user_idfs', $me->User_ID);
            $checkWh->equalTo('rank', 0);
            $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
            $userHasGuild = $this->mGuildUserTbl->select($checkWh);

            if(count($userHasGuild) == 0) {
                return new ApiProblem(404, 'You are not guildmaster of any guild');
            } else {
                $userGuildRole = $userHasGuild->current();
                # double check we have no 0 guild id
                if($userGuildRole->guild_idfs == 0) {
                    return new ApiProblem(400, 'Seems like you are guildmaster of an invalid guild. Please contact admin.');
                }
                $guild = $this->mGuildTbl->select(['Guild_ID' => $userGuildRole->guild_idfs]);
                if($guild->count() == 0) {
                    return new ApiProblem(404, 'Guild not found');
                }
                $guild = $guild->current();
                # check if guild invite link should be updated
                if(isset($data->link)) {
                    $secResult = $this->mSecTools->basicInputCheck([$data->link]);
                    if($secResult !== 'ok') {
                        # ban user and force logout on client
                        $this->mUserSetTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'setting_name' => 'user-tempban',
                            'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guild Rename',
                        ]);
                        return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                    }
                    # guild can be renamed only once per 7 days
                    $lastRenameCounter = $this->mTransaction->findGuildTransaction($userGuildRole->guild_idfs, date('Y-m-d H:i:s', strtotime('-7 days')),'guild-link');
                    if($lastRenameCounter) {
                        return new ApiProblem(400, 'You can change your invite link only once per week (7 days)');
                    }

                    # get new name
                    $newLink = strtolower(filter_var($data->link, FILTER_SANITIZE_STRING));
                    if(strlen($newLink) < 5) {
                        return new ApiProblem(418, 'Invalid link');
                    }
                    # check if there is already a guild with a likely name
                    $likeWh = new Where();
                    $likeWh->like('page_url', $newLink.'%');
                    $nameUsed = $this->mGuildTbl->select($likeWh);
                    if(count($nameUsed) > 0) {
                        return new ApiProblem(400, 'Another Guild already has the same invite link. Please choose another one');
                    }
                    # check if guild has enough balance for renaming
                    $renamePrice = 1000;
                    if($this->mTransaction->checkGuildBalance($renamePrice, $userGuildRole->guild_idfs)) {

                        $newBalance = $this->mTransaction->executeGuildTransaction($renamePrice, true, $userGuildRole->guild_idfs, $userGuildRole->guild_idfs, 'guild-link', 'Guild Invite Link activated or changed', $me->User_ID);

                        # rename
                        if($newBalance !== false) {
                            $this->mGuildTbl->update([
                                'page_url' => $newLink,
                            ],[
                                'Guild_ID' => $userGuildRole->guild_idfs,
                            ]);

                            return [
                                'token_balance' => $newBalance,
                                'link' => $newLink,
                            ];
                        } else {
                            return new ApiProblem(500, 'Transaction error. Please contact support.');
                        }
                    } else {
                        return new ApiProblem(400, 'Guild Balance is too low for rename');
                    }
                }
                # check if name should be updated
                if(isset($data->name)) {
                    $secResult = $this->mSecTools->basicInputCheck([$data->name]);
                    if($secResult !== 'ok') {
                        # ban user and force logout on client
                        $this->mUserSetTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'setting_name' => 'user-tempban',
                            'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guild Rename',
                        ]);
                        return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                    }
                    # guild can be renamed only once per 7 days
                    $lastRenameCounter = $this->mTransaction->findGuildTransaction($userGuildRole->guild_idfs, date('Y-m-d H:i:s', strtotime('-7 days')),'guild-rename');
                    if($lastRenameCounter) {
                        return new ApiProblem(400, 'You can rename your guild only once per week (7 days)');
                    }

                    # get new name
                    $newName = filter_var($data->name, FILTER_SANITIZE_STRING);
                    if(strlen($newName) < 5) {
                        return new ApiProblem(400, 'Invalid guild name');
                    }

                    # check if there is already a guild with a likely name
                    $likeWh = new Where();
                    $likeWh->like('label', $newName.'%');
                    $nameUsed = $this->mGuildTbl->select($likeWh);
                    if(count($nameUsed) > 0) {
                        return new ApiProblem(400, 'There is already a guild with that name. Please choose another one');
                    }
                    # check if guild has enough balance for renaming
                    $renamePrice = 1000;
                    if($this->mTransaction->checkGuildBalance($renamePrice, $userGuildRole->guild_idfs)) {

                        $newBalance = $this->mTransaction->executeGuildTransaction($renamePrice, true,
                            $userGuildRole->guild_idfs, $userGuildRole->guild_idfs,
                            'guild-rename', 'Renamed Guild from '.$guild->label.' to '.$newName, $me->User_ID);

                        # rename
                        if($newBalance !== false) {
                            $this->mGuildTbl->update([
                                'label' => $newName,
                            ],[
                                'Guild_ID' => $userGuildRole->guild_idfs,
                            ]);

                            return [
                                'token_balance' => $newBalance,
                                'name' => $newName,
                            ];
                        } else {
                            return new ApiProblem(500, 'Transaction error. Please contact support.');
                        }
                    } else {
                        return new ApiProblem(400, 'Guild Balance is too low for rename');
                    }

                }

                # check if language should be updated
                if(isset($data->language)) {
                    $secResult = $this->mSecTools->basicInputCheck([$data->language]);
                    if($secResult !== 'ok') {
                        # ban user and force logout on client
                        $this->mUserSetTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'setting_name' => 'user-tempban',
                            'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guild Rank Rename',
                        ]);
                        return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                    }

                    $newLang = filter_var($data->language, FILTER_SANITIZE_STRING);
                    if(strlen($newLang) > 3) {
                        return new ApiProblem(400, 'Invalid Language');
                    }

                    $this->mGuildTbl->update([
                        'main_language' => $newLang
                    ],['Guild_ID' => $guild->Guild_ID]);
                }

                # check if rank should be updated
                if(isset($data->rank_name)) {
                    $secResult = $this->mSecTools->basicInputCheck([$data->rank_name,$data->rank_id]);
                    if($secResult !== 'ok') {
                        # ban user and force logout on client
                        $this->mUserSetTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'setting_name' => 'user-tempban',
                            'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guild Rank Rename',
                        ]);
                        return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                    }

                    # get new name
                    $newName = filter_var($data->rank_name, FILTER_SANITIZE_STRING);
                    if(strlen($newName) < 5) {
                        return new ApiProblem(418, 'Invalid rank name');
                    }
                    $rankId = filter_var($data->rank_id, FILTER_SANITIZE_NUMBER_INT);

                    # check if there is already a guild with a likely name
                    $likeWh = new Where();
                    $likeWh->equalTo('guild_idfs', $userGuildRole->guild_idfs);
                    $likeWh->notEqualTo('level', $rankId);
                    $likeWh->like('label', utf8_encode($newName));
                    $nameUsed = $this->mGuildRankTbl->select($likeWh);
                    if(count($nameUsed) > 0) {
                        return new ApiProblem(400, 'There is already a rank with that name. Please choose another one');
                    }
                    # check if guild has enough balance for renaming
                    $renamePrice = 500;
                    if($this->mTransaction->checkGuildBalance($renamePrice, $userGuildRole->guild_idfs)) {

                        $newBalance = $this->mTransaction->executeGuildTransaction($renamePrice, true,
                            $userGuildRole->guild_idfs, $userGuildRole->guild_idfs,
                            'rank-rename', 'Renamed Rank '.$rankId.' to '.$newName, $me->User_ID);

                        # rename
                        if($newBalance !== false) {
                            $this->mGuildRankTbl->update([
                                'label' => utf8_encode($newName),
                            ],[
                                'guild_idfs' => $userGuildRole->guild_idfs,
                                'level' => $rankId
                            ]);

                            $ranks = [];
                            $guildRanks = $this->mGuildRankTbl->select(['guild_idfs' => $userGuildRole->guild_idfs]);
                            if(count($guildRanks) > 0) {
                                foreach($guildRanks as $rank) {
                                    $ranks[] = (object)[
                                        'id' => $rank->level,
                                        'name' => $rank->label,
                                    ];
                                }
                            }

                            return [
                                'token_balance' => $newBalance,
                                'ranks' => $ranks
                            ];
                        } else {
                            return new ApiProblem(500, 'Transaction error. Please contact support.');
                        }
                    } else {
                        return new ApiProblem(400, 'Guild Balance is too low for rank rename');
                    }
                }

                # check if description should be updated
                if(isset($data->description)) {
                    $secResult = $this->mSecTools->basicInputCheck([$data->description]);
                    if($secResult !== 'ok') {
                        # ban user and force logout on client
                        $this->mUserSetTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'setting_name' => 'user-tempban',
                            'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guild Description Update',
                        ]);
                        return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                    }
                    $newDescription = filter_var($data->description, FILTER_SANITIZE_STRING);
                    $this->mGuildTbl->update([
                        'description' => $newDescription,
                    ],[
                        'Guild_ID' => $userGuildRole->guild_idfs,
                    ]);
                }

                # check if emblem should be updated
                if(isset($data->emblem_shield) && isset($data->emblem_icon)) {
                    $renamePrice = 0;
                    if($this->mTransaction->checkGuildBalance($renamePrice, $userGuildRole->guild_idfs)) {

                        $newBalance = $this->mTransaction->executeGuildTransaction($renamePrice, true,
                            $userGuildRole->guild_idfs, $userGuildRole->guild_idfs,
                            'emblem-change', 'Emblem Change', $me->User_ID);

                        # rename
                        if ($newBalance !== false) {
                            $newShield = filter_var($data->emblem_shield, FILTER_SANITIZE_NUMBER_INT);
                            if($newShield <= 0 || $newShield > 5) {
                                return new ApiProblem(400, 'invalid icon');
                            }
                            $newIcon = filter_var($data->emblem_icon, FILTER_SANITIZE_NUMBER_INT);
                            if($newIcon <= 0 || $newIcon > 10) {
                                return new ApiProblem(400, 'invalid icon');
                            }
                            $this->mGuildTbl->update([
                                'emblem_shield' => $newShield,
                                'emblem_icon' => $newIcon,
                            ],[
                                'Guild_ID' => $userGuildRole->guild_idfs,
                            ]);
                        } else {
                            return new ApiProblem(400, 'Transaction Error. Please contact support');
                        }
                    } else {
                        return new ApiProblem(400, 'Guild Balance is too low for emblem change');
                    }
                }

                # check if description should be updated
                if(isset($data->welcome_message)) {
                    $secResult = $this->mSecTools->basicInputCheck([$data->welcome_message]);
                    if($secResult !== 'ok') {
                        # ban user and force logout on client
                        $this->mUserSetTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'setting_name' => 'user-tempban',
                            'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Welcome Message Update',
                        ]);
                        return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                    }
                    $newDescription = filter_var($data->welcome_message, FILTER_SANITIZE_STRING);
                    $this->mGuildTbl->update([
                        'welcome_message' => $newDescription,
                    ],[
                        'Guild_ID' => $userGuildRole->guild_idfs,
                    ]);
                }

                # check if description should be updated
                if(isset($data->focus)) {
                    $secResult = $this->mSecTools->basicInputCheck([
                        $data->focus['focus_faucet'],
                        $data->focus['focus_shortlinks'],
                        $data->focus['focus_offerwalls'],
                        $data->focus['focus_lottery'],
                        $data->focus['focus_mining']
                    ]);
                    if($secResult !== 'ok') {
                        # ban user and force logout on client
                        $this->mUserSetTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'setting_name' => 'user-tempban',
                            'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Welcome Message Update',
                        ]);
                        return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                    }
                    $fFaucet = filter_var($data->focus['focus_faucet'], FILTER_SANITIZE_NUMBER_INT);
                    $fSH = filter_var($data->focus['focus_shortlinks'], FILTER_SANITIZE_NUMBER_INT);
                    $fOF = filter_var($data->focus['focus_offerwalls'], FILTER_SANITIZE_NUMBER_INT);
                    $fLot = filter_var($data->focus['focus_lottery'], FILTER_SANITIZE_NUMBER_INT);
                    $fMin = filter_var($data->focus['focus_mining'], FILTER_SANITIZE_NUMBER_INT);

                    // enforce 1 and 0
                    $focus = [
                        'f' => ($fFaucet == 1) ? 1 : 0,
                        'sl' => ($fSH == 1) ? 1 : 0,
                        'of' => ($fOF == 1) ? 1 : 0,
                        'lt' => ($fLot == 1) ? 1 : 0,
                        'm' => ($fMin == 1) ? 1 : 0,
                    ];

                    if($userGuildRole->guild_idfs != 0) {
                        $this->mGuildTbl->update(['focus' => json_encode($focus)], ['Guild_ID' => $userGuildRole->guild_idfs]);
                    }
                    if($userGuildRole->guild_idfs != 0) {
                        $this->mGuildFocusTbl->delete(['guild_idfs' => $userGuildRole->guild_idfs]);
                    }
                    if($focus['f'] == 1) {
                        $this->mGuildFocusTbl->insert([
                            'guild_idfs' => $userGuildRole->guild_idfs,
                            'focus_idfs' => 1
                        ]);
                    }
                    if($focus['sl'] == 1) {
                        $this->mGuildFocusTbl->insert([
                            'guild_idfs' => $userGuildRole->guild_idfs,
                            'focus_idfs' => 2
                        ]);
                    }
                    if($focus['of'] == 1) {
                        $this->mGuildFocusTbl->insert([
                            'guild_idfs' => $userGuildRole->guild_idfs,
                            'focus_idfs' => 3
                        ]);
                    }
                    if($focus['lt'] == 1) {
                        $this->mGuildFocusTbl->insert([
                            'guild_idfs' => $userGuildRole->guild_idfs,
                            'focus_idfs' => 4
                        ]);
                    }
                    if($focus['m'] == 1) {
                        $this->mGuildFocusTbl->insert([
                            'guild_idfs' => $userGuildRole->guild_idfs,
                            'focus_idfs' => 5
                        ]);
                    }
                }

                return $this->mGuildTbl->select(['Guild_ID' => $userGuildRole->guild_idfs])->current();

            }
        } elseif($id == 'join') {
            # check if user already has joined or created a guild
            $checkWh = new Where();
            $checkWh->equalTo('user_idfs', $me->User_ID);
            $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
            $userHasGuild = $this->mGuildUserTbl->select($checkWh);

            if(count($userHasGuild) == 0) {
                # get information about the desired guild
                $secResult = $this->mSecTools->basicInputCheck([$data->guild]);
                if($secResult !== 'ok') {
                    # ban user and force logout on client
                    $this->mUserSetTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'setting_name' => 'user-tempban',
                        'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guild Join',
                    ]);
                    return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                }
                $guildId = filter_var($data->guild, FILTER_SANITIZE_NUMBER_INT);
                $guild = $this->mGuildTbl->select(['Guild_ID' => $guildId]);
                if(count($guild) > 0) {
                    # load guild info
                    $guild = $guild->current();

                    # check if user does not have too much open requests in general
                    $maxOpenRequests = 5;
                    $checkWh = new Where();
                    $checkWh->equalTo('user_idfs', $me->User_ID);
                    $checkWh->like('date_joined', '0000-00-00 00:00:00');
                    $userOpenRequests = $this->mGuildUserTbl->select($checkWh);
                    if($userOpenRequests->count() >= $maxOpenRequests) {
                        return new ApiProblem(403, 'You have reached the limit of '.$maxOpenRequests.' guild join requests. Wait for approval or cancel requests.');
                    }
                    $guildAlreadyRequested = false;
                    # check if user already has an open request for this guild
                    if(count($userOpenRequests) > 0) {
                        foreach($userOpenRequests as $userReq) {
                            if($userReq->guild_idfs == $guildId) {
                                $guildAlreadyRequested = true;
                            }
                        }
                    }

                    if(!$guildAlreadyRequested) {
                        # create a new join request
                        $this->mGuildUserTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'guild_idfs' => $guildId,
                            'rank' => 9,
                            'date_requested' => date('Y-m-d H:i:s', time()),
                            'date_joined' => '0000-00-00 00:00:00',
                            'date_declined' => '0000-00-00 00:00:00',
                        ]);

                        $this->mGuildTbl->update(['members' => $guild->members+1],['Guild_ID' => $guild->Guild_ID]);

                        # success
                        return (object)[
                            'state' => 'success',
                            'message' => 'Successfully sent a join request to guild '.$guild->label.'. Please wait for approval by guild.',
                        ];
                    } else {
                        return new ApiProblem(409, 'You already have an open request for the guild '.$guild->label.'. Please wait for approval by guild.');
                    }
                } else {
                    return new ApiProblem(404, 'The guild you want to join does not exist');
                }
            } else {
                # load existing guild info
                $guild = $this->mGuildTbl->select(['Guild_ID' => $userHasGuild->current()->guild_idfs]);
                # make sure guild does still exist
                if(count($guild) > 0) {
                    $guild = $guild->current();
                    return new ApiProblem(409, 'User is already member of the guild '.$guild->label.'. ('.$id.') Please leave guild before joining another one');
                } else {
                    return new ApiProblem(409, 'User is already member of a removed guild. please contact admin.');
                }
            }
        }

        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
