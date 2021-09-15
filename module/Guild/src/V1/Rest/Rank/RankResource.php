<?php
namespace Guild\V1\Rest\Rank;

use Faucet\Tools\SecurityTools;
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
    }
    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        return new ApiProblem(405, 'The POST method has not been defined');
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
        $rankId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

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
                } else {
                    return new ApiProblem(404, 'Rank not found.');
                }
            } else {
                return new ApiProblem(403, 'You must own a guild to delete it.');
            }
        }
    }
}
