<?php
namespace User\V1\Rpc\Forgot;

use Application\Controller\IndexController;
use Faucet\Tools\ApiTools;
use Faucet\Tools\EmailTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\ClientStatic;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ForgotController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * oAuth Table
     *
     * @var TableGateway $mOAuthTbl
     * @since 1.0.0
     */
    protected $mOAuthTbl;

    /**
     * Api Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * Settings Table
     *
     * @var TableGateway $mSettingsTbl
     * @since 1.0.0
     */
    protected $mSettingsTbl;

    /**
     * E-Mail Helper
     *
     * @var EmailTools $mMailTools
     * @since 1.0.0
     */
    protected $mMailTools;

    /**
     * Constructor
     *
     * ConfirmController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper, $viewRenderer)
    {
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mOAuthTbl = new TableGateway('oauth_users', $mapper);
        $this->mApiTools = new ApiTools($mapper);
        $this->mMailTools = new EmailTools($mapper, $viewRenderer);
    }

    /**
     * Forgot Password - Send Email to Reset PW
     *
     * @return ApiProblemResponse|string[]
     * @since 1.2.1
     */
    public function forgotAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {

        }

        if($request->isPut()) {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['password','password_verify','email'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            $token = filter_var($json->email, FILTER_SANITIZE_STRING);
            $password = filter_var($json->password, FILTER_SANITIZE_STRING);
            $passwordCheck = filter_var($json->password_verify, FILTER_SANITIZE_STRING);

            if($password != $passwordCheck) {
                return new ApiProblemResponse(new ApiProblem(400, 'Passwords do not match'));
            }

            $user = $this->mUserTbl->select(['password_reset_token' => $token]);
            if(count($user) == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Token is not valid.'));
            }
            $user = $user->current();

            if(strtotime($user->password_reset_date) > time()-(3600*48)) {
                if($user->User_ID != 0) {
                    $newPw = password_hash($password, PASSWORD_DEFAULT);
                    $this->mUserTbl->update([
                        'password' => $newPw,
                        'password_reset_token' => '',
                        'password_reset_date' => null,
                    ],['User_ID' => $user->User_ID]);

                    $this->mOAuthTbl->update([
                        'password' => $newPw,
                    ],['username' => $user->User_ID]);
                }

                return [
                    'state' => 'done',
                ];
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'Token is not valid anymore. You have 48 hours to set password. Start again.'));
            }
        }

        if($request->isPost()) {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['email','captcha','captcha_mode'],$this->getRequest()->getContent());
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

            $email = filter_var($json->email, FILTER_SANITIZE_EMAIL);
            $user = $this->mUserTbl->select(['email' => $email]);
            if(count($user) > 0) {
                $user = $user->current();
                if($user->password_reset_date == null) {
                    $secToken = $this->mMailTools->generateSecurityToken($user);
                    $confirmLink = $this->mMailTools->getSystemURL().'/#/reset-pw/'.$secToken;
                    $this->mUserTbl->update([
                        'password_reset_token' => $secToken,
                        'password_reset_date' => date('Y-m-d H:i:s', time()),
                    ],[
                        'User_ID' => $user->User_ID
                    ]);
                    $this->mMailTools->sendMail('email_forgot', [
                        'sEmailTitle' => 'Reset your Password',
                        'footerInfo' => 'Swissfaucet.io - Faucet #1',
                        'link' => $confirmLink
                    ], $this->mMailTools->getAdminEmail(), $user->email, 'Reset your Password');

                    return [
                        'status' => 'sent',
                    ];
                } else {
                    return [
                        'status' => 'already sent',
                    ];
                }
            } else {
                return [
                    'status' => 'nf',
                ];
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed.'));
    }
}
