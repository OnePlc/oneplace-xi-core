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
use Faucet\Tools\UserTools;
use Faucet\Transaction\InventoryHelper;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Http\ClientStatic;
use Mailjet\Resources;

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
     * User Tools Helper
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Settings Table
     *
     * @var TableGateway $mSettingsTbl
     * @since 1.0.0
     */
    protected $mSettingsTbl;

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
     * User Inbox Table
     *
     * @var TableGateway $mInboxTbl
     * @since 1.0.0
     */
    protected $mInboxTbl;

    protected $mUserAccsTbl;
    /**
     * @var TableGateway
     */
    private $mClaimTbl;
    /**
     * @var TableGateway
     */
    private $mTaskDoneTbl;
    /**
     * @var TableGateway
     */
    private $mShortDoneTbl;
    /**
     * @var TableGateway
     */
    private $mTaskTbl;

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
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mSessionTbl = new TableGateway('user_session', $mapper);
        $this->mInboxTbl = new TableGateway('user_inbox', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mUserAccsTbl = new TableGateway('user_linked_account', $mapper);
        $this->mClaimTbl = new TableGateway('faucet_claim', $mapper);
        $this->mTaskDoneTbl = new TableGateway('faucet_dailytask_user', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mTaskTbl = new TableGateway('faucet_dailytask', $mapper);

        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mInventory = new InventoryHelper($mapper);
        $this->mMailTools = new EmailTools($mapper, $viewRenderer);
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
            $data->terms
        ];
        if(isset($data->ref_id)) {
            $checkFields[] = $data->ref_id;
        }
        if(isset($data->ref_source)) {
            $checkFields[] = $data->ref_source;
        }
        if(isset($data->guild_id)) {
            $checkFields[] = $data->guild_id;
        }
        if(isset($data->captcha_mode)) {
            $checkFields[] = $data->captcha_mode;
        }
        if(isset($data->development)) {
            $checkFields[] = $data->development;
        }
        $secResult = $this->mSecTools->basicInputCheck($checkFields);
        if($secResult !== 'ok') {
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }
        $username = filter_var($data->username, FILTER_SANITIZE_STRING);
        $email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
        $password = filter_var($data->password, FILTER_SANITIZE_STRING);
        $passwordCheck = filter_var($data->passverify, FILTER_SANITIZE_STRING);
        $captcha = filter_var($data->captcha, FILTER_SANITIZE_STRING);
        $captchaMode = filter_var($data->captcha_mode, FILTER_SANITIZE_STRING);
        $terms = filter_var($data->terms, FILTER_SANITIZE_NUMBER_INT);
        $refId = filter_var((isset($data->ref_id)) ? $data->ref_id : 0, FILTER_SANITIZE_NUMBER_INT);
        $refSource = substr(filter_var((isset($data->ref_source)) ? $data->ref_source : null, FILTER_SANITIZE_STRING), 0, 50);
        $development = filter_var((isset($data->development)) ? $data->development : '', FILTER_SANITIZE_NUMBER_INT);
        $guildId = filter_var((isset($data->guild_id)) ? $data->guild_id : 0, FILTER_SANITIZE_NUMBER_INT);

        # Check which captcha secret key we should load
        $captchaKey = 'recaptcha-secret-login';
        if($captchaMode == 'app') {
            $captchaKey = 'recaptcha-app-secretkey';
        }

        # check captcha (google v2)
        $captchaSecret = $this->mSettingsTbl->select(['settings_key' => $captchaKey]);
        if(count($captchaSecret) > 0) {
            $captchaSecret = $captchaSecret->current()->settings_value;
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
            $lastTime = 0;
            foreach($sessCheck as $oSess) {
                $aUsersByIp[$oSess->user_idfs] = 1;
                $time = strtotime($oSess->date_created);
                if($time > $lastTime) {
                    $lastTime = $time;
                }
            }
            if(count($aUsersByIp) > 10) {
                return new ApiProblem(400, 'It is not allowed to have multiple accounts per household / ip. Please contact admin@swissfaucet.io if this is your first account.');
            } else {
                if(time()-$lastTime <= 3600) {
                    return new ApiProblem(400, 'There is already an account created from this ip. Please contact support if you think this is wrong. admin@swissfaucet.io');
                }
            }
        }

        # check password
        if($password != $passwordCheck) {
            return new ApiProblem(400, 'Passwords do not match');
        }

        # check email for blacklisted domains
        $domain = explode('@',$email);
        if(count($domain) == 2) {

        } else {
            return new ApiProblem(400, 'Invalid Email Address');
        }

        # check username
        $existingUser = $this->mapper->select(['username' => $username]);
        if(count($existingUser) > 0) {
            return new ApiProblem(400, 'Username is already taken');
        } else {
            $existingUser2 = $this->mapper->select(['username' => trim($username)]);
            if(count($existingUser2) > 0) {
                return new ApiProblem(400, 'Username is already taken');
            }
        }
        /**
        if(!$this->mSecTools->usernameBlacklistCheck($username) || empty($username) || strlen($username) < 3) {
            return new ApiProblem(400, 'Username not valid. Please choose another one.');
        }**/
        if(empty($username) || strlen($username) < 3) {
            return new ApiProblem(400, 'Username not valid. Please choose another one.');
        }
        if(!$this->mSecTools->usernameBlacklistCheck($username)) {
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
        # don't let "." mess up verification
        $tmp = explode('@', $email);
        $hasDot = stripos( $tmp[0], '.');
        if($hasDot !== false) {
            $noDot = str_replace(['.'],[''],$tmp[0]).'@'.$tmp[1];

            $existingUser = $this->mapper->select(['email' => $noDot]);
            if(count($existingUser) > 0) {
                return new ApiProblem(400, 'There is already an account with that e-mail. please use login.');
            }
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

        # get country by ip for offerwalls
        $country = 'GGG';
        try {
            $country = $this->ip_info(NULL, 'countrycode');
        } catch (\RuntimeException $e) {
            # country get error
        }

        $guildInviteId = 0;
        if($guildId != 0) {
            $gCheck = $this->mGuildTbl->select(['Guild_ID' => $guildId]);
            if($gCheck->count() > 0) {
                $referal = $gCheck->current()->owner_idfs;
                $guildInviteId = $guildId;
            }
        }

        # add user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $this->mapper->insert([
            'login_counter' => 0,
            'username' => $username,
            'ref_user_idfs' => $referal,
            'ref_source' => $refSource,
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
            'country' => substr($country,0,3),
            'created_by' => 1,
            'created_date' => date('Y-m-d H:i:s', time()),
            'modified_by' => 1,
            'modified_date' => date('Y-m-d H:i:s', time()),
        ]);

        # get new user's id
        $userId = $this->mapper->lastInsertValue;

        if($guildInviteId != 0) {
            $this->mGuildUserTbl->insert([
                'user_idfs' => $userId,
                'guild_idfs' => $guildInviteId,
                'rank' => 9,
                'date_requested' => date('Y-m-d H:i:s', time()),
                'date_joined' => '0000-00-00 00:00:00',
                'date_declined' => '0000-00-00 00:00:00',
            ]);
        }

        # add user session
        $this->mSessionTbl->insert([
            'user_idfs' => $userId,
            'ipaddress' => strip_tags($sIpAddr),
            'browser' => substr($_SERVER['HTTP_USER_AGENT'],0,25),
            'date_created' => date('Y-m-d H:i:s', time()),
            'date_last_login' => date('Y-m-d H:i:s', time()),
        ]);

        $this->mApiTbl->insert([
            'username' => $userId,
            'password' => $passwordHash,
            'first_name' => $username,
            'last_name' => '',
        ]);

        $userNew = $this->mapper->select(['User_ID' => $userId]);
        if(count($userNew) > 0) {
            $userNew = $userNew->current();
            # generate friend tag
            $usrBase = $username;
            $hasMail = stripos($username,'@');
            if($hasMail === false) {
            } else {
                $usrBase = explode('@', $username)[0];
            }
            $tag = str_replace([
                    ' ','ö','ä','ü','@gmail.com','@yahoo.com','@mail.ru','@outlook.es','@hotmail.com','@ukr.net',
                    '@outlook.com','Outlook.es','.com','@'
                ],[
                    '.','o','a','u','','','','','','','','','',''
                ], substr($usrBase, 0, 100)).'#'.substr($userId,strlen($userId)-4);

            # send verification email
            $secToken = $this->mMailTools->generateSecurityToken($userNew);
            $confirmLink = $this->mMailTools->getApiURL().'/verify-email/'.$secToken;
            $this->mapper->update([
                'friend_tag' => $tag,
                'send_verify' => date('Y-m-d H:i:s', time()),
                'password_reset_token' => $secToken,
                'password_reset_date' => date('Y-m-d H:i:s', time()),
            ],[
                'User_ID' => $userNew->User_ID
            ]);

            /**
            $emailV = new \SendGrid\Mail\Mail();
            $emailV->setFrom("no-reply@swissfaucet.io", "Swissfaucet.io");
            $emailV->setSubject("Sending with SendGrid is Fun");
            $emailV->addTo($email, utf8_decode($username));
            /**
            $email->addContent("text/plain", "and easy to do anywhere, even with PHP");
            $email->addContent(
            "text/html", "<strong>and easy to do anywhere, even with PHP</strong>"
            );

            $emailV->setTemplateId('d-74a424e5ad4540ad8fa2585f575aff04');
            $emailV->addDynamicTemplateDatas( [
                'token'     => $secToken,
                'user_name' => 'Praesidiarius'
            ] );
            $apiKey = $this->mSecTools->getCoreSetting('sendgrid-api-key');
            $sendgrid = new \SendGrid($apiKey);

            try {
                $response = $sendgrid->send($emailV);
                //print $response->statusCode() . "\n";
                //print_r($response->headers());
                //print $response->body() . "\n";
            } catch (Exception $e) {
                //echo 'Caught exception: '. $e->getMessage() ."\n";
            }
             **/

            $mjKey = $this->mSecTools->getCoreSetting('mailjet-key');
            $mjSecret = $this->mSecTools->getCoreSetting('mailjet-secret');

            if($mjKey && $mjSecret) {
                $mj = new \Mailjet\Client($mjKey, $mjSecret, true, ['version' => 'v3.1']);
                $body = [
                    'Messages' => [
                        [
                            'From' => [
                                'Email' => "admin@swissfaucet.io",
                                'Name' => "Swissfaucet.io"
                            ],
                            'To' => [
                                [
                                    'Email' => $email,
                                    'Name' => $email
                                ]
                            ],
                            'Subject' => "Activate your Account",
                            'HTMLPart' => "<p>All we need to do is validate your email address to activate your Swissfaucet account. Just click on the following link:</p><h3><a href='" . $confirmLink . "'>Activate Account</a></h3>",
                            'CustomID' => "AppGettingStartedTest"
                        ]
                    ]
                ];

                try {
                    $response = $mj->post(Resources::$Email, ['body' => $body]);
                    $response->success();
                } catch (Exception $e) {

                }
            }
        }

        return [
            'state' => 'success',
        ];
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

    private function ip_info($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
        $output = NULL;
        if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
            $ip = $_SERVER["REMOTE_ADDR"];
            if ($deep_detect) {
                if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
            }
        }
        $purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
        $support    = array("country", "countrycode", "state", "region", "city", "location", "address");
        $continents = array(
            "AF" => "Africa",
            "AN" => "Antarctica",
            "AS" => "Asia",
            "EU" => "Europe",
            "OC" => "Australia (Oceania)",
            "NA" => "North America",
            "SA" => "South America"
        );
        if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {
            $ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
            if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
                switch ($purpose) {
                    case "location":
                        $output = array(
                            "city"           => @$ipdat->geoplugin_city,
                            "state"          => @$ipdat->geoplugin_regionName,
                            "country"        => @$ipdat->geoplugin_countryName,
                            "country_code"   => @$ipdat->geoplugin_countryCode,
                            "continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
                            "continent_code" => @$ipdat->geoplugin_continentCode
                        );
                        break;
                    case "address":
                        $address = array($ipdat->geoplugin_countryName);
                        if (@strlen($ipdat->geoplugin_regionName) >= 1)
                            $address[] = $ipdat->geoplugin_regionName;
                        if (@strlen($ipdat->geoplugin_city) >= 1)
                            $address[] = $ipdat->geoplugin_city;
                        $output = implode(", ", array_reverse($address));
                        break;
                    case "city":
                        $output = @$ipdat->geoplugin_city;
                        break;
                    case "state":
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case "region":
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case "country":
                        $output = @$ipdat->geoplugin_countryName;
                        break;
                    case "countrycode":
                        $output = @$ipdat->geoplugin_countryCode;
                        break;
                }
            }
        }
        return $output;
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

        if($user->User_ID == 335892443) {
            return new ApiProblem(418, 'YOU MUST UPGRADE YOUR APP');
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

        /**
         * Load User Withdrawals
         */
        $withdrawals = ['done' => [],'cancel' => [],'new' => [], 'total_items' => 0];
        /**
        $userWithdrawals = $this->mWithdrawTbl->select(['user_idfs' => $user->User_ID,'state' => 'new']);
        if(count($userWithdrawals) > 0) {
            foreach($userWithdrawals as $wth) {
                $withdrawals[$wth->state][] = $wth;
            }
        } **/

        /**
         * Calculate Crypto Balance
         */
        $tokenValue = $this->mTransaction->getTokenValue();
        $coinInfo = $this->mWalletTbl->select(['coin_sign' => $user->prefered_coin]);
        $prefColor = "#1bc5bd";
        $prefText = '#fff';
        $cryptoBalance = 0;
        if(count($coinInfo) > 0) {
            $coinInfo = $coinInfo->current();
            $prefColor = $coinInfo->bgcolor;
            $prefText = $coinInfo->textcolor;
            $cryptoBalance = $user->token_balance*$tokenValue;
            if($coinInfo->dollar_val > 0) {
                $cryptoBalance = $cryptoBalance/$coinInfo->dollar_val;
            } else {
                $cryptoBalance = $cryptoBalance*$coinInfo->dollar_val;
            }
            $cryptoBalance = number_format($cryptoBalance,8,'.','');
        }

        /**
         * Public User Object
         */
        //$userInventory = $this->mInventory->getInventory($user->User_ID);

        $inboxMessages = $this->mInboxTbl->select(['to_idfs' => $user->User_ID,'is_read' => 0])->count();

        # Set Timer for next claim
        $sTime = 0;
        $timeCheck = '-1 hour';
        # Lets check if there was a claim less than 60 minutes ago
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $user->User_ID);
        $oWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime($timeCheck)));
        $oClaimCheck = $this->mClaimTbl->select($oWh);
        if(count($oClaimCheck) > 0) {
            $oClaimCheck = $oClaimCheck->current();
            # override timer
            $sTime = strtotime($oClaimCheck->date_next)-time();
        }

        $claimSound = 'none';
        $claimSoundSet = $this->mUserTools->getSetting($user->User_ID, 'claim-sound');
        if($claimSoundSet) {
            $claimSound = $claimSoundSet;
        }

        $returnData = [
            'id' => (int)$user->User_ID,
            'name' => $user->username,
            'email' => $user->email,
            'avatar' => ($user->avatar != '') ? $user->avatar : $user->username,
            'servertime' => date('Y-m-d H:i:s', time()),
            'claim_timer' => $sTime,
            'claim_sound' => $claimSound,
            'daily_claim_count' => $this->getDailyTasksReadyToClaim($user),
            'emp_mode' => ($user->is_employee == 1) ? 'mod' : '',
            'verified' => (int)$user->email_verified,
            'show_verify_mail' => ($user->send_verify == null) ? ($user->email_verified == 1) ? false : true : false,
            'token_balance' => (float)$user->token_balance,
            'credit_balance' => (float)$user->credit_balance,
            'crypto_balance' => (float)$cryptoBalance,
            'xp_level' => (int)$user->xp_level,
            'xp_percent' => (float)$dPercent,
            'time_zone' => $user->timezone,
            'prefered_coin' => $user->prefered_coin,
            'prefcoin_bg' => $prefColor,
            'prefcoin_text' => $prefText,
            'guild' => $guild,
            'withdrawals' => $withdrawals, // remove v2
            'inventory' => [], // remove v2
            //'inventory_bags' => $this->mInventory->getUserBags($user->User_ID),
            'inventory_bags' => [], // remove v2
            'inventory_slots' => 0, // remove v2
            'inventory_slots_used' => 0, // remove v2
            //'inventory_slots' => $this->mInventory->getInventorySlots($user->User_ID),
            //'inventory_slots_used' => count($userInventory),
            'inbox_count' => $inboxMessages
        ];

        $forceUpdateTo = '2.0.9';
        if(isset($_REQUEST['v'])) {
            $clientVersion = substr(filter_var($_REQUEST['v'], FILTER_SANITIZE_STRING),0, 6);

            if(!empty($user->User_ID) && $user->User_ID > 0) {
                $this->mapper->update([
                    'client_version' => $clientVersion
                ],['User_ID' => $user->User_ID]);
            }

            if($forceUpdateTo) {
                if($forceUpdateTo != $clientVersion) {
                    $returnData['force_update'] = 1;
                }
            }
        }

        $systemAlert = $this->mSettingsTbl->select(['settings_key' => 'system_alert']);
        if(count($systemAlert) > 0) {
            $returnData['system_alert'] = $systemAlert->current()->settings_value;
        }

        # only send public fields
        return (object)$returnData;
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
        if(isset($data->name)) {
            $checkFields[] = $data->name;
        }
        if(isset($data->favCoin)) {
            $checkFields[] = $data->favCoin;
        }
        if(isset($data->email)) {
            $checkFields[] = $data->email;
        }
        if(isset($data->language)) {
            $checkFields[] = $data->language;
        }
        if(isset($data->time_zone)) {
            $checkFields[] = $data->time_zone;
        }
        if(isset($data->avatar)) {
            $checkFields[] = $data->avatar;
        }
        if(isset($data->passwordCheck)) {
            $checkFields[] = $data->passwordCheck;
            $checkFields[] = $data->passwordNew;
            $checkFields[] = $data->passwordNewVerify;
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

        $name = filter_var($data->name, FILTER_SANITIZE_STRING);
        $email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
        $time_zone = filter_var($data->time_zone, FILTER_SANITIZE_STRING);
        $language = filter_var($data->language, FILTER_SANITIZE_STRING);
        $passwordNew = filter_var($data->passwordNew, FILTER_SANITIZE_STRING);
        $passwordNewVer = filter_var($data->passwordNewVerify, FILTER_SANITIZE_STRING);
        $passwordCheck = filter_var($data->passwordCheck, FILTER_SANITIZE_STRING);
        $avatar = filter_var($data->avatar, FILTER_SANITIZE_STRING);

        $favCoin = filter_var($data->favCoin, FILTER_SANITIZE_STRING);

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

        if($avatar != '') {
            if(strlen($avatar) > 100) {
                return new ApiProblem(409, 'name for avatar is too long');
            }
            $update['avatar'] = $avatar;
        } else {
            $avatar = $user->username;
        }

        # check if password has changed
        if($passwordNew != '') {
            # check current
            if(!password_verify($passwordCheck, $user->password)) {
                return new ApiProblem(409, 'Your current password is wrong');
            }

            # verify new
            if($passwordNew != $passwordNewVer) {
                return new ApiProblem(409, 'New passwords do not match');
            }

            # should never happen but rather be safe than sorry
            if($user->User_ID != 0) {
                # update password
                $this->mSecTools->updatePassword($passwordNew, $user->User_ID);
            } else {
                return new ApiProblem(404, 'user not found');
            }
        }

        /**
         * Change E-Mail Address
         */
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

        /**
         * Update Timezone
         */
        if($time_zone != $user->time_zone && $time_zone != '' && substr($time_zone,0,4) == '(GMT') {
            $update['timezone'] = $time_zone;
            $user->time_zone = $update['timezone'];

            # timezone achievement
            if(!$this->mUserTools->hasAchievementCompleted(28, $user->User_ID)) {
                $this->mUserTools->completeAchievement(28, $user->User_ID);
            }
        }

        /**
         * Update Favorite Coin
         */
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

        if(count($update) > 0) {
            $this->mapper->update($update,[
                'User_ID' => $user->User_ID
            ]);
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
        /**
        $userWithdrawals = $this->mWithdrawTbl->select(['user_idfs' => $user->User_ID]);
        if(count($userWithdrawals) > 0) {
            foreach($userWithdrawals as $wth) {
                $withdrawals[$wth->state][] = $wth;
            }
        } **/

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
            'name' => $user->username,
            'email' => $user->email,
            'avatar' => $avatar,
            'token_balance' => $user->token_balance,
            'crypto_balance' => $cryptoBalance,
            'xp_level' => $user->xp_level,
            'xp_percent' => $dPercent,
            'prefered_coin' => $favCoin,
            'time_zone' => $user->timezone,
            'guild' => $guild,
            'messages' => $messages,
            'link' => $confirmLink,
            'withdrawals' => []
        ]];
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $checkFields = [];
        if(isset($data->name)) {
            $checkFields[] = $data->name;
        }
        if(isset($data->favCoin)) {
            $checkFields[] = $data->favCoin;
        }
        if(isset($data->email)) {
            $checkFields[] = $data->email;
        }
        if(isset($data->language)) {
            $checkFields[] = $data->language;
        }
        if(isset($data->time_zone)) {
            $checkFields[] = $data->time_zone;
        }
        if(isset($data->avatar)) {
            $checkFields[] = $data->avatar;
        }
        if(isset($data->account_gacha)) {
            $checkFields[] = $data->account_gacha;
        }
        if(isset($data->claim_sound)) {
            $checkFields[] = $data->claim_sound;
        }
        if(isset($data->passwordCheck)) {
            $checkFields[] = $data->passwordCheck;
            $checkFields[] = $data->passwordNew;
            $checkFields[] = $data->passwordNewVerify;
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

        $name = filter_var($data->name, FILTER_SANITIZE_STRING);
        $email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
        $time_zone = filter_var($data->time_zone, FILTER_SANITIZE_STRING);
        $language = filter_var($data->language, FILTER_SANITIZE_STRING);
        $passwordNew = filter_var($data->passwordNew, FILTER_SANITIZE_STRING);
        $passwordNewVer = filter_var($data->passwordNewVerify, FILTER_SANITIZE_STRING);
        $passwordCheck = filter_var($data->passwordCheck, FILTER_SANITIZE_STRING);
        $avatar = filter_var($data->avatar, FILTER_SANITIZE_STRING);
        $gachaAcc = filter_var($data->account_gacha, FILTER_SANITIZE_STRING);
        $claimSound = filter_var($data->claim_sound, FILTER_SANITIZE_STRING);

        $favCoin = filter_var($data->favCoin, FILTER_SANITIZE_STRING);

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

        if($claimSound != '') {
            $allowedSounds = [
                'none',
                'mario-coin-sound.ogg',
                'mixkit-clinking-coins-1993.ogg',
                'mixkit-coin-win-notification-1992.ogg',
                'mixkit-final-level-bonus-2061.ogg',
                'mixkit-game-treasure-coin-2038.ogg',
                'mixkit-gold-coin-prize-1999.ogg',
                'mixkit-magical-coin-win-1936.ogg',
                'mixkit-melodic-gold-price-2000.ogg',
                'mixkit-money-bag-drop-1989.ogg',
                'mixkit-winning-a-coin-video-game-2069.ogg'];
            if(!in_array($claimSound, $allowedSounds)) {
                return new ApiProblem(409, 'invalid sound - please choose another one');
            }

            $this->mUserTools->setSetting($user->User_ID, 'claim-sound', $claimSound);
        }

        if($avatar != '') {
            if(strlen($avatar) > 100) {
                return new ApiProblem(409, 'name for avatar is too long');
            }
            $update['avatar'] = $avatar;
        } else {
            if($user->avatar == '') {
                $avatar = $user->username;
            } else {
                $avatar = $user->avatar;
            }
        }

        # check if password has changed
        if($passwordNew != '') {
            # check current
            if(!password_verify($passwordCheck, $user->password)) {
                return new ApiProblem(409, 'Your current password is wrong');
            }

            # verify new
            if($passwordNew != $passwordNewVer) {
                return new ApiProblem(409, 'New passwords do not match');
            }

            # should never happen but rather be safe than sorry
            if($user->User_ID != 0) {
                # update password
                $this->mSecTools->updatePassword($passwordNew, $user->User_ID);

                return true;
            } else {
                return new ApiProblem(404, 'user not found');
            }
        }

        /**
         * Change E-Mail Address
         */
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

        /**
         * Update Timezone
         */
        if($time_zone != $user->time_zone && $time_zone != '' && substr($time_zone,0,4) == '(GMT') {
            $update['timezone'] = $time_zone;
            $user->time_zone = $update['timezone'];

            # timezone achievement
            if(!$this->mUserTools->hasAchievementCompleted(28, $user->User_ID)) {
                $this->mUserTools->completeAchievement(28, $user->User_ID);
            }
        }

        /**
         * Link Gachaminer Account
         */
        // account_gacha
        if($gachaAcc != '') {
            $check = $this->mUserAccsTbl->select(['user_idfs' => $user->User_ID, 'account' => 'gachaminer']);
            if($check->count() == 0) {
                $this->mUserAccsTbl->insert([
                    'user_idfs' => $user->User_ID,
                    'account' => 'gachaminer',
                    'email' => $gachaAcc
                ]);
            } else {
                return new ApiProblem(404, 'You have already linked a gachaminer account');
            }
        }

        /**
         * Update Favorite Coin
         */
        if($favCoin != '') {
            # check if coin has changed
            if($user->prefered_coin != $favCoin) {
                $wallet = $this->mWalletTbl->select(['coin_sign' => $favCoin]);
                if($wallet->count() == 0) {
                    return new ApiProblem(404, 'Coin '.$favCoin.' is not valid');
                }
                $update['prefered_coin'] = $favCoin;
            }
        }

        if(count($update) > 0) {
            $this->mapper->update($update,[
                'User_ID' => $user->User_ID
            ]);
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
        /**
        $userWithdrawals = $this->mWithdrawTbl->select(['user_idfs' => $user->User_ID]);
        if(count($userWithdrawals) > 0) {
            foreach($userWithdrawals as $wth) {
                $withdrawals[$wth->state][] = $wth;
            }
        }
         * **/

        $tokenValue = 0.00004;

        $coinInfo = $this->mWalletTbl->select(['coin_sign' => $favCoin]);
        $cryptoBalance = 0;
        $prefColor = "#1bc5bd";
        $prefText = '#fff';
        if(count($coinInfo) > 0) {
            $coinInfo = $coinInfo->current();
            $prefColor = $coinInfo->bgcolor;
            $prefText = $coinInfo->textcolor;
            $cryptoBalance = $user->token_balance*$tokenValue;
            if($coinInfo->dollar_val > 0) {
                $cryptoBalance = $cryptoBalance/$coinInfo->dollar_val;
            } else {
                $cryptoBalance = $cryptoBalance*$coinInfo->dollar_val;
            }
            $cryptoBalance = number_format($cryptoBalance,8,'.','');
        }

        # only send public fields
        return [
            'user' => (object)[
                'id' => $user->User_ID,
                'name' => $user->username,
                'email' => $user->email,
                'avatar' => $avatar,
                'token_balance' => $user->token_balance,
                'crypto_balance' => $cryptoBalance,
                'xp_level' => $user->xp_level,
                'xp_percent' => $dPercent,
                'prefered_coin' => $favCoin,
                'prefcoin_bg' => $prefColor,
                'prefcoin_text' => $prefText,
                'time_zone' => $user->timezone,
                'guild' => $guild,
                'messages' => $messages,
                'link' => $confirmLink,
                'withdrawals' => $withdrawals
            ]
        ];
    }

    private function getDailyTasksReadyToClaim($me) {
        $sDate = date('Y-m-d', time());

        /**
         * Gather relevant data for progress
         */
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date_claimed', $sDate.'%');
        $shortlinksDone = $this->mShortDoneTbl->select($oWh)->count();

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date', $sDate.'%');
        $claimsDone = $this->mClaimTbl->select($oWh)->count();

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date', $sDate.'%');
        $dailysToday = $this->mTaskDoneTbl->select($oWh);
        $dailyDoneById = [];
        foreach($dailysToday as $daily) {
            $dailyDoneById['task-'.$daily->task_idfs] = 1;
        }
        $dailysDone = $dailysToday->count();

        # Load Dailytasks
        $oWh = new Where();
        $oWh->NEST
            ->equalTo('mode', 'website')
            ->OR
            ->equalTo('mode', 'global')
            ->UNNEST;
        $dailySel = new Select($this->mTaskTbl->getTable());
        $dailySel->where($oWh);
        $dailySel->order('sort_id ASC');
        $achievementsDB = $this->mTaskTbl->selectWith($dailySel);
        $readyToClaim = 0;
        foreach($achievementsDB as $achiev) {
            switch($achiev->type) {
                case 'shortlink':
                    $progress = $shortlinksDone;
                    break;
                case 'claim':
                    $progress = $claimsDone;
                    break;
                case 'daily':
                    $progress = $dailysDone;
                    break;
                default:
                    $progress = 0;
                    break;
            }

            if($progress >= $achiev->goal && !array_key_exists('task-'.$achiev->Dailytask_ID, $dailyDoneById)) {
                $readyToClaim++;
            }
        }

        return $readyToClaim;
    }
}
