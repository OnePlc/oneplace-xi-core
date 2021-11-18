<?php
namespace Faucet\V1\Rpc\Item;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\InventoryHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class ItemController extends AbstractActionController
{
    /**
     * Item Table
     *
     * @var TableGateway $mItemTbl
     * @since 1.0.0
     */
    protected $mItemTbl;

    /**
     * Item User Table
     *
     * @var TableGateway $mItemUserTbl
     * @since 1.0.0
     */
    protected $mItemUserTbl;

    /**
     * User Buff Table
     *
     * @var TableGateway $mUserBuffTbl
     * @since 1.0.0
     */
    protected $mUserBuffTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Inventory Helper
     *
     * @var InventoryHelper $mInventory
     * @since 1.0.0
     */
    protected $mInventory;

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
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mItemTbl = new TableGateway('faucet_item', $mapper);
        $this->mItemUserTbl = new TableGateway('faucet_item_user', $mapper);
        $this->mUserBuffTbl = new TableGateway('user_buff', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mBagTbl = new TableGateway('user_bag', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mInventory = new InventoryHelper($mapper);
    }

    /**
     * Use an Item for current User
     *
     * @since 1.0.0
     */
    public function itemAction()
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
            $userInventory = $this->mInventory->getInventory($me->User_ID);
            return [
                'inventory' => $userInventory,
                'inventory_bags' => $this->mInventory->getUserBags($me->User_ID),
                'inventory_slots' => $this->mInventory->getInventorySlots($me->User_ID),
                'inventory_slots_used' => count($userInventory),
            ];
        }

        if($request->isPost()) {
            $json = IndexController::loadJSONFromRequestBody(['item_id'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }
            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$json->amount]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Item Use',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
            }
            $itemId = filter_var($json->item_id, FILTER_SANITIZE_NUMBER_INT);

            # check if item is valid and still exists
            $item = $this->mItemTbl->select(['Item_ID' => $itemId]);
            if(count($item) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'item not found'));
            }
            $item = $item->current();

            # check if user really has this item
            $userItem = $this->mItemUserTbl->select(['item_idfs' => $itemId,'user_idfs' => $me->User_ID,'used' => 0,'amount' => 1]);
            if(count($userItem) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'you do not own this item'));
            }
            $userItem = $userItem->current();

            $bUse = true;

            # enable buff
            switch($item->buff_type) {
                case 'daily_withdrawal':
                    for($i = 0;$i < $item->buff_timer;$i++) {
                        $this->mUserBuffTbl->insert([
                            'user_idfs' => $me->User_ID,
                            'source_idfs' => $itemId,
                            'source_type' => 'item',
                            'date' => date('Y-m-d H:i:s', time()+($i*86400)),
                            'expires' => date('Y-m-d H:i:s', time()+($i*86400)),
                            'buff_type' => 'daily-withdraw-buff',
                            'buff' => $item->buff
                        ]);
                    }

                    # use item
                    $this->mItemUserTbl->update([
                        'used' => 1,
                        'date_used' => date('Y-m-d H:i:s', time()),
                    ],[
                        'item_idfs' => $itemId,
                        'user_idfs' => $me->User_ID,
                        'hash' => $userItem->hash,
                    ]);

                    $userInventory =$this->mInventory->getInventory($me->User_ID);

                    return [
                        'state' => 'success',
                        'inventory' => $userInventory,
                        'inventory_bags' => $this->mInventory->getUserBags($me->User_ID),
                        'inventory_slots' => $this->mInventory->getInventorySlots($me->User_ID),
                        'inventory_slots_used' => count($userInventory),
                        'message' => 'Buff '.$item->label.' activated for '.$item->buff_timer.' Days! ',
                    ];
                case 'inventory':
                    $userBags = $this->mBagTbl->select(['user_idfs' => $me->User_ID])->count();
                    if($userBags < 4) {
                        $this->mBagTbl->insert([
                            'item_idfs' => $itemId,
                            'user_idfs' => $me->User_ID,
                            'slot' => $userBags
                        ]);

                        # use item
                        $this->mItemUserTbl->update([
                            'amount' => 0,
                            'used' => 1,
                            'date_used' => date('Y-m-d H:i:s', time()),
                        ],[
                            'item_idfs' => $itemId,
                            'user_idfs' => $me->User_ID,
                            'hash' => $userItem->hash,
                        ]);

                        $userInventory =$this->mInventory->getInventory($me->User_ID);

                        return [
                            'state' => 'success',
                            'inventory' => $userInventory,
                            'inventory_bags' => $this->mInventory->getUserBags($me->User_ID),
                            'inventory_slots' => $this->mInventory->getInventorySlots($me->User_ID),
                            'inventory_slots_used' => count($userInventory),
                            'message' => 'New Bag successfully equipped.',
                        ];
                    } else {
                        $bUse = false;
                        return new ApiProblemResponse(new ApiProblem(400, 'You already have 4 Bags equiped. Remove a bag first before equiping this one.'));
                    }
                default:
                    break;
            }
        }

        /**
         * Replace Bag
         */
        if($request->isPut()) {
            $json = IndexController::loadJSONFromRequestBody(['item_id'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }

            $invHash = filter_var($json->id, FILTER_SANITIZE_STRING);
            $bagSlotId = filter_var($json->slot, FILTER_SANITIZE_NUMBER_INT);
            $itemId = filter_var($json->item_id, FILTER_SANITIZE_NUMBER_INT);

            # get item from inventory
            $bagFound = $this->mItemUserTbl->select(['item_idfs' => $itemId, 'hash' => $invHash,'user_idfs' => $me->User_ID,'used' => 0]);
            if($bagFound->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'bag not found in your inventory'));
            }
            $bagFound = $bagFound->current();

            # get bag slot
            $bagSlot = $this->mBagTbl->select(['user_idfs' => $me->User_ID, 'slot' => $bagSlotId]);
            if($bagSlot->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'equiped bag slot not found'));
            }
            $bagSlot = $bagSlot->current();

            # use new bag
            $this->mItemUserTbl->update([
                'used' => 1,
            ],[
                'user_idfs' => $bagFound->user_idfs,
                'item_idfs' => $bagFound->item_idfs,
                'hash' => $bagFound->hash
            ]);

            # we currently destroy bags
            # put old bag in inventory
            /**
            $oldBag = $this->mItemUserTbl->select(['item_idfs' => $bagSlot->item_idfs, 'user_idfs' => $me->User_ID,'used' => 1]);
            if($oldBag->count() > 0) {
                $oldBag = $oldBag->current();
                $this->mItemUserTbl->update([
                    'used' => 0,
                ],[
                    'user_idfs' => $oldBag->user_idfs,
                    'item_idfs' => $oldBag->item_idfs,
                    'hash' => $oldBag->hash
                ]);
            } **/

            # equip new bag
            $this->mBagTbl->update(['item_idfs' => $itemId],['user_idfs' => $me->User_ID, 'slot' => $bagSlot->slot]);
            $userInventory = $this->mInventory->getInventory($me->User_ID);
            return [
                'inventory' => $userInventory,
                'inventory_bags' => $this->mInventory->getUserBags($me->User_ID),
                'inventory_slots' => $this->mInventory->getInventorySlots($me->User_ID),
                'inventory_slots_used' => count($userInventory),
            ];
        }

        /**
         * Remove Item from Inventory
         **/
        if($request->isDelete()) {
            $itemHash = filter_var($_REQUEST['hash'], FILTER_SANITIZE_STRING);

            $slotFound = $this->mItemUserTbl->select(['user_idfs' => $me->User_ID, 'hash' => $itemHash]);
            if($slotFound->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'Item not found in your inventory'));
            }
            $slotFound = $slotFound->current();
            $this->mItemUserTbl->delete(['user_idfs' => $me->User_ID, 'hash' => $slotFound->hash]);

            $userInventory = $this->mInventory->getInventory($me->User_ID);
            return [
                'inventory' => $userInventory,
                'inventory_bags' => $this->mInventory->getUserBags($me->User_ID),
                'inventory_slots' => $this->mInventory->getInventorySlots($me->User_ID),
                'inventory_slots_used' => count($userInventory),
            ];
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
