<?php
/**
 * VerifyController.php - E-Mail Verification Controller
 *
 * Main Controller for User E-Mail Verification
 *
 * @category Resource
 * @package User
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */


namespace User\V1\Rpc\Verify;

use Faucet\Tools\ApiTools;
use Faucet\Tools\EmailTools;
use Faucet\Tools\SecurityTools;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class VerifyController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Api Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * Constructor
     *
     * ConfirmController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mApiTools = new ApiTools($mapper);
    }

    public function verifyAction()
    {
        $request = $this->getRequest();

        /**
         * Verify E-Mail Address
         *
         * @since 1.0.0
         */
        if($request->isGet()) {
            echo '<div class="container">';
            # get token
            $token = filter_var($this->params()->fromRoute('token', ''), FILTER_SANITIZE_STRING);

            # get user by token
            $userFound = $this->mUserTbl->select(['password_reset_token' => $token]);
            if(count($userFound) == 0) {
                echo 'Invalid token';
                return false;
            }
            $userFound = $userFound->current();

            # check timer
            if((strtotime($userFound->send_verify)+172800) < time()) {
                echo 'Link is not valid anymore. You must verify within 48 hours. Please start again.';
                return false;
            }

            # double check user id
            if($userFound->User_ID == 0 || !is_numeric($userFound->User_ID)) {
                echo 'There was some technical error.';
                return false;
            }

            # do not double verify
            if($userFound->email_verified == 1) {
                echo 'Account is already verified';
                return false;
            }

            # verify
            $this->mUserTbl->update([
                'email_verified' => 1,
                'password_reset_token' => '',
            ],[
                'User_ID' => $userFound->User_ID,
            ]);

            echo 'Successfully verified.';

            $redirectUrl = $this->mApiTools->getDashboardURL();
            if($redirectUrl !== false) {
                echo ' You get redirected to the dashboard in 5 seconds...otherwise ';
                # redirect to website
                echo '<a href="'.$redirectUrl.'">click here </a>';
                echo '<meta http-equiv="refresh" content="5; url='.$redirectUrl.'" />';
            }

            echo '</div>';

            return false;
        }
    }
}
