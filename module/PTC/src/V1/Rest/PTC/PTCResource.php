<?php
namespace PTC\V1\Rest\PTC;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class PTCResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * PTC Table
     *
     * @var TableGateway $mPTCTbl
     * @since 1.0.0
     */
    protected $mPTCTbl;

    /**
     * PTC User (View) Table
     *
     * @var TableGateway $mPTCViewTbl
     * @since 1.0.0
     */
    protected $mPTCViewTbl;

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
     * PTCResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mPTCTbl = new TableGateway('ptc', $mapper);
        $this->mPTCViewTbl = new TableGateway('ptc_user', $mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        $secResult = $this->mSecTools->basicInputCheck([
            $data->title,
            $data->description,
            $data->views,
            $data->redirect,
            $data->nsfw,
            $data->website,
            $data->timer,
            $data->occur,
        ]);
        if($secResult !== 'ok') {
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }

        $title = filter_var($data->title, FILTER_SANITIZE_STRING);
        $description = filter_var($data->description, FILTER_SANITIZE_STRING);
        $views = filter_var($data->views, FILTER_SANITIZE_NUMBER_INT);
        $redirect = filter_var($data->redirect, FILTER_SANITIZE_NUMBER_INT);
        $nsfw = filter_var($data->nsfw, FILTER_SANITIZE_NUMBER_INT);
        $website = filter_var($data->website, FILTER_SANITIZE_STRING);
        $timer = filter_var($data->timer, FILTER_SANITIZE_NUMBER_INT);
        $occurence = filter_var($data->occur, FILTER_SANITIZE_STRING);
        if($occurence != 'DAY' && $occurence != 'WEK' && $occurence != 'UNQ') {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
        }
        $url = filter_var($data->url, FILTER_SANITIZE_STRING);

        if(!$views) {
            $views = 0;
        }
        if($views < 200) {
            return new ApiProblemResponse(new ApiProblem(400, 'You need at least 200 views'));
        }
        if($redirect) {
            $redirect = 1;
        } else {
            $redirect = 0;
        }
        if($nsfw) {
            $nsfw = 1;
        } else {
            $nsfw = 0;
        }

        $price = 0;
        switch($timer) {
            case 15:
                $price = 0.75;
                break;
            case 30:
                $price = 1;
                break;
            case 60:
                $price = 1.375;
                break;
            case 90:
                $price = 1.75;
                break;
            default:
                break;
        }

        $amount = $views * $price;

        # basic check for ad title blacklist and such

        # check for existing ads

        # check balance
        if($this->mTransaction->checkUserCreditBalance($amount, $me->User_ID)) {
            # save ptc
            $this->mPTCTbl->insert([
                'title' => utf8_encode($title),
                'description' => utf8_encode($description),
                'view_balance' => $views,
                'redirect' => $redirect,
                'timer' => $timer,
                'nsfw_warn' => $nsfw,
                'occur' => $occurence,
                'website_idfs' => 1,
                'url' => $url,
                'created_by' => $me->User_ID,
                'created_date' => date('Y-m-d H:i:s', time()),
            ]);
            $ptcId = $this->mPTCTbl->lastInsertValue;
            $newBalance = $this->mTransaction->executeCreditTransaction($amount, true, $me->User_ID, $ptcId, 'ptcadd');
            if($newBalance !== false) {
                return [
                'credit_balance' => $newBalance,
                ];
            } else {
                return new ApiProblemResponse(new ApiProblem(500, 'Error while Credits transaction'));
            }
        } else {
            return new ApiProblemResponse(new ApiProblem(400, 'Your Credit Balance is too low'));
        }
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        $ptcFound = $this->mPTCTbl->select(['PTC_ID' => $id]);
        if(count($ptcFound) == 0) {
            return new ApiProblem(404, 'PTC Ad not found');
        }
        $ptcInfo = $ptcFound->current();
        if($ptcInfo->view_balance <= 0) {
            return new ApiProblem(400, 'PTC is not available at the moment');
        }

        if($ptcInfo->verified != 1) {
            return new ApiProblem(400, 'PTC is not available at the moment');
        }

        switch($ptcInfo->occur) {
            case 'DAY':
                $dayWh = new Where();
                $dayWh->equalTo('user_idfs', $me->User_ID);
                $dayWh->greaterThanOrEqualTo('date_started', date('Y-m-d H:i:s', strtotime('-24 hours')));
                $viewToday = $this->mPTCViewTbl->select($dayWh);
                if(count($viewToday) == 0) {
                    $this->mPTCViewTbl->insert([
                        'ptc_idfs' => $ptcInfo->PTC_ID,
                        'user_idfs' => $me->User_ID,
                        'website_idfs' => 1,
                        'date_started' => date('Y-m-d H:i:s', time()),
                    ]);
                } else {
                    if($viewToday->current()->date_completed != null) {
                        return new ApiProblem(400, 'You have already view this ad');
                    }
                }
                break;
            default:
                break;
        }

        return (object)[
            'ptc' => (object)[
                'id' => $ptcInfo->PTC_ID,
                'title' => utf8_decode($ptcInfo->title),
                'description' => utf8_decode($ptcInfo->description),
                'url' => $ptcInfo->url,
                'timer' => $ptcInfo->timer,
                'redirect' => $ptcInfo->redirect,
            ]
        ];
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
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

        if($me->User_ID != 335874987) {
            return (object)[
                'ptc_my' => [],
                'ptc' => [],
            ];
        }

        $totalViews = 0;
        $activeAds = 0;
        $finishedAds = 0;
        $myPTCAds = [];
        $ptcFound = $this->mPTCTbl->select(['created_by' => $me->User_ID]);
        if(count($ptcFound) > 0) {
            foreach($ptcFound as $ptc) {
                $totalViews+=$ptc->view_balance;
                if($ptc->verified == 1 && $ptc->view_balance > 0) {
                    $activeAds++;
                }
                if($ptc->verified == 1 && $ptc->view_balance == 0) {
                    $finishedAds++;
                }
                $myPTCAds[] = (object)[
                    'id' => $ptc->PTC_ID,
                    'title' => utf8_decode($ptc->title),
                    'description' => utf8_decode( $ptc->description),
                    'views' => $ptc->view_balance,
                    'redirect' => $ptc->redirect,
                    'nsfw' => $ptc->nsfw_warn,
                    'active' => $ptc->active,
                    'timer' => $ptc->timer,
                    'occurence' => $ptc->occur,
                    'website' => 'swissfaucet.io',
                    'url' => $ptc->url,
                    'verified' => $ptc->verified
                ];
            }
        }

        $viewPTCAds = [];
        $ptcViewWh = new Where();
        $ptcViewWh->equalTo('verified', 1);
        $ptcViewWh->greaterThanOrEqualTo('view_balance', 1);
        $ptcFoundView = $this->mPTCTbl->select($ptcViewWh);

        $rewardsByTimer = [15 => 10,30 => 15,60 => 20,90 => 25];

        $totalReward = 0;

        if(count($ptcFoundView) > 0) {
            foreach($ptcFoundView as $ptc) {
                switch($ptc->occur) {
                    case 'DAY':
                        $dayWh = new Where();
                        $dayWh->equalTo('user_idfs', $me->User_ID);
                        $dayWh->greaterThanOrEqualTo('date_completed', date('Y-m-d H:i:s', strtotime('-24 hours')));
                        $viewToday = $this->mPTCViewTbl->select($dayWh);
                        if(count($viewToday) == 0) {
                            $totalReward+=$rewardsByTimer[$ptc->timer];
                            $viewPTCAds[] = (object)[
                                'id' => $ptc->PTC_ID,
                                'title' => utf8_decode($ptc->title),
                                'description' => utf8_decode( $ptc->description),
                                'redirect' => $ptc->redirect,
                                'nsfw' => $ptc->nsfw_warn,
                                'reward' => $rewardsByTimer[$ptc->timer],
                                'timer' => $ptc->timer,
                                'url' => $ptc->url,
                            ];
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        $ptcById = [];
        $allPTC = $this->mPTCTbl->select();
        foreach($allPTC as $ptc) {
            $ptcById[$ptc->PTC_ID] = $ptc;
        }

        # User PTC History
        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $pageSize = 10;
        # Compile history
        $history = [];
        $historySel = new Select($this->mPTCViewTbl->getTable());
        $historySel->where(['user_idfs' => $me->User_ID]);
        $historySel->order('date_started DESC');
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $historySel,
            # the adapter to run it against
            $this->mPTCViewTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $offersPaginated = new Paginator($oPaginatorAdapter);
        $offersPaginated->setCurrentPageNumber($page);
        $offersPaginated->setItemCountPerPage($pageSize);
        foreach($offersPaginated as $offer) {
            $history[] = (object)[
                'date' => $offer->date_completed,
                'reward' => $rewardsByTimer[$ptcById[$offer->ptc_idfs]->timer],
                'url' => $ptcById[$offer->ptc_idfs]->url,
                'title' => $ptcById[$offer->ptc_idfs]->title,
            ];
        }
        $totalHistory = $this->mPTCViewTbl->select(['user_idfs' => $me->User_ID])->count();

        return (object)[
            'ptc_my' => $myPTCAds,
            'ptc' => $viewPTCAds,
            'reward_total' => $totalReward,
            'total_items' => count($viewPTCAds),
            'total_views' => $totalViews,
            'ads_active' => $activeAds,
            'ads_finished' => $finishedAds,
            'history' => [
                'history' => $history,
                'total_items' => $totalHistory,
                'page' => $page,
                'page_count' => (round($totalHistory/$pageSize) > 0) ? round($totalHistory/$pageSize) : 1,
            ]
        ];
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        $ptcFound = $this->mPTCTbl->select(['PTC_ID' => $id]);
        if(count($ptcFound) == 0) {
            return new ApiProblem(404, 'PTC Ad not found');
        }
        $ptcInfo = $ptcFound->current();
        if($ptcInfo->view_balance <= 0) {
            return new ApiProblem(400, 'PTC is not available at the moment');
        }

        if($ptcInfo->verified != 1) {
            return new ApiProblem(400, 'PTC is not available at the moment');
        }

        $dayWh = new Where();
        $dayWh->equalTo('user_idfs', $me->User_ID);
        $dayWh->greaterThanOrEqualTo('date_started', date('Y-m-d H:i:s', strtotime('-24 hours')));
        $dayWh->isNull('date_completed');
        $openView = $this->mPTCViewTbl->select($dayWh);

        if(count($openView) == 0) {
            return new ApiProblem(400, 'You have no open view for this ptc');
        }
        $openView = $openView->current();

        $this->mPTCViewTbl->update([
            'date_completed' => date('Y-m-d H:i:s', time()),
        ],[
            'ptc_idfs' => $openView->ptc_idfs,
            'user_idfs' => $openView->user_idfs,
            'date_completed' => null,
        ]);

        $this->mPTCTbl->update([
            'view_balance' => $ptcInfo->view_balance-1
        ],['PTC_ID' => $ptcInfo->PTC_ID]);

        $rewardsByTimer = [15 => 10,30 => 15,60 => 20,90 => 25];

        $reward = (int)$rewardsByTimer[$ptcInfo->timer];

        if($reward > 0) {
            $newBalance = $this->mTransaction->executeTransaction($reward, false, $me->User_ID, $ptcInfo->PTC_ID, 'ptc-view', 'Viewed Ad '.$ptcInfo->title.' for '.$ptcInfo->timer.' seconds', 1);
            if($newBalance !== false) {
                return [
                    'token_balance' => $newBalance,
                    'state' => 'done'
                ];
            } else {
                return new ApiProblem(500, 'Transaction error');
            }
        }

        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
