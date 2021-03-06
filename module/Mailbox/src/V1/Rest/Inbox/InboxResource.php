<?php
namespace Mailbox\V1\Rest\Inbox;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\InventoryHelper;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
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
     * Inventory Helper
     *
     * @var InventoryHelper $mInventory
     * @since 1.0.0
     */
    protected $mInventory;

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
        $this->mInventory = new InventoryHelper($mapper);
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

        if($message->credits > 0) {
            return new ApiProblem(400, 'You have Coins to collect in this message');
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
        if(empty($messageId) || $messageId <= 0) {
            return new ApiProblem(404, 'Invalid message id');
        }
        $msgSel = new Select($this->mInboxTbl->getTable());
        $msgSel->join(['u' => 'user'],'u.User_ID = user_inbox.from_idfs', ['username']);
        $msgSel->where(['Mail_ID' => $messageId]);
        $message = $this->mInboxTbl->selectWith($msgSel);
        if(count($message) == 0) {
            return new ApiProblem(404, 'Message not found');
        }
        $message = $message->current();
        // do not tell attackers that the message exists
        if($message->to_idfs != $user->User_ID) {
            return new ApiProblem(404, 'Message not found');
        }
        $from = (object)['id' => $message->from_idfs, 'name' => $message->username];

        // attachments disabled as there are no more items
        $attachments = [];
        /**
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
                        'image' => $attachItem->image,
                        'slot' => $attach->slot,
                        'amount' => $attach->amount,
                        'rarity' => $attachItem->level,
                    ];
                }
            }
        } **/

        return [
            'message' => [
                'id' => $message->Mail_ID,
                'subject' => $message->label,
                'message' => $message->message,
                'credits' => $message->credits,
                'date' => $message->date,
                'is_read' => $message->is_read,
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
        $msgSel->join(['u' => 'user'],'u.User_ID = user_inbox.from_idfs', ['username']);
        $msgSel->where(['to_idfs' => $user->User_ID, 'is_read' => 0]);
        $msgSel->order('date DESC');
        $unreadMessages = $this->mInboxTbl->selectWith($msgSel);
        foreach($unreadMessages as $msg) {
            $from = (object)['id' => $msg->from_idfs, 'name' => $msg->username];
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

        $credits = filter_var($data->credits, FILTER_SANITIZE_NUMBER_INT);

        if(!isset($data->attachment_id) && $credits == 0) {
            return new ApiProblem(404, 'Attachment not found');
        } else {
            $attachmentId = filter_var($data->attachment_id, FILTER_SANITIZE_NUMBER_INT);
        }

        if($attachmentId == 0 && $credits == 1) {
            $newBalance = $this->mTransaction->executeTransaction($message->credits, 0, $user->User_ID, $messageId, 'msg-credit', 'Received Coins from Message '.$message->label);
            $this->mInboxTbl->update(['credits' => 0],['Mail_ID' => $messageId]);

            $msgSel = new Select($this->mInboxTbl->getTable());
            $msgSel->join(['u' => 'user'],'u.User_ID = user_inbox.from_idfs', ['username']);
            $msgSel->where(['Mail_ID' => $messageId]);
            $message = $this->mInboxTbl->selectWith($msgSel);
            if($message->count() == 0) {
                return new ApiProblem(404, 'message not found');
            }
            $message = $message->current();
            $from = (object)['id' => $message->from_idfs, 'name' => $message->username];

            return [
                'message' => [
                    'id' => $message->Mail_ID,
                    'subject' => $message->label,
                    'message' => $message->message,
                    'credits' => $message->credits,
                    'date' => $message->date,
                    'is_read' => $message->is_read,
                    'from' => $from,
                ],
                'token_balance' => $newBalance
            ];
        } else {
            $attachment = $this->mInboxAttachTbl->select(['mail_idfs' => $message->Mail_ID, 'used' => 0, 'slot' => $attachmentId]);
            if ($attachment->count() == 0) {
                return new ApiProblem(404, 'Attachment not found or already used');
            }
            $attachment = $attachment->current();
            $attachItem = $this->mItemTbl->select(['Item_ID' => $attachment->item_idfs]);
            if ($attachItem->count() > 0) {
                $attachItem = $attachItem->current();

                # check if there is already a free slot for this item in user inventory
                $slotCheck = new Where();
                $slotCheck->equalTo('item_idfs', $attachment->item_idfs);
                $slotCheck->equalTo('user_idfs', $user->User_ID);
                $slotCheck->equalTo('used', 0);
                $slotCheck->lessThanOrEqualTo('amount', $attachItem->stack_size-$attachment->amount);
                $slotCheck->greaterThan('amount', 0);

                $hasSlot = $this->mItemUsrTbl->select($slotCheck);

                $slotInfo = $hasSlot->current();

                if($hasSlot->count() == 0) {
                    $userInventory = $this->mInventory->getInventory($user->User_ID);
                    $slotsUsed = count($userInventory);
                    $slotsTotal = $this->mInventory->getInventorySlots($user->User_ID);
                    if($slotsUsed < $slotsTotal) {
                        $this->mItemUsrTbl->insert([
                            'user_idfs' => $user->User_ID,
                            'item_idfs' => $attachment->item_idfs,
                            'date_created' => date('Y-m-d H:i:s', time()),
                            'date_received' => date('Y-m-d H:i:s', time()),
                            'comment' => $message->message,
                            'hash' => password_hash($attachItem->Item_ID . $user->User_ID . uniqid(), PASSWORD_DEFAULT),
                            'created_by' => $user->User_ID,
                            'received_from' => $message->from_idfs,
                            'amount' => $attachment->amount,
                            'used' => 0
                        ]);
                    } else {
                        return new ApiProblem(404, 'Your inventory is full.');
                    }
                } else {
                    $slotInfo = $hasSlot->current();
                    $this->mItemUsrTbl->update([
                        'amount' => $slotInfo->amount + $attachment->amount
                    ], [
                        'user_idfs' => $slotInfo->user_idfs,
                        'item_idfs' => $slotInfo->item_idfs,
                        'hash' => $slotInfo->hash,
                    ]);
                }

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
