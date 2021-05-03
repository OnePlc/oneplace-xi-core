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

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Session\Container;

class ShortlinkResource extends AbstractResourceListener
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        # Load Shortlink Provider List
        $shortlinksDB = $this->mShortProviderTbl->select(['Shortlink_ID' => $id]);
        if(count($shortlinksDB) == 0) {
            return new ApiProblem(404, 'Shortlink provider not found');
        } else {
            $provider = $shortlinksDB->current();

            # get provider links
            $links = $this->mShortTbl->select(['shortlink_idfs' => $id]);
            if(count($links) > 0) {
                foreach($links as $lnk) {
                    # Check if link is already started
                    $oWhDone = new Where();
                    $oWhDone->equalTo('user_idfs', $me->User_ID);
                    $oWhDone->like('link_id', $lnk->link_id);
                    $oWhDone->equalTo('shortlink_idfs', $lnk->shortlink_idfs);
                    $oWhDone->greaterThanOrEqualTo('date_started', date('Y-m-d H:i:s', strtotime('-24 hours')));
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
                        break;
                    } else {
                        # link already started - give same link again
                        $checkLnk = $checkLnk->current();
                        if($checkLnk->date_claimed == '0000-00-00 00:00:00') {
                            $provider->link = $checkLnk;
                            break;
                        }
                    }
                }

                # get final link info
                $finalLink = $this->mShortTbl->select([
                    'link_id' => $provider->link->link_id,
                    'shortlink_idfs' => $provider->link->shortlink_idfs
                ]);
                if(count($finalLink) == 0) {
                    return new ApiProblem(404, 'Shortlink final link not found');
                }

                # return next usable provider shortlink
                $provider->link = $finalLink->current();
                return $provider;
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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSession->auth;

        # Load Shortlink Provider List
        $shortlinksDB = $this->mShortProviderTbl->select();
        $shortlinks = [];
        $totalLinks = 0;
        $totalReward = 0;
        foreach($shortlinksDB as $sh) {
            # get links for provider
            $links = $this->mShortTbl->select(['shortlink_idfs' => $sh->Shortlink_ID]);

            # Count links for provider
            $totalLinks+=count($links);
            $sh->linksTotal = count($links);

            # check for completed links for user
            $linksDone = [];
            foreach($links as $lnk) {
                $oWh = new Where();
                $oWh->equalTo('shortlink_idfs', $lnk->shortlink_idfs);
                $oWh->like('link_id', $lnk->link_id);
                $oWh->equalTo('user_idfs', $me->User_ID);
                $oWh->greaterThanOrEqualTo('date_completed', date('Y-m-d H:i:s', strtotime('-24 hours')));
                $slCheck = $this->mShortDoneTbl->select($oWh);
                if(count($slCheck) > 0) {
                    $sl = $slCheck->current();
                    $linksDone[] = $sl;
                    $sh->last_done = $sl->date_completed;
                }
            }
            $sh->linksDone = count($linksDone);
            $totalReward+=($sh->linksTotal-$sh->linksDone)*$sh->reward;

            # get link global statistics over all users
            $doneCheck = $this->mShortDoneTbl->select(['shortlink_idfs' => $sh->Shortlink_ID]);
            if(count($doneCheck) > 0) {
                $sh->count_complete = 0;
                $sh->count_started = 0;
                foreach($doneCheck as $ck) {
                    if($ck->date_completed == '0000-00-00 00:00:00') {
                        $sh->count_started++;
                    } else {
                        $sh->count_complete++;
                    }
                }
            }

            $shortlinks[] = $sh;
        }

        $return = (object)['provider' => $shortlinks,'total_reward' => $totalReward,'total_links' => $totalLinks];

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
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}