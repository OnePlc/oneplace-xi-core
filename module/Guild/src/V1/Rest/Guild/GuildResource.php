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

use Application\Controller\IndexController;
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
