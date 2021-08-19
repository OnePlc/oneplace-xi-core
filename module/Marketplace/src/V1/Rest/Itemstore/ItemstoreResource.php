<?php
namespace Marketplace\V1\Rest\Itemstore;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\InventoryHelper;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;

class ItemstoreResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Item Table
     *
     * @var TableGateway $mItemTbl
     * @since 1.0.0
     */
    protected $mItemTbl;

    /**
     * Item Category Table
     *
     * @var TableGateway $mItemCatTbl
     * @since 1.0.0
     */
    protected $mItemCatTbl;

    /**
     * Item User Table
     *
     * @var TableGateway $mItemUserTbl
     * @since 1.0.0
     */
    protected $mItemUserTbl;

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
        $this->mItemUserTbl = new TableGateway('faucet_item_user', $mapper);
        $this->mItemCatTbl = new TableGateway('faucet_item_category', $mapper);

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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $itemId = filter_var($data->item_id, FILTER_SANITIZE_NUMBER_INT);
        $amount = filter_var($data->amount, FILTER_SANITIZE_NUMBER_INT);

        # get item info
        $itemInfo = $this->mItemTbl->select(['Item_ID' => $itemId]);
        if($itemInfo->count() == 0) {
            return new ApiProblem(404, 'Item not found');
        }
        $itemInfo = $itemInfo->current();

        # get item category
        $itemCategory = $this->mItemCatTbl->select(['Category_ID' => $itemInfo->category_idfs]);
        if($itemCategory->count() == 0) {
            return new ApiProblem(404, 'Item Category not found');
        }
        $itemCategory = $itemCategory->current();
        if($itemCategory->in_store == 1) {
            return new ApiProblem(400, 'You cannot buy this item');
        }

        # check if user has enough funds to buy items
        $price = $amount*$itemInfo->price;
        if(!$this->mTransaction->checkUserBalance($price, $user->User_ID)) {
            return new ApiProblem(400, 'You do not have enough Coins to buy '.$amount.'x '.$itemInfo->label);
        }

        # add items to inventory
        $userInventoryUsed = count($this->mInventory->getInventory($user->User_ID));
        $userInventorySlots = $this->mInventory->getInventorySlots($user->User_ID);

        $stacksToUse = round( $amount/$itemInfo->stack_size, 0);
        if($stacksToUse < 1) {
            $stacksToUse = 1;
        }
        if(($userInventoryUsed + $stacksToUse) <=  $userInventorySlots) {
            $amountLeft = $amount-($stacksToUse*$itemInfo->stack_size);
            if($amountLeft < 0) {
                $amountLeft = $amount;
                $stacksToUse = 0;
            }

            for($i = 0;$i < $stacksToUse; $i++) {
                $this->mInventory->addItemToUserInventory($itemId, $itemInfo->stack_size, $user->User_ID, 'Bought from itemstore', 1);
            }
            $this->mInventory->addItemToUserInventory($itemId, $amountLeft, $user->User_ID, 'Bought from itemstore', 1);

            $this->mTransaction->executeTransaction($price, 1, $user->User_ID, $itemId, 'buy-item', 'Bought '.$amount.'x '.$itemInfo->label.' from Store');

            return true;
        } else {
            return new ApiProblem(400, 'You do not have enough space in your inventory to buy '.$amount.'x '.$itemInfo->label);
        }
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function delete($id)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for individual resources');
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
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $categoryName = filter_var($id, FILTER_SANITIZE_STRING);

        $category = $this->mItemCatTbl->select(['url' => $categoryName,'in_store' => 0]);
        if($category->count() == 0) {
            return new ApiProblem(404, 'Category not found');
        }
        $category = $category->current();

        $items = [];
        $categoryItems = $this->mItemTbl->select(['category_idfs' => $category->Category_ID]);
        if($categoryItems->count() > 0) {
            foreach($categoryItems as $item) {
                $items[] = (object)[
                    'id' => $item->Item_ID,
                    'name' => $item->label,
                    'description' => $item->description,
                    'image' => $item->image,
                    'price' => $item->price,
                ];
            }
        }

        return [
            'category' => (object)[
                'id' => $category->Category_ID,
                'name' => $category->label,
            ],
            'item' => $items
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
        return new ApiProblem(405, 'The GET method has not been defined for collections');
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
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
