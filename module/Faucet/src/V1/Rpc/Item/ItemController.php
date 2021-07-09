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
            $userItem = $this->mItemUserTbl->select(['item_idfs' => $itemId,'user_idfs' => $me->User_ID,'used' => 0]);
            if(count($userItem) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'you do not own this item'));
            }
            $userItem = $userItem->current();

            # use item
            $this->mItemUserTbl->update([
                'used' => 1,
                'date_used' => date('Y-m-d H:i:s', time()),
            ],[
                'item_idfs' => $itemId,
                'user_idfs' => $me->User_ID,
                'date_created' => $userItem->date_created,
                'date_received' => $userItem->date_received
            ]);

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

                    return [
                        'state' => 'success',
                        'inventory' => $this->mInventory->getInventory($me->User_ID),
                        'message' => 'Buff '.$item->label.' activated for '.$item->buff_timer.' Days! ',
                    ];
                default:
                    break;
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
