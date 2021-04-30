<?php
/**
 * LoginController.php - Application Login Controller
 *
 * Main Controller for Application Login
 *
 * @category Controller
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
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;

class LoginController extends AbstractActionController
{
    private $mUserTbl;
    private $mXPLevelTbl;

    // Add this constructor:
    public function __construct($table)
    {
        $this->mUserTbl = new TableGateway('user', $table);
        $this->mXPLevelTbl = new TableGateway('user_xp_level', $table);
    }

    /**
     * User Login for Application
     *
     * @return ViewModel|ApiProblem
     * @since 1.1.1
     */
    public function loginAction()
    {
        # Load Data from Request Body
        $json = IndexController::loadJSONFromRequestBody(['username','password'],$this->getRequest()->getContent());
        if(!$json) {
            return new ApiProblem(400, 'Invalid Request Body (required fields missing)');
        }

        # Perform Login
        $userCheck = $this->mUserTbl->select(['username' => $json->username]);
        if(count($userCheck) > 0) {
            $userCheck = $userCheck->current();
            if(!password_verify($json->password,$userCheck->password)) {
                return new ApiProblemResponse(new ApiProblem(401, 'Wrong Password'));
            }

            # Calculate XP Percent
            $oNextLvl = $this->mXPLevelTbl->select(['Level_ID' => ($userCheck->xp_level + 1)]);
            if(count($oNextLvl) > 0) {
                $oNextLvl = $oNextLvl->current();
            } else {
                # Max Level
                $oNextLvl = $this->mXPLevelTbl->select(['Level_ID' => ($userCheck->xp_level)])->current();
            }
            $dPercent = 0;
            if ($userCheck->xp_current != 0) {
                $dPercent = round((100 / ($oNextLvl->xp_total / $userCheck->xp_current)), 2);
            }

            # Return User Object
            return new ViewModel([
                'username' => $userCheck->username,
                'token_balance' => $userCheck->token_balance,
                'xp_level' => $userCheck->xp_level,
                'xp_percent' => $dPercent,
            ]);
        } else {
            return new ApiProblemResponse(new ApiProblem(404, 'User not found'));
        }
    }
}
