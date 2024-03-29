<?php
/**
 * OfferwallResource.php - Offerwall Resource
 *
 * Main Resource for Faucet Offerwalls
 *
 * @category Resource
 * @package Offerwall
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Offerwall\V1\Rest\Offerwall;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Faucet\Transaction\TransactionHelper;

class OfferwallResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Offerwall Table
     *
     * @var TableGateway $mOfferwallTbl
     * @since 1.0.0
     */
    protected $mOfferwallTbl;

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
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * @var TableGateway
     */
    private $mOfferwallRatingTbl;

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
        $this->mOfferwallTbl = new TableGateway('offerwall', $mapper);
        $this->mOfferwallUserTbl = new TableGateway('offerwall_user', $mapper);
        $this->mOfferwallRatingTbl = new TableGateway('offerwall_rating', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
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
     * Fetch all or a subset of offerwalls
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = [])
    {
        # Check if user is logged in
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if($me->email_verified == 0) {
            //return new ApiProblem(401, 'Your Account must be verified to access Offerwalls. Please check your Inbox for Verification E-Mail');
        }

        $ofrSel = new Select($this->mOfferwallRatingTbl->getTable());
        $ofrSel->join(['u' => 'user'], 'u.User_ID = offerwall_rating.user_idfs', ['username']);
        $ofRatings = $this->mOfferwallRatingTbl->selectWith($ofrSel);
        $ofRatingsByOfferWallId = [];
        foreach($ofRatings as $ofr) {
            $rating = ceil($ofr->rating);
            if(!array_key_exists('of-'.$ofr->offerwall_idfs, $ofRatingsByOfferWallId)) {
                $ofRatingsByOfferWallId['of-'.$ofr->offerwall_idfs] = ['total' => 0, 'ratings' => [], 'comments' => []];
            }
            if(!array_key_exists('r-'.$rating, $ofRatingsByOfferWallId['of-'.$ofr->offerwall_idfs]['ratings'])) {
                $ofRatingsByOfferWallId['of-'.$ofr->offerwall_idfs]['ratings']['r-'.$rating] = 0;
            }
            $ofRatingsByOfferWallId['of-'.$ofr->offerwall_idfs]['ratings']['r-'.$rating]++;
            $ofRatingsByOfferWallId['of-'.$ofr->offerwall_idfs]['total']++;
            if(strlen($ofr->comment) > 5) {
                $ofRatingsByOfferWallId['of-'.$ofr->offerwall_idfs]['comments'][] = ['comment' => $ofr->comment, 'username' => $ofr->username, 'rating' => $rating, 'id' => $ofr->user_idfs];
            }
        }

        # Compile list of all offerwall providers
        $offerwalls = [];
        $offerwallsById = [];
        $ofWh = ['active' => 1];
        if($me->User_ID == 335874987) {
            //$ofWh = [];
        }
        $ofSel = new Select($this->mOfferwallTbl->getTable());
        $ofSel->where($ofWh);
        $ofSel->order('sortid ASC');
        $offerwallsDB = $this->mOfferwallTbl->selectWith($ofSel);
        foreach($offerwallsDB as $offerwall) {
            $rDetail = [];
            $rTotal = 0;
            $rComments = [];
            if(array_key_exists('of-'.$offerwall->Offerwall_ID, $ofRatingsByOfferWallId)) {
                $rDetail = $ofRatingsByOfferWallId['of-'.$offerwall->Offerwall_ID]['ratings'];
                $rTotal = $ofRatingsByOfferWallId['of-'.$offerwall->Offerwall_ID]['total'];
                $rComments = $ofRatingsByOfferWallId['of-'.$offerwall->Offerwall_ID]['comments'];
            }
            $offerwalls[] = (object)[
                'id' => $offerwall->Offerwall_ID,
                'url' => $offerwall->wall_name,
                'background' => $offerwall->background,
                'name' => $offerwall->label,
                'time' => $offerwall->time,
                'rating' => $offerwall->rating,
                'rating_count' => $rTotal,
                'rating_detail' => $rDetail,
                'rating_comments' => $rComments,
                'reward' => $offerwall->reward
            ];
            $offerwallsById[$offerwall->Offerwall_ID] = $offerwall->label;
        }

        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        if($page <= 0) {
            return new ApiProblem(400, 'Invalid Page');
        }
        $pageSize = 10;

        # Compile history
        $history = [];
        $historySel = new Select($this->mOfferwallUserTbl->getTable());
        $historySel->where(['user_idfs' => $me->User_ID]);
        $historySel->order('date_completed DESC');
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $historySel,
            # the adapter to run it against
            $this->mOfferwallUserTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $offersPaginated = new Paginator($oPaginatorAdapter);
        $offersPaginated->setCurrentPageNumber($page);
        $offersPaginated->setItemCountPerPage($pageSize);
        foreach($offersPaginated as $offer) {
            $history[] = (object)[
                'date' => $offer->date_completed,
                'amount' => $offer->amount,
                'name' => $offer->label,
                'offerwall' => $offerwallsById[$offer->offerwall_idfs],
            ];
        }
        $totalOffers = $this->mOfferwallUserTbl->select(['user_idfs' => $me->User_ID])->count();

        $viewData = [
            '_links' => [],
            '_embedded' => [
                'offerwall' => $offerwalls,
                'history' => $history,
                'total_items' => $totalOffers,
                'page_size' => $pageSize,
                'page_count' => (round($totalOffers/$pageSize) > 0) ? round($totalOffers/$pageSize) : 1,
                'page' => $page
            ]
        ];

        // Special Handling for SkyppyAds... omg...
        /**
        $skippySecret = $this->mSecTools->getCoreSetting('skippy-secret');
        $skippyKey = md5($me->User_ID.$skippySecret);
        $viewData['_embedded']['skippy_key'] = $skippyKey;
         * **/

        $hasMessage = $this->mSecTools->getCoreSetting('faucet-offers-msg-content');
        if($hasMessage) {
            $message = $hasMessage;
            $messageType = $this->mSecTools->getCoreSetting('faucet-offers-msg-level');
            $xpReq = $this->mSecTools->getCoreSetting('faucet-offers-msg-xplevel');
            $addMsg = false;
            if($xpReq) {
                if($me->xp_level >= $xpReq) {
                    $addMsg = true;
                }
            } else {
                $addMsg = true;
            }

            if($addMsg && strlen($message) > 0) {
                $viewData['message'] = [
                    'type' => $messageType,
                    'message' => $message
                ];
            }
        }

        return (object)$viewData;
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
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
