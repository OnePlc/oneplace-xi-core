<?php
namespace User\V1\Rpc\Friends;

use Application\Controller\IndexController;
use Faucet\Tools\ApiTools;
use Faucet\Tools\EmailTools;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class FriendsController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Transaction Table
     *
     * @var TableGateway $mTransTbl
     * @since 1.0.0
     */
    protected $mTransTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Friends Table
     *
     * @var TableGateway $mFriendTbl
     * @since 1.0.0
     */
    protected $mFriendTbl;

    /**
     * Api Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * E-Mail Helper
     *
     * @var EmailTools $mMailTools
     * @since 1.0.0
     */
    protected $mMailTools;

    /**
     * Constructor
     *
     * ConfirmController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper, $viewRenderer)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mFriendTbl = new TableGateway('user_friend', $mapper);
        $this->mTransTbl = new TableGateway('faucet_transaction', $mapper);
        $this->mApiTools = new ApiTools($mapper);
        $this->mMailTools = new EmailTools($mapper, $viewRenderer);
        $this->mSecTools = new SecurityTools($mapper);
    }

    public function friendsAction()
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

        if($request->isGet()) {
            $myFriends = [];

            $friendsWh = new Where();
            $friendsWh->nest()
                ->equalTo('user_idfs', $me->User_ID)
                ->or->equalTo('friend_idfs', $me->User_ID)
                ->unnest();
            $friendsWh->isNotNull('date_confirmed');
            $friendsDB = $this->mFriendTbl->select($friendsWh);
            foreach($friendsDB as $friend) {
                $friendId = $friend->friend_idfs;
                if($friend->friend_idfs == $me->User_ID) {
                    $friendId = $friend->user_idfs;
                }
                $friendUser = $this->mUserTbl->select(['User_ID' => $friendId]);
                if(count($friendUser) > 0) {
                    $friendUser = $friendUser->current();
                    $lastSel = new Select($this->mTransTbl->getTable());
                    $lastSel->where(['user_idfs' => $friendId,'is_output' => 0]);
                    $lastSel->order('date DESC');
                    $lastSel->limit(1);
                    $lastTrans = $this->mTransTbl->selectWith($lastSel);
                    $lastAction = '-';
                    if(count($lastTrans) > 0) {
                        switch($lastTrans->current()->ref_type) {
                            case 'shortlink-complete':
                            case 'shortlink':
                                $lastAction = 'Doing Shortlinks';
                                break;
                            case 'web-faucet':
                                $lastAction = 'Claimed Faucet';
                                break;
                            case 'dailytask-claim':
                                $lastAction = 'Completed Daily Task';
                                break;
                            default:
                                break;
                        }
                    }
                    $myFriends[] = (object)[
                        'id' => $friendUser->User_ID,
                        'name' => $friendUser->username,
                        'avatar' => ($friendUser->avatar != '') ? $friendUser->avatar : $friendUser->username,
                        'activity' => $lastAction,
                        'state' => 'online'
                    ];
                }
            }

            $myRequests = [];
            $friendsWh = new Where();
            $friendsWh->equalTo('user_idfs', $me->User_ID);
            $friendsWh->isNull('date_confirmed');
            $friendsWh->isNull('date_declined');
            $friendsDB = $this->mFriendTbl->select($friendsWh);
            foreach($friendsDB as $friend) {
                $friendId = $friend->friend_idfs;
                if($friend->friend_idfs == $me->User_ID) {
                    $friendId = $friend->user_idfs;
                }
                $friendUser = $this->mUserTbl->select(['User_ID' => $friendId]);
                if(count($friendUser) > 0) {
                    $friendUser = $friendUser->current();
                    $myRequests[] = (object)[
                        'id' => $friendUser->User_ID,
                        'avatar' => ($friendUser->avatar != '') ? $friendUser->avatar : $friendUser->username,
                        'name' => $friendUser->username,
                    ];
                }
            }

            $openRequests = [];
            $friendsWh = new Where();
            $friendsWh->equalTo('friend_idfs', $me->User_ID);
            $friendsWh->isNull('date_confirmed');
            $friendsWh->isNull('date_declined');
            $friendsDB = $this->mFriendTbl->select($friendsWh);
            foreach($friendsDB as $friend) {
                $friendId = $friend->user_idfs;
                $friendUser = $this->mUserTbl->select(['User_ID' => $friendId]);
                if(count($friendUser) > 0) {
                    $friendUser = $friendUser->current();
                    $openRequests[] = (object)[
                        'id' => $friendUser->User_ID,
                        'avatar' => ($friendUser->avatar != '') ? $friendUser->avatar : $friendUser->username,
                        'name' => $friendUser->username,
                    ];
                }
            }

            return [
                'friends' => $myFriends,
                'my_requests' => $myRequests,
                'open_requests' => $openRequests,
                'friendlist' => (object)[
                    'online' => count($myFriends),
                    'total' => count($myFriends)
                ],
            ];
        }

        if($request->isPost()) {
            $json = IndexController::loadJSONFromRequestBody(['user_tag'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }

            $friendTag = filter_var($json->user_tag, FILTER_SANITIZE_STRING);

            $friendFound = $this->mUserTbl->select(['friend_tag' => $friendTag]);
            if(count($friendFound) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'User not found'));
            }
            $friendFound = $friendFound->current();
            if($friendFound->User_ID == $me->User_ID) {
                return new ApiProblemResponse(new ApiProblem(400, 'You cannot add yourself as a friend.'));
            }

            $friendCheck = $this->mFriendTbl->select([
                'user_idfs' => $me->User_ID,
                'friend_idfs' => $friendFound->User_ID
            ]);
            $friendCheckReverse = $this->mFriendTbl->select([
                'friend_idfs' => $me->User_ID,
                'user_idfs' => $friendFound->User_ID
            ]);

            if(count($friendCheckReverse) == 0 && count($friendCheck) == 0) {
                $this->mFriendTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'friend_idfs' => $friendFound->User_ID,
                    'date_requested' => date('Y-m-d H:i:s', time()),
                ]);

                return [
                    'state' => 'done'
                ];
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'You already have an open request'));
            }
        }

        if($request->isPut()) {
            $json = IndexController::loadJSONFromRequestBody(['friend_id','accept'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }

            $friendId = filter_var($json->friend_id, FILTER_SANITIZE_NUMBER_INT);
            $accept = filter_var($json->accept, FILTER_SANITIZE_NUMBER_INT);
            if($accept != 0 && $accept != 1) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }

            $friendCheck = $this->mFriendTbl->select([
                'friend_idfs' => $me->User_ID,
                'user_idfs' => $friendId
            ]);

            if(count($friendCheck) == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'No matching friend request found'));
            }
            if($accept == 1) {
                $this->mFriendTbl->update([
                    'date_confirmed' => date('Y-m-d H:i:s', time())
                ],[
                    'friend_idfs' => $me->User_ID,
                    'user_idfs' => $friendId
                ]);
            } else {
                $this->mFriendTbl->update([
                    'date_declined' => date('Y-m-d H:i:s', time())
                ],[
                    'friend_idfs' => $me->User_ID,
                    'user_idfs' => $friendId
                ]);
            }

            return [
                'state' => 'done'
            ];
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
