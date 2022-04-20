<?php
namespace Batch\V1\Rpc\MinerPayments;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class MinerPaymentsController extends AbstractActionController
{
    /**
     * Miner Shares Table
     *
     * @var TableGateway $mSharesTbl
     * @since 1.0.0
     */
    protected $mSharesTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSharesTbl = new TableGateway('faucet_miner_shares', $mapper);

        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    public function minerPaymentsAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            if (isset($_REQUEST['authkey'])) {
                $ayetPBKey = $this->mSecTools->getCoreSetting('batch-miner-payments');
                if ($ayetPBKey) {
                    $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                    if ($checkKey == $ayetPBKey) {
                        $paid = 0;

                        $openPayments = $this->mSharesTbl->select(['state' => 'open', 'amount_coin' => 0]);
                        foreach($openPayments as $pay) {
                            $newBalance = $this->mTransaction->executeTransaction($pay->amount_approx, false, $pay->user_idfs, $pay->shares, $pay->coin.'-nanov2', $pay->share_percent.'% of all shares on pool.');
                            if($newBalance) {
                                $paid += $pay->amount_approx;
                                $this->mSharesTbl->update(['state' => 'paid', 'amount_coin' => $pay->amount_approx],['id' => $pay->id]);
                            }
                        }
                        return [
                            'state' => 'done',
                            'paid' => $paid
                        ];
                    }
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));
    }
}
