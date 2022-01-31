<?php
namespace Shortlink\V1\Rpc\Complete;

use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;

class CompleteController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Api Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * User Tools Helper
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Shortlink Provider Table
     *
     * @var TableGateway $mShortProviderTbl
     * @since 1.0.0
     */
    protected $mShortProviderTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

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
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Shortlink Achievements
     *
     * @var array $mAchievementPoints
     * @since 1.0.0
     */
    protected $mAchievementPoints;

    /**
     * Constructor
     *
     * ConfirmController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mShortProviderTbl = new TableGateway('shortlink', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mApiTools = new ApiTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);

        /**
         * Load Achievements to Cache
         */
        $achievTbl = new TableGateway('faucet_achievement', $mapper);
        $achievsXP = $achievTbl->select(['type' => 'shortlink']);
        $achievsFinal = [];
        if(count($achievsXP) > 0) {
            foreach($achievsXP as $achiev) {
                $achievsFinal[$achiev->goal] = $achiev;
            }
        }
        $this->mAchievementPoints = $achievsFinal;
    }

    public function completeAction()
    {
        $request = $this->getRequest();

        /**
         * Verify E-Mail Address
         *
         * @since 1.0.0
         */
        if($request->isGet()) {
            $token = filter_var($this->params()->fromRoute('token', ''), FILTER_SANITIZE_STRING);

            $shFound = $this->mShortDoneTbl->select(['link_id' => $token]);
            if(count($shFound) == 0) {
                echo 'shortlink not found ';
            } else {
                $shFound = $shFound->current();
                $linkInfo = $this->mShortProviderTbl->select(['Shortlink_ID' => $shFound->shortlink_idfs]);
                if(count($linkInfo) == 0) {
                    echo 'shortlink not found';
                    return false;
                }
                $linkInfo = $linkInfo->current();
                $sCheck = $linkInfo->refer_check;
                $bMultiCheck = stripos($sCheck, '|');
                $bHostFound = false;
                if($bMultiCheck === false) {
                    if(isset($_SERVER['HTTP_REFERER'])) {
                        $bHostFound = stripos($_SERVER['HTTP_REFERER'], $sCheck);
                    }
                } else {
                    $aChecks = explode('|', $sCheck);
                    foreach($aChecks as $sCheckM) {
                        $bHostFound = stripos($_SERVER['HTTP_REFERER'], $sCheckM);
                        if ($bHostFound === false) {

                        } else {
                            break;
                        }
                    }
                }
                $bCanSkip = true;

                $bCanSkip = ($_SERVER['HTTP_REFERER'] == NULL && $linkInfo->refer_check == NULL);
                //$bCanSkip = false;
                $bFixForNow = false;
                /**
                if($_SERVER['HTTP_REFERER'] == NULL || $_SERVER['HTTP_REFERER'] == "") {
                    $bFixForNow = true;
                }
                 * **/
                if($bFixForNow) {
                    echo '<form action="" method="POST"><div class="container">';
                    echo '<input type="hidden" name="shortlink_id" value="'.$token.'" />';
                    echo 'invalid referer '.$_SERVER['HTTP_REFERER'].' != '.$linkInfo->refer_check;
                    $actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    echo '<br/><b>Please copy this link and send an E-Mail to <a href="mailto:admin@swissfaucet.io?subject=Please verify Shortlink&body='.$actual_link.'">admin@swissfaucet.io</a> - The Shortlink will be credited! You see this because there seems to be an issue with the shortlink verification. <b>Your Coins are not lost</b></b>';
                    //echo '<div class="g-recaptcha" data-sitekey="6LcP5h0UAAAAACCA2YcZnschPWLbujY_vZPFjhQk"></div>';
                    //echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
                    //echo '<input type="submit" value="Confirm Shortlink manually" \>';
                    echo '</div></form>';
                } else {
                    if($shFound->date_completed == '0000-00-00 00:00:00') {
                        $this->mShortDoneTbl->update([
                            'date_completed' => date('Y-m-d H:i:s', time()),
                            'date_claimed' =>  date('Y-m-d H:i:s', time()),
                        ],[
                            'user_idfs' => $shFound->user_idfs,
                            'shortlink_idfs' => $shFound->shortlink_idfs,
                            'link_id' => $shFound->link_id,
                            'date_claimed' => '0000-00-00 00:00:00',
                            'date_completed' => '0000-00-00 00:00:00'
                        ]);

                        $newBalance = $this->mTransaction->executeTransaction($linkInfo->reward, false, $shFound->user_idfs, $shFound->shortlink_idfs, 'shortlink-complete', 'Shortlink '.$shFound->link_id.' completed');
                        if($newBalance !== false) {
                            $xpInfo = $this->mUserTools->addXP('shortlink-claim', $shFound->user_idfs);

                            $itemDrop = $this->mUserTools->getItemDropChance('shortlink-claim', $shFound->user_idfs);

                            # check for achievement completetion
                            $currentLinksDone = $this->mShortDoneTbl->select(['user_idfs' => $shFound->user_idfs])->count();

                            # check if user has completed an achievement
                            if(array_key_exists($currentLinksDone,$this->mAchievementPoints)) {
                                $this->mUserTools->completeAchievement($this->mAchievementPoints[$currentLinksDone]->Achievement_ID, $shFound->user_idfs);
                            }

                            # drive me crazy achievement
                            if($linkInfo->difficulty == 'ultra') {
                                $this->mUserTools->completeAchievement(29, $shFound->user_idfs);
                            }

                            $redirectUrl = $this->mSecTools->getCoreSetting('sh-complete-url');
                            return $this->redirect()->toUrl($redirectUrl);
                        }
                    } else {
                        echo 'already done';
                    }
                }
            }
        }

        if($request->isPost()) {
            echo 'verify link';
            var_dump($_POST);
        }

        return false;
    }
}
