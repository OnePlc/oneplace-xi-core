<?php
namespace Batch\V1\Rpc\Migrate;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class MigrateController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Log Table
     *
     * @var TableGateway $mBuffOldTbl
     * @since 1.0.0
     */
    protected $mBuffOldTbl;

    /**
     * User Buff Table
     *
     * @var TableGateway $mBuffTbl
     * @since 1.0.0
     */
    protected $mBuffTbl;

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
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mBuffTbl = new TableGateway('faucet_withdraw_buff', $mapper);
        $this->mBuffOldTbl = new TableGateway('user_buff', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function migrateAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            if (isset($_REQUEST['authkey'])) {
                $ayetPBKey = $this->mSecTools->getCoreSetting('cpx-pb-key');
                if ($ayetPBKey) {
                    $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                    if ($checkKey == $ayetPBKey) {
                        $migrated = 0;

                        $oldWh = new Where();
                        $oldWh->greaterThanOrEqualTo('expires', date('Y-m-d H:i:s', time()));
                        $oldWh->like('buff_type', 'daily-withdraw-buff');

                        $oldActiveBuffs = $this->mBuffOldTbl->select($oldWh);
                        foreach($oldActiveBuffs as $oldBuff) {
                            $numDays = round(abs(time() - strtotime($oldBuff->expires))/60/60/24)+1;

                            $this->mBuffTbl->insert([
                                'ref_idfs' => 0,
                                'ref_type' => 'oldbuff',
                                'label' => 'Daily Withdraw Buff (Migrated from Old System)',
                                'days_left' => $numDays,
                                'days_total'=> $numDays,
                                'amount' => $oldBuff->buff,
                                'created_date' => date('Y-m-d H:i:s', time()),
                                'user_idfs' => $oldBuff->user_idfs
                            ]);

                            $migrated++;
                        }

                        return [
                            'state' => 'done',
                            'migrated' => $migrated
                        ];
                    }
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));

    }
}
