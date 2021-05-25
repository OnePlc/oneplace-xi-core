<?php
/**
 * TicketController.php - Support Ticket Controller
 *
 * Main Controller for Support Ticket Handling
 * - Get Information
 * - Send Reply
 *
 * @category Controller
 * @package Support
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Support\V1\Rpc\Ticket;

use Application\Controller\IndexController;
use Faucet\Tools\EmailTools;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class TicketController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Support Request Table
     *
     * @var TableGateway $mSupportTbl
     * @since 1.0.0
     */
    protected $mSupportTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * User Basic Tools
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * E-Mail Tools
     *
     * @var EmailTools $mEmailTools
     * @since 1.0.0
     */
    protected $mEmailTools;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Constructor
     *
     * SupportController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper, $viewRender)
    {
        # Init Tables for this API
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mSupportTbl = new TableGateway('user_request', $mapper);
        $this->mUserTools = new UserTools($mapper);
        $this->mEmailTools = new EmailTools($mapper, $viewRender);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
    }

    /**
     * Support Ticket Main Function
     * - Get Ticket Info (GET)
     * - Send Ticket Reply (POST)
     *
     * @return array|ApiProblemResponse
     * @since 1.0.0
     */
    public function ticketAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # this is only for emplyoees ...
        if((int)$me->is_employee !== 1) {
            return new ApiProblemResponse(new ApiProblem(418, 'Nice try - but no coffee for you.'));
        }

        $request = $this->getRequest();

        # get data from body
        $json = IndexController::loadJSONFromRequestBody(['ticket_id'],$this->getRequest()->getContent());
        # check for attack vendors
        $secResult = $this->mSecTools->basicInputCheck([$json->ticket_id]);
        if($secResult !== 'ok') {
            # ban user and force logout on client
            $this->mUserSetTbl->insert([
                'user_idfs' => $me->User_ID,
                'setting_name' => 'user-tempban',
                'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' on Ticket Get Info',
            ]);
            return new ApiProblemResponse(new ApiProblem(418, 'Potential XSS Attack - Goodbye'));
        }

        # get ticket info
        $ticketId = filter_var($json->ticket_id, FILTER_SANITIZE_NUMBER_INT);
        $ticket = $this->mSupportTbl->select(['Request_ID' => $ticketId]);
        if(count($ticket) == 0) {
            return new ApiProblemResponse(new ApiProblem(404, 'Ticket not found'));
        }
        $ticket = $ticket->current();
        $member = $this->mUserTbl->select(['User_ID' => $ticket->user_idfs]);
        if(count($member) == 0) {
            return new ApiProblemResponse(new ApiProblem(404, 'User not found'));
        }
        $member = $member->current();

        $moderator = (object)[];
        if($ticket->reply_user_idfs != 0) {
            $moderatorDB = $this->mUserTbl->select(['User_ID' => $ticket->reply_user_idfs]);
            if(count($moderatorDB) > 0) {
                $moderatorDB = $moderatorDB->current();
                $moderator = (object)[
                    'id' => $moderatorDB->User_ID,
                    'name' => $moderatorDB->username
                ];
            }
        }

        /**
         * Get Ticket Info (POST)
         */
        if($request->isPost()) {
            return [
                'ticket' => (object)[
                    'id' => $ticket->Request_ID,
                    'message' => $ticket->message,
                    'reply' => $ticket->reply,
                    'replied' => ($ticket->reply != "") ? true : false,
                    'user' => [
                        'id' => $member->User_ID,
                        'name' => $member->username,
                        'token_balance' => $member->token_balance,
                        'verified' => (boolean)$member->email_verified
                    ],
                    'moderator' => $moderator,
                    'date'=> $ticket->date,
                ]
            ];
        }

        /**
         * Send Ticket Reply (PUT)
         */
        if($request->isPut()) {
            if($ticket->reply != '' || $ticket->reply_user_idfs != 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Ticket is already done!'));
            }
            if($ticket->user_idfs == $me->User_ID) {
                return new ApiProblemResponse(new ApiProblem(400, 'You cannot reply to your own tickets...please...dont do that, its stupid.'));
            }
            # get data from body
            $replyInfo = IndexController::loadJSONFromRequestBody(['message'],$this->getRequest()->getContent());
            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$replyInfo->message]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' on Ticket Reply',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential XSS Attack - Goodbye'));
            }
            $replyMessage = filter_var($replyInfo->message, FILTER_SANITIZE_STRING);
            if(strlen($replyMessage) < 50) {
                return new ApiProblemResponse(new ApiProblem(400, 'Please do not give such short answers'));
            }

            # double check ticket id
            if($ticketId <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid ticket id'));
            }

            # save reply to database
            $this->mSupportTbl->update([
                'reply' => $replyMessage,
                'reply_user_idfs' => $me->User_ID,
                'date_reply' => date('Y-m-d H:i:s', time()),
                'state' => 'done',
            ],[
                'Request_ID' => $ticketId,
            ]);

            # send e-mail
            $emailSent = $this->mEmailTools->sendMail('ticket_reply', [
                'reply' => $replyMessage,
                'moderator' => $me->username,
                'username' => $member->username,
                'ticketId' => $ticketId,
                'footerInfo' => 'Swissfaucet.io - Your #1 Crypto Community',
                'sEmailTitle' => 'Reply to Support Ticket #'.$ticketId
            ], $this->mEmailTools->getAdminEmail(), $member->email, 'Reply to Support Ticket #'.$ticketId);

            # add xp for moderator
            $newLevel = $this->mUserTools->addXP('support-ticket-reply', $me->User_ID);
            if($newLevel !== false) {
                $me->xp_level = $newLevel['xp_level'];
                $me->xp_current = $newLevel['xp_current'];
                $me->xp_percent = $newLevel['xp_percent'];
            }

            # payment for moderator
            $newBalance = $this->mTransaction->executeTransaction(100, false, $me->User_ID, $ticketId, 'ticket-reply', substr($replyMessage,0,100));
            if($newBalance !== false) {
                $me->token_balance = $newBalance;
            }

            # api response
            return [
                'me' => (object)[
                    'xp_level' => $me->xp_level,
                    'xp_percent' => $me->xp_percent,
                    'token_balance' => $me->token_balance,
                    'crypto_balance' => $this->mTransaction->getCryptoBalance($me->token_balance, $me),
                ],
                'ticket' => (object)[
                    'id' => $ticket->Request_ID,
                    'message' => $ticket->message,
                    'reply' => $replyMessage,
                    'user' => [
                        'id' => $ticket->User_ID,
                        'name' => $member->username,
                    ],
                    'date'=> $ticket->date,
                ]
            ];
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
