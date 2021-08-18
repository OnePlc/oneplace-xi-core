<?php
namespace Mailbox\V1\Rest\Inbox;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;

class InboxResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Miner Table
     *
     * @var TableGateway $mItemTbl
     * @since 1.0.0
     */
    protected $mItemTbl;

    /**
     * Miner User Table
     *
     * @var TableGateway $mItemUsrTbl
     * @since 1.0.0
     */
    protected $mItemUsrTbl;

    /**
     * User Inbox Table
     *
     * @var TableGateway $mInboxTbl
     * @since 1.0.0
     */
    protected $mInboxTbl;

    /**
     * User Inbox Attachment Table
     *
     * @var TableGateway $mInboxAttachTbl
     * @since 1.0.0
     */
    protected $mInboxAttachTbl;

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
     * MailboxController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mItemTbl = new TableGateway('faucet_item', $mapper);
        $this->mItemUsrTbl = new TableGateway('faucet_item_user', $mapper);
        $this->mInboxTbl = new TableGateway('user_inbox', $mapper);
        $this->mInboxAttachTbl = new TableGateway('user_inbox_item', $mapper);

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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $messageId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        $message = $this->mInboxTbl->select(['Mail_ID' => $messageId]);
        if(count($message) == 0) {
            return new ApiProblem(404, 'Message not found');
        }
        $message = $message->current();
        // do not tell attackers that the message exists
        if($message->to_idfs != $user->User_ID) {
            return new ApiProblem(404, 'Message not found');
        }

        $this->mInboxTbl->update([
            'is_read' => 1
        ],['Mail_ID' => $messageId]);

        return true;
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
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($user);
        }

        $messageId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        $message = $this->mInboxTbl->select(['Mail_ID' => $messageId]);
        if(count($message) == 0) {
            return new ApiProblem(404, 'Message not found');
        }
        $message = $message->current();
        // do not tell attackers that the message exists
        if($message->to_idfs != $user->User_ID) {
            return new ApiProblem(404, 'Message not found');
        }
        $from = (object)['id' => 0, 'name' => 'Store'];

        $attachments = [];
        $msgAttachments = $this->mInboxAttachTbl->select(['mail_idfs' => $message->Mail_ID,'used' => 0]);
        if(count($msgAttachments) > 0) {
            foreach($msgAttachments as $attach) {
                $attachItem = $this->mItemTbl->select(['Item_ID' => $attach->item_idfs]);
                if(count($attachItem) > 0) {
                    $attachItem = $attachItem->current();
                    $attachments[] = [
                        'id' => $attach->item_idfs,
                        'name' => $attachItem->label.' ( 1 )',
                        'icon' => $attachItem->icon,
                        'rarity' => $attachItem->level,
                    ];
                }
            }
        }

        return [
            'message' => [
                'id' => $message->Mail_ID,
                'subject' => $message->label,
                'message' => $message->message,
                'credits' => $message->credits,
                'date' => $message->date,
                'from' => $from,
            ],
            'attachments' => $attachments,
            'token_balance' => $user->token_balance
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
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($user);
        }

        $inbox = [];
        $msgSel = new Select($this->mInboxTbl->getTable());
        $msgSel->where(['to_idfs' => $user->User_ID, 'is_read' => 0]);
        $msgSel->order('date ASC');
        $unreadMessages = $this->mInboxTbl->selectWith($msgSel);
        foreach($unreadMessages as $msg) {
            $from = (object)['id' => 0, 'name' => 'Store'];
            $attachment = false;
            $msgAttachments = $this->mInboxAttachTbl->select(['mail_idfs' => $msg->Mail_ID]);
            if(count($msgAttachments) > 0) {
                $msgAttachments = $msgAttachments->current();
                $attachItem = $this->mItemTbl->select(['Item_ID' => $msgAttachments->item_idfs]);
                if(count($attachItem) > 0) {
                    $attachItem = $attachItem->current();
                    $attachment = $attachItem->label.' ( 1 )';
                }
            }
            $inbox[] = (object)[
                'id' => $msg->Mail_ID,
                'subject' => $msg->label,
                'message' => $msg->message,
                'credits' => $msg->credits,
                'date' => $msg->date,
                'from' => $from,
                'attachment' => $attachment
            ];
        }

        return $inbox;
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
        if (!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if (get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($user);
        }

        $messageId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        $message = $this->mInboxTbl->select(['Mail_ID' => $messageId]);
        if (count($message) == 0) {
            return new ApiProblem(404, 'Message not found');
        }
        $message = $message->current();
        // do not tell attackers that the message exists
        if ($message->to_idfs != $user->User_ID) {
            return new ApiProblem(404, 'Message not found');
        }

        $attachmentId = filter_var($data->attachment_id, FILTER_SANITIZE_NUMBER_INT);
        $credits = filter_var($data->credits, FILTER_SANITIZE_NUMBER_INT);

        if($attachmentId == 0 && $credits == 1) {
            $this->mTransaction->executeTransaction($message->credits, 0, $user->User_ID, $messageId, 'msg-credit', 'Received Coins from Message '.$message->label);
            $this->mInboxTbl->update(['credits' => 0],['Mail_ID' => $messageId]);

            return true;
        } else {
            $attachment = $this->mInboxAttachTbl->select(['mail_idfs' => $message->Mail_ID, 'used' => 0, 'slot' => $attachmentId]);
            if ($attachment->count() == 0) {
                return new ApiProblem(404, 'Attachment not found or already used');
            }
            $attachment = $attachment->current();
            $attachItem = $this->mItemTbl->select(['Item_ID' => $attachment->item_idfs]);
            if ($attachItem->count() > 0) {
                $attachItem = $attachItem->current();

                $this->mItemUsrTbl->insert([
                    'user_idfs' => $user->User_ID,
                    'item_idfs' => $attachment->item_idfs,
                    'date_created' => date('Y-m-d H:i:s', time()),
                    'date_received' => date('Y-m-d H:i:s', time()),
                    'comment' => $message->message,
                    'hash' => password_hash($attachItem->Item_ID . $user->User_ID . time(), PASSWORD_DEFAULT),
                    'created_by' => $user->User_ID,
                    'received_from' => $message->from_idfs,
                    'amount' => 1,
                    'used' => 0
                ]);

                $this->mInboxAttachTbl->update([
                    'used' => 1
                ], [
                    'mail_idfs' => (int)$attachment->mail_idfs,
                    'item_idfs' => (int)$attachment->item_idfs,
                    'slot' => (int)$attachmentId
                ]);

                return true;
            } else {
                return new ApiProblem(404, 'Attached Item not found');
            }
        }
    }
}
