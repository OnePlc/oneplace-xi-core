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
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Session\Container;

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
     * Constructor
     *
     * LoginController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
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
        $json = IndexController::loadJSONFromRequestBody(['username','password'],$this->getRequest()->getContent());
        if(!$json) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
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

        # Create user session
        $session = new Container('webauth');
        $session->auth =  $oUser;

        # return user_id so client can get token
        return new ViewModel([
            'user_id' => $oUser->User_ID,
        ]);
    }
}
