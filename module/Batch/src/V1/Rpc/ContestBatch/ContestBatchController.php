<?php
namespace Batch\V1\Rpc\ContestBatch;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class ContestBatchController extends AbstractActionController
{

    /**
     * Daily Task User Table
     *
     * @var TableGateway $mTasksDoneTbl
     * @since 1.0.0
     */
    protected $mTasksDoneTbl;

    /**
     * User Statistics Table
     *
     * @var TableGateway $mUserStatsTbl
     * @since 1.0.0
     */
    protected $mUserStatsTbl;

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
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mTasksDoneTbl = new TableGateway('faucet_dailytask_user', $mapper);
        $this->mUserStatsTbl = new TableGateway('user_faucet_stat', $mapper);
        $this->mSettingsTbl = new TableGateway('settings', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    /**
     * Run Batch
     *
     * @return ApiProblemResponse|string[]
     */
    public function contestBatchAction()
    {
        $req = $this->getRequest();

        if($req->isGet()) {
            if(isset($_REQUEST['authkey'])) {
                $batchKey = $this->mSecTools->getCoreSetting('job_user_dailytask_key');

                $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                if($checkKey == $batchKey) {
                    $offset = $this->mSecTools->getCoreSetting('job_user_dailytask_offset');
                    $lastrun = $this->mSecTools->getCoreSetting('job_user_dailytask_lastrun');
                    $limit = 20000;

                    if(strtotime($lastrun) <= time()-7200) {
                        $tSel = new Select($this->mTasksDoneTbl->getTable());
                        $tSel->where(['platform' => 'web']);
                        $tSel->order('date ASC');
                        $tSel->offset($offset);
                        $tSel->limit($limit);

                        $tasksDone = $this->mTasksDoneTbl->selectWith($tSel);

                        $tasksByUserId = [];
                        $tasksMonthlyByUserId = [];
                        foreach($tasksDone as $t) {
                            if(!array_key_exists($t->user_idfs, $tasksByUserId)) {
                                $tasksByUserId[$t->user_idfs] = 0;
                            }
                            $tasksByUserId[$t->user_idfs]++;

                            $month = date('n-Y', strtotime($t->date));
                            if(!array_key_exists($t->user_idfs.'-'.$month,$tasksMonthlyByUserId)) {
                                $tasksMonthlyByUserId[$t->user_idfs.'-'.$month] = 0;
                            }
                            $tasksMonthlyByUserId[$t->user_idfs.'-'.$month]++;
                        }

                        foreach(array_keys($tasksByUserId) as $userId) {
                            $check = $this->mUserStatsTbl->select(['user_idfs' => $userId, 'stat_key' => 'user-dailys-total']);
                            if($check->count() == 0) {
                                $this->mUserStatsTbl->insert([
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-dailys-total',
                                    'stat_data' => $tasksByUserId[$userId],
                                    'date' => date('Y-m-d H:i:s', time())
                                ]);
                            } else {
                                $cur = $check->current()->stat_data;
                                $this->mUserStatsTbl->update([
                                    'stat_data' => $tasksByUserId[$userId]+$cur,
                                    'date' => date('Y-m-d H:i:s', time())
                                ],[
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-dailys-total',
                                ]);
                            }
                        }

                        foreach(array_keys($tasksMonthlyByUserId) as $userIdMonth) {
                            $info = explode('-', $userIdMonth);
                            $userId = $info[0];
                            $month = $info[1];
                            $year = $info[2];

                            $check = $this->mUserStatsTbl->select(['user_idfs' => $userId, 'stat_key' => 'user-dailys-m-'.$month.'-'.$year]);
                            if($check->count() == 0) {
                                $this->mUserStatsTbl->insert([
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-dailys-m-'.$month.'-'.$year,
                                    'stat_data' => $tasksMonthlyByUserId[$userIdMonth],
                                    'date' => date('Y-m-d H:i:s', time())
                                ]);
                            } else {
                                $cur = $check->current()->stat_data;
                                $this->mUserStatsTbl->update([
                                    'stat_data' => $tasksMonthlyByUserId[$userIdMonth]+$cur,
                                    'date' => date('Y-m-d H:i:s', time())
                                ],[
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-dailys-m-'.$month.'-'.$year,
                                ]);
                            }
                        }

                        $processed = $tasksDone->count();

                        $this->mSettingsTbl->update([
                            'settings_value' => date('Y-m-d H:i:s', time())
                        ],[
                            'settings_key' => 'job_user_dailytask_lastrun'
                        ]);

                        $this->mSettingsTbl->update([
                            'settings_value' => $offset+$processed
                        ],[
                            'settings_key' => 'job_user_dailytask_offset'
                        ]);

                        return [
                            'state' => 'success',
                            'processed' => $processed
                        ];
                    } else {
                        return [
                            'state' => 'error',
                            'message' => 'Batch has already run within the last 2 hours'
                        ];
                    }
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));
    }
}
