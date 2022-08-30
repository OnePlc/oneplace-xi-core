<?php
/**
 * SupportController.php - Support Controller
 *
 * Main Controller for User Support Frontend
 *
 * @category Controller
 * @package Support
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Support\V1\Rpc\Support;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Laminas\Session\Container;

class SupportController extends AbstractActionController
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;


    /**
     * Support Request Table
     *
     * @var TableGateway $mSupportTbl
     * @since 1.0.0
     */
    protected $mSupportTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;


    /**
     * Constructor
     *
     * SupportController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        # Init Tables for this API
        $this->mSupportTbl = new TableGateway('user_request', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mSession = new Container('webauth');
        $this->mSecTools = new SecurityTools($mapper);
    }

    private function getUserRequests($me, $page)
    {
        $pageSize = 10;
        $requests = [];
        $reqSel = new Select($this->mSupportTbl->getTable());
        $reqSel->where(['user_idfs' => $me->User_ID]);
        $reqSel->order('date DESC');
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $reqSel,
            # the adapter to run it against
            $this->mSupportTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $requestsPaginated = new Paginator($oPaginatorAdapter);
        $requestsPaginated->setCurrentPageNumber($page);
        $requestsPaginated->setItemCountPerPage($pageSize);
        foreach($requestsPaginated as $req) {
            $requests[] = (object)[
                'id' => $req->Request_ID,
                'message' => strip_tags($req->message),
                'reply' => strip_tags($req->reply),
                'rating' => $req->rating,
                'date' => $req->date,
                'state' => $req->state,
            ];
        }

        return $requests;
    }



    /**
     * User Support Tickets
     *
     * Get Ticket history or submit a
     * new ticket
     *
     * @return ApiProblemResponse|ViewModel
     * @since 1.0.0
     */
    public function supportAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        return new ApiProblemResponse(new ApiProblem(403, 'page support is disabled'));

        $request = $this->getRequest();

        /**
         * User Support History
         */
        if($request->isGet()) {
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $pageSize = 10;
            $requests = $this->getUserRequests($me, $page);
            $totalItems = $this->mSupportTbl->select(['user_idfs' => $me->User_ID])->count();

            # Check if there is already an open request to prevent spam
            $openRequest = $this->mSupportTbl->select([
                'user_idfs' => $me->User_ID,
                'state' => 'new'
            ]);

            return new ViewModel([
                'request' => $requests,
                'page_size' => $pageSize,
                'page' => $page,
                'page_count' => (round($totalItems/$pageSize) > 0) ? round($totalItems/$pageSize) : 1,
                'total_items' => $totalItems,
                'has_open_request' => (count($openRequest) > 0) ? 1 : 0
            ]);
        }

        /**
         * Get Support Request from Client
         */
        if($request->isPut()) {
            $json = IndexController::loadJSONFromRequestBody(['message'],$this->getRequest()->getContent());
            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$json->message]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' on Support Request Form',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential XSS Attack - Goodbye'));
            }
            $message = filter_var($json->message, FILTER_SANITIZE_STRING);
            if(empty($message)) {
                return new ApiProblemResponse(new ApiProblem(400, 'You must provide a valid message'));
            }

            # Check if there is already an open request to prevent spam
            $openRequest = $this->mSupportTbl->select([
                'user_idfs' => $me->User_ID,
                'state' => 'new'
            ]);
            if(count($openRequest) > 0) {
                return new ApiProblemResponse(new ApiProblem(409, 'You already have an open support request. Please wait for an answer before sending another one'));
            }

            # Add Message to Support System
            $this->mSupportTbl->insert([
                'user_idfs' => $me->User_ID,
                'message' => $message,
                'name' => $me->username,
                'date' => date('Y-m-d H:i:s', time()),
                'state' => 'new',
                'reply' => '',
                'reply_user_idfs' => 0,
                'mail_name' => '',
                'rating' => 0
            ]);

            $pageSize = 10;
            $requests = $this->getUserRequests($me, 1);
            $totalItems = $this->mSupportTbl->select(['user_idfs' => $me->User_ID])->count();

            return new ViewModel([
                'request' => $requests,
                'page_size' => $pageSize,
                'has_open_request' => 1,
                'page' => 1,
                'page_count' => (round($totalItems/$pageSize) > 0) ? round($totalItems/$pageSize) : 1,
                'total_items' => $totalItems
            ]);
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
