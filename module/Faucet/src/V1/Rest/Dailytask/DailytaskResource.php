<?php
/**
 * DailytaskResource.php - Dailytask Resource
 *
 * Main Resource for Faucet Dailytasks
 *
 * @category Resource
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rest\Dailytask;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;

class DailytaskResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Dailytask Table
     *
     * @var TableGateway $mTaskTbl
     * @since 1.0.0
     */
    protected $mTaskTbl;

    /**
     * Dailytask Table User Table
     *
     * Relation between dailytask and User
     * to determine if user has completed a task
     *
     * @var TableGateway $mTaskDoneTbl
     * @since 1.0.0
     */
    protected $mTaskDoneTbl;

    /**
     * Shortlinks Table User Table
     *
     * Relation between shortlinks and User
     * to determine if user has completed a shortlinks
     *
     * @var TableGateway $mShortDoneTbl
     * @since 1.0.0
     */
    protected $mShortDoneTbl;

    /**
     * Faucet Claim Table
     *
     * @var TableGateway $mClaimTbl
     * @since 1.0.0
     */
    protected $mClaimTbl;


    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;


    /**
     * Constructor
     *
     * DailytaskResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mTaskTbl = new TableGateway('faucet_dailytask', $mapper);
        $this->mTaskDoneTbl = new TableGateway('faucet_dailytask_user', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mClaimTbl = new TableGateway('faucet_claim', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function create($data)
    {
        return new ApiProblem(405, 'The POST method has not been defined');
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function delete($id)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for individual resources');
    }

    /**
     * Delete a collection, or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function deleteList($data)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for collections');
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function fetch($id)
    {
        return new ApiProblem(405, 'The GET method has not been defined for individual resources');
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function fetchAll($params = [])
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        # Check platform
        if(!isset($_REQUEST['platform'])) {
            return new ApiProblem(400, 'You must specify a plattform (website|app)');
        }
        $platform = filter_var($_REQUEST['platform'], FILTER_SANITIZE_STRING);
        if($platform != 'website' && $platform != 'app') {
            return new ApiProblem(400, 'You must specify a plattform (website|app)');
        }

        $sDate = date('Y-m-d', time());

        /**
         * Gather relevant data for progress
         */
        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date_claimed', $sDate.'%');
        $shortlinksDone = $this->mShortDoneTbl->select($oWh)->count();

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date', $sDate.'%');
        $claimsDone = $this->mClaimTbl->select($oWh)->count();

        $oWh = new Where();
        $oWh->equalTo('user_idfs', $me->User_ID);
        $oWh->like('date', $sDate.'%');
        $dailysDone = $this->mTaskDoneTbl->select($oWh)->count();

        # Load Dailytasks
        $oWh = new Where();
        $oWh->NEST
            ->equalTo('mode', $platform)
            ->OR
            ->equalTo('mode', 'global')
            ->UNNEST;
        $achievementsDB = $this->mTaskTbl->select($oWh);
        $achievements = [];
        foreach($achievementsDB as $achiev) {
            switch($achiev->type) {
                case 'shortlink':
                    $progress = $shortlinksDone;
                    break;
                case 'claim':
                    $progress = $claimsDone;
                    break;
                case 'daily':
                    $progress = $dailysDone;
                    break;
                default:
                    $progress = 0;
                    break;
            }
            # Check if task is already claimed today
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->equalTo('task_idfs', $achiev->Dailytask_ID);
            $oWh->like('date', $sDate.'%');
            $oDailysDone = $this->mTaskDoneTbl->select($oWh);

            $achievements[] = (object)[
                'id' => $achiev->Dailytask_ID,
                'name' => $achiev->label,
                'goal' => $achiev->goal,
                'reward' => $achiev->reward,
                'mode' => $achiev->mode,
                'progress' => $progress,
                'done' => (count($oDailysDone) == 0) ? 0 : 1
            ];
        }

        # Return referall info
        return (object)([
            '_links' => [],
            'total_items' => count($achievements),
            'user_task' => [],
            'task' => $achievements,
        ]);
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function patch($id, $data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for individual resources');
    }

    /**
     * Patch (partial in-place update) a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function patchList($data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for collections');
    }

    /**
     * Replace a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function replaceList($data)
    {
        return new ApiProblem(405, 'The PUT method has not been defined for collections');
    }

    /**
     * Update a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     * @since 1.0.0
     */
    public function update($id, $data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        # check for attack vendors
        $secResult = $this->mSecTools->basicInputCheck([$id]);
        if($secResult !== 'ok') {
            # ban user and force logout on client
            $this->mUserSetTbl->insert([
                'user_idfs' => $me->User_ID,
                'setting_name' => 'user-tempban',
                'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Dailytask Claim',
            ]);
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }

        $iTaskID = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        # Check platform
        if(!is_numeric($iTaskID) || $iTaskID == 0) {
            return new ApiProblem(400, 'You must specifiy a valid dailytask id');
        }

        # Load Daily Tasks
        $dailyTask = $this->mTaskTbl->select(['Dailytask_ID' => $iTaskID]);
        if(count($dailyTask) > 0) {
            $dailyTask = $dailyTask->current();

            # Check if task is already claimed today
            $sDate = date('Y-m-d', time());
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->equalTo('task_idfs', $iTaskID);
            $oWh->like('date', $sDate.'%');
            $oDailysDone = $this->mTaskDoneTbl->select($oWh);
            if(count($oDailysDone) > 0) {
                return new ApiProblem(409, 'Daily Task '.$dailyTask->label.' already claimed today ('.$sDate.')');
            } else {
                # Check if Dailytask is actually completed
                switch($dailyTask->type) {
                    case 'shortlink':
                        # Count Shortlinks done for date and user
                        $oWh = new Where();
                        $oWh->equalTo('user_idfs', $me->User_ID);
                        $oWh->like('date_completed', $sDate.'%');
                        $iShortLinksDone = $this->mShortDoneTbl->select($oWh)->count();
                        if($dailyTask->goal > $iShortLinksDone) {
                            return new ApiProblem(409, 'Daily Task '.$dailyTask->label.' is not completed ('.$iShortLinksDone.'/'.$dailyTask->goal.')');
                        }
                        break;
                    case 'claim':
                        # Count Faucet Claims done for date and user
                        $oWh = new Where();
                        $oWh->equalTo('user_idfs', $me->User_ID);
                        $oWh->like('date', $sDate.'%');
                        $iClaimsDone = $this->mClaimTbl->select($oWh)->count();
                        if($dailyTask->goal > $iClaimsDone) {
                            return new ApiProblem(409, 'Daily Task '.$dailyTask->label.' is not completed ('.$iClaimsDone.'/'.$dailyTask->goal.')');
                        }
                        break;
                    case 'daily':
                        # Count Dailytasks done for date and user
                        $oWh = new Where();
                        $oWh->equalTo('user_idfs', $me->User_ID);
                        $oWh->like('date', $sDate.'%');
                        $iDailysDone = $this->mTaskDoneTbl->select($oWh)->count();
                        if($dailyTask->goal > $iDailysDone) {
                            return new ApiProblem(409, 'Daily Task '.$dailyTask->label.' is not completed ('.$iDailysDone.'/'.$dailyTask->goal.')');
                        }
                        break;
                    default:
                        break;
                }

                # Transaction
                $newBalance = $this->mTransaction->executeTransaction($dailyTask->reward, false, $me->User_ID, $iTaskID, 'dailytask-claim', 'Daily Task '.$dailyTask->label.' completed');
                if($newBalance !== false) {
                    # Add Done
                    $this->mTaskDoneTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'task_idfs' => $iTaskID,
                        'date' => date('Y-m-d H:i:s', time()),
                    ]);
                } else {
                    return new ApiProblem(500, 'Transaction error');
                }
            }

            /**
             * Gather relevant data for progress
             */
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->like('date_completed', $sDate.'%');
            $shortlinksDone = $this->mShortDoneTbl->select($oWh)->count();

            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->like('date', $sDate.'%');
            $claimsDone = $this->mClaimTbl->select($oWh)->count();

            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->like('date', $sDate.'%');
            $dailysDone = $this->mTaskDoneTbl->select($oWh)->count();

            # Load Dailytasks
            $oWh = new Where();
            $oWh->NEST
                ->equalTo('mode', $data->platform)
                ->OR
                ->equalTo('mode', 'global')
                ->UNNEST;
            $achievementsDB = $this->mTaskTbl->select($oWh);
            $achievements = [];
            foreach($achievementsDB as $achiev) {
                switch($achiev->type) {
                    case 'shortlink':
                        $progress = $shortlinksDone;
                        break;
                    case 'claim':
                        $progress = $claimsDone;
                        break;
                    case 'daily':
                        $progress = $dailysDone;
                        break;
                    default:
                        $progress = 0;
                        break;
                }
                # Check if task is already claimed today
                $oWh = new Where();
                $oWh->equalTo('user_idfs', $me->User_ID);
                $oWh->equalTo('task_idfs', $achiev->Dailytask_ID);
                $oWh->like('date', $sDate.'%');
                $oDailysDone = $this->mTaskDoneTbl->select($oWh);

                $achievements[] = (object)[
                    'id' => $achiev->Dailytask_ID,
                    'name' => $achiev->label,
                    'goal' => $achiev->goal,
                    'reward' => $achiev->reward,
                    'mode' => $achiev->mode,
                    'progress' => $progress,
                    'done' => (count($oDailysDone) == 0) ? 0 : 1
                ];
            }

            # Return referall info
            return (object)([
                '_links' => [],
                'total_items' => count($achievements),
                'user_task' => [],
                'reward' => $dailyTask->reward,
                'token_balance' => $newBalance,
                'task' => $achievements
            ]);
        } else {
            return new ApiProblem(404, 'Daily task not found');
        }
    }
}
