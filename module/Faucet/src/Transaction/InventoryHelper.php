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
    }

    /**
     * Get User Inventory
     *
     * @return array
     * @since 1.0.0
     */
    public function getInventory($userId) {
        $inventory = [];
        $userItems = $this->mItemUserTbl->select([
            'user_idfs' => $userId,
            'used' => 0,
        ]);
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
}