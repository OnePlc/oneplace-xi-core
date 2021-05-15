<?php
/**
 * UserResource.php - User Resource
 *
 * Main Resource for User API
 *
 * @category Resource
 * @package User
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace User\V1\Rest\User;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Session\Container;
use Laminas\Db\Sql\Where;

class UserResource extends AbstractResourceListener
{
    /**
     * User Table
     *
     * @var TableGateway $mapper
     * @since 1.0.0
     */
    protected $mapper;

    /**
     * User XP Level Table
     *
     * @var TableGateway $mXPLvlTbl
     * @since 1.0.0
     */
    protected $mXPLvlTbl;

    /**
     * User Withdrawal Table
     *
     * @var TableGateway $mWithdrawTbl
     * @since 1.0.0
     */
    protected $mWithdrawTbl;

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
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * Faucet Wallets Table
     *
     * @var TableGateway $mWalletTbl
     * @since 1.0.0
     */
    protected $mWalletTbl;

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
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mapper = new TableGateway('user', $mapper);
        $this->mXPLvlTbl = new TableGateway('user_xp_level', $mapper);
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        $this->mGuildRankTbl = new TableGateway('faucet_guild_rank', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mWithdrawTbl = new TableGateway('faucet_withdraw', $mapper);
        $this->mSession = new Container('webauth');
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
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

        # get user from db
        $user = $this->mapper->select(['User_ID' => $id])->current();

        # get user next level xp
        $oNextLvl = $this->mXPLvlTbl->select(['Level_ID' => ($user->xp_level + 1)])->current();
        $dPercent = 0;
        if ($user->xp_current != 0) {
            $dPercent = round((100 / ($oNextLvl->xp_total / $user->xp_current)), 2);
        }

        # only send public fields
        return (object)[
            'id' => $user->User_ID,
            'session_user' => $this->mSession->auth->username,
            'username' => $user->username,
            'token_balance' => $user->token_balance,
            'xp_level' => $user->xp_level,
            'xp_percent' => $dPercent,
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
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        # get user from db
        $user = $this->mapper->select(['User_ID' => $this->mSession->auth->User_ID])->current();

        # get user next level xp
        $oNextLvl = $this->mXPLvlTbl->select(['Level_ID' => ($user->xp_level + 1)])->current();
        $dPercent = 0;
        if ($user->xp_current != 0) {
            $dPercent = round((100 / ($oNextLvl->xp_total / $user->xp_current)), 2);
        }

        # check if user already has joined or created a guild
        $guild = (object)[];
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $user->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) > 0) {
            $guildRank = $userHasGuild->current();
            $guildDB = $this->mGuildTbl->select(['Guild_ID' => $guildRank->guild_idfs]);
            if(count($guildDB) > 0) {
                $guildDB = $guildDB->current();
                $rank = '-';
                $rankDB = $this->mGuildRankTbl->select([
                    'guild_idfs' => $guildDB->Guild_ID,
                    'level' => $guildRank->rank,
                ]);
                if(count($rankDB) > 0) {
                    $rank = $rankDB->current()->label;
                }
                $guildXPPercent = 0;
                if ($guildDB->xp_current != 0) {
                    $guildNextLvl = $this->mXPLvlTbl->select(['Level_ID' => ($guildDB->xp_level + 1)])->current();
                    $guildXPPercent = round((100 / ($guildNextLvl->xp_total / $guildDB->xp_current)), 2);
                }
                $guild = (object)[
                    'id' => $guildDB->Guild_ID,
                    'name' => $guildDB->label,
                    'icon' => $guildDB->icon,
                    'xp_level' => $guildDB->xp_level,
                    'xp_total' => $guildDB->xp_total,
                    'xp_current' => $guildDB->xp_current,
                    'xp_percent' => $guildXPPercent,
                    'token_balance' => $guildDB->token_balance,
                    'rank' => (object)['id' => $guildRank->rank, 'name' => $rank],
                ];
            }
        }

        $withdrawals = ['done' => [],'cancel' => [],'new' => [], 'total_items' => 0];
        $userWithdrawals = $this->mWithdrawTbl->select(['user_idfs' => $user->User_ID]);
        if(count($userWithdrawals) > 0) {
            foreach($userWithdrawals as $wth) {
                $withdrawals[$wth->state][] = $wth;
            }
        }

        $tokenValue = 0.00004;

        $coinInfo = $this->mWalletTbl->select(['coin_sign' => $user->prefered_coin]);
        $cryptoBalance = 0;
        if(count($coinInfo) > 0) {
            $coinInfo = $coinInfo->current();
            $cryptoBalance = $user->token_balance*$tokenValue;
            if($coinInfo->dollar_val > 0) {
                $cryptoBalance = $cryptoBalance/$coinInfo->dollar_val;
            } else {
                $cryptoBalance = $cryptoBalance*$coinInfo->dollar_val;
            }
            $cryptoBalance = number_format($cryptoBalance,8,'.','');
        }

        # only send public fields
        return (object)[
            'id' => $user->User_ID,
            'name' => $user->username,
            'email' => $user->email,
            'token_balance' => $user->token_balance,
            'crypto_balance' => $cryptoBalance,
            'xp_level' => $user->xp_level,
            'xp_percent' => $dPercent,
            'prefered_coin' => $user->prefered_coin,
            'guild' => $guild,
            'withdrawals' => $withdrawals
        ];
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
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        # get user from db
        $user = $this->mapper->select(['User_ID' => $this->mSession->auth->User_ID])->current();
        if($this->mSession->auth->User_ID == 0) {
            return new ApiProblem(400, 'invalid user id');
        }

        # check for attack vendors
        $secResult = $this->mSecTools->basicInputCheck([$data[0]->name,$data[0]->favCoin]);
        if($secResult !== 'ok') {
            # ban user and force logout on client
            $this->mUserSetTbl->insert([
                'user_idfs' => $user->User_ID,
                'setting_name' => 'user-tempban',
                'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' User Profile Form',
            ]);
            return new ApiProblem(418, 'Potential XSS Attack - Goodbye');
        }

        $name = filter_var($data[0]->name, FILTER_SANITIZE_STRING);
        $favCoin = filter_var($data[0]->favCoin, FILTER_SANITIZE_STRING);

        $update = [];
        # check if name has changed
        if($name != $user->username) {
            # check if name is already taken
            $nameCheck = $this->mapper->select(['username' => $name]);
            if(count($nameCheck) > 0) {
                return new ApiProblem(409, 'name already taken');
            }
            $update['username'] = $name;
        }

        # check if coin has changed
        if($user->prefered_coin != $favCoin) {
            $wallet = $this->mWalletTbl->select(['coin_sign' => $favCoin]);
            if(count($wallet) == 0) {
                return new ApiProblem(404, 'Coin '.$favCoin.' is not valid');
            }
            $wallet = $wallet->current();
            $update['prefered_coin'] = $favCoin;
        }

        $this->mapper->update($update,[
            'User_ID' => $this->mSession->auth->User_ID
        ]);

        # get user next level xp
        $oNextLvl = $this->mXPLvlTbl->select(['Level_ID' => ($user->xp_level + 1)])->current();
        $dPercent = 0;
        if ($user->xp_current != 0) {
            $dPercent = round((100 / ($oNextLvl->xp_total / $user->xp_current)), 2);
        }

        # check if user already has joined or created a guild
        $guild = (object)[];
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $user->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) > 0) {
            $guildRank = $userHasGuild->current();
            $guildDB = $this->mGuildTbl->select(['Guild_ID' => $guildRank->guild_idfs]);
            if(count($guildDB) > 0) {
                $guildDB = $guildDB->current();
                $rank = '-';
                $rankDB = $this->mGuildRankTbl->select([
                    'guild_idfs' => $guildDB->Guild_ID,
                    'level' => $guildRank->rank,
                ]);
                if(count($rankDB) > 0) {
                    $rank = $rankDB->current()->label;
                }
                $guildXPPercent = 0;
                if ($guildDB->xp_current != 0) {
                    $guildNextLvl = $this->mXPLvlTbl->select(['Level_ID' => ($guildDB->xp_level + 1)])->current();
                    $guildXPPercent = round((100 / ($guildNextLvl->xp_total / $guildDB->xp_current)), 2);
                }
                $guild = (object)[
                    'id' => $guildDB->Guild_ID,
                    'name' => $guildDB->label,
                    'icon' => $guildDB->icon,
                    'xp_level' => $guildDB->xp_level,
                    'xp_total' => $guildDB->xp_total,
                    'xp_current' => $guildDB->xp_current,
                    'xp_percent' => $guildXPPercent,
                    'token_balance' => $guildDB->token_balance,
                    'rank' => (object)['id' => $guildRank->rank, 'name' => $rank],
                ];
            }
        }

        $withdrawals = ['done' => [],'cancel' => [],'new' => [], 'total_items' => 0];
        $userWithdrawals = $this->mWithdrawTbl->select(['user_idfs' => $user->User_ID]);
        if(count($userWithdrawals) > 0) {
            foreach($userWithdrawals as $wth) {
                $withdrawals[$wth->state][] = $wth;
            }
        }

        $tokenValue = 0.00004;

        $coinInfo = $this->mWalletTbl->select(['coin_sign' => $favCoin]);
        $cryptoBalance = 0;
        if(count($coinInfo) > 0) {
            $coinInfo = $coinInfo->current();
            $cryptoBalance = $user->token_balance*$tokenValue;
            if($coinInfo->dollar_val > 0) {
                $cryptoBalance = $cryptoBalance/$coinInfo->dollar_val;
            } else {
                $cryptoBalance = $cryptoBalance*$coinInfo->dollar_val;
            }
            $cryptoBalance = number_format($cryptoBalance,8,'.','');
        }

        # only send public fields
        return [(object)[
            'id' => $user->User_ID,
            'name' => $name,
            'email' => $user->email,
            'token_balance' => $user->token_balance,
            'crypto_balance' => $cryptoBalance,
            'xp_level' => $user->xp_level,
            'xp_percent' => $dPercent,
            'prefered_coin' => $favCoin,
            'guild' => $guild,
            'withdrawals' => $withdrawals
        ]];

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
