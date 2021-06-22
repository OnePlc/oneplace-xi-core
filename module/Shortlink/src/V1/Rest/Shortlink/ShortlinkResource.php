<?php
/**
 * ShortlinkResource.php - Shortlink Resource
 *
 * Main Resource for Faucet Shortlink
 *
 * @category Resource
 * @package Shortlink
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Shortlink\V1\Rest\Shortlink;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Http\ClientStatic;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Laminas\Session\Container;

class ShortlinkResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Shortlink Table
     *
     * @var TableGateway $mShortTbl
     * @since 1.0.0
     */
    protected $mShortTbl;

    /**
     * Shortlink Provider Table
     *
     * @var TableGateway $mShortProviderTbl
     * @since 1.0.0
     */
    protected $mShortProviderTbl;

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
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * User Basic Tools
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

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
     * ShortlinkResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mShortProviderTbl = new TableGateway('shortlink', $mapper);
        $this->mShortTbl = new TableGateway('shortlink_link', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);


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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        # Load Shortlink Provider List
        $shortlinksDB = $this->mShortProviderTbl->select(['url' => $id]);
        if(count($shortlinksDB) == 0) {
            return new ApiProblem(404, 'Shortlink provider not found');
        } else {
            $provider = $shortlinksDB->current();

            $links = $this->mShortTbl->select(['shortlink_idfs' => $provider->Shortlink_ID]);

            # get provider links
            $linkCount = $this->mShortTbl->select(['shortlink_idfs' => $provider->Shortlink_ID])->count();
            $oWhDone = new Where();
            $oWhDone->equalTo('user_idfs', $me->User_ID);
            $oWhDone->equalTo('shortlink_idfs',  $provider->Shortlink_ID);
            $oWhDone->greaterThanOrEqualTo('date_started', date('Y-m-d H:i:s', strtotime('-23 hours')));
            $linksDone = $this->mShortDoneTbl->select($oWhDone)->count();


            $finLink = "#";
            if(count($links) > 0) {
                foreach($links as $lnk) {
                    if($provider->url_api == "") {
                        $dev = '1';
                        # Check if link is already started
                        $oWhDone = new Where();
                        $oWhDone->equalTo('user_idfs', $me->User_ID);
                        $oWhDone->like('link_id', $lnk->link_id);
                        $oWhDone->equalTo('shortlink_idfs', $lnk->shortlink_idfs);
                        $oWhDone->greaterThanOrEqualTo('date_started', date('Y-m-d H:i:s', strtotime('-23 hours')));
                        $checkLnk = $this->mShortDoneTbl->select($oWhDone);

                        # if not started, start it now and choose this link
                        if(count($checkLnk) == 0) {
                            $newLink = [
                                'user_idfs' => $me->User_ID,
                                'link_id' => $lnk->link_id,
                                'shortlink_idfs' => $lnk->shortlink_idfs,
                                'date_started' => date('Y-m-d H:i:s', time()),
                                'date_claimed' => '0000-00-00 00:00:00',
                                'date_completed' => '0000-00-00 00:00:00'
                            ];
                            $this->mShortDoneTbl->insert($newLink);
                            $provider->link = (object)$newLink;
                        } else {
                            # link already started - give same link again
                            $checkLnk = $checkLnk->current();
                            if($checkLnk->date_claimed == '0000-00-00 00:00:00') {
                                $provider->link = $checkLnk;
                            }
                        }

                        # get final link info
                        $finalLink = $this->mShortTbl->select([
                            'link_id' => $lnk->link_id,
                            'shortlink_idfs' => $lnk->shortlink_idfs
                        ]);
                        if(count($finalLink) == 0) {
                            return new ApiProblem(404, 'Shortlink final link not found');
                        }


                        # return next usable provider shortlink
                        $finLink = $finalLink->current()->href;
                    }
                }

                if(($linksDone < $linkCount) && $provider->url_api != '') {
                    $time = date('Y-m-d H:i:s', time());
                    $dev = '2';
                    $destHash = hash('sha256', $provider->url_api.$me->User_ID.$time);
                    $destLink = $this->mSecTools->getCoreSetting('api-url').'/task/complete/'.$destHash;
                    /**
                    $result = @json_decode(file_get_contents($provider->url_api."&url=".$destLink),TRUE);
                    if($result["status"] === 'error') {
                        //echo $result["message"];
                        $finLink = "errror";
                    } else {
                        $finLink = $result["shortenedUrl"];
                        $newLink = [
                            'user_idfs' => $me->User_ID,
                            'link_id' => $destHash,
                            'link_url' => $finLink,
                            'shortlink_idfs' => $lnk->shortlink_idfs,
                            'date_started' => $time,
                            'date_claimed' => '0000-00-00 00:00:00',
                            'date_completed' => '0000-00-00 00:00:00'
                        ];
                        $this->mShortDoneTbl->insert($newLink);
                    } **/
                    $response = ClientStatic::get($provider->url_api."&url=".$destLink, []);

                    $status = $response->getStatusCode();
                    $googleResponse = $response->getBody();

                    $googleJson = json_decode($googleResponse);
                    $status = "errror";
                    if(is_array($googleJson)) {
                        $status = $googleJson["status"];
                    } else {
                        $status = $googleJson->status;
                    }
                    if($status === 'error') {
                        $finLink = "sherror";
                        $dev = '3';
                    } else {
                        $dev = '4';
                        if(is_array($googleJson)) {
                            $finLink = $googleJson["shortenedUrl"];
                        } else {
                            $finLink = $googleJson->shortenedUrl;
                        }
                        $newLink = [
                            'user_idfs' => $me->User_ID,
                            'link_id' => $destHash,
                            'link_url' => $finLink,
                            'shortlink_idfs' => $lnk->shortlink_idfs,
                            'date_started' => $time,
                            'date_claimed' => '0000-00-00 00:00:00',
                            'date_completed' => '0000-00-00 00:00:00'
                        ];
                        $this->mShortDoneTbl->insert($newLink);
                    }
                } elseif($provider->url_api != '') {
                    $dev = '5';
                    $oWhDone = new Where();
                    $oWhDone->equalTo('user_idfs', $me->User_ID);
                    $oWhDone->notLike('link_url', null);
                    $oWhDone->equalTo('shortlink_idfs', $lnk->shortlink_idfs);
                    $oWhDone->greaterThanOrEqualTo('date_started', strtotime('-23 hours'));
                    $oWhDone->like('date_claimed', '0000-00-00 00:00:00');
                    $oWhDone->like('date_completed', '0000-00-00 00:00:00');
                    $checkLnk = $this->mShortDoneTbl->select($oWhDone);
                    if(count($checkLnk) > 0) {
                        $finLink = $checkLnk->current()->link_url;
                    } else {
                        $time = date('Y-m-d H:i:s', time());
                    }
                }

                if($finLink == null) {
                    $finLink = "#";
                }

                $link = (object)[
                    'href' => $finLink,
                ];
                return (object)[
                    'link' =>$link,
                    'id' => $provider->Shortlink_ID,
                    'name' => $provider->label
                ];
            } else {
                return new ApiProblem(404, 'No links for provider '.$provider->label.' found');
            }
        }
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

        # Load Shortlink Provider List
        $provSel = new Select($this->mShortProviderTbl->getTable());
        $provSel->where(['active' => 1]);
        $provSel->order('sort_id ASC');
        $shortlinksDB = $this->mShortProviderTbl->selectWith($provSel);
        $shortlinks = [];
        $shortlinksById = [];
        $totalLinks = 0;
        $totalReward = 0;
        foreach($shortlinksDB as $sh) {
            # get links for provider
            $shortlinksById[$sh->Shortlink_ID] = ['name' =>  $sh->label,'reward' =>  $sh->reward];
            # Count links for provider
            $totalLinks+=$sh->views_per_day;
            $sh->linksTotal = $sh->views_per_day;

            $sh->last_done = "";
            $sh->unlock_in = 0;

            # check for completed links for user
            $linksDone = 0;
            $oWh = new Where();
            $oWh->equalTo('shortlink_idfs', $sh->Shortlink_ID);
            //$oWh->like('link_id', $lnk->link_id);
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->greaterThanOrEqualTo('date_completed', date('Y-m-d H:i:s', strtotime('-23 hours')));
            $slCheck = $this->mShortDoneTbl->select($oWh);
            if(count($slCheck) > 0) {
                foreach($slCheck as $check) {
                    $linksDone++;
                    $sh->last_done = $check->date_completed;
                    $sh->unlock_in = strtotime($check->date_completed)+(23*3600)-time();
                }
            }
            $sh->linksDone = $linksDone;
            $totalReward+=($sh->linksTotal-$sh->linksDone)*$sh->reward;

            $shortlinks[] = (object)[
                'id' => $sh->Shortlink_ID,
                'name' => $sh->label,
                'reward' => $sh->reward,
                'url' => $sh->url,
                'links_done' => $sh->linksDone,
                'links_total' => $sh->linksTotal,
                'difficulty' => $sh->difficulty,
                'last_done' => $sh->last_done,
                'unlock_in' =>  $sh->unlock_in,
            ];
        }

        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $pageSize = 10;

        # Compile history
        $history = [];
        $historySel = new Select($this->mShortDoneTbl->getTable());
        $historySel->where(['user_idfs' => $me->User_ID]);
        $historySel->order('date_started DESC');
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $historySel,
            # the adapter to run it against
            $this->mShortDoneTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $offersPaginated = new Paginator($oPaginatorAdapter);
        $offersPaginated->setCurrentPageNumber($page);
        $offersPaginated->setItemCountPerPage($pageSize);
        foreach($offersPaginated as $offer) {
            $history[] = (object)[
                'date_start' => $offer->date_started,
                'date_done' => $offer->date_completed,
                'reward' => $shortlinksById[$offer->shortlink_idfs]['reward'],
                'name' => $shortlinksById[$offer->shortlink_idfs]['name'],
                'shortlink' => $shortlinksById[$offer->shortlink_idfs]['name'],
                'status' => ($offer->date_completed == '0000-00-00 00:00:00') ? 'started' : 'done',
            ];
        }

        $totalDone24Wh = new Where();
        $totalDone24Wh->equalTo('user_idfs', $me->User_ID);
        $totalDone24Wh->greaterThanOrEqualTo('date_completed', date('Y-m-d H:i:s', strtotime('-23 hours')));
        $totalLinksDone24h = $this->mShortDoneTbl->select($totalDone24Wh)->count();

        $totalDoneWh = new Where();
        $totalDoneWh->equalTo('user_idfs', $me->User_ID);
        $totalLinksDone = $this->mShortDoneTbl->select($totalDoneWh)->count();

        $return = (object)[
            'provider' => $shortlinks,
            'total_reward' => $totalReward,
            'total_links' => $totalLinks,
            'links_done' => $totalLinksDone24h,
            'history' => [
                'items' => $history,
                'total_items' => $totalLinksDone,
                'page_size' => $pageSize,
                'page' => $page,
                'page_count' => (round($totalLinksDone/$pageSize) > 0) ? round($totalLinksDone/$pageSize) : 1,
            ]
        ];

        return [
            'shortlinks' => $return,
        ];
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

        # check link id for malicious code
        $secResult = $this->mSecTools->basicInputCheck([$data->link_id]);
        if($secResult !== 'ok') {
            # ban user and force logout on client
            $this->mUserSetTbl->insert([
                'user_idfs' => $me->User_ID,
                'setting_name' => 'user-tempban',
                'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Shortlink Complete',
            ]);
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }

        $linkId = filter_var($data->link_id, FILTER_SANITIZE_STRING);
        $hasStarted = $this->mShortDoneTbl->select([
            'user_idfs' => $me->User_ID,
            'link_id' => $linkId,
            'date_claimed' => '0000-00-00 00:00:00'
        ]);

        if(count($hasStarted) == 0) {
            return new ApiProblem(404, 'Could not find entry for this shortlink. Did you start it correctly ?');
        }
        $hasStarted = $hasStarted->current();

        /**
         * Add Anti-Fraud here
         * - 10 Second Timer for Claim
         * - Captcha for Star
         * - Check further
         */

        $linkInfo = $this->mShortProviderTbl->select(['Shortlink_ID' => $hasStarted->shortlink_idfs]);
        if(count($linkInfo) > 0) {
            $linkInfo = $linkInfo->current();

            $this->mShortDoneTbl->update([
                'date_claimed' => date('Y-m-d H:i:s', time()),
                'date_completed' => date('Y-m-d H:i:s', time()),
            ],[
                'user_idfs' => $me->User_ID,
                'link_id' => $linkId,
                'date_completed' => '0000-00-00 00:00:00',
            ]);

            # check for achievement completetion
            $currentLinksDone = $this->mShortDoneTbl->select(['user_idfs' => $me->User_ID])->count();

            # check if user has completed an achievement
            if(array_key_exists($currentLinksDone,$this->mAchievementPoints)) {
                $this->mUserTools->completeAchievement($this->mAchievementPoints[$currentLinksDone]->Achievement_ID, $me->User_ID);
            }

            # drive me crazy achievement
            if($linkInfo->difficulty == 'ultra') {
                $this->mUserTools->completeAchievement(29, $me->User_ID);
            }

            # Add User XP
            $newLevel = $this->mUserTools->addXP('shortlink-claim', $me->User_ID);
            if($newLevel !== false) {
                $me->xp_level = $newLevel['xp_level'];
                $me->xp_percent = $newLevel['xp_percent'];
            }

            $newBalance = $this->mTransaction->executeTransaction($linkInfo->reward, false, $me->User_ID, $linkInfo->Shortlink_ID, 'shortlink-complete', 'Shortlink '.$linkId.' completed');
            if($newBalance !== false) {
                //$var_str = var_export($_SERVER, true);
                //$var_str2 = var_export(getallheaders(), true);
                //file_put_contents('/var/www/devlog/test_'.time(), $var_str.'#####'.$var_str2);
                return [
                    'link_id' => $linkId,
                    'reward' => $linkInfo->reward,
                    'test' => $_SERVER,
                    'test2' => getallheaders(),
                    'token_balance' => $newBalance,
                    'xp_level' => $me->xp_level,
                    'xp_percent' => $me->xp_percent,
                    'crypto_balance' => $this->mTransaction->getCryptoBalance($newBalance, $me),
                ];
            } else {
                return new ApiProblem(500, 'Transaction Error. Please contact admin');
            }
        } else {
            return new ApiProblem(404, 'Could not find shortlink provider.');
        }
    }
}
