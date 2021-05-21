<?php
/**
 * ConfirmController.php - Confirm Controller
 *
 * Main Controller for User E-Mail Confirmations
 *
 * @category Resource
 * @package User
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace User\V1\Rpc\Confirm;

use Application\Controller\IndexController;
use Faucet\Tools\EmailTools;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class ConfirmController extends AbstractActionController
{

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

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
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mMailTools = new EmailTools($mapper, $viewRenderer);
    }

    /**
     * Confirm Actions with Tokens sent by Mail
     *
     * @return ApiProblemResponse|string[]
     * @since 1.0.0
     */
    public function confirmAction()
    {
        # Get Data from Request Body
        $json = IndexController::loadJSONFromRequestBody(['action','token'],$this->getRequest()->getContent());
        if(!$json) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
        }

        # check for attack vendors
        $secResult = $this->mSecTools->basicInputCheck([$json->action,$json->token]);
        if($secResult !== 'ok') {
            return new ApiProblemResponse(new ApiProblem(418, 'Potential Attack - Goodbye'));
        }

        $token = filter_var($json->token, FILTER_SANITIZE_STRING);
        $action = filter_var($json->action, FILTER_SANITIZE_STRING);

        if(strlen($action) < 5) {
            return new ApiProblemResponse(new ApiProblem(418, 'No coffee here'));
        }

        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        /**
         * Send Request E-Mail is special action
         */
        if($action == 'request_verify') {
            # Prevent 500 error
            if(!$this->getIdentity()) {
                return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
            }
            $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
            if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
                return new ApiProblemResponse(new ApiProblemResponse($me));
            }

            if($me->send_verify) {
                return [
                    'state' => 'already sent',
                ];
            } else {
                $secToken = $this->mMailTools->generateSecurityToken($me);
                $confirmLink = $this->mMailTools->getSystemURL().'/#/verify-email/'.$secToken;
                $this->mUserTbl->update([
                    'send_verify' => date('Y-m-d H:i:s', time()),
                    'password_reset_token' => $secToken,
                    'password_reset_date' => date('Y-m-d H:i:s', time()),
                ],[
                    'User_ID' => $me->User_ID
                ]);
                $this->mMailTools->sendMail('email_verify', [
                    'footerInfo' => 'Swissfaucet.io - Faucet #1',
                    'link' => $confirmLink
                ], $this->mMailTools->getAdminEmail(), $me->email, 'Verify your E-Mail Address');
                return [
                    'state' => 'success',
                ];
            }
        } else {
            if(strlen($token) < 10) {
                return new ApiProblemResponse(new ApiProblem(418, 'No coffee here'));
            }
            $user = $this->mUserTbl->select(['password_reset_token' => $token]);
            if(count($user) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'Invalid token - not user found'));
            }
            $user = $user->current();

            switch($action) {
                /**
                 * Confirm E-Mail Change
                 */
                case 'email_change':
                    $secToken = $this->mMailTools->generateSecurityToken($user);
                    $confirmLink = $this->mMailTools->getSystemURL().'/#/verify-email/'.$secToken;
                    $this->mUserTbl->update([
                        'email' => $user->email_change,
                        'email_verified' => 0,
                        'email_change' => '',
                        'password_reset_token' => $secToken,
                        'password_reset_date' => date('Y-m-d H:i:s', time()),
                    ],[
                        'User_ID' => $user->User_ID
                    ]);
                    $this->mMailTools->sendMail('email_verify', [
                        'footerInfo' => 'Swissfaucet.io - Faucet #1',
                        'link' => $confirmLink
                    ], $this->mMailTools->getAdminEmail(), $user->email_change, 'Verify your E-Mail Address');
                    return [
                        'state' => 'success',
                    ];
                /**
                 * Verify E-Mail Address
                 */
                case 'verify_email':
                    $this->mUserTbl->update([
                        'email_verified' => 1,
                        'password_reset_token' => '',
                        'password_reset_date' => NULL,
                        'send_verify' => NULL,
                    ],[
                        'User_ID' => $user->User_ID
                    ]);
                    return [
                        'state' => 'success',
                        'verified' => 1,
                    ];
                default:
                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid action'));
            }
        }
    }
}
