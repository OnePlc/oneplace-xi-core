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

use Faucet\Tools\EmailTools;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\InventoryHelper;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;
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
     * User API Table
     *
     * @var TableGateway $mApiTbl
     * @since 1.0.0
     */
    protected $mApiTbl;

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
     * User Session Table
     *
     * @var TableGateway $mSessionTbl
     * @since 1.0.0
     */
    protected $mSessionTbl;

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
     * E-Mail Helper
     *
     * @var EmailTools $mMailTools
     * @since 1.0.0
     */
    protected $mMailTools;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * Inventory Helper
     *
     * @var InventoryHelper $mInventory
     * @since 1.0.0
     */
    protected $mInventory;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper, $viewRenderer)
    {
        $this->mApiTbl = new TableGateway('oauth_users', $mapper);
        $this->mapper = new TableGateway('user', $mapper);
        $this->mXPLvlTbl = new TableGateway('user_xp_level', $mapper);
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        $this->mGuildRankTbl = new TableGateway('faucet_guild_rank', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mWithdrawTbl = new TableGateway('faucet_withdraw', $mapper);
        $this->mSessionTbl = new TableGateway('user_session', $mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mInventory = new InventoryHelper($mapper);
        $this->mMailTools = new EmailTools($mapper, $viewRenderer);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
    }

    /**
     * User Signup
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function create($data)
    {
        # check for attack vendors
        $checkFields = [
            $data->username,
            $data->email,
            $data->password,
            $data->passverify,
            $data->captcha,
            $data->terms,
            $data->ref_id,
            $data->development
        ];
        $secResult = $this->mSecTools->basicInputCheck($checkFields);
        if($secResult !== 'ok') {
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }
        $username = filter_var($data->username, FILTER_SANITIZE_STRING);
        $email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
        $password = filter_var($data->password, FILTER_SANITIZE_STRING);
        $passwordCheck = filter_var($data->passverify, FILTER_SANITIZE_STRING);
        $captcha = filter_var($data->captcha, FILTER_SANITIZE_STRING);
        $terms = filter_var($data->terms, FILTER_SANITIZE_NUMBER_INT);
        $refId = filter_var($data->ref_id, FILTER_SANITIZE_NUMBER_INT);
        $development = filter_var($data->development, FILTER_SANITIZE_NUMBER_INT);


        # check captcha

        # check terms
        if($terms != 1) {
            return new ApiProblem(400, 'You need to accept our terms & conditions to create an account');
        }

        # check if ip is blacklisted
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $sIpAddr = filter_var ($_SERVER['HTTP_CLIENT_IP'], FILTER_SANITIZE_STRING);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $sIpAddr = filter_var ($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_SANITIZE_STRING);
        } else {
            $sIpAddr = filter_var ($_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_STRING);
        }
        $sessCheck = $this->mSessionTbl->select([
            'ipaddress' => strip_tags($sIpAddr),
        ]);
        if(count($sessCheck) > 0) {
            $aUsersByIp = [];
            foreach($sessCheck as $oSess) {
                $aUsersByIp[$oSess->user_idfs] = 1;
            }
            if(count($aUsersByIp) > 25) {
                return new ApiProblem(400, 'It is not allowed to have multiple accounts per household / ip. Please contact admin@swissfaucet.io if this is your first account.');
            }
        }

        # check password
        if($password != $passwordCheck) {
            return new ApiProblem(400, 'Passwords do not match');
        }

        # check username
        $existingUser = $this->mapper->select(['username' => $username]);
        if(count($existingUser) > 0) {
            return new ApiProblem(400, 'Username is already taken');
        }
        if(array_key_exists($username, $this->mSecTools->getUsernameBlacklist()) || empty($username) || strlen($username) < 3) {
            return new ApiProblem(400, 'Username not valid. Please choose another one.');
        }

        # check email
        $existingUser = $this->mapper->select(['email' => $email]);
        if(count($existingUser) > 0) {
            return new ApiProblem(400, 'There is already an account with that e-mail. please use login.');
        }
        if(array_key_exists($email, $this->mSecTools->getUsernameBlacklist()) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new ApiProblem(400, 'Invalid E-Mail address. Please choose another one');
        }

        $referal = 0;
        if($refId != 0) {
            $refCheck = $this->mapper->select(['User_ID' => $refId]);
            if(count($refCheck) > 0) {
                $referal = $refCheck->current()->User_ID;
            }
        }

        if($development == 1) {
            return [
                'state' => 'success',
            ];
        }
        # add user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $this->mapper->insert([
            'login_counter' => 0,
            'username' => $username,
            'ref_user_idfs' => $referal,
            'full_name' => $username,
            'email' => $email,
            'email_verified' => 0,
            'password' => $passwordHash,
            'xp_level' => 1,
            'xp_total' => 0,
            'xp_current' => 0,
            'token_balance' => 0,
            'prefered_coin' => 'BCH',
            'is_backend_user' => 1,
            'telegram_chatid' => '',
            'lang' => 'en_US',
            'theme' => 'faucet',
            'created_by' => 1,
            'created_date' => date('Y-m-d H:i:s', time()),
            'modified_by' => 1,
            'modified_date' => date('Y-m-d H:i:s', time()),
        ]);

        $userId = $this->mapper->lastInsertValue;
        $this->mApiTbl->insert([
            'username' => $userId,
            'password' => $passwordHash,
            'first_name' => $username,
            'last_name' => '',
        ]);

        return [
            'state' => 'success',
        ];

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
        return new ApiProblem(405, 'The GET method has not been defined for invidiual resources');
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
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

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
                    'id' => (int)$guildDB->Guild_ID,
                    'name' => $guildDB->label,
                    'icon' => $guildDB->icon,
                    'xp_level' => (int)$guildDB->xp_level,
                    'xp_total' => (int)$guildDB->xp_total,
                    'xp_current' => (int)$guildDB->xp_current,
                    'xp_percent' => (float)$guildXPPercent,
                    'token_balance' => (float)$guildDB->token_balance,
                    'rank' => (object)['id' => (int)$guildRank->rank, 'name' => $rank],
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

        $tokenValue = $this->mTransaction->getTokenValue();

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
            'id' => (int)$user->User_ID,
            'name' => $user->username,
            'email' => $user->email,
            'verified' => (int)$user->email_verified,
            'token_balance' => (float)$user->token_balance,
            'crypto_balance' => (float)$cryptoBalance,
            'xp_level' => (int)$user->xp_level,
            'xp_percent' => (float)$dPercent,
            'prefered_coin' => $user->prefered_coin,
            'guild' => $guild,
            'withdrawals' => $withdrawals,
            'inventory' => $this->mInventory->getInventory($user->User_ID)
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $checkFields = [];
        if(isset($data[0]->name)) {
            $checkFields[] = $data[0]->name;
        }
        if(isset($data[0]->favCoin)) {
            $checkFields[] = $data[0]->favCoin;
        }
        if(isset($data[0]->email)) {
            $checkFields[] = $data[0]->email;
        }
        if(isset($data[0]->language)) {
            $checkFields[] = $data[0]->language;
        }
        if(isset($data[0]->time_zone)) {
            $checkFields[] = $data[0]->time_zone;
        }
        # check for attack vendors
        $secResult = $this->mSecTools->basicInputCheck($checkFields);
        if($secResult !== 'ok') {
            # ban user and force logout on client
            $this->mUserSetTbl->insert([
                'user_idfs' => $user->User_ID,
                'setting_name' => 'user-tempban',
                'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' User Profile Form',
            ]);
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }

        $messages = [];

        $name = filter_var($data[0]->name, FILTER_SANITIZE_STRING);
        $email = filter_var($data[0]->email, FILTER_SANITIZE_EMAIL);
        $time_zone = filter_var($data[0]->time_zone, FILTER_SANITIZE_STRING);
        $language = filter_var($data[0]->language, FILTER_SANITIZE_STRING);

        $favCoin = filter_var($data[0]->favCoin, FILTER_SANITIZE_STRING);

        $update = [];
        # check if name has changed
        if($name != $user->username && $name != '') {
            # check if name is already taken
            $nameCheck = $this->mapper->select(['username' => $name]);
            if(count($nameCheck) > 0) {
                return new ApiProblem(409, 'name already taken');
            }
            $update['username'] = $name;
        }

        if($email != $user->email && $email != '') {
            # check if email is already taken
            $mailCheck = $this->mapper->select(['email' => $email]);
            if(count($mailCheck) > 0) {
                return new ApiProblem(409, 'there is already an account with that e-mail');
            }
            $secToken = $this->mMailTools->generateSecurityToken($user);
            $confirmLink = $this->mMailTools->getSystemURL().'/#/change-email/'.$secToken;
            $update['email_change'] = $email;
            $update['password_reset_token'] = $secToken;
            $update['password_reset_date'] = date('Y-m-d H:i:s', time());
            $this->mMailTools->sendMail('email_change', [
                'email_new' => $email,
                'footerInfo' => 'Swissfaucet.io - Faucet #1',
                'link' => $confirmLink
            ], $this->mMailTools->getAdminEmail(), $user->email, 'Confirm E-Mail Address Change');
            $messages[] = 'Please check the Inbox of your current E-Mail to confirm the change of your Account E-Mail';
        }

        if($favCoin != '') {
            # check if coin has changed
            if($user->prefered_coin != $favCoin) {
                $wallet = $this->mWalletTbl->select(['coin_sign' => $favCoin]);
                if(count($wallet) == 0) {
                    return new ApiProblem(404, 'Coin '.$favCoin.' is not valid');
                }
                $wallet = $wallet->current();
                $update['prefered_coin'] = $favCoin;
            }
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
            'messages' => $messages,
            'link' => $confirmLink,
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
