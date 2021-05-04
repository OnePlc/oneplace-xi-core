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
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mSession = new Container('webauth');
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
        return new ApiProblem(405, 'The POST method has not been defined');
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     * @since 1.0.0
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

        # Compile list of all guilds
        $guilds = [];
        $guildsDB = $this->mGuildTbl->select();
        foreach($guildsDB as $guild) {
            # count guild members
            $guild->members = $this->mGuildUserTbl->select(['guild_idfs' => $guild->Guild_ID])->count();
            $guilds[] = $guild;
        }

        return $guilds;
    }

    /**
     * Patch (partial in-place update) a resource
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
     * Update a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function update($id, $data)
    {
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
