<?php
namespace Batch\V1\Rpc\OfferwallStats;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class OfferwallStatsController extends AbstractActionController
{
    /**
     * Offerwall User Table
     *
     * @var TableGateway $mOffersDoneTbl
     * @since 1.0.0
     */
    protected $mOffersDoneTbl;

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
        $this->mOffersDoneTbl = new TableGateway('offerwall_user', $mapper);
        $this->mUserStatsTbl = new TableGateway('user_faucet_stat', $mapper);
        $this->mSettingsTbl = new TableGateway('settings', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function offerwallStatsAction()
    {
        $req = $this->getRequest();

        if($req->isGet()) {
            if(isset($_REQUEST['authkey'])) {
                $batchKey = $this->mSecTools->getCoreSetting('job_user_offerwalls_key');

                $checkKey = filter_var($_REQUEST['authkey'], FILTER_SANITIZE_STRING);
                if($checkKey == $batchKey) {
                    $offset = $this->mSecTools->getCoreSetting('job_user_offerwalls_offset');
                    $lastrun = $this->mSecTools->getCoreSetting('job_user_offerwalls_lastrun');
                    $limit = 10000;

                    if(strtotime($lastrun) <= time()-7200) {
                        $tSel = new Select($this->mOffersDoneTbl->getTable());
                        $tSel->order('date_completed ASC');
                        $tSel->offset($offset);
                        $tSel->limit($limit);

                        $tasksDone = $this->mOffersDoneTbl->selectWith($tSel);

                        $offerSmallByUserId = [];
                        $offerSmallMonthlyByUserId = [];

                        $offerBigByUserId = [];
                        $offerBigMonthlyByUserId = [];
                        foreach($tasksDone as $t) {
                            if($t->amount >= 5000) {
                                if(!array_key_exists($t->user_idfs, $offerBigByUserId)) {
                                    $offerBigByUserId[$t->user_idfs] = 0;
                                }
                                $offerBigByUserId[$t->user_idfs]++;

                                $month = date('n-Y', strtotime($t->date_completed));
                                if(!array_key_exists($t->user_idfs.'-'.$month,$offerBigMonthlyByUserId)) {
                                    $offerBigMonthlyByUserId[$t->user_idfs.'-'.$month] = 0;
                                }
                                $offerBigMonthlyByUserId[$t->user_idfs.'-'.$month]++;
                            } else {
                                if(!array_key_exists($t->user_idfs, $offerSmallByUserId)) {
                                    $offerSmallByUserId[$t->user_idfs] = 0;
                                }
                                $offerSmallByUserId[$t->user_idfs]++;

                                $month = date('n-Y', strtotime($t->date_completed));
                                if(!array_key_exists($t->user_idfs.'-'.$month,$offerSmallMonthlyByUserId)) {
                                    $offerSmallMonthlyByUserId[$t->user_idfs.'-'.$month] = 0;
                                }
                                $offerSmallMonthlyByUserId[$t->user_idfs.'-'.$month]++;
                            }
                        }

                        foreach(array_keys($offerSmallByUserId) as $userId) {
                            $check = $this->mUserStatsTbl->select(['user_idfs' => $userId, 'stat_key' => 'user-offersmall-total']);
                            if($check->count() == 0) {
                                $this->mUserStatsTbl->insert([
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offersmall-total',
                                    'stat_data' => $offerSmallByUserId[$userId],
                                    'date' => date('Y-m-d H:i:s', time())
                                ]);
                            } else {
                                $cur = $check->current()->stat_data;
                                $this->mUserStatsTbl->update([
                                    'stat_data' => $offerSmallByUserId[$userId]+$cur,
                                    'date' => date('Y-m-d H:i:s', time())
                                ],[
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offersmall-total',
                                ]);
                            }
                        }

                        foreach(array_keys($offerBigByUserId) as $userId) {
                            $check = $this->mUserStatsTbl->select(['user_idfs' => $userId, 'stat_key' => 'user-offerbig-total']);
                            if($check->count() == 0) {
                                $this->mUserStatsTbl->insert([
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offerbig-total',
                                    'stat_data' => $offerBigByUserId[$userId],
                                    'date' => date('Y-m-d H:i:s', time())
                                ]);
                            } else {
                                $cur = $check->current()->stat_data;
                                $this->mUserStatsTbl->update([
                                    'stat_data' => $offerBigByUserId[$userId]+$cur,
                                    'date' => date('Y-m-d H:i:s', time())
                                ],[
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offerbig-total',
                                ]);
                            }
                        }

                        foreach(array_keys($offerBigMonthlyByUserId) as $userIdMonth) {
                            $info = explode('-', $userIdMonth);
                            $userId = $info[0];
                            $month = $info[1];
                            $year = $info[2];

                            $check = $this->mUserStatsTbl->select(['user_idfs' => $userId, 'stat_key' => 'user-offerbig-m-'.$month.'-'.$year]);
                            if($check->count() == 0) {
                                $this->mUserStatsTbl->insert([
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offerbig-m-'.$month.'-'.$year,
                                    'stat_data' => $offerBigMonthlyByUserId[$userIdMonth],
                                    'date' => date('Y-m-d H:i:s', time())
                                ]);
                            } else {
                                $cur = $check->current()->stat_data;
                                $this->mUserStatsTbl->update([
                                    'stat_data' => $offerBigMonthlyByUserId[$userIdMonth]+$cur,
                                    'date' => date('Y-m-d H:i:s', time())
                                ],[
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offerbig-m-'.$month.'-'.$year,
                                ]);
                            }
                        }

                        foreach(array_keys($offerSmallMonthlyByUserId) as $userIdMonth) {
                            $info = explode('-', $userIdMonth);
                            $userId = $info[0];
                            $month = $info[1];
                            $year = $info[2];

                            $check = $this->mUserStatsTbl->select(['user_idfs' => $userId, 'stat_key' => 'user-offersmall-m-'.$month.'-'.$year]);
                            if($check->count() == 0) {
                                $this->mUserStatsTbl->insert([
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offersmall-m-'.$month.'-'.$year,
                                    'stat_data' => $offerSmallMonthlyByUserId[$userIdMonth],
                                    'date' => date('Y-m-d H:i:s', time())
                                ]);
                            } else {
                                $cur = $check->current()->stat_data;
                                $this->mUserStatsTbl->update([
                                    'stat_data' => $offerSmallMonthlyByUserId[$userIdMonth]+$cur,
                                    'date' => date('Y-m-d H:i:s', time())
                                ],[
                                    'user_idfs' => $userId,
                                    'stat_key' => 'user-offersmall-m-'.$month.'-'.$year,
                                ]);
                            }
                        }

                        $this->mSettingsTbl->update([
                            'settings_value' => date('Y-m-d H:i:s', time())
                        ],[
                            'settings_key' => 'job_user_offerwalls_lastrun'
                        ]);

                        $this->mSettingsTbl->update([
                            'settings_value' => $offset+$limit
                        ],[
                            'settings_key' => 'job_user_offerwalls_offset'
                        ]);

                        return [
                            'state' => 'success',
                            'processed' => $tasksDone->count()
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
