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

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;

class AchievementResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

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
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Shortlink Table User Table
     *
     * Relation between Shortlink and User
     * to determine if user has completed a Shortlink
     *
     * @var TableGateway $mShortDoneTbl
     * @since 1.0.0
     */
    protected $mShortDoneTbl;

    /**
     * Offerwall Table User Table
     *
     * Relation between Offerwall and User
     * to determine if user has complete an offer
     * from an Offerwall
     *
     * @var TableGateway $mOfferwallUserTbl
     * @since 1.0.0
     */
    protected $mOfferwallUserTbl;

    /**
     * Claim Table
     *
     * @var TableGateway $mClaimTbl
     * @since 1.0.0
     */
    protected $mClaimTbl;

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
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mAchievDoneTbl = new TableGateway('faucet_achievement_user', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mOfferwallUserTbl = new TableGateway('offerwall_user', $mapper);
        $this->mClaimTbl = new TableGateway('faucet_claim', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
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

    private function getNextLevelAchievement($achiev, $user) {
        $id = (isset($achiev->Achievement_ID)) ? $achiev->Achievement_ID : $achiev->id;
        $nextSel = new Select($this->mAchievTbl->getTable());
        $nextSel->where(['series' => $id]);
        $nextSel->order('reward ASC');
        $nextSel->limit(1);
        $hasNext = $this->mAchievTbl->selectWith($nextSel);
        if(count($hasNext) > 0) {
            $achiev = $hasNext->current();
            $progress = 0;
            switch($achiev->type) {
                case 'xplevel':
                    $progress = $user->xp_level;
                    break;
                case 'faucetclaim':
                    $progress = $this->mClaimTbl->select(['user_idfs' => $user->User_ID,'source' => 'website'])->count();
                    break;
                case 'shortlink':
                    $progress = $this->mShortDoneTbl->select(['user_idfs' => $user->User_ID])->count();
                    break;
                case 'offerwall':
                    $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $user->User_ID])->count();
                    break;
                case 'offer-ayet':
                    $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $user->User_ID,'offerwall_idfs' => 5])->count();
                    break;
                case 'offer-asiamag':
                    $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $user->User_ID,'offerwall_idfs' => 6])->count();
                    break;
                case 'offer-wannads':
                    $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $user->User_ID,'offerwall_idfs' => 8])->count();
                    break;
                case 'offer-cpx':
                    $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $user->User_ID,'offerwall_idfs' => 1])->count();
                    break;
                case 'miningtime':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totalhours','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                case 'gpushares':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totalshares','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                case 'nighthours':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-nighthours','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                case 'nightdays':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-nighthours','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = round($progressFound->current()->setting_value/5);
                    }
                    break;
                case 'miningmonth':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totaldays','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                case 'faucethour':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'faucet-claimtimes','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                case 'faucetdaily':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'faucet-claimdays','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                case 'wthcurrency':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'withdraw-coins','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = count((array)json_decode($progressFound->current()->setting_value));
                    }
                    break;
                case 'withdrawal':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'withdraw-total','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                case 'earning':
                    $progressFound = $this->mUserSetTbl->select(['setting_name' => 'totalearned-coins','user_idfs' => $user->User_ID]);
                    if(count($progressFound) > 0) {
                        $progress = (int)$progressFound->current()->setting_value;
                    }
                    break;
                default:
                    break;
            }
            return (object)[
                'id' => $achiev->Achievement_ID,
                'name' => $achiev->label,
                'icon' => $achiev->icon,
                'description' => $achiev->description,
                'goal' => $achiev->goal,
                'reward' => $achiev->reward,
                'mode' => $achiev->mode,
                'progress' => $progress
            ];
        }

        return false;
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

        $achievCats = $this->mAchievCatTbl->select();
        $achievementCategories = [];
        foreach($achievCats as $category) {
            $targetCat = $this->mAchievTbl->select(['category_idfs' => $category->Category_ID])->count();

            $achievementCategories[$category->Category_ID] = (object)[
                'id' => $category->Category_ID,
                'name' => $category->label,
                'icon' => $category->icon,
                'counter' => $category->counter,
                'target' => $targetCat,
                'progress' => 0,
                'achievements' => [],
                'user_achievements' => [],
            ];
        }

        # get recent achievements
        $recent = [];
        $recSel = new Select($this->mAchievDoneTbl->getTable());
        $recSel->where(['user_idfs' => $me->User_ID]);
        $recSel->order('date DESC');
        $recentDB = $this->mAchievDoneTbl->selectWith($recSel);
        $userAchievementsDone = [];
        $achievementPoints = 0;
        $totalDone = 0;
        if(count($recentDB) > 0) {
            foreach($recentDB as $rec) {
                $userAchievementsDone[$rec->achievement_idfs] = true;
                $achievRec = $this->mAchievTbl->select(['Achievement_ID' => $rec->achievement_idfs]);
                if(count($achievRec) > 0) {
                    $totalDone++;
                    $achievRec = $achievRec->current();
                    if(count($recent) < 4) {
                        if(!array_key_exists($achievRec->category_idfs, $achievementCategories)) {
                            continue;
                        }
                        $recent[] =(object)[
                            'id' => $achievRec->Achievement_ID,
                            'name' => $achievRec->label,
                            'icon' => $achievRec->icon,
                            'description' => $achievRec->description,
                            'goal' => $achievRec->goal,
                            'reward' => $achievRec->reward,
                            'mode' => $achievRec->mode,
                        ];
                    }
                    $achievementPoints+=$achievRec->reward;
                }
            }
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
        $totalAchievements = $this->mAchievTbl->select()->count();
        foreach($achievementsDB as $achiev) {
            if(!array_key_exists($achiev->category_idfs, $achievementCategories)) {
                continue;
            }
            $progress = 0;
            if(array_key_exists($achiev->Achievement_ID, $userAchievementsDone)) {
                $progress = $achiev->goal;
                $achievementCategories[$achiev->category_idfs]->progress++;
                $achievementCategories[$achiev->category_idfs]->user_achievements[] = (object)[
                    'id' => $achiev->Achievement_ID,
                    'name' => $achiev->label,
                    'icon' => $achiev->icon,
                    'description' => $achiev->description,
                    'goal' => $achiev->goal,
                    'reward' => $achiev->reward,
                    'mode' => $achiev->mode,
                    'progress' => $progress
                ];
                $nextLevel = $this->getNextLevelAchievement($achiev, $me);
                if(is_object($nextLevel)) {
                    if($nextLevel->progress >= $nextLevel->goal) {
                        $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel;
                        $nextLevel2 = $this->getNextLevelAchievement($nextLevel, $me);
                        if(is_object($nextLevel2)) {
                            if($nextLevel2->progress >= $nextLevel2->goal) {
                                $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel2;
                                $nextLevel3 = $this->getNextLevelAchievement($nextLevel2, $me);
                                if(is_object($nextLevel3)) {
                                    if($nextLevel3->progress >= $nextLevel3->goal) {
                                        $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel3;
                                        $nextLevel4 = $this->getNextLevelAchievement($nextLevel3, $me);
                                        if(is_object($nextLevel4)) {
                                            if($nextLevel4->progress >= $nextLevel4->goal) {
                                                $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel4;
                                                $nextLevel5 = $this->getNextLevelAchievement($nextLevel4, $me);
                                                if(is_object($nextLevel5)) {
                                                    if($nextLevel5->progress >= $nextLevel5->goal) {
                                                        $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel5;
                                                        $nextLevel6 = $this->getNextLevelAchievement($nextLevel5, $me);
                                                        if(is_object($nextLevel6)) {
                                                            if($nextLevel6->progress >= $nextLevel6->goal) {
                                                                $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel6;
                                                                $nextLevel7 = $this->getNextLevelAchievement($nextLevel6, $me);
                                                                if(is_object($nextLevel7)) {
                                                                    if($nextLevel7->progress >= $nextLevel7->goal) {
                                                                        $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel7;
                                                                        $nextLevel8 = $this->getNextLevelAchievement($nextLevel7, $me);
                                                                        if(is_object($nextLevel8)) {
                                                                            if($nextLevel8->progress >= $nextLevel8->goal) {
                                                                                $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel8;
                                                                                $nextLevel9 = $this->getNextLevelAchievement($nextLevel8, $me);
                                                                                if(is_object($nextLevel9)) {
                                                                                    if($nextLevel9->progress >= $nextLevel9->goal) {
                                                                                        $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel9;
                                                                                        $nextLevel10 = $this->getNextLevelAchievement($nextLevel9, $me);
                                                                                        if(is_object($nextLevel10)) {
                                                                                            if($nextLevel10->progress >= $nextLevel10->goal) {
                                                                                                $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel10;
                                                                                                $nextLevel11 = $this->getNextLevelAchievement($nextLevel10, $me);
                                                                                                if (is_object($nextLevel11)) {
                                                                                                    if($nextLevel11->progress >= $nextLevel11->goal) {
                                                                                                        $achievementCategories[$achiev->category_idfs]->user_achievements[] = $nextLevel11;
                                                                                                    }
                                                                                                }
                                                                                            } else {
                                                                                                $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel10;
                                                                                            }
                                                                                        }
                                                                                    } else {
                                                                                        $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel9;
                                                                                    }
                                                                                }
                                                                            } else {
                                                                                $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel8;
                                                                            }
                                                                        }
                                                                    } else {
                                                                        $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel7;
                                                                    }
                                                                }
                                                            } else {
                                                                $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel6;
                                                            }
                                                        }
                                                    } else {
                                                        $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel5;
                                                    }
                                                }
                                            } else {
                                                $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel4;
                                            }
                                        }
                                    } else {
                                        $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel3;
                                    }
                                }
                            } else {
                                $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel2;
                            }
                        }
                    } else {
                        $achievementCategories[$achiev->category_idfs]->achievements[] = $nextLevel;
                    }
                }
            } else {
                switch($achiev->type) {
                    case 'xplevel':
                        $progress = $me->xp_level;
                        break;
                    case 'shortlink':
                        $progress = $this->mShortDoneTbl->select(['user_idfs' => $me->User_ID])->count();
                        break;
                    case 'faucetclaim':
                        $progress = $this->mClaimTbl->select(['user_idfs' => $me->User_ID,'source' => 'website'])->count();
                        break;
                    case 'offerwall':
                        $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $me->User_ID])->count();
                        break;
                    case 'offer-ayet':
                        $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $me->User_ID,'offerwall_idfs' => 5])->count();
                        break;
                    case 'offer-asiamag':
                        $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $me->User_ID,'offerwall_idfs' => 6])->count();
                        break;
                    case 'offer-wannads':
                        $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $me->User_ID,'offerwall_idfs' => 8])->count();
                        break;
                    case 'offer-cpx':
                        $progress = $this->mOfferwallUserTbl->select(['user_idfs' => $me->User_ID,'offerwall_idfs' => 1])->count();
                        break;
                    case 'miningtime':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totalhours','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    case 'gpushares':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totalshares','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    case 'nighthours':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-nighthours','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    case 'nightdays':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-nighthours','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = round($progressFound->current()->setting_value/5);
                        }
                        break;
                    case 'miningmonth':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totaldays','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    case 'faucethour':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'faucet-claimtimes','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    case 'faucetdaily':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'faucet-claimdays','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    case 'wthcurrency':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'withdraw-coins','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = count((array)json_decode($progressFound->current()->setting_value));
                        }
                        break;
                    case 'withdrawal':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'withdraw-total','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    case 'earning':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'totalearned-coins','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $progress = (int)$progressFound->current()->setting_value;
                        }
                        break;
                    default:
                        break;
                }
                $achievementCategories[$achiev->category_idfs]->achievements[] = (object)[
                    'id' => $achiev->Achievement_ID,
                    'name' => $achiev->label,
                    'icon' => $achiev->icon,
                    'description' => $achiev->description,
                    'goal' => $achiev->goal,
                    'reward' => $achiev->reward,
                    'mode' => $achiev->mode,
                    'progress' => $progress
                ];
            }

        }

        $categoriesExport = [];
        foreach(array_keys($achievementCategories) as $categoryExportID) {
            $categoriesExport[] = $achievementCategories[$categoryExportID];
        }

        # Return referall info
        return (object)([
            '_links' => [],
            'total_items' => $totalAchievements,
            'total_done' => $totalDone,
            'user_points' => $achievementPoints,
            'user_recent' => $recent,
            'achievement' => $categoriesExport,
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
                'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Achievement Claim',
            ]);
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }

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
                    case 'miningtime':
                        $progressFound = $this->mUserSetTbl->select(['setting_name' => 'gpuminer-totalhours','user_idfs' => $me->User_ID]);
                        if(count($progressFound) > 0) {
                            $achievement->done = $progressFound->current()->setting_value;
                        }
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
