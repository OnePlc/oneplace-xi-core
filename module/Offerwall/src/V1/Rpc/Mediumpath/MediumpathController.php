<?php
namespace Offerwall\V1\Rpc\Mediumpath;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\ClientStatic;
use Laminas\Mvc\Controller\AbstractActionController;

class MediumpathController extends AbstractActionController
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

    public function mediumpathAction()
    {

        $sUrl = 'https://www.mediumpath.com/api/offers/?api_key=3353c215d88d5042759be8abd3b50217&devices=desktop,ipad,android&country=us,de';
        $response = ClientStatic::get($sUrl);

        $status = $response->getStatusCode();
        $googleResponse = $response->getBody();

        $oRep = json_decode($googleResponse);

        echo 'Offers: '.$oRep->number_of_offers.'<br/><br/>';

        $aOffers = $oRep->offers;

        foreach($oRep->offers as $offer) {
            echo '<br/>- '.$offer->title.' - '.$offer->target_countries.' - '.$offer->payout_usd;
        }

        return false;

        return new ViewModel([
            'response' => json_decode($googleResponse),
        ]);
    }
}
