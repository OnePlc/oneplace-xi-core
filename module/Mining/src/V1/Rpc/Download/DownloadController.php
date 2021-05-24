<?php
/**
 * DownloadController.php - Mining Download Controller
 *
 * Main Controller for Faucet Miner Download Generator
 *
 * @category Controller
 * @package Mining
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Mining\V1\Rpc\Download;

use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Mvc\Controller\AbstractActionController;

class DownloadController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Emal Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * Constructor
     *
     * DownloadController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
        $this->mApiTools = new ApiTools($mapper);
    }

    /**
     * Generate Miner Download Link for User
     *
     * @return ApiProblemResponse
     * @since 1.0.0
     */
    public function downloadAction()
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

        if($request->isGet()) {
            if(file_exists('/var/nanominer/nanominer-'.$me->User_ID.'.zip')) {
                $sLink = $this->mApiTools->getSystemURL().'/miner-download/nanominer-'.$me->User_ID.'.zip';
            } else {
                $sConfig = file_get_contents('/var/nano-config.ini');
                $sLink = '';
                $zip = new \ZipArchive;
                copy('/var/nanominer/nanominer-windows-3.3.5.zip','/var/nanominer/nanominer-'.$me->User_ID.'.zip');
                if ($zip->open('/var/nanominer/nanominer-'.$me->User_ID.'.zip') === TRUE) {
                    //Modify contents:
                    $newContents = str_replace(['swissfaucetio1'],['swissfaucetio'.$me->User_ID],$sConfig);
                    //Delete the old...
                    $zip->deleteName('nanominer-windows-3.3.5/config.ini');
                    //Write the new...
                    $zip->addFromString('nanominer-windows-3.3.5/config.ini', $newContents);
                    //And write back to the filesystem.
                    $zip->close();
                    $sLink = $this->mApiTools->getSystemURL().'/miner-download/nanominer-'.$me->User_ID.'.zip';
                }

                if($sLink == '') {
                    return new ApiProblemResponse(new ApiProblem(500, 'Could not generate download link'));
                }
            }

            return new ViewModel([
                'link' => $sLink,
            ]);
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}