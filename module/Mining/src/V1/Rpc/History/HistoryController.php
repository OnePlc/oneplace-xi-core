<?php
/**
 * HistoryController.php - Mining History Controller
 *
 * Main Controller for Faucet Mining History
 *
 * @category Controller
 * @package Mining
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Mining\V1\Rpc\History;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class HistoryController extends AbstractActionController
{
    /**
     * Mining History Table
     *
     * @var TableGateway $mMinerTbl
     * @since 1.0.0
     */
    protected $mMinerTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Basic Tools
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Quote Table
     *
     * @var TableGateway $mQuoteTbl
     * @since 1.0.0
     */
    protected $mQuoteTbl;

    /**
     * Constructor
     *
     * HistoryController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mMinerTbl = new TableGateway('faucet_miner', $mapper);
        $this->mQuoteTbl = new TableGateway('faucet_didyouknow', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
    }

    /**
     * Get Users Mining History
     *
     * @since 1.0.0
     */
    public function historyAction()
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
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $pageSize = 25;
            $miningHistory = [];
            $historySel = new Select($this->mMinerTbl->getTable());
            $checkWh = new Where();
            $checkWh->equalTo('user_idfs', $me->User_ID);
            $checkWh->greaterThan('amount_coin', 0);
            $historySel->where($checkWh);
            $historySel->order('date DESC');
            # Create a new pagination adapter object
            $oPaginatorAdapter = new DbSelect(
            # our configured select object
                $historySel,
                # the adapter to run it against
                $this->mMinerTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $historyPaginated = new Paginator($oPaginatorAdapter);
            $historyPaginated->setCurrentPageNumber($page);
            $historyPaginated->setItemCountPerPage($pageSize);
            foreach($historyPaginated as $history) {
                # skip empty entries
                if($history->amount_coin > 0) {
                    $history->amount_coin = (float)$history->amount_coin;
                    $history->shares = (int)$history->shares;
                    $miningHistory[] = $history;
                }
            }

            $totalHistory = $this->mMinerTbl->select($checkWh)->count();

            # get current hashrate for gpu miner
            $gpuCurrentHash = 0;
            $bHashFound = $this->mUserTools->getSetting($me->User_ID, 'gpuminer-currenthashrate');
            if($bHashFound) {
                $gpuCurrentHash = number_format($bHashFound, 2);
            }

            $bPoolFound = $this->mUserTools->getSetting($me->User_ID, 'gpuminer-currentpool');
            $poolUrl = "#";
            if($bPoolFound) {
                $poolUrlDB = $this->mSecTools->getCoreSetting('nanopool-'.$bPoolFound);
                if($poolUrlDB) {
                    $poolUrl = $poolUrlDB;
                }
            }

            # get current hashrate for cpu miner
            $cpuCurrentHash = 0;
            $bcpuHashFound = $this->mUserTools->getSetting($me->User_ID, 'cpuminer-currenthashrate');
            if($bcpuHashFound) {
                $cpuCurrentHash = number_format($bcpuHashFound, 2);
            }
            $bcpuPoolFound = $this->mUserTools->getSetting($me->User_ID, 'cpuminer-currentpool');
            $cpuPoolUrl = "#";
            if($bcpuPoolFound) {
                $poolUrlDB = $this->mSecTools->getCoreSetting('nanopool-'.$bcpuPoolFound);
                if($poolUrlDB) {
                    $cpuPoolUrl = $poolUrlDB;
                }
            }

            $quote = "";
            if($page == 1) {
                # get some random satoshi quote
                $quotes = $this->mQuoteTbl->select(['page' => 'mining']);
                $quotesOrdered = [];
                foreach($quotes as $q) {
                    $quotesOrdered[] = (object)['id' => $q->Tip_ID,'quote' => $q->description,'href' => $q->href];
                }
                $quoteRandom = rand(0, count($quotesOrdered)-1);
                $quote = $quotesOrdered[$quoteRandom];
            }

            return [
                '_self' => [],
                '_embedded' => [
                    'gpu_current_hash' => $gpuCurrentHash,
                    'cpu_current_hash' => $cpuCurrentHash,
                    'total_items' => $totalHistory,
                    'pool_url' => $poolUrl.'/swissfaucetio'.$me->User_ID,
                    'cpu_pool_url' => $cpuPoolUrl.'/swissfaucetio'.$me->User_ID,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'page_count' => (round($totalHistory / $pageSize) > 0) ? round($totalHistory / $pageSize) : 1,
                    'history' => $miningHistory,
                    'show_info' => false,
                    'quote' => $quote,
                    'show_info_msg' => ''
                ]
            ];
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
