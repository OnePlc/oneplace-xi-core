<?php
/**
 * InventoryHelper.php - Token Transaction Helper
 *
 * Main Helper for Faucet Token Transactions
 *
 * @category Helper
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\Transaction;

use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class InventoryHelper {

    /**
     * Item Table
     *
     * @var TableGateway $mItemTbl
     * @since 1.0.0
     */
    private $mItemTbl;

    /**
     * User Item Table
     *
     * @var TableGateway $mItemUserTbl
     * @since 1.0.0
     */
    private $mItemUserTbl;

    /**
     * Guild Item Table
     *
     * @var TableGateway $mItemGuildTbl
     * @since 1.0.0
     */
    private $mItemGuildTbl;

    /**
     * User Bag Table
     *
     * @var TableGateway $mBagTbl
     * @since 1.0.0
     */
    private $mBagTbl;

    /**
     * Constructor
     *
     * LoginController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mItemTbl = new TableGateway('faucet_item', $mapper);
        $this->mItemUserTbl = new TableGateway('faucet_item_user', $mapper);
        $this->mItemGuildTbl = new TableGateway('faucet_item_guild', $mapper);
        $this->mBagTbl = new TableGateway('user_bag', $mapper);
    }

    /**
     * Get User Inventory Slots
     */
    public function getInventorySlots($userId) {
        $baseSlots = 6;

        $userBags = $this->mBagTbl->select(['user_idfs' => $userId]);
        if($userBags->count() > 0) {
            foreach($userBags as $bag) {
                $bagInfo = $this->mItemTbl->select(['Item_ID' => $bag->item_idfs]);
                if($bagInfo->count() > 0) {
                    $bagInfo = $bagInfo->current();
                    $baseSlots+=$bagInfo->buff;
                }
            }
        }

        return $baseSlots;
    }

    public function getUserBags($userId) {
        $bags = [];
        $userBags = $this->mBagTbl->select(['user_idfs' => $userId]);
        if($userBags->count() > 0) {
            foreach($userBags as $bag) {
                $bagInfo = $this->mItemTbl->select(['Item_ID' => $bag->item_idfs]);
                if($bagInfo->count() > 0) {
                    $bagInfo = $bagInfo->current();
                    $bags[] = (object)[
                        'id' => $bagInfo->Item_ID,
                        'name' => $bagInfo->label,
                        'slot' => $bag->slot,
                        'icon' => $bagInfo->icon,
                        'image' => $bagInfo->image,
                        'rarity' => $bagInfo->level
                    ];
                }
            }
        }

        return $bags;
    }

    /**
     * Get User Inventory
     *
     * @return array
     * @since 1.0.0
     */
    public function getInventory($userId) {
        $inventory = [];
        $invWh = new Where();
        $invWh->equalTo('user_idfs', $userId);
        $invWh->equalTo('used', 0);
        $invWh->greaterThanOrEqualTo('amount', 1);
        $userItems = $this->mItemUserTbl->select($invWh);
        if (count($userItems) > 0) {
            foreach ($userItems as $userItem) {
                $itemInfo = $this->mItemTbl->select(['Item_ID' => $userItem->item_idfs]);
                if (count($itemInfo) > 0 ) {
                    $itemInfo = $itemInfo->current();
                    $inventory[] = (object)[
                        'id' => $itemInfo->Item_ID,
                        'name' => $itemInfo->label,
                        'date_received' => $userItem->date_received,
                        'used' => $userItem->used,
                        'usable' => ($itemInfo->usable == 1) ? true : false,
                        'amount' => $userItem->amount,
                        'icon' => $itemInfo->icon,
                        'hash' => $userItem->hash,
                        'image' => $itemInfo->image,
                        'buff_type' => $itemInfo->buff_type,
                        'rarity' => $itemInfo->level,
                        'description' => $itemInfo->description
                    ];
                }
            }
        }

        return $inventory;
    }

    /**
     * Get Guild Inventory
     *
     * @return array
     * @since 1.0.0
     */
    public function getGuildInventory($guildId) {
        $inventory = [];
        $userItems = $this->mItemGuildTbl->select([
            'guild_idfs' => $guildId,
            'user_withdraw_idfs' => NULL,
        ]);
        if (count($userItems) > 0) {
            foreach ($userItems as $userItem) {
                $itemInfo = $this->mItemTbl->select(['Item_ID' => $userItem->item_idfs]);
                if (count($itemInfo) > 0 ) {
                    $itemInfo = $itemInfo->current();
                    $inventory[] = (object)[
                        'id' => $itemInfo->Item_ID,
                        'name' => $itemInfo->label,
                        'date_deposited' => $userItem->date_deposited,
                        'icon' => $itemInfo->icon,
                        'rarity' => $itemInfo->level,
                        'description' => $itemInfo->description
                    ];
                }
            }
        }

        return $inventory;
    }

    /**
     * Check if user has a specific item (unused)
     * in his inventory
     *
     * @param $itemId
     * @param $userId
     * @return bool
     * @since 1.0.0
     */
    public function userHasItemActive($itemId, $userId) {
        $userItems = $this->mItemUserTbl->select([
            'user_idfs' => $userId,
            'item_idfs' => $itemId,
            'used' => 0,
        ]);
        if(count($userItems) == 0) {
            return false;
        } else {
            return $userItems->current();
        }
    }

    /**
     * Move Item from User Inventory to Guild Bank
     *
     * @param $itemId
     * @param $userId
     * @param $guildId
     * @return bool
     * @since 1.0.0
     */
    public function depositItemToGuildBank($itemId, $userId, $guildId) {
        $itemFound = $this->userHasItemActive($itemId, $userId);
        if(is_object($itemFound)) {
            $this->mItemUserTbl->update([
                'used' => 1,
            ],[
                'item_idfs' => $itemId,
                'user_idfs' => $userId,
                'used' => 0,
                'hash' => $itemFound->hash
            ]);

            $this->mItemGuildTbl->insert([
                'guild_idfs' => $guildId,
                'user_idfs' => $userId,
                'date_deposited' => date('Y-m-d H:i:s', time()),
                'comment' => $itemFound->comment,
            ]);

            return true;
        } else {
            return false;
        }
    }

    public function addItemToUserInventory($itemId, $amount, $userId, $comment, $fromId) {
        $itemInfo = $this->mItemTbl->select(['Item_ID' => $itemId]);
        if($itemInfo->count() > 0) {
            $itemInfo = $itemInfo->current();

            $slotsUsed = count($this->getInventory($userId));
            $slotsAvailable = $this->getInventorySlots($userId);

            if($amount > $itemInfo->stack_size) {
                $amountLeft = $amount;
                $amoutPerRun = $itemInfo->stack_size;

                while($amountLeft > 0) {
                    if(($slotsUsed+1) > $slotsAvailable) {
                        break;
                    }
                    if($amountLeft < $itemInfo->stack_size) {
                        $amoutPerRun = $amountLeft;
                    }
                    # check if there is already a free slot for this item in user inventory
                    $slotCheck = new Where();
                    $slotCheck->equalTo('item_idfs', $itemId);
                    $slotCheck->equalTo('user_idfs', $userId);
                    $slotCheck->equalTo('used', 0);
                    $slotCheck->lessThan('amount', $amoutPerRun);
                    $slotCheck->greaterThan('amount', 0);

                    $hasFreeSlot = $this->mItemUserTbl->select($slotCheck);

                    if($hasFreeSlot->count() == 0) {
                        $slotsUsed++;
                        $rand = rand(0,1000);
                        $this->mItemUserTbl->insert([
                            'user_idfs' => $userId,
                            'item_idfs' => $itemId,
                            'date_created' => date('Y-m-d H:i:s', time()),
                            'date_received' => date('Y-m-d H:i:s', time()),
                            'comment' => $comment,
                            'hash' => password_hash($itemId . $userId . time().$rand, PASSWORD_DEFAULT),
                            'created_by' => $userId,
                            'received_from' => $fromId,
                            'amount' => $amoutPerRun,
                            'used' => 0
                        ]);

                        $amountLeft = $amountLeft - $amoutPerRun;
                    } else {
                        $slotInfo = $hasFreeSlot->current();
                        $this->mItemUserTbl->update([
                            'amount' => $amoutPerRun
                        ], [
                            'user_idfs' => $slotInfo->user_idfs,
                            'item_idfs' => $slotInfo->item_idfs,
                            'hash' => $slotInfo->hash,
                        ]);
                    }
                }
            } else {
                # check if there is already a free slot for this item in user inventory
                $slotCheck = new Where();
                $slotCheck->equalTo('item_idfs', $itemId);
                $slotCheck->equalTo('user_idfs', $userId);
                $slotCheck->equalTo('used', 0);
                $slotCheck->lessThanOrEqualTo('amount', $itemInfo->stack_size-$amount);
                $slotCheck->greaterThan('amount', 0);

                $hasFreeSlot = $this->mItemUserTbl->select($slotCheck);

                if($hasFreeSlot->count() == 0) {
                    if(($slotsUsed+1) <= $slotsAvailable) {
                        $this->mItemUserTbl->insert([
                            'user_idfs' => $userId,
                            'item_idfs' => $itemId,
                            'date_created' => date('Y-m-d H:i:s', time()),
                            'date_received' => date('Y-m-d H:i:s', time()),
                            'comment' => $comment,
                            'hash' => password_hash($itemId . $userId . time(), PASSWORD_DEFAULT),
                            'created_by' => $userId,
                            'received_from' => $fromId,
                            'amount' => $amount,
                            'used' => 0
                        ]);
                    }
                } else {
                    $slotInfo = $hasFreeSlot->current();
                    $this->mItemUserTbl->update([
                        'amount' => $slotInfo->amount + $amount
                    ], [
                        'user_idfs' => $slotInfo->user_idfs,
                        'item_idfs' => $slotInfo->item_idfs,
                        'hash' => $slotInfo->hash,
                    ]);
                }
            }

            return true;
        } else {
            return false;
        }
    }
}