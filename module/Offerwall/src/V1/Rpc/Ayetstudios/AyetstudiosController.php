<?php
/**
 * AyetstudiosController.php - Ayetstudios Controller
 *
 * Main Controller for Ayetstudios Offerwall Basic Integration
 *
 * @category Resource
 * @package User
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Offerwall\V1\Rpc\Ayetstudios;

use Faucet\Tools\EmailTools;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Http\ClientStatic;

class AyetstudiosController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Settings Table
     *
     * @var TableGateway $mSettingsTbl
     * @since 1.0.0
     */
    protected $mSettingsTbl;

    /**
     * Constructor
     *
     * AyetstudiosController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
    }

    /**
     * Get current Ayetstudios Offerwall for User
     *
     * @since 1.0.0
     */
    public function ayetstudiosAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $baseUrl = $this->mSettingsTbl->select(['settings_key' => 'ayetstudio-baseurl']);
        if(count($baseUrl) == 0) {
            return new ApiProblemResponse(new ApiProblem(500, 'Aysetserver URL not set. Contact Admin.'));
        }
        $baseUrl = $baseUrl->current()->settings_value;

        $sUrl = str_replace(['##USERID##','##USERAGENT##','##IP##'],[$me->User_ID,urlencode($_SERVER['HTTP_USER_AGENT']),$_SERVER['REMOTE_ADDR']],$baseUrl);
        $response = ClientStatic::get($sUrl);

        $status = $response->getStatusCode();
        $googleResponse = $response->getBody();

        return new ViewModel([
            'response' => json_decode($googleResponse),
        ]);
    }
}
