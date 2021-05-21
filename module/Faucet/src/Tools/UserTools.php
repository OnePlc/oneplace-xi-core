<?php
/**
 * UserTools.php - E-Mail Helper
 *
 * Main Helper for Faucet User Leveling
 *
 * @category Helper
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\Tools;

use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mail;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;


class UserTools extends AbstractResourceListener {

    /**
     * Settings Table
     *
     * @var TableGateway $mSettingsTbl
     * @since 1.0.0
     */
    protected $mSettingsTbl;

    /**
     * XP Activity Table
     *
     * @var TableGateway $mActivityTbl
     * @since 1.0.0
     */
    protected $mActivityTbl;

    /**
     * Achievement Table
     *
     * @var TableGateway $mAchievTbl
     * @since 1.0.0
     */
    protected $mAchievTbl;

    /**
     * User Achievement Table
     *
     * @var TableGateway $mAchievUserTbl
     * @since 1.0.0
     */
    protected $mAchievUserTbl;

    /**
     * XP Level Table
     *
     * @var TableGateway $mLevelTbl
     * @since 1.0.0
     */
    protected $mLevelTbl;

    /**
     * User Table
     *
     * @var TableGateway $mActivityTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Level Up Achievements
     *
     * @var array $mAchievementPoints
     * @since 1.0.0
     */
    protected $mAchievementPoints;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;


    /**
     * Constructor
     *
     * EmailTools constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSettingsTbl = new TableGateway('settings', $mapper);
        $this->mActivityTbl = new TableGateway('user_xp_activity', $mapper);
        $this->mLevelTbl = new TableGateway('user_xp_level', $mapper);
        $this->mAchievTbl = new TableGateway('faucet_achievement', $mapper);
        $this->mAchievUserTbl = new TableGateway('faucet_achievement_user', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mTransaction = new TransactionHelper($mapper);

        /**
         * Load Achievements to Cache
         */
        $achievTbl = new TableGateway('faucet_achievement', $mapper);
        $achievsXP = $achievTbl->select(['type' => 'xplevel']);
        $achievsFinal = [];
        if(count($achievsXP) > 0) {
            foreach($achievsXP as $achiev) {
                $achievsFinal[$achiev->goal] = $achiev;
            }
        }
        $this->mAchievementPoints = $achievsFinal;
    }

    /**
     * Add User Experience
     *
     * @param $action
     * @param $userId
     * @return false|array
     * @since 1.0.0
     */
    public function addXP($action, $userId) {
        $actionInfo = $this->mActivityTbl->select(['xp_key' => $action]);
        if(count($actionInfo) == 0) {
            return false;
        }
        $actionInfo = $actionInfo->current();

        $currentXP = $this->mUserTbl->select(['User_ID' => $userId]);
        if(count($currentXP) == 0) {
            return false;
        }
        $currentXP = $currentXP->current();

        # get base xp
        $iXP = $actionInfo->xp_base;

        # get next level
        $oNextLvl = $this->mLevelTbl->select(['Level_ID' => ($currentXP->xp_level + 1)])->current();

        # calculate new level and experience
        $iNewLvl = $currentXP->xp_level;
        $iCurrentXP = $currentXP->xp_current;
        $achievementComplete = (object)['id' => 0];
        if ($oNextLvl->xp_total <= ($iCurrentXP + $iXP)) {
            $iNewLvl++;
            $iCurrentXP = ($currentXP->xp_current + $iXP) - $oNextLvl->xp_total;
            # check if user has completed an achievement
            if(array_key_exists($iNewLvl,$this->mAchievementPoints)) {
                $this->completeAchievement($this->mAchievementPoints[$iNewLvl]->Achievement_ID, $userId);
                $achievementComplete = (object)[
                    'id' => $this->mAchievementPoints[$iNewLvl]->Achievement_ID,
                    'name' => $this->mAchievementPoints[$iNewLvl]->label,
                    'description' => $this->mAchievementPoints[$iNewLvl]->description,
                    'reward' => $this->mAchievementPoints[$iNewLvl]->reward,
                    'icon' => $this->mAchievementPoints[$iNewLvl]->reward,
                ];
            }
        } else {
            $iCurrentXP = $iCurrentXP + $iXP;
        }
        $xpPercent = round((100 / ($oNextLvl->xp_total / $iCurrentXP)), 2);

        # save to database
        $this->mUserTbl->update([
            'xp_level' => $iNewLvl,
            'xp_current' => $iCurrentXP,
            'xp_total' => $currentXP->xp_total + $iXP,
        ], ['User_ID' => $userId]);

        return [
            'xp_level' => (int)$iNewLvl,
            'xp_current' => (int)$iCurrentXP,
            'achievement' => $achievementComplete,
            'xp_percent' => (float)$xpPercent,
        ];
    }

    public function completeAchievement($achievementId, $userId) {
        # check if achievement is still lactive
        $achiev = $this->mAchievTbl->select(['Achievement_ID' => $achievementId]);
        if(count($achiev) == 0) {
            return false;
        }
        $check = $this->mAchievUserTbl->select([
            'achievement_idfs' => $achievementId,
            'user_idfs' => $userId
        ]);
        if(count($check) == 0) {
            $this->mAchievUserTbl->insert([
                'achievement_idfs' => $achievementId,
                'user_idfs' => $userId,
                'date' => date('Y-m-d H:i:s', time()),
            ]);
            return true;
        } else {
            return false;
        }
    }
}