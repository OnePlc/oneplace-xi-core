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
                        'icon' => $itemInfo->icon,
                        'rarity' => $itemInfo->level,
                        'description' => $itemInfo->description
                    ];
                }
            }
        }

        return $inventory;
    }
}