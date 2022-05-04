<?php
namespace Backend\V1\Rpc\ShortEarnings;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class ShortEarningsController extends AbstractActionController
{
    /**
     * Shortlink Provider Table
     *
     * @var TableGateway $mShortProviderTbl
     * @since 1.0.0
     */
    protected $mShortProviderTbl;

    /**
     * Shortlink Table User Table
     *
     * Relation between Shortlink and User
     * to determine if user has completed a Shortlink
     *
     * @var TableGateway $mShortDoneTbl
     * @since 1.0.0
     */
    protected $mShortDoneTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mShortProviderTbl = new TableGateway('shortlink', $mapper);
        $this->mShortDoneTbl = new TableGateway('faucet_transaction', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function shortEarningsAction()
    {
        $request = $this->getRequest();

        /**
         * Load Shortlink Info
         *
         * @since 1.0.0
         */
        if($request->isPost()) {
            $json = IndexController::loadJSONFromRequestBody(['date'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            $date = $json->date;

            $shActive = $this->mShortProviderTbl->select();

            $doneSel = new Select($this->mShortDoneTbl->getTable());
            $doneWh = new Where();
            $doneWh->equalTo('ref_type', 'shortlink-complete');
            $doneWh->between('date', date('Y-m', strtotime($date)).'-01',date('Y-m', strtotime($date)).'-31');
            $doneSel->where($doneWh);

            //$doneLinks = [];
            //$doneCount = 0;
            $doneLinks = $this->mShortDoneTbl->selectWith($doneSel);
            $doneCount = $doneLinks->count();

            $doneByProvider = [];
            foreach($doneLinks as $dl) {
                if(!array_key_exists($dl->ref_idfs, $doneByProvider)) {
                    $doneByProvider[$dl->ref_idfs] = ['cost' => 0,'views' => 0];
                }
                $doneByProvider[$dl->ref_idfs]['cost']+=$dl->amount;
                $doneByProvider[$dl->ref_idfs]['views']++;
            }

            $tokenValue = $this->mSecTools->getCoreSetting('token-value');

            $shortlinks = [];
            foreach($shActive as $sh) {
                if(array_key_exists($sh->Shortlink_ID, $doneByProvider)) {
                    $shortlinks[] = [
                        'id' => $sh->Shortlink_ID,
                        'name' => $sh->label,
                        'cost_coin' => $doneByProvider[$sh->Shortlink_ID]['cost'],
                        'cost_usd' => round($doneByProvider[$sh->Shortlink_ID]['cost']*$tokenValue,2),
                        'views' => $doneByProvider[$sh->Shortlink_ID]['views']
                    ];
                } else {
                    $shortlinks[] = [
                        'id' => $sh->Shortlink_ID,
                        'name' => $sh->label,
                        'cost_coin' => 0,
                        'cost_usd' => 0,
                        'views' => 0
                    ];
                }
            }

            return [
                'shortlink' => $shortlinks,
                'total' => $doneCount,
                'dev' => $doneByProvider,
                'date' => $date,
                'from' => date('Y-m', strtotime($date)).'-01 00:00:00',
                'to' => date('Y-m', strtotime($date)).'-31 23:59:59'
            ];
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not alloawed'));

    }
}
