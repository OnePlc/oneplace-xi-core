<?php
namespace Faucet\V1\Rest\Achievement;

use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Session\Container;

class AchievementResource extends AbstractResourceListener
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * Achievement Table
     *
     * @var TableGateway $mAchievTbl
     * @since 1.0.0
     */
    protected $mAchievTbl;

    /**
     * Achievement Table User Table
     *
     * Relation between Achievement and User
     * to determine if user has completed an Achievement
     *
     * @var TableGateway $mAchievDoneTbl
     * @since 1.0.0
     */
    protected $mAchievDoneTbl;

    /**
     * Miner Table
     *
     * @var TableGateway $mMinerTbl
     * @since 1.0.0
     */
    protected $mMinerTbl;

    /**
     * Constructor
     *
     * AchievementResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mAchievTbl = new TableGateway('faucet_achievement', $mapper);
        $this->mMinerTbl = new TableGateway('faucet_miner', $mapper);
        $this->mAchievDoneTbl = new TableGateway('faucet_achievement_user', $mapper);
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

        # Load Achievements
        $oWh = new Where();
        $oWh->NEST
            ->equalTo('mode', $platform)
            ->OR
            ->equalTo('mode', 'global')
            ->UNNEST;
        $achievementsDB = $this->mAchievTbl->select($oWh);
        $achievements = [];
        foreach($achievementsDB as $achiev) {
            $achievements[] = $achiev;
        }

        return $achievements;
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

        $achievementId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        # Check platform
        if(!is_numeric($achievementId) || $achievementId == 0) {
            return new ApiProblem(400, 'You must specifiy a valid achievement id');
        }

        # Load Achievements
        $achievement = $this->mAchievTbl->select(['Achievement_ID' => $achievementId]);
        if(count($achievement) > 0) {
            $achievement = $achievement->current();

            # Check if achievement is already claimed today
            $sDate = date('Y-m-d', time());
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->equalTo('achievement_idfs', $achievementId);
            $oAchievDone = $this->mAchievDoneTbl->select($oWh);
            if(count($oAchievDone) > 0) {
                return new ApiProblem(409, 'Achievement '.$achievement->label.' already claimed');
            } else {
                # Check if achievement is actually completed
                switch($achievement->type) {
                    case 'xplevel':
                        $achievement->done = $me->xp_level;
                        break;
                    case 'gpushares':
                        $shareSel = new Select($this->mMinerTbl->getTable());
                        $shareSel->where(['user_idfs' => $me->User_ID,'pool' => 'nanopool']);
                        $shareSel->order('date DESC');
                        $myShares = $this->mMinerTbl->selectWith($shareSel);
                        $fTotalShares = 0;
                        if(count($myShares) > 0) {
                            foreach($myShares as $sh) {
                                $fTotalShares+=$sh->shares;
                            }
                        }
                        $achievement->done = $fTotalShares;
                        break;
                    default:
                        $achievement->done = 0;
                        break;
                }

                if($achievement->done >= $achievement->goal) {
                    # Transaction
                    $oTransHelper = new TransactionHelper($this->mMapper);
                    if($oTransHelper->executeTransaction($achievement->reward, false, $me->User_ID, $achievementId, 'achievement-claim', 'Achievement '.$achievement->label.' completed')) {
                        # Add Done
                        $this->mTaskDoneTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'achievement_idfs' => $achievementId,
                            'date' => date('Y-m-d H:i:s', time()),
                        ]);
                    }
                } else {
                    return new ApiProblem(409, 'Achievement '.$achievement->label.' is not completed ('.$achievement->done.'/'.$achievement->goal.')');
                }
            }

            # if all good, return dailytask object as confirmation
            return $achievement;
        } else {
            return new ApiProblem(404, 'Achievement not found');
        }
    }
}
