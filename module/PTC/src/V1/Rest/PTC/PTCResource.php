<?php
namespace PTC\V1\Rest\PTC;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;

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
     * Constructor
     *
     * PTCResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
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

        # basic check for ad title blacklist and such

        # check for existing ads

        # check balance

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

        return new ViewModel([
            'credit_balance' => 1000,
        ]);
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

        $myPTCAds = [];
        $ptcFound = $this->mPTCTbl->select(['created_by' => $me->User_ID]);
        if(count($ptcFound) > 0) {
            foreach($ptcFound as $ptc) {
                $myPTCAds[] = (object)[
                    'id' => $ptc->PTC_ID,
                    'title' => utf8_decode($ptc->title),
                    'description' => utf8_decode( $ptc->description),
                    'views' => $ptc->view_balance,
                    'redirect' => $ptc->redirect,
                    'nsfw' => $ptc->nsfw_warn,
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

        if(count($ptcFoundView) > 0) {
            foreach($ptcFoundView as $ptc) {
                switch($ptc->occur) {
                    case 'DAY':
                        $dayWh = new Where();
                        $dayWh->equalTo('user_idfs', $me->User_ID);
                        $dayWh->greaterThanOrEqualTo('date_completed', date('Y-m-d H:i:s', strtotime('-24 hours')));
                        $viewToday = $this->mPTCViewTbl->select($dayWh);
                        if(count($viewToday) == 0) {
                            $viewPTCAds[] = (object)[
                                'id' => $ptc->PTC_ID,
                                'title' => utf8_decode($ptc->title),
                                'description' => utf8_decode( $ptc->description),
                                'redirect' => $ptc->redirect,
                                'nsfw' => $ptc->nsfw_warn,
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

        return (object)[
            'ptc_my' => $myPTCAds,
            'ptc' => $viewPTCAds,
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
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
