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

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;
use Laminas\Db\ResultSet\ResultSet;
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
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

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
        $this->mGuildTaskTbl = new TableGateway('faucet_guild_weekly', $mapper);
        $this->mGuildAchievTbl = new TableGateway('faucet_guild_achievement', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mSession = new Container('webauth');
        $this->mTransaction = new TransactionHelper($mapper);
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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) == 0) {
            # check if user has enough funds to create a guild
            $guildPrice = 1000;
            if($this->mTransaction->checkUserBalance($guildPrice,$me->User_ID)) {
                # create guild
                $guildName = $data->name;
                $guildIcon = $data->icon;
                $guildData = [
                    'label' => $guildName,
                    'owner_idfs' => $me->User_ID,
                    'created_date' => date('Y-m-d H:i:s', time()),
                    'xp_level' => 1,
                    'xp_current' => 0,
                    'xp_total' => 0,
                    'icon' => $guildIcon,
                    'is_vip' => 0,
                    'token_balance' => 0,
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

                    # create guild ranks
                    $guildRanks = [0 => 'Guildmaster',1 => 'Officer',2 => 'Veteran', 3 => 'Member',9 => 'Newbie'];
                    foreach(array_keys($guildRanks) as $rankLevel) {
                        $this->mGuildRankTbl->insert([
                            'guild_idfs' => $newGuildId,
                            'level' => $rankLevel,
                            'label' => $guildRanks[$rankLevel],
                        ]);
                    }

                    # deduct cost from user balance
                    $newBalance = $this->mTransaction->executeTransaction($guildPrice, 1, $me->User_ID, $newGuildId, 'create-guild', 'Guild '.$guildName.' created');
                    if($newBalance) {
                        $guildInfo = (object)$guildData;
                        unset($guildInfo->owner_idfs);
                        $guildInfo->owner = (object)['id' => $me->User_ID, 'name' => $me->username,'token_balance' => $newBalance];
                        return $guildInfo;
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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

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
                $userGuildInfo = $userHasGuild->current();
                if($userGuildInfo->rank == 0) {
                    return new ApiProblem(403, 'You cannot leave the guild as guildmaster. Please promote a new guildmaster first');
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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        $guild = $this->mGuildTbl->select(['Guild_ID' => $id]);
        if(count($guild) == 0) {
            return new ApiProblem(404, 'Guild not found');
        }
        $guild = $guild->current();

        $guildRanks = [];
        $guildRanksDB = $this->mGuildRankTbl->select(['guild_idfs' => $id]);
        if(count($guildRanksDB) > 0) {
            foreach($guildRanksDB as $rank) {
                $guildRanks[$rank->level] = $rank->label;
            }
        }

        /**
         * Load Guild Members List (paginated)
         */
        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $guildMembers = [];
        $memberSel = new Select($this->mGuildUserTbl->getTable());
        $checkWh = new Where();
        $checkWh->equalTo('guild_idfs', $id);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $memberSel->where($checkWh);
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
            $member = $this->mUserTbl->select(['User_ID' => $guildMember->user_idfs]);
            if(count($member) > 0) {
                $member = $member->current();
                $guildMembers[] = (object)[
                    'id' => $member->User_ID,
                    'name' => $member->username,
                    'xp_level' => $member->xp_level,
                    'rank' => (object)[
                        'id' => $guildMember->rank,
                        'name'=> $guildRanks[$guildMember->rank]
                    ]
                ];
            }
        }
        $totalMembers = $this->mGuildUserTbl->select($checkWh)->count();

        /**
         * Load Guild Tasks List (Weeklys)
         */
        $weeklyTasks = [];
        $weeklysDB = $this->mGuildTaskTbl->select();
        foreach($weeklysDB as $weekly) {
            $weeklyTasks[] = (object)[
                'id' => $weekly->Weekly_ID,
                'name' => $weekly->label,
                'description' => $weekly->description,
                'target' => $weekly->target,
                'target_mode' => $weekly->target_mode,
                'reward' => $weekly->reward,
                'current' => 0,
            ];
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

        return (object)[
            'guild' => (object)[
                'id' => $guild->Guild_ID,
                'name'=> $guild->label,
                'members' => $guildMembers,
                'tasks' => $weeklyTasks,
                'achievements' => $achievements,
                'total_members' => $totalMembers,
                'page_count' => round($totalMembers/25),
                'page_size' => 25,
                'page' => $page,
            ],
        ];

        return new ApiProblem(405, 'The GET method has not been defined for individual resources');
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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;

        # Compile list of all guilds
        $guilds = [];
        $guildSel = new Select($this->mGuildTbl->getTable());
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

        $totalGuilds = $this->mGuildTbl->select()->count();

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
            $guildAPI = (object)['id' => $guild->Guild_ID,'name' => $guild->label,'members' => $guild->members,
                'xp_level' => $guild->xp_level,'xp_current' => $guild->xp_current,'xp_total' => $guild->xp_total,
                'icon' => $guild->icon];
            if(array_key_exists($guild->Guild_ID,$userRequests)) {
                $guildAPI->userHasRequestOpen = 1;
            } else {
                $guildAPI->userHasRequestOpen = 0;
            }
            $guilds[] =$guildAPI;
        }

        // TODO: Remove static URL
        return (object)[
            '_links' => (object)['self' => (object)['href' => 'https://xi.api.swissfaucet.io/guild']],
            '_embedded' => (object)['guild' => $guilds],
            'total_items' => $totalGuilds,
            'page_count' => round($totalGuilds/4),
            'page_size' => 4,
            'page' => $page,
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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

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
            # check if name should be updated
            if(isset($data->name)) {
                $newName = filter_var($data->name, FILTER_SANITIZE_STRING);
                $this->mGuildTbl->update([
                    'label' => $newName,
                ],[
                    'Guild_ID' => $userGuildRole->guild_idfs,
                ]);
            }
            # check if name should be updated
            if(isset($data->icon)) {
                $newIcon = filter_var($data->icon, FILTER_SANITIZE_STRING);
                $this->mGuildTbl->update([
                    'icon' => $newIcon,
                ],[
                    'Guild_ID' => $userGuildRole->guild_idfs,
                ]);
            }

            return $this->mGuildTbl->select(['Guild_ID' => $userGuildRole->guild_idfs])->current();

        }

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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) == 0) {
            # get information about the desired guild
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
                return new ApiProblem(409, 'User is already member of the guild '.$guild->label.'. Please leave guild before joining another one');
            } else {
                return new ApiProblem(409, 'User is already member of a removed guild. please contact admin.');
            }
        }

        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
