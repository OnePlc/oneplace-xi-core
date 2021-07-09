<?php
namespace Support\V1\Rpc\Browser;

use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class BrowserController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Tools Helper
     *
     * @var ApiTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

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
     * FAQController constructor.
     * @param Adapter $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        # Init Tables for this API
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mApiTools = new ApiTools($mapper);
    }

    public function browserAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $request = $this->getRequest();

        $platform = 'windows';
        if(isset($_REQUEST['platform'])) {
            $platform = filter_var($_REQUEST['platform'], FILTER_SANITIZE_STRING);
        }

        if($request->isGet()) {
            switch($platform) {
                case 'windows64':
                    $this->mUserTools->setSetting($me->User_ID, 'browser-win64-download', date('Y-m-d H:i:s', time()));
                    return [
                        'link' => $this->mApiTools->getSystemURL().'/browser-download/chromium-portable-win64.zip',
                    ];
                case 'linux':
                    $this->mUserTools->setSetting($me->User_ID, 'browser-linux-download', date('Y-m-d H:i:s', time()));
                    return [
                        'link' => $this->mApiTools->getSystemURL().'/browser-download/ungoogled-chromium_91.0.4472.114-1.1.AppImage',
                    ];
                case 'macos':
                    $this->mUserTools->setSetting($me->User_ID, 'browser-macos-download', date('Y-m-d H:i:s', time()));
                    return [
                        'link' => $this->mApiTools->getSystemURL().'/browser-download/chromium.app.ungoogled-91.0.4472.101.zip',
                    ];
                default:
                    return new ApiProblemResponse(new ApiProblem(404, 'Platform not found'));
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
