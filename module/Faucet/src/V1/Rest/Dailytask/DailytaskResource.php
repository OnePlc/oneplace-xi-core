<?php
namespace Faucet\V1\Rest\Dailytask;

use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Session\Container;

class DailytaskResource extends AbstractResourceListener
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

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
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mTaskTbl = new TableGateway('faucet_dailytask', $mapper);
        $this->mTaskDoneTbl = new TableGateway('faucet_dailytask_user', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mClaimTbl = new TableGateway('faucet_claim', $mapper);
        $this->mSession = new Container('webauth');
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
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
     */
    public function fetchAll($params = [])
    {
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        # Check platform
        if(!isset($_REQUEST['platform'])) {
            return new ApiProblem(400, 'You must specify a plattform (website|app)');
        }
        $platform = filter_var($_REQUEST['platform'], FILTER_SANITIZE_STRING);
        if($platform != 'website' && $platform != 'app') {
            return new ApiProblem(400, 'You must specify a plattform (website|app)');
        }

        # Load Daily Tasks
        $dailyTasksDB = $this->mTaskTbl->select(['mode' => $platform]);
        $dailyTasks = [];
        foreach($dailyTasksDB as $task) {
            $dailyTasks[] = $task;
        }

        return $dailyTasks;
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
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
     */
    public function update($id, $data)
    {
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

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
                $oTransHelper = new TransactionHelper($this->mMapper);
                if($oTransHelper->executeTransaction($dailyTask->reward, false, $me->User_ID, $iTaskID, 'dailytask-claim', 'Daily Task '.$dailyTask->label.' completed')) {
                    # Add Done
                    $this->mTaskDoneTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'task_idfs' => $iTaskID,
                        'date' => date('Y-m-d H:i:s', time()),
                    ]);
                }
            }

            # if all good, return dailytask object as confirmation
            return $dailyTask;
        } else {
            return new ApiProblem(404, 'Daily task not found');
        }
    }
}
