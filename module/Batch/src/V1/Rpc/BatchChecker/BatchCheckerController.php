<?php
namespace Batch\V1\Rpc\BatchChecker;

use Faucet\Tools\EmailTools;
use Faucet\Tools\SecurityTools;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class BatchCheckerController extends AbstractActionController
{
    /**
     * Settings Table
     *
     * @var TableGateway $mSettingTbl
     * @since 1.0.0
     */
    protected $mSettingTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * E-Mail Helper
     *
     * @var EmailTools $mMailTools
     * @since 1.0.0
     */
    protected $mMailTools;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper, $viewRenderer)
    {
        $this->mSettingTbl = new TableGateway('settings', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mMailTools = new EmailTools($mapper, $viewRenderer);
    }

    public function batchCheckerAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            if (isset($_REQUEST['authkey'])) {
                $ayetPBKey = $this->mSecTools->getCoreSetting('batch-run-key');
                if ($ayetPBKey) {
                    $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                    if ($checkKey == $ayetPBKey) {
                        $bWh = new Where();
                        $bWh->like('settings_key', '%_lastrun');

                        $batches = $this->mSettingTbl->select($bWh);

                        $totalBatches = $batches->count();

                        $today = time();
                        $notRun = [];
                        $run = [];
                        $runCount = 0;

                        foreach ($batches as $batch) {
                            if(strtotime($batch->settings_value) < $today-(3600*24)) {
                                $notRun[] = $batch->settings_key;
                            } else {
                                $run[] = $batch;
                                $runCount++;
                            }
                        }

                        $this->mMailTools->sendMail('batch_info', [
                            'totalB' => $totalBatches,
                            'runB' => $runCount,
                            'notB' => json_encode($notRun)
                        ], $this->mMailTools->getAdminEmail(), $this->mMailTools->getAdminEmail(), 'Daily Batch Statistics');

                        return [
                            'state' => 'done',
                            'total' => $totalBatches,
                            'run' => $run,
                            'not_run' => $notRun
                        ];
                    }
                }
            }
        }
    }
}
