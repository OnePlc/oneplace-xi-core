<?php
namespace Guild\V1\Rpc\Chat;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class ChatController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Guild Chat Ban Table
     *
     * @var TableGateway $mGuildChatBanTbl
     * @since 1.0.0
     */
    protected $mGuildChatBanTbl;

    /**
     * Guild Chat Report Table
     *
     * @var TableGateway $mGuildChatRepTbl
     * @since 1.0.0
     */
    protected $mGuildChatRepTbl;

    /**
     * Constructor
     *
     * BankController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mGuildChatBanTbl = new TableGateway('faucet_guild_chat_ban', $mapper);
        $this->mGuildChatRepTbl = new TableGateway('faucet_guild_chat_report', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    public function chatAction()
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
            $myChatBans = [];
            $myBansFound = $this->mGuildChatBanTbl->select(['user_idfs' => $me->User_ID]);
            if(count($myBansFound) > 0) {
                foreach($myBansFound as $ban) {
                    $banUser = $this->mUserTbl->select(['User_ID' => (int)$ban->ban_user_idfs]);
                    if(count($banUser) > 0) {
                        $banUser = $banUser->current();
                        $myChatBans[] = (object)[
                            'id' => $banUser->User_ID,
                            'name' => $banUser->username,
                            'date' => $ban->date,
                        ];
                    }
                }
            }

            return [
                'ban_list' => $myChatBans,
            ];
        }

        if($request->isPut()) {
            $json = IndexController::loadJSONFromRequestBody(['user_id','action'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }

            $action = filter_var($json->action, FILTER_SANITIZE_STRING);
            if($action != 'ban' && $action != 'unban' && $action != 'report' && $action != 'minify') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Action'));
            }
            $userId = filter_var($json->user_id, FILTER_SANITIZE_NUMBER_INT);
            if($userId <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid User'));
            }

            if($action == 'ban') {
                $banCheck = $this->mGuildChatBanTbl->select([
                    'user_idfs' => $me->User_ID,
                    'ban_user_idfs' => $userId,
                ]);
                if(count($banCheck) == 0) {
                    $this->mGuildChatBanTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'ban_user_idfs' => $userId,
                        'date' => date('Y-m-d H:i:s', time()),
                        'comment' => 'Guildchat Mute'
                    ]);
                }
            }
            if($action == 'unban' && $userId != 0 && $me->User_ID != 0) {
                if($me->User_ID != 0) {
                    $banCheck = $this->mGuildChatBanTbl->delete([
                        'user_idfs' => $me->User_ID,
                        'ban_user_idfs' => $userId,
                    ]);
                }
            }

            if($action == 'minify') {
                $isMinified = ($me->chat_minified == 0) ? 1 : 0;
                if($me->User_ID != 0) {
                    $this->mUserTbl->update([
                        'chat_minified' => $isMinified
                    ],['User_ID' => $me->User_ID]);
                }
            }

            if($action == 'report') {
                $messageId = $userId;

                $json = IndexController::loadJSONFromRequestBody(['reason','comment'],$this->getRequest()->getContent());
                if(!$json) {
                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
                }

                $reason = filter_var($json->reason, FILTER_SANITIZE_STRING);
                $comment = filter_var($json->comment, FILTER_SANITIZE_STRING);

                if($reason != 'spam' && $reason != 'harassment' && $reason != 'nudity' && $reason != 'hate') {
                    return new ApiProblemResponse(new ApiProblem(400, 'Invalid Report Reason'));
                }

                $repCheck = $this->mGuildChatRepTbl->select(['user_idfs' => $me->User_ID,'message_idfs' => $messageId]);
                if(count($repCheck) == 0) {
                    $this->mGuildChatRepTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'message_idfs' => $messageId,
                        'date' => date('Y-m-d H:i:s', time()),
                        'reason' => $reason,
                        'comment' => $comment
                    ]);
                }

                return [
                    'state' => 'done'
                ];
            }

            $myChatBans = [];
            $myChatBansMng = [];
            $myBansFound = $this->mGuildChatBanTbl->select(['user_idfs' => $me->User_ID]);
            if(count($myBansFound) > 0) {
                foreach($myBansFound as $ban) {
                    $myChatBans[] = (int)$ban->ban_user_idfs;
                    $banUser = $this->mUserTbl->select(['User_ID' => (int)$ban->ban_user_idfs]);
                    if(count($banUser) > 0) {
                        $banUser = $banUser->current();
                        $myChatBansMng[] = (object)[
                            'id' => $banUser->User_ID,
                            'name' => $banUser->username,
                            'date' => $ban->date,
                        ];
                    }
                }
            }

            return [
                'ban_list' => $myChatBans,
                'mng_ban_list' => $myChatBansMng,
            ];
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
