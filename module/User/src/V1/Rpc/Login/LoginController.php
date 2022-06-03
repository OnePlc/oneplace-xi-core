<?php
/**
 * LoginController.php - Login Controller
 *
 * Main Controller for User API Login
 *
 * @category Resource
 * @package User
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace User\V1\Rpc\Login;

use Application\Controller\IndexController;
use Laminas\Http\ClientStatic;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Session\Container;
use Laminas\Db\Sql\Where;

class LoginController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

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
     * User Session Table
     *
     * @var TableGateway $mSessionTbl
     * @since 1.0.0
     */
    protected $mSessionTbl;

    /**
     * Constructor
     *
     * LoginController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mapper = $mapper;
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
        $this->mSessionTbl = new TableGateway('user_session', $mapper);
    }

    /**
     * Login User for further API use
     *
     * @return ApiProblemResponse|ViewModel
     * @since 1.0.0
     */
    public function loginAction()
    {
        # Get Data from Request Body
        $json = IndexController::loadJSONFromRequestBody(['username','password','captcha','captcha_mode'],$this->getRequest()->getContent());
        if(!$json) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
        }

        $captcha = filter_var($json->captcha, FILTER_SANITIZE_STRING);
        $captchaMode = filter_var($json->captcha_mode, FILTER_SANITIZE_STRING);

        # Check which captcha secret key we should load
        $captchaKey = 'recaptcha-secret-login';
        if($captchaMode == 'app') {
            $captchaKey = 'recaptcha-app-secretkey';
        }

        if($captchaMode == 'app') {
            return new ApiProblemResponse(new ApiProblem(403, 'The Android app is disabled. Please use our website swissfaucet.io in your browser, you can login with your existing user.'));
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
                return new ApiProblemResponse(new ApiProblem(400, 'Captcha not valid. Please try again or contact support.'));
            }
        }

        # Try to find user by username
        $oUserByName = $this->mUserTbl->select(['username' => $json->username]);
        $oUser = false;
        if(count($oUserByName) > 0) {
            $oUser = $oUserByName->current();
        } else {
            # Try to find user by email
            $oUserByEmail = $this->mUserTbl->select(['email' => $json->username]);
            if(count($oUserByEmail) > 0) {
                $oUser = $oUserByEmail->current();
            }
        }

        # Check if user was found
        if(!$oUser) {
            return new ApiProblemResponse(new ApiProblem(404, 'User not found'));
        }

        # Password check
        if(!password_verify($json->password,$oUser->password)) {
            return new ApiProblemResponse(new ApiProblem(401, 'Invalid credentials'));
        }

        # check for user bans
        $userTempBan = $this->mUserSetTbl->select([
            'user_idfs' => $oUser->User_ID,
            'setting_name' => 'user-tempban',
        ]);
        if(count($userTempBan) > 0) {
            return new ApiProblemResponse(new ApiProblem(403, 'You are temporarly banned. Please contact support.'));
        }

        if($oUser->User_ID <= 0) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid user id'));
        }

        # Create user session
        $session = new Container('webauth');
        $session->auth = $oUser;

        # check if ip is blacklisted
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $sIpAddr = filter_var ($_SERVER['HTTP_CLIENT_IP'], FILTER_SANITIZE_STRING);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $sIpAddr = filter_var ($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_SANITIZE_STRING);
        } else {
            $sIpAddr = filter_var ($_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_STRING);
        }

        # add user session
        $sessCheck = $this->mSessionTbl->select([
            'user_idfs' => $oUser->User_ID,
            'ipaddress' => strip_tags($sIpAddr),
        ]);
        if(count($sessCheck) == 0) {
            $this->mSessionTbl->insert([
                'user_idfs' => $oUser->User_ID,
                'ipaddress' => strip_tags($sIpAddr),
                'browser' => substr($_SERVER['HTTP_USER_AGENT'],0,25),
                'date_created' => date('Y-m-d H:i:s', time()),
                'date_last_login' => date('Y-m-d H:i:s', time()),
            ]);
        } else {
            $this->mSessionTbl->update([
                'date_last_login' => date('Y-m-d H:i:s', time()),
            ],[
                'user_idfs' => $oUser->User_ID,
                'ipaddress' => strip_tags($sIpAddr),
            ]);
        }
        # return user_id so client can get token
        return new ViewModel([
            'user_id' => $oUser->User_ID,
            'name' => $oUser->username,
            'photo' => 'media/users/300_21.jpg',
            'surname' => 'Herr',
            'company_name' => 'Swissfaucet',
            'job' => 'Member',
            'email' => $oUser->email,
            'phone' => '+00 000 00 00',
            'company_site' => 'swissfaucet',
        ]);
    }

    /**
     * Get header Authorization
     * */
    private function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }
    /**
     * get access token from header
     * */
    private function getBearerToken() {
        $headers = getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
