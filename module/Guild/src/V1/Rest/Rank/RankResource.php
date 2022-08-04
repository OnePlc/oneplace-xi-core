<?php
namespace Guild\V1\Rest\Rank;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class RankResource extends AbstractResourceListener
{
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
     * Guild Rank Permission Table
     *
     * @var TableGateway $mRankPermTbl
     * @since 1.0.0
     */
    protected $mRankPermTbl;

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
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

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
        $this->mRankPermTbl = new TableGateway('faucet_guild_rank_permission', $mapper);

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
            # only guildmaster is allowed to see this info
            $userGuildInfo = $userHasGuild->current();
            $guildId = $userGuildInfo->guild_idfs;
            if ($userGuildInfo->rank == 0) {
                # get rank info
                $ranks = $this->mGuildRankTbl->select(['guild_idfs' => $guildId]);
                if ($ranks->count() < 10) {
                    $renamePrice = 1000;
                    if($this->mTransaction->checkGuildBalance($renamePrice, $guildId)) {
                        $rankName = filter_var($data->rank_name, FILTER_SANITIZE_STRING);

                        $newBalance = $this->mTransaction->executeGuildTransaction($renamePrice, true,
                            $guildId, $guildId, 'rank-create', 'Created New Rank ' . $rankName, $me->User_ID);

                        $rankLevels = [
                            'rank-1' => 1,
                            'rank-2' => 2,
                            'rank-3' => 3,
                            'rank-4' => 4,
                            'rank-5' => 5,
                            'rank-6' => 6,
                            'rank-7' => 7,
                            'rank-8' => 8,
                            'rank-9' => 9];

                        foreach($ranks as $r) {
                            if(array_key_exists('rank-'.$r->level, $rankLevels)) {
                                unset($rankLevels['rank-'.$r->level]);
                            }
                        }

                        $rankLevel = 1;
                        foreach($rankLevels as $rankId) {
                            $rankLevel = $rankId;
                            break;
                        }

                        $this->mGuildRankTbl->insert([
                            'guild_idfs' => $guildId,
                            'level' => $rankLevel,
                            'label' => $rankName,
                            'daily_withdraw' => 0
                        ]);

                        $ranks = [];
                        $guildRanks = $this->mGuildRankTbl->select(['guild_idfs' => $guildId]);
                        if(count($guildRanks) > 0) {
                            foreach($guildRanks as $rank) {
                                $ranks[] = (object)[
                                    'id' => $rank->level,
                                    'name' => $rank->label,
                                ];
                            }
                        }

                        return [
                            'state' => 'done',
                            'token_balance' => $newBalance,
                            'ranks' => $ranks
                        ];
                    } else {
                        return new ApiProblem(403, 'Your Guild Token Balance is too low to create a new rank. You need '.$renamePrice.' Coins to create a new rank');
                    }
                } else {
                    return new ApiProblem(403, 'Guilds cannot have more than 10 Ranks.');
                }
            } else {
                return new ApiProblem(403, 'You must be a guildmaster to create ranks.');
            }
        }
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function delete($id)
    {
        $rankId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

        if($rankId < 0 || empty($rankId)) {
            return new ApiProblem(400, 'Invalid rank');
        }
        if($rankId == 0) {
            return new ApiProblem(400, 'You cannot delete guild master rank');
        }

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
            # only guildmaster is allowed to see this info
            $userGuildInfo = $userHasGuild->current();
            if ($userGuildInfo->rank == 0) {
                # get rank info
                $rank = $this->mGuildRankTbl->select(['level' => $rankId, 'guild_idfs' => $userGuildInfo->guild_idfs]);
                if ($rank->count() > 0) {
                    $rankMembers = $this->mGuildUserTbl->select(['rank' => $rankId, 'guild_idfs' => $userGuildInfo->guild_idfs]);
                    $memberCount = $rankMembers->count();
                    if($memberCount == 0) {
                        $this->mGuildRankTbl->delete(['level' => $rankId, 'guild_idfs' => $userGuildInfo->guild_idfs]);

                        return true;
                    } else {
                        return new ApiProblem(404, 'You have '.$memberCount. ' member with this rank. please change their rank before you delete the rank.');
                    }
                } else {
                    return new ApiProblem(404, 'Rank not found.');
                }
            } else {
                return new ApiProblem(403, 'You must own a guild to delete it.');
            }
        }
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
        $rankId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

        if($rankId < 0 || $rankId == '' || $rankId == null) {
            return new ApiProblem(400, 'Invalid rank');
        }

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
            # only guildmaster is allowed to see this info
            $userGuildInfo = $userHasGuild->current();
            if($userGuildInfo->rank == 0) {
                # get rank info
                $rank = $this->mGuildRankTbl->select(['level' => $rankId,'guild_idfs' => $userGuildInfo->guild_idfs]);
                if($rank->count() > 0) {
                    $rank = $rank->current();

                    $permissions = [];
                    $rankPermissions = $this->mRankPermTbl->select(['rank_idfs' => $rankId,'guild_idfs' => $userGuildInfo->guild_idfs]);
                    if($rankPermissions->count() > 0) {
                        foreach($rankPermissions as $rp) {
                            $permissions[] = (object)[
                                'permission' => $rp->permission
                            ];
                        }
                    }

                    return (object)[
                        'id' => $rankId,
                        'name' => $rank->label,
                        'daily_withdraw' => $rank->daily_withdraw,
                        'permissions' => $permissions,
                    ];
                } else {
                    return new ApiProblem(404, 'Rank not found.');
                }
                return true;
            } else {
                return new ApiProblem(403, 'You must own a guild to delete it.');
            }
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
        return new ApiProblem(405, 'The GET method has not been defined for collections');
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
        return new ApiProblem(405, 'The PUT method has not been defined for collections');
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
        $rankId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if($rankId < 0 || $rankId == '' || $rankId == null) {
            return new ApiProblem(400, 'Invalid rank');
        }

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) == 0) {
            return new ApiProblem(404, 'User is not part of any guild.');
        } else {
            # only guildmaster is allowed to see this info
            $userGuildInfo = $userHasGuild->current();
            if($userGuildInfo->rank == 0) {
                # get rank info
                $rank = $this->mGuildRankTbl->select(['level' => $rankId,'guild_idfs' => $userGuildInfo->guild_idfs]);
                if($rank->count() > 0) {
                    $rank = $rank->current();

                    $cmd = filter_var($data->cmd, FILTER_SANITIZE_STRING);
                    $secResult = $this->mSecTools->basicInputCheck([$data->cmd]);
                    if ($secResult !== 'ok') {
                        return new ApiProblem(418, 'Potential ' . $secResult . ' Attack - Goodbye');
                    }
                    if($cmd == 'update') {
                        $secResult = $this->mSecTools->basicInputCheck([$data->daily_limit, $data->invite]);
                        if ($secResult !== 'ok') {
                            return new ApiProblem(418, 'Potential ' . $secResult . ' Attack - Goodbye');
                        }

                        $dailyLimit = filter_var($data->daily_limit, FILTER_SANITIZE_NUMBER_INT);
                        $canInvite = filter_var($data->invite, FILTER_SANITIZE_NUMBER_INT);
                        # prevent negative values
                        if($dailyLimit < 0) {
                            $dailyLimit = 0;
                        }
                        # force value we need
                        if($canInvite != 0 && $canInvite != 1) {
                            return new ApiProblem(400, 'Invalid Permissions');
                        }

                        # update permissions
                        $permissions = [];
                        $this->mRankPermTbl->delete(['guild_idfs' => $userGuildInfo->guild_idfs]);
                        if($canInvite == 1) {
                            $this->mRankPermTbl->insert([
                                'guild_idfs' => $userGuildInfo->guild_idfs,
                                'rank_idfs' => $rankId,
                                'permission' => 'invite'
                            ]);
                            $permissions[] = 'invite';
                        }

                        # set daily withdrawal limit
                        $this->mGuildRankTbl->update([
                            'daily_withdraw' => $dailyLimit
                        ],['level' => $rankId,'guild_idfs' => $userGuildInfo->guild_idfs]);

                        return (object)[
                            'id' => $rankId,
                            'name' => $rank->label,
                            'daily_withdraw' => $rank->daily_withdraw,
                            'permissions' => $permissions,
                        ];
                    }
                    if($cmd == 'sort') {
                        $secResult = $this->mSecTools->basicInputCheck([$data->sort]);
                        if ($secResult !== 'ok') {
                            return new ApiProblem(418, 'Potential ' . $secResult . ' Attack - Goodbye');
                        }
                        if($rank->level == 0) {
                            return new ApiProblem(404, 'you cannot change rank level of guild master');
                        }
                        $sort = filter_var($data->sort, FILTER_SANITIZE_STRING);
                        switch($sort) {
                            case 'up':
                                if($rank->level == 1) {
                                    return new ApiProblem(404, 'guild master rank must be highest rank');
                                }
                                // free number as its unique
                                $this->mGuildRankTbl->update(['level' => 11], ['level' => $rank->level-1,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // +1 rank
                                $this->mGuildRankTbl->update(['level' => $rank->level-1], ['level' => $rank->level,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // old rank to rank that was above
                                $this->mGuildRankTbl->update(['level' => $rank->level], ['level' => 11,'guild_idfs' => $userGuildInfo->guild_idfs]);

                                // update guild member rank
                                $this->mGuildUserTbl->update(['rank' => 11],['rank' => $rank->level-1,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // +1 rank
                                $this->mGuildUserTbl->update(['rank' => $rank->level-1],['rank' => $rank->level,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // old rank to rank that was above
                                $this->mGuildUserTbl->update(['rank' => $rank->level],['rank' => 11,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                break;
                            case 'down':
                                // free number as its unique
                                $this->mGuildRankTbl->update(['level' => 11], ['level' => $rank->level+1,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // +1 rank
                                $this->mGuildRankTbl->update(['level' => $rank->level+1], ['level' => $rank->level,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // old rank to rank that was above
                                $this->mGuildRankTbl->update(['level' => $rank->level], ['level' => 11,'guild_idfs' => $userGuildInfo->guild_idfs]);

                                // update guild member rank
                                $this->mGuildUserTbl->update(['rank' => 11],['rank' => $rank->level+1,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // +1 rank
                                $this->mGuildUserTbl->update(['rank' => $rank->level+1],['rank' => $rank->level,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                // old rank to rank that was above
                                $this->mGuildUserTbl->update(['rank' => $rank->level],['rank' => 11,'guild_idfs' => $userGuildInfo->guild_idfs]);
                                break;
                            default:
                                return new ApiProblem(404, 'invalid sort command.');
                        }

                        $ranks = [];
                        $guildRanks = $this->mGuildRankTbl->select(['guild_idfs' => $userGuildInfo->guild_idfs]);
                        if(count($guildRanks) > 0) {
                            foreach($guildRanks as $rank) {
                                $ranks[] = (object)[
                                    'id' => $rank->level,
                                    'name' => $rank->label,
                                ];
                            }
                        }

                        return [
                            'state' => 'done',
                            'ranks' => $ranks
                        ];
                    }
                    return new ApiProblem(404, 'invalid rank command.');
                } else {
                    return new ApiProblem(404, 'Rank not found.');
                }
            } else {
                return new ApiProblem(403, 'You must own a guild to delete it.');
            }
        }
    }
}
