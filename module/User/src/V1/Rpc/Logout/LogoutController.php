<?php
/**
 * LogoutController.php - Logout Controller
 *
 * Main Controller for User API Logout
 *
 * @category Resource
 * @package User
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace User\V1\Rpc\Logout;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;

class LogoutController extends AbstractActionController
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * Constructor
     *
     * LogoutController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSession = new Container('webauth');
    }

    /**
     * Destroy User Session of it exists
     *
     * @return ApiProblemResponse|ViewModel
     * @since 1.0.0
     */
    public function logoutAction()
    {
        if(!isset($this->mSession->auth)) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }

        unset($this->mSession->auth);

        return new ViewModel([
            'bye' => time(),
        ]);
    }
}
