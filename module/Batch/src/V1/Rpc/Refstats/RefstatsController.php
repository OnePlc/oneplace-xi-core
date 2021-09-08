<?php
namespace Batch\V1\Rpc\Refstats;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class RefstatsController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Stat Table
     *
     * @var TableGateway $mStatTbl
     * @since 1.0.0
     */
    protected $mStatTbl;

    /**
     * SH Done Table
     *
     * @var TableGateway $mShortsTbl
     * @since 1.0.0
     */
    protected $mShortsTbl;

    /**
     * Offer Done Table
     *
     * @var TableGateway $mOffersTbl
     * @since 1.0.0
     */
    protected $mOffersTbl;

    /**
     * Miner Table
     *
     * @var TableGateway $mMinerTbl
     * @since 1.0.0
     */
    protected $mMinerTbl;

    /**
     * Guild Tasks Table
     *
     * @var TableGateway $mGtaskTbl
     * @since 1.0.0
     */
    protected $mGtaskTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mStatTbl = new TableGateway('faucet_statistic', $mapper);
        $this->mShortsTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mOffersTbl = new TableGateway('offerwall_user', $mapper);
        $this->mMinerTbl = new TableGateway('faucet_miner', $mapper);
        $this->mGtaskTbl = new TableGateway('faucet_guild_weekly_claim', $mapper);
    }

    public function refstatsAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            if(isset($_REQUEST['authkey'])) {
                if($_REQUEST['authkey'] == 'refbatch') {
                    $aTopRefs = [];
                    $oRefWh = new Where();
                    $oRefWh->notEqualTo('ref_user_idfs', 0);
                    $oRefUsers = $this->mUserTbl->select($oRefWh);
                    $iTotalRefCount = 0;
                    $aAdAcounts = [335875860 => true, 335877074 => true,335876060 => true,335880700 => true,335875071 => true,335880436 => true,335890616 => true,335898589 => true];
                    foreach($oRefUsers as $oRef) {
                        if(!array_key_exists($oRef->ref_user_idfs,$aAdAcounts)) {
                            $iTotalRefCount++;

                            if(!array_key_exists($oRef->ref_user_idfs,$aTopRefs)) {
                                $aTopRefs[$oRef->ref_user_idfs] = 0;
                            }
                            $aTopRefs[$oRef->ref_user_idfs]++;
                        }
                    }
                    $iRefUsers = count($aTopRefs)-count($aAdAcounts);
                    $iAvgRef = round($iTotalRefCount/$iRefUsers);

                    $aTopFinales = [];
                    arsort($aTopRefs);
                    $iCount = 0;
                    foreach(array_keys($aTopRefs) as $iTopID) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $iTopID]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $iTopID,'rank' => $iCount+1];
                            $oTopWh->count = $aTopRefs[$iTopID];
                            $aTopFinales[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'referral-toplist');

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'referral-toplist',
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopFinales)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopFinales)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'referral-toplist',
                        ]);
                    }

                    $shDone = $this->mShortsTbl->select();
                    $doneByUser = [];
                    $doneByUserThisMonth = [];
                    foreach($shDone as $sh) {
                        if(!array_key_exists($sh->user_idfs,$doneByUser)) {
                            $doneByUser[$sh->user_idfs] = 0;
                        }
                        $doneByUser[$sh->user_idfs]++;

                        if(date('Y-m',strtotime($sh->date_completed)) == '2021-09') {
                            if(!array_key_exists($sh->user_idfs,$doneByUserThisMonth)) {
                                $doneByUserThisMonth[$sh->user_idfs] = 0;
                            }
                            $doneByUserThisMonth[$sh->user_idfs]++;
                        }
                    }

                    arsort($doneByUser);
                    arsort($doneByUserThisMonth);

                    $iCount = 0;
                    $aTopShUsers = [];
                    foreach(array_keys($doneByUser) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUser[$topId];
                            $aTopShUsers[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'shdone-toplist');

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'shdone-toplist',
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'shdone-toplist',
                        ]);
                    }

                    $iCount = 0;
                    $aTopShUsersM = [];
                    foreach(array_keys($doneByUserThisMonth) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUserThisMonth[$topId];
                            $aTopShUsersM[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'shmonth-top-'.date('m',time()));

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'shmonth-top-'.date('m',time()),
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'shmonth-top-'.date('m',time()),
                        ]);
                    }


                    /**
                     * Offerwall Toplist
                     */
                    $ofWh = new Where();
                    $ofWh->greaterThanOrEqualTo('amount', 10000);
                    $shDone = $this->mOffersTbl->select($ofWh);
                    $doneByUser = [];
                    $doneByUserThisMonth = [];
                    foreach($shDone as $sh) {
                        if(!array_key_exists($sh->user_idfs,$doneByUser)) {
                            $doneByUser[$sh->user_idfs] = 0;
                        }
                        $doneByUser[$sh->user_idfs]++;

                        if(date('Y-m',strtotime($sh->date_completed)) == '2021-09') {
                            if(!array_key_exists($sh->user_idfs,$doneByUserThisMonth)) {
                                $doneByUserThisMonth[$sh->user_idfs] = 0;
                            }
                            $doneByUserThisMonth[$sh->user_idfs]++;
                        }
                    }

                    arsort($doneByUser);
                    arsort($doneByUserThisMonth);

                    $iCount = 0;
                    $aTopShUsers = [];
                    foreach(array_keys($doneByUser) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUser[$topId];
                            $aTopShUsers[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'ofdone-toplist');

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'ofdone-toplist',
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'ofdone-toplist',
                        ]);
                    }

                    $iCount = 0;
                    $aTopShUsersM = [];
                    foreach(array_keys($doneByUserThisMonth) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUserThisMonth[$topId];
                            $aTopShUsersM[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'ofmonth-top-'.date('m',time()));

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'ofmonth-top-'.date('m',time()),
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'ofmonth-top-'.date('m',time()),
                        ]);
                    }

                    /**
                     * CPU Miner Toplist
                     */
                    $ofWh = new Where();
                    $ofWh->like('coin', 'xmr');
                    $shDone = $this->mMinerTbl->select($ofWh);
                    $doneByUser = [];
                    $doneByUserThisMonth = [];
                    foreach($shDone as $sh) {
                        if(!array_key_exists($sh->user_idfs,$doneByUser)) {
                            $doneByUser[$sh->user_idfs] = 0;
                        }
                        $doneByUser[$sh->user_idfs]+=$sh->shares;

                        if(date('Y-m',strtotime($sh->date)) == '2021-09') {
                            if(!array_key_exists($sh->user_idfs,$doneByUserThisMonth)) {
                                $doneByUserThisMonth[$sh->user_idfs] = 0;
                            }
                            $doneByUserThisMonth[$sh->user_idfs]+=$sh->shares;
                        }
                    }

                    arsort($doneByUser);
                    arsort($doneByUserThisMonth);

                    $iCount = 0;
                    $aTopShUsers = [];
                    foreach(array_keys($doneByUser) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUser[$topId];
                            $aTopShUsers[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'xmrshare-toplist');

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'xmrshare-toplist',
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'xmrshare-toplist',
                        ]);
                    }

                    $iCount = 0;
                    $aTopShUsersM = [];
                    foreach(array_keys($doneByUserThisMonth) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUserThisMonth[$topId];
                            $aTopShUsersM[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'xmrshm-top-'.date('m',time()));

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'xmrshm-top-'.date('m',time()),
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'xmrshm-top-'.date('m',time()),
                        ]);
                    }

                    /**
                     * GPU Miner Toplist
                     */
                    $ofWh = new Where();
                    $ofWh->notLike('coin', 'xmr');
                    $shDone = $this->mMinerTbl->select($ofWh);
                    $doneByUser = [];
                    $doneByUserThisMonth = [];
                    foreach($shDone as $sh) {
                        if(!array_key_exists($sh->user_idfs,$doneByUser)) {
                            $doneByUser[$sh->user_idfs] = 0;
                        }
                        $doneByUser[$sh->user_idfs]+=$sh->shares;

                        if(date('Y-m',strtotime($sh->date)) == '2021-09') {
                            if(!array_key_exists($sh->user_idfs,$doneByUserThisMonth)) {
                                $doneByUserThisMonth[$sh->user_idfs] = 0;
                            }
                            $doneByUserThisMonth[$sh->user_idfs]+=$sh->shares;
                        }
                    }

                    arsort($doneByUser);
                    arsort($doneByUserThisMonth);

                    $iCount = 0;
                    $aTopShUsers = [];
                    foreach(array_keys($doneByUser) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUser[$topId];
                            $aTopShUsers[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'gpushare-toplist');

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'gpushare-toplist',
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsers)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'gpushare-toplist',
                        ]);
                    }

                    $iCount = 0;
                    $aTopShUsersM = [];
                    foreach(array_keys($doneByUserThisMonth) as $topId) {
                        if($iCount == 50) {
                            break;
                        }
                        $oTop = $this->mUserTbl->select(['User_ID' => $topId]);
                        if(count($oTop) > 0) {
                            $oTop = $oTop->current();
                            $oTopWh = (object)['name' => $oTop->username,'count' => 0,'id' => $topId,'rank' => $iCount+1];
                            $oTopWh->count = $doneByUserThisMonth[$topId];
                            $aTopShUsersM[] = $oTopWh;
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'gpushm-top-'.date('m',time()));

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'gpushm-top-'.date('m',time()),
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($aTopShUsersM)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'gpushm-top-'.date('m',time()),
                        ]);
                    }

                    /**
                     * Guild Toplist
                     */
                    $gWh = new Where();
                    $gWh->notEqualTo('guild_idfs', 1);
                    $gWh->like('date_claimed', '2021-09-%');

                    $gTasks = $this->mGtaskTbl->select($gWh);
                    $tasksByGuild = [];
                    foreach($gTasks as $t) {
                        if(!array_key_exists($t->guild_idfs, $tasksByGuild)) {
                            $tasksByGuild[$t->guild_idfs] = 0;
                        }
                        $tasksByGuild[$t->guild_idfs]++;
                    }

                    arsort($tasksByGuild);

                    $topGuilds = [];
                    $iCount = 0;
                    foreach(array_keys($tasksByGuild) as $gId) {
                        if($iCount == 10) {
                            break;
                        }
                        $gInfo = $this->mGuildTbl->select(['Guild_ID' => $gId]);
                        if($gInfo->count() > 0) {
                            $gInfo = $gInfo->current();
                            $topGuilds[] = [
                                'id' => $gId,
                                'name' => $gInfo->label,
                                'count' => $tasksByGuild[$gId]
                            ];
                            $iCount++;
                        }
                    }

                    $statWh = new Where();
                    $statWh->like('date',date('Y-m-', time()).'%');
                    $statWh->like('stat-key', 'guild-top-'.date('m',time()));

                    $statFound = $this->mStatTbl->select($statWh);
                    if($statFound->count() == 0) {
                        $this->mStatTbl->insert([
                            'stat-key' => 'guild-top-'.date('m',time()),
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($topGuilds)
                        ]);
                    } else {
                        $statFound = $statFound->current();
                        $this->mStatTbl->update([
                            'date' => date('Y-m-d H:i:s', time()),
                            'stat-data' => json_encode($topGuilds)
                        ],[
                            'date' => $statFound->date,
                            'stat-key' => 'guild-top-'.date('m',time()),
                        ]);
                    }

                    return [
                        'state' => 'done',
                    ];
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Not allowed'));
    }
}
