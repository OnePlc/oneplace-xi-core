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
use Faucet\Tools\UserTools;
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
     * Api Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * User Tools Helper
     *
     * @var ApiTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

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
        $this->mUserTools = new UserTools($mapper);
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

        $platform = 'windows';
        if(isset($_REQUEST['platform'])) {
            $platformNew = filter_var($_REQUEST['platform'], FILTER_SANITIZE_STRING);
            if($platformNew == 'linux') {
                $platform = 'linux';
            }
        }

        if($request->isGet()) {
            /**
             * Generate Download Link
             */
            if(isset($_REQUEST['cpuminer'])) {
                # For CPU miner (xmr-rig)
                $this->mUserTools->setSetting($me->User_ID, 'cpuminer-download', date('Y-m-d H:i:s', time()));
                if(file_exists('/var/nanominer/xmrminer-'.$me->User_ID.'.zip')) {
                    $sLink = $this->mApiTools->getSystemURL().'/miner-download/xmrminer-'.$me->User_ID.'.zip';
                } else {
                    $sConfig = file_get_contents('/var/xmr-config.json');
                    $sLink = '';
                    $zip = new \ZipArchive;
                    copy('/var/nanominer/xmrig-6.15.2.zip','/var/nanominer/xmrminer-'.$me->User_ID.'.zip');
                    if ($zip->open('/var/nanominer/xmrminer-'.$me->User_ID.'.zip') === TRUE) {
                        //Modify contents:
                        $newContents = str_replace(['swissfaucetio1'],['swissfaucetio'.$me->User_ID],$sConfig);
                        //Delete the old...
                        $zip->deleteName('xmrig-6.15.2/config.json');
                        //Write the new...
                        $zip->addFromString('xmrig-6.15.2/config.json', $newContents);
                        //And write back to the filesystem.
                        $zip->close();
                        $sLink = $this->mApiTools->getSystemURL().'/miner-download/xmrminer-'.$me->User_ID.'.zip';
                    }

                    if($sLink == '') {
                        return new ApiProblemResponse(new ApiProblem(500, 'Could not generate download link'));
                    }
                }
            } else {
                if(isset($_REQUEST['nanominer'])) {
                    $this->mUserTools->setSetting($me->User_ID, 'gpuminer-download', date('Y-m-d H:i:s', time()));
                    if(file_exists('/var/nanominer/nanominer-'.$me->User_ID.'-'.$platform.'.zip')) {
                        $sLink = $this->mApiTools->getSystemURL().'/miner-download/nanominer-'.$me->User_ID.'-'.$platform.'.zip';
                    } else {
                        $sConfig = file_get_contents('/var/nano-config.ini');
                        $sLink = '';
                        $zip = new \ZipArchive;
                        copy('/var/nanominer/nanominer-' . $platform . '-3.4.3.zip', '/var/nanominer/nanominer-' . $me->User_ID . '-' . $platform . '.zip');
                        if ($zip->open('/var/nanominer/nanominer-' . $me->User_ID . '-' . $platform . '.zip') === TRUE) {
                            //Modify contents:
                            $newContents = str_replace(['swissfaucetio1'], ['swissfaucetio' . $me->User_ID], $sConfig);
                            //Delete the old...
                            $zip->deleteName('nanominer-' . $platform . '-3.4.3/config.ini');
                            //Write the new...
                            $zip->addFromString('nanominer-' . $platform . '-3.4.3/config.ini', $newContents);
                            //And write back to the filesystem.
                            $zip->close();
                            $sLink = $this->mApiTools->getSystemURL() . '/miner-download/nanominer-' . $me->User_ID . '-' . $platform . '.zip';
                        }

                        if ($sLink == '') {
                            return new ApiProblemResponse(new ApiProblem(500, 'Could not generate download link'));
                        }
                    }
                } else {
                    $this->mUserTools->setSetting($me->User_ID, 'gpuminer-download', date('Y-m-d H:i:s', time()));
                    if(file_exists('/var/nanominer/gminer_2_70-'.$me->User_ID.'-'.$platform.'.zip')) {
                        $sLink = $this->mApiTools->getSystemURL().'/miner-download/gminer_2_70-'.$me->User_ID.'-'.$platform.'.zip';
                    } else {
                        $sConfig = file_get_contents('/var/mine_etc.bat');
                        $sLink = '';
                        $zip = new \ZipArchive;
                        copy('/var/nanominer/gminer_2_70_windows64.zip','/var/nanominer/gminer_2_70-'.$me->User_ID.'-'.$platform.'.zip');
                        if ($zip->open('/var/nanominer/gminer_2_70-'.$me->User_ID.'-'.$platform.'.zip') === TRUE) {
                            //Modify contents:
                            $newContents = str_replace(['swissfaucetio1'],['swissfaucetio'.$me->User_ID],$sConfig);
                            //Delete the old...
                            $zip->deleteName('mine_etc.bat');
                            //Write the new...
                            $zip->addFromString('mine_etc.bat', $newContents);
                            //And write back to the filesystem.
                            $zip->close();
                            $sLink = $this->mApiTools->getSystemURL().'/miner-download/gminer_2_70-'.$me->User_ID.'-'.$platform.'.zip';
                        }

                        if($sLink == '') {
                            return new ApiProblemResponse(new ApiProblem(500, 'Could not generate download link'));
                        }
                    }
                }
            }

            return new ViewModel([
                'link' => $sLink,
            ]);
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
