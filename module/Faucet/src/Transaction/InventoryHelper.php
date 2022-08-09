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
     * Constructor
     *
     * LoginController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
    }

    /**
     * Get User Inventory Slots
     */
    public function getInventorySlots($userId) {
        return 0;
    }

    public function getUserBags($userId) {
        return [];
    }

    /**
     * Get User Inventory
     *
     * @return array
     * @since 1.0.0
     */
    public function getInventory($userId) {
        return [];
    }

    /**
     * Get Guild Inventory
     *
     * @return array
     * @since 1.0.0
     */
    public function getGuildInventory($guildId) {
        return [];
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
        return false;
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
        return false;
    }

    public function addItemToUserInventory($itemId, $amount, $userId, $comment, $fromId) {
       return true;
    }
}