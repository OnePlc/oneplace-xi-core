<?php
namespace Offerwall\V1\Rpc\Rating;

use Application\Controller\IndexController;
use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class RatingController extends AbstractActionController
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
     * Offerwall Provider Table
     *
     * @var TableGateway $mOfferwallTbl
     * @since 1.0.0
     */
    protected $mOfferwallTbl;

    /**
     * Offerwall Rating Table
     *
     * @var TableGateway $mOfferRateTbl
     * @since 1.0.0
     */
    protected $mOfferRateTbl;

    /**
     * Offerwall Table User Table
     *
     * Relation between Offerwall and User
     * to determine if user has completed a Offerwall
     *
     * @var TableGateway $mOfferDoneTbl
     * @since 1.0.0
     */
    protected $mOfferDoneTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

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
     * RatingController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mOfferwallTbl = new TableGateway('offerwall', $mapper);
        $this->mOfferDoneTbl = new TableGateway('offerwall_user', $mapper);
        $this->mOfferRateTbl = new TableGateway('offerwall_rating', $mapper);
        $this->mApiTools = new ApiTools($mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
    }

    public function ratingAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $request = $this->getRequest();

        /**
         * List open ratings for user or admin
         */
        if($request->isGet()) {
            $retMode = 'def';
            if(isset($_REQUEST['mode'])) {
                $mode = filter_var($_REQUEST['mode'], FILTER_SANITIZE_STRING);
                if($mode == 'admin') {
                    $retMode = 'admin';
                }
            }

            if($retMode == 'def') {
                $openRatings = [];

                $linksToCheck = $this->mOfferwallTbl->select(['active' => 1]);
                foreach($linksToCheck as $sh) {
                    $rated = $this->mOfferRateTbl->select(['user_idfs' => $me->User_ID, 'offerwall_idfs' => $sh->Offerwall_ID])->count();
                    if($rated == 0) {
                        $linksDone = $this->mOfferDoneTbl->select(['user_idfs' => $me->User_ID, 'offerwall_idfs' => $sh->Offerwall_ID])->count();

                        if($linksDone >= 5) {
                            $openRatings[] = [
                                'id' => $sh->Offerwall_ID,
                                'name' => $sh->label,
                                'links_done' => $linksDone
                            ];
                        }
                    }
                }
            } else {
                if((int)$me->is_employee !== 1) {
                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields no emp)'));
                }
                if($this->mSecTools->checkIpRestrictedAccess() !== true) {
                    return new ApiProblem(400, 'You are not allowed this access this api');
                }

                $openRatings = [];

                $selWh = new Where();
                $selWh->isNull('verified_by');
                $linksToCheck = $this->mOfferRateTbl->select($selWh);
                foreach($linksToCheck as $rate) {
                    $rateUser = $this->mUserTbl->select(['User_ID' => $rate->user_idfs]);
                    $linkInfo = $this->mOfferwallTbl->select(['Offerwall_ID' => $rate->offerwall_idfs]);
                    if(count($rateUser) > 0 && count($linkInfo) > 0) {
                        $rateUser = $rateUser->current();
                        $linkInfo = $linkInfo->current();
                        $openRatings[] = [
                            'id' => $rate->offerwall_idfs,
                            'name' => $linkInfo->label,
                            'rating' => $rate->rating,
                            'comment' => $rate->comment,
                            'date' => $rate->date,
                            'user' => [
                                'id' => $rateUser->User_ID,
                                'name' => $rateUser->username
                            ]
                        ];
                    }
                }
            }

            return [
                'open' => $openRatings
            ];
        }

        /**
         * Submit Rating for Shortlink (User)
         */
        if($request->isPost()) {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['link_id','rating','comment'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            $linkId = filter_var($json->link_id, FILTER_SANITIZE_NUMBER_INT);
            $rating = (float)$json->rating;
            $comment = filter_var($json->comment, FILTER_SANITIZE_STRING);

            if($linkId == 0 || ($rating < 0 || $rating > 5)) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }
            $comment = substr($comment,0,255);

            $linkInfo = $this->mOfferwallTbl->select(['Offerwall_ID' => $linkId]);
            if(count($linkInfo) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'Shortlink not found'));
            }
            $linkInfo = $linkInfo->current();

            # check if user already has made a rating
            $rated = $this->mOfferRateTbl->select(['user_idfs' => $me->User_ID, 'offerwall_idfs' => $linkId])->count();
            if($rated == 0) {
                # double check that user is allowed to rate the link
                $linksDone = $this->mOfferDoneTbl->select(['user_idfs' => $me->User_ID, 'offerwall_idfs' => $linkId])->count();

                if($linksDone > 5) {
                    $this->mOfferRateTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'offerwall_idfs' => $linkId,
                        'rating' => $rating,
                        'comment' => $comment,
                        'date' => date('Y-m-d H:i:s', time()),
                    ]);

                    $fNewBalance = $this->mTransaction->executeTransaction(10, false, $me->User_ID, $linkId, 'of-rating', 'Rating for Offerwall '.$linkInfo->label, $me->User_ID);
                    if($fNewBalance !== false) {
                        return [
                            'token_balance' => $fNewBalance
                        ];
                    } else {
                        return new ApiProblemResponse(new ApiProblem(400, 'Error during reward transaction'));
                    }
                } else {
                    return new ApiProblemResponse(new ApiProblem(400, 'You are not allowed to rate this offerwall'));
                }
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'You have already rated this offerwall'));
            }
        }

        /**
         * Confirm or decline rating (admin)
         */
        if($request->isPut()) {
            # only for admins
            if((int)$me->is_employee !== 1) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields no emp)'));
            }
            if($this->mSecTools->checkIpRestrictedAccess() !== true) {
                return new ApiProblem(400, 'You are not allowed this access this api');
            }

            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['link_id','user_id','accept'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }
            # some basic security
            $linkId = filter_var($json->link_id, FILTER_SANITIZE_NUMBER_INT);
            $userId = filter_var($json->user_id, FILTER_SANITIZE_NUMBER_INT);
            $accept = filter_var($json->accept, FILTER_SANITIZE_NUMBER_INT);
            if($accept != 0 && $accept != 1) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }
            if($userId == 0 || $linkId == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            # check if rating really exists
            $ratingFound = $this->mOfferRateTbl->select([
                'user_idfs' => $userId,
                'offerwall_idfs' => $linkId
            ]);
            if(count($ratingFound) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'Rating not found'));
            }
            $ratingFound = $ratingFound->current();

            /**
             * Accept or decline rating
             */
            if($accept == 1) {
                # verify rating as accepted
                $this->mOfferRateTbl->update([
                    'active' => 1,
                    'verified_by' => $me->User_ID,
                    'verified_date' => date('Y-m-d H:i:s', time())
                ],['offerwall_idfs' => $linkId, 'user_idfs' => $userId]);

                # calculate new overall rating
                $allRatings = $this->mOfferRateTbl->select(['offerwall_idfs' => $linkId,'active' => 1]);
                $totalRating = 0;
                $ratingCount = 0;
                foreach($allRatings as $rating) {
                    $totalRating+=$rating->rating;
                    $ratingCount++;
                }
                # update rating for shortlink
                $baseRating = number_format($totalRating/$ratingCount, 2, '.', '');
                $this->mOfferwallTbl->update([
                    'rating' => $baseRating,
                    'rating_count' => $ratingCount,
                ],['Offerwall_ID' => $linkId]);
            } else {
                $this->mOfferRateTbl->update([
                    'declined_comment' => '',
                    'verified_by' => $me->User_ID,
                    'declined_date' => date('Y-m-d H:i:s', time())
                ],['offerwall_idfs' => $linkId, 'user_idfs' => $userId]);
            }

            return [
                'state' => 'succcess'
            ];
        }
    }
}
