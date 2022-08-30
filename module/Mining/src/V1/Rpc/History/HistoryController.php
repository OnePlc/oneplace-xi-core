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

    protected $mMinerBatchTbl;

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
        $this->mMinerBatchTbl = new TableGateway('faucet_miner_payment', $mapper);
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
            if($page <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Page'));
            }
            $pageSize = 25;
            $miningHistory = [];
            $historySel = new Select($this->mMinerBatchTbl->getTable());
            $checkWh = new Where();
            $checkWh->equalTo('user_idfs', $me->User_ID);
            $checkWh->greaterThan('amount_coin', 0);
            $checkWh->greaterThanOrEqualTo('date', '2021-10-28 23:00:00');
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

            $totalHistory = $this->mMinerBatchTbl->select($checkWh)->count();

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

            $activeWorkerWh = new Where();
            $activeWorkerWh->equalTo('user_idfs', $me->User_ID);
            $activeWorkerWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', time()-3600));

            $hashrateFormat = [
                'etc' => ' MH/s',
                'rvn' => ' MH/s',
                'xmr' => ' H/s'
            ];

            $poolAddresses = [
                'etc' => '0x9b79a4ad71e6f1db71adc5b4f0dddbee4c1bcad1',
                'rvn' => 'RQMwgG6sY3aby48Hdo7MdQUHZUTUvACCcT',
                'xmr' => '45mYciovPc8GNWBuQaymPyGcNvubron5DeyVRNgMRAExHCumTDZXwnH657atftktRkEF4xD14wcFZTcCaWzo99wg317afGf'
            ];

            $myWorkers = [];
            $activeWorkers = $this->mMinerBatchTbl->select($activeWorkerWh);
            foreach($activeWorkers as $worker) {
                $hashrateInfo = '';
                if(array_key_exists($worker->coin, $hashrateFormat)) {
                    $hashrateInfo = $hashrateFormat[$worker->coin];
                }
                $poolUrl = '';
                if(array_key_exists($worker->coin, $poolAddresses)) {
                    $poolUrl = 'https://'.$worker->coin.'.nanopool.org/account/'.$poolAddresses[$worker->coin].'/swissfaucetio'.$me->User_ID;
                    if($worker->worker != 'default') {
                        $poolUrl.='-'.$worker->worker;
                    }
                }
                $myWorkers[] = [
                    'coin' => $worker->coin,
                    'hashrate' => round($worker->hashrate,2).$hashrateInfo,
                    'name' => $worker->worker,
                    'pool' => $poolUrl
                ];
            }

            $viewData = [
                'workers' => $myWorkers,
                'gpu_current_hash' => 0,
                'cpu_current_hash' => 0,
                'total_items' => $totalHistory,
                'pool_url' => '',
                'cpu_pool_url' => '',
                'page' => $page,
                'page_size' => $pageSize,
                'page_count' => (round($totalHistory / $pageSize) > 0) ? round($totalHistory / $pageSize) : 1,
                'history' => $miningHistory,
                'show_info' => false,
                'quote' => $quote,
                'show_info_msg' => ''
            ];

            if($page == 1) {
                $viewData['token_balance'] = $me->token_balance;
            }

            $hasMessage = $this->mSecTools->getCoreSetting('faucet-mining-msg-content');
            if($hasMessage) {
                $message = $hasMessage;
                $messageType = $this->mSecTools->getCoreSetting('faucet-mining-msg-level');
                $xpReq = $this->mSecTools->getCoreSetting('faucet-mining-msg-xplevel');
                $addMsg = false;
                if($xpReq) {
                    if($me->xp_level >= $xpReq) {
                        $addMsg = true;
                    }
                } else {
                    $addMsg = true;
                }

                if($addMsg && strlen($message) > 0) {
                    $viewData['message'] = [
                        'type' => $messageType,
                        'message' => $message
                    ];
                }
            }

            return [
                '_self' => [],
                '_embedded' => $viewData
            ];
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
