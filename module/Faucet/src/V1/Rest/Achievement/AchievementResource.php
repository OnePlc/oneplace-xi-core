<?php
/**
 * AchievementResource.php - Achievement Resource
 *
 * Main Resource for Faucet Achievements
 *
 * @category Resource
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
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
     * Achievement Category Table
     *
     * @var TableGateway $mAchievCatTbl
     * @since 1.0.0
     */
    protected $mAchievCatTbl;

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
        $this->mAchievCatTbl = new TableGateway('faucet_achievement_category', $mapper);
        $this->mMinerTbl = new TableGateway('faucet_miner', $mapper);
        $this->mAchievDoneTbl = new TableGateway('faucet_achievement_user', $mapper);
        $this->mSession = new Container('webauth');
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
        $oWh->equalTo('series', 0);
        $achievementsDB = $this->mAchievTbl->select($oWh);
        $achievements = [];
        $achievementCategories = [];
        foreach($achievementsDB as $achiev) {
            if(!array_key_exists($achiev->category_idfs,$achievements)) {
                $category = $this->mAchievCatTbl->select(['Category_ID' => $achiev->category_idfs])->current();
                $achievements[$achiev->category_idfs] = [];
                $achievementCategories[] = (object)[
                    'id' => $category->Category_ID,
                    'name' => $category->label,
                    'icon' => $category->icon,
                    'target' => 100,
                    'progress' => 0,
                ];
            }
            $achievements[$achiev->category_idfs][] = (object)[
                'id' => $achiev->Achievement_ID,
                'name' => $achiev->label,
                'goal' => $achiev->goal,
                'reward' => $achiev->reward,
                'mode' => $achiev->mode,
                'progress' => 0
            ];
        }

        # Return referall info
        return (object)([
            '_links' => [],
            'total_items' => count($achievements),
            'user_achievement' => [],
            'category' => $achievementCategories,
            'achievement' => $achievements,
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
                        $totalShares = 0;
                        if(count($myShares) > 0) {
                            foreach($myShares as $sh) {
                                $totalShares+=$sh->shares;
                            }
                        }
                        $achievement->done = $totalShares;
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
