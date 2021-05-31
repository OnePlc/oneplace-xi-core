<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-skeleton for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-skeleton/blob/master/LICENSE.md New BSD License
 */

namespace Application\Controller;

use Laminas\ApiTools\Admin\Module as AdminModule;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;

class IndexController extends AbstractActionController
{
    private $mapper;

    /**
     * Constructor
     *
     * IndexController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mapper = $mapper;
    }

    public function indexAction()
    {
        /**
        if (class_exists(AdminModule::class, false)) {
            return $this->redirect()->toRoute('api-tools/ui');
        } **/

        $settingsTbl = new TableGateway('settings', $this->mapper);
        $apiName = $settingsTbl->select(['settings_key' => 'app-title']);
        $apiTitle = '-';
        if(count($apiName) > 0) {
            $apiTitle = $apiName->current()->settings_value;
        }
        $this->layout()->appTitle = $apiTitle;
        return new ViewModel([
            'title' => $apiTitle
        ]);
    }

    /**
     * Load and check JSON from Request Body
     *
     * @param array $aRequiredFields
     * @return false|mixed
     * @since 1.1.1
     */
    public static function loadJSONFromRequestBody($aRequiredFields = [],$sContent)
    {
        $oJSON = json_decode($sContent);

        if(!is_object($oJSON)) {
            return false;
        } else {
            if(count($aRequiredFields) > 0) {
                foreach($aRequiredFields as $sField) {
                    if(!property_exists($oJSON,$sField)) {
                        return false;
                    }
                }
            }
            return $oJSON;
        }
    }
}
