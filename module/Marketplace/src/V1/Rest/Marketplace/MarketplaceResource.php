<?php
namespace Marketplace\V1\Rest\Marketplace;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class MarketplaceResource extends AbstractResourceListener
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
     * Marketplace Auction Table
     *
     * @var TableGateway $mAuctionTbl
     * @since 1.0.0
     */
    protected $mAuctionTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

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
        $this->mAuctionTbl = new TableGateway('marketplace_item', $mapper);
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $itemId = filter_var($data->item_id, FILTER_SANITIZE_NUMBER_INT);

        $item = $this->mItemTbl->select(['Item_ID' => $itemId]);
        if($item->count() == 0) {
            return new ApiProblem(404, 'Item not found');
        }
        $itemInfo = $item->current();

        if($itemInfo->category_idfs == 11) {
            return new ApiProblem(404, 'You cannot sell items from tailor store.');
        }

        $itemWh = new Where();
        $itemWh->equalTo('item_idfs', $itemId);
        $itemWh->equalTo('user_idfs', $user->User_ID);
        $itemWh->greaterThanOrEqualTo('amount', 1);
        $itemWh->equalTo('used', 0);
        $itemUser = $this->mItemUserTbl->select($itemWh);
        if($itemUser->count() == 0) {
            return new ApiProblem(404, 'Item not found in your inventory');
        }
        $amountInInventory = 0;
        $myItems = [];
        foreach($itemUser as $slot) {
            $myItems[] = $slot;
            $amountInInventory+=$slot->amount;
        }

        $amount = filter_var($data->amount, FILTER_SANITIZE_NUMBER_INT);
        if($amount > $amountInInventory) {
            return new ApiProblem(404, 'You do not have that many items in your inventory. Choose smaller amount');
        }
        $duration = filter_var($data->duration, FILTER_SANITIZE_NUMBER_INT);
        if($duration != 12 && $duration != 24 && $duration != 48) {
            return new ApiProblem(400, 'Invalid duration');
        }

        $baseFee = $itemInfo->auction_fee;
        if($duration == 24) {
            $baseFee = round($baseFee*1.2,0);
        }
        if($duration == 48) {
            $baseFee = round($baseFee*1.5,0);
        }

        $baseFee = $baseFee * $amount;

        $now = date('Y-m-d H:i:s', time());
        $expire = date('Y-m-d H:i:s', time()+(3600*$duration));

        $price = filter_var($data->price, FILTER_SANITIZE_NUMBER_INT);
        if($price == 0) {
            return new ApiProblem(400, 'Price must be greater than 0');
        }

        if($this->mTransaction->checkUserBalance($baseFee, $user->User_ID)) {
            if($itemInfo->stack_size > 1) {
                # remove items from inventory
                $amountToUse = $amount;
                foreach($myItems as $item) {
                    $amountLeft = $item->amount - $amountToUse;
                    if($amountLeft < 0) {
                        $amountLeft = 0;
                        $amountToUse = $amountToUse - $item->amount;
                    } elseif ($amountLeft == 0) {
                        $amountToUse = $amountToUse - $item->amount;
                    }
                    $this->mItemUserTbl->update([
                        'amount' => $amountLeft,
                    ], [
                        'user_idfs' => $item->user_idfs,
                        'item_idfs' => $item->item_idfs,
                        'hash' => $item->hash
                    ]);

                    if($amountToUse == 0) {
                        break;
                    }
                }
            } else {
                $itemUsed = 0;
                foreach($myItems as $item) {
                    $this->mItemUserTbl->update([
                        'amount' => 0,
                        'used' => 1,
                    ], [
                        'user_idfs' => $item->user_idfs,
                        'item_idfs' => $item->item_idfs,
                        'hash' => $item->hash
                    ]);
                    $itemUsed++;
                    if($itemUsed == $amount) {
                        break;
                    }
                }
            }


            # create auction
            $this->mAuctionTbl->insert([
                'created_by' => $user->User_ID,
                'created_date' => $now,
                'expire_date' => $expire,
                'item_idfs' => $itemId,
                'amount' => $amount,
                'price_per_unit' => $price,
                'category_idfs' => $itemInfo->category_idfs
            ]);

            $auctionId = $this->mAuctionTbl->lastInsertValue;

            $fNewBalance = $this->mTransaction->executeTransaction($baseFee, 1, $user->User_ID, $auctionId, 'create-auction', 'Auction created '.$amount.' x '.$itemInfo->label.' for '.$duration.' hours');
            if($fNewBalance !== false) {
                return true;
            } else {
                return new ApiProblem(400, 'Error during deposit transaction');
            }
        } else {
            return new ApiProblem(400, 'Your balance is too low to pay the deposit of '.number_format($baseFee, 2));
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $auctionId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

        $auction = $this->mAuctionTbl->select(['Auction_ID' => $auctionId]);
        if($auction->count() == 0) {
            return new ApiProblem(404, 'Auction not found');
        }
        $auction = $auction->current();

        if($auction->created_by != $user->User_ID) {
            return new ApiProblem(404, 'Auction not found');
        }

        $amount = $auction->amount;
        $itemId = $auction->item_idfs;

        $itemInfo = $this->mItemTbl->select(['Item_ID' => $itemId]);
        if($itemInfo->count() == 0) {
            return new ApiProblem(404, 'Item not found');
        }
        $itemInfo = $itemInfo->current();

        $this->mAuctionTbl->delete(['Auction_ID' => $auctionId]);

        # create message to buyer inbox
        $this->mInboxTbl->insert([
            'label' => 'MARKETPLACE.CANCELLEDMSG.SUBJECT',
            'message' => 'MARKETPLACE.CANCELLEDMSG.MESSAGE',
            'credits' => 0,
            'from_idfs' => 1,
            'to_idfs' => $user->User_ID,
            'date' => date('Y-m-d H:i:s', time()),
            'is_read' => 0
        ]);
        $messageId = $this->mInboxTbl->lastInsertValue;

        /**
        # add purchased items as attachment
        for($itemSent = 0;$itemSent < $amount;$itemSent++) {
            $this->mInboxAttachTbl->insert([
                'mail_idfs' => $messageId,
                'item_idfs' => $itemId,
                'slot' => $itemSent,
                'used' => 0
            ]);
        } **/


        if($itemInfo->stack_size > 1) {
            $attachmentCount = round($amount/$itemInfo->stack_size);
            if($attachmentCount < 1) {
                $this->mInboxAttachTbl->insert([
                    'mail_idfs' => $messageId,
                    'item_idfs' => $itemId,
                    'slot' => 0,
                    'amount' => $amount,
                    'used' => 0
                ]);
            } else {
                if($itemInfo->stack_size - $amount > 0) {
                    $this->mInboxAttachTbl->insert([
                        'mail_idfs' => $messageId,
                        'item_idfs' => $itemId,
                        'slot' => 0,
                        'amount' => $amount,
                        'used' => 0
                    ]);
                } else {
                    for($itemSent = 0;$itemSent < $attachmentCount;$itemSent++) {
                        $this->mInboxAttachTbl->insert([
                            'mail_idfs' => $messageId,
                            'item_idfs' => $itemId,
                            'slot' => $itemSent,
                            'amount' => $itemInfo->stack_size,
                            'used' => 0
                        ]);
                    }
                    $amountLeft = $amount - ($attachmentCount*$itemInfo->stack_size);
                    if($amountLeft > 0) {
                        $this->mInboxAttachTbl->insert([
                            'mail_idfs' => $messageId,
                            'item_idfs' => $itemId,
                            'slot' => $itemSent,
                            'amount' => $amountLeft,
                            'used' => 0
                        ]);
                    }
                }

            }
        } else {
            # add purchased items as attachment
            for($itemSent = 0;$itemSent < $amount;$itemSent++) {
                $this->mInboxAttachTbl->insert([
                    'mail_idfs' => $messageId,
                    'item_idfs' => $itemId,
                    'slot' => $itemSent,
                    'used' => 0
                ]);
            }
        }

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
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $categoryId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);

        $category = $this->mItemCatTbl->select(['Category_ID' => $categoryId]);
        if($category->count() == 0) {
            return new ApiProblem(404, 'Category not found');
        }
        $category = $category->current();

        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $pageSize = 10000;

        $itemWh = new Where();
        $itemWh->equalTo('category_idfs', $categoryId);
        //$itemWh->notEqualTo('created_by', $user->User_ID);
        $itemsInCategory = $this->mItemTbl->select($itemWh);

        $items = [];
        $itemsById = [];
        $itemsByAmount = [];
        foreach($itemsInCategory as $item) {
            $itemSel = new Select($this->mAuctionTbl->getTable());
            $itemSWh = new Where();
            $itemSWh->equalTo('item_idfs', $item->Item_ID);
            $itemSWh->notEqualTo('created_by', $user->User_ID);
            $itemSel->where($itemSWh);
            $itemSel->group(['price_per_unit']);
            $itemSel->order('created_date ASC');
            # Create a new pagination adapter object
            $oPaginatorAdapter = new DbSelect(
            # our configured select object
                $itemSel,
                # the adapter to run it against
                $this->mAuctionTbl->getAdapter()
            );

            $auctions = [];
            $totalAmount = 0;
            # Create Paginator with Adapter
            $auctionsPaginated = new Paginator($oPaginatorAdapter);
            $auctionsPaginated->setCurrentPageNumber($page);
            $auctionsPaginated->setItemCountPerPage($pageSize);
            $cheapest = 0;
            foreach($auctionsPaginated as $auction) {
                $totalAmount+=$auction->amount;
                if($cheapest == 0) {
                    $cheapest = $auction->price_per_unit;
                } else {
                    if($auction->price_per_unit < $cheapest) {
                        $cheapest = $auction->price_per_unit;
                    }
                }
                if(!array_key_exists($auction->price_per_unit,$auctions)) {
                    $auctions[$auction->price_per_unit] = [
                        'price' => $auction->price_per_unit,
                        'amount' => $auction->amount,
                    ];
                } else {
                    $auctions[$auction->price_per_unit]['amount']+=$auction->amount;
                }
                /**
                $auctions[] = [
                    'price' => $auction->price_per_unit,
                    'amount' => $auction->amount,
                ]; **/
            }
            if($totalAmount > 0) {
                $items[] = (object)[
                    'id' => $item->Item_ID,
                    'name' => $item->label,
                    'price' => $cheapest,
                    'amount' => $totalAmount,
                    'auctions' => $auctions
                ];
            }
        }

        return [
            'category' => (object)[
                'id' => $category->Category_ID,
                'name' => $category->label
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $categories = [];
        $categoriesById = [];
        $categoriesFromDB = $this->mItemCatTbl->select(['in_store' => 1]);
        foreach($categoriesFromDB as $cat) {
            if($cat->parent_idfs == 0) {
                $categoriesById[$cat->Category_ID] = ['category' => (object)[
                    'id' => $cat->Category_ID,
                    'name' => $cat->label,
                    'icon' => $cat->icon
                ], 'children' => []];
            } else {
                if(array_key_exists($cat->parent_idfs,$categoriesById)) {
                    $categoriesById[$cat->parent_idfs]['children'][] = (object)[
                        'id' => $cat->Category_ID,
                        'name' => $cat->label
                    ];
                }
            }
        }

        foreach($categoriesById as $category) {
            $categories[] = $category;
        }

        return $categories;
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
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $itemId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        $amount = filter_var($data->amount, FILTER_SANITIZE_NUMBER_INT);
        $price = filter_var($data->price, FILTER_SANITIZE_NUMBER_INT);

        if($amount < 1) {
            return new ApiProblem(400, 'Amount must be at least 1');
        }

        if($price == 0) {
            return new ApiProblem(400, 'Price cannot be zero');
        }

        if($itemId == 0) {
            return new ApiProblem(400, 'Invalid item');
        }

        $itemInfo = $this->mItemTbl->select(['Item_ID' => $itemId]);
        if($itemInfo->count() == 0) {
            return new ApiProblem(404, 'Item not found');
        }
        $itemInfo = $itemInfo->current();

        $auctionWh = new Where();
        $auctionWh->equalTo('item_idfs', $itemId);
        $auctionWh->equalTo('price_per_unit', $price);

        $auctionSel = new Select($this->mAuctionTbl->getTable());
        $auctionSel->where($auctionWh);
        $auctionSel->order('created_date');

        $auctions = $this->mAuctionTbl->selectWith($auctionSel);
        if($auctions->count() == 0) {
            return new ApiProblem(404, 'No auction found for this item');
        }
        $amountToBuy = $amount;

        $auctionsMatching = [];
        $totalAmount = 0;
        foreach($auctions as $auction) {
            $auctionsMatching[] = $auction;
            $totalAmount+=$auction->amount;
        }

        if($amount > $totalAmount) {
            return new ApiProblem(400, 'There is not that much '.$itemInfo->label.' in the marketplace for this price');
        }

        $totalPrice = $amount * $price;
        if($this->mTransaction->checkUserBalance($totalPrice, $user->User_ID)) {
            # consume auctions
            foreach($auctionsMatching as $auction) {
                $amountLeft = $auction->amount - $amountToBuy;
                if($amountLeft < 0) {
                    // remove auction
                    $this->mAuctionTbl->delete(['Auction_ID' => $auction->Auction_ID]);
                    $amountToBuy = $amountToBuy - $auction->amount;

                    $userAmount = $auction->amount * $auction->price_per_unit;
                    $fee = $userAmount*.1;
                    $finalAmount = $userAmount*.9;

                    $userMsg = 'Attached are the coins for your sold item. <br/><br/> ';
                    $userMsg .= $auction->amount.'x '.$itemInfo->label.'<br/>- ';
                    $userMsg .= $auction->amount.'x '.$auction->price_per_unit.' = '.number_format($userAmount,2,'.','\'');
                    $userMsg .= '<br/>- Marketplace Fee : '.number_format($fee, 2,'.','\'');
                    $userMsg .= '<br/>= '.number_format($finalAmount, 2,'.','\'');

                    // payout to seller
                    # create message to buyer inbox
                    $this->mInboxTbl->insert([
                        'label' => 'Your Auction was sold',
                        'message' => $userMsg,
                        'credits' => $finalAmount,
                        'from_idfs' => 1,
                        'to_idfs' => $auction->created_by,
                        'date' => date('Y-m-d H:i:s', time()),
                        'is_read' => 0
                    ]);
                } else {
                    if($amountLeft == 0) {
                        $this->mAuctionTbl->delete(['Auction_ID' => $auction->Auction_ID]);

                        $userAmount = $auction->amount * $auction->price_per_unit;
                        $fee = $userAmount*.1;
                        $finalAmount = $userAmount*.9;

                        $userMsg = 'Attached are the coins for your sold item. <br/><br/> ';
                        $userMsg .= $auction->amount.'x '.$itemInfo->label.'<br/>- ';
                        $userMsg .= $auction->amount.'x '.$auction->price_per_unit.' = '.number_format($userAmount,2,'.','\'');
                        $userMsg .= '<br/>- Marketplace Fee : '.number_format($fee, 2,'.','\'');
                        $userMsg .= '<br/>= '.number_format($finalAmount, 2,'.','\'');

                        # create message to buyer inbox
                        $this->mInboxTbl->insert([
                            'label' => 'Your Auction was sold',
                            'message' => $userMsg,
                            'credits' => $finalAmount,
                            'from_idfs' => 1,
                            'to_idfs' => $auction->created_by,
                            'date' => date('Y-m-d H:i:s', time()),
                            'is_read' => 0
                        ]);
                        break;
                    } else {
                        $userAmount = ($auction->amount - $amountLeft) * $auction->price_per_unit;
                        $fee = $userAmount*.1;
                        $finalAmount = $userAmount*.9;

                        $userMsg = 'Attached are the coins for your sold item. <br/><br/> ';
                        $userMsg .= ($auction->amount - $amountLeft).'x '.$itemInfo->label.'<br/>- ';
                        $userMsg .= ($auction->amount - $amountLeft).'x '.$auction->price_per_unit.' = '.number_format($userAmount,2,'.','\'');
                        $userMsg .= '<br/>- Marketplace Fee : '.number_format($fee, 2,'.','\'');
                        $userMsg .= '<br/>= '.number_format($finalAmount, 2,'.','\'');

                        # create message to buyer inbox
                        $this->mInboxTbl->insert([
                            'label' => 'Your Auction was sold',
                            'message' => $userMsg,
                            'credits' => $finalAmount,
                            'from_idfs' => 1,
                            'to_idfs' => $auction->created_by,
                            'date' => date('Y-m-d H:i:s', time()),
                            'is_read' => 0
                        ]);
                        $this->mAuctionTbl->update([
                            'amount' => $amountLeft,
                        ],['Auction_ID' => $auction->Auction_ID]);
                    }
                }
            }

            $fNewBalance = $this->mTransaction->executeTransaction($totalPrice, 1, $user->User_ID, $itemId, 'mp-buy', 'Bought '.$amount.' of '.$itemInfo->label);

            # create message to buyer inbox
            $this->mInboxTbl->insert([
                'label' => 'Your Marketplace Purchase',
                'message' => 'Attached are is your Marketplace Purchase',
                'credits' => 0,
                'from_idfs' => 1,
                'to_idfs' => $user->User_ID,
                'date' => date('Y-m-d H:i:s', time()),
                'is_read' => 0
            ]);
            $messageId = $this->mInboxTbl->lastInsertValue;

            if($itemInfo->stack_size > 1) {
                $attachmentCount = round($amount/$itemInfo->stack_size);
                if($attachmentCount < 1) {
                    $this->mInboxAttachTbl->insert([
                        'mail_idfs' => $messageId,
                        'item_idfs' => $itemId,
                        'slot' => 0,
                        'amount' => $amount,
                        'used' => 0
                    ]);
                } else {
                    if($itemInfo->stack_size - $amount > 0) {
                        $this->mInboxAttachTbl->insert([
                            'mail_idfs' => $messageId,
                            'item_idfs' => $itemId,
                            'slot' => 0,
                            'amount' => $amount,
                            'used' => 0
                        ]);
                    } else {
                        for($itemSent = 0;$itemSent < $attachmentCount;$itemSent++) {
                            $this->mInboxAttachTbl->insert([
                                'mail_idfs' => $messageId,
                                'item_idfs' => $itemId,
                                'slot' => $itemSent,
                                'amount' => $itemInfo->stack_size,
                                'used' => 0
                            ]);
                        }
                        $amountLeft = $amount - ($attachmentCount*$itemInfo->stack_size);
                        if($amountLeft > 0) {
                            $this->mInboxAttachTbl->insert([
                                'mail_idfs' => $messageId,
                                'item_idfs' => $itemId,
                                'slot' => $itemSent,
                                'amount' => $amountLeft,
                                'used' => 0
                            ]);
                        }
                    }

                }
            } else {
                # add purchased items as attachment
                for($itemSent = 0;$itemSent < $amount;$itemSent++) {
                    $this->mInboxAttachTbl->insert([
                        'mail_idfs' => $messageId,
                        'item_idfs' => $itemId,
                        'slot' => $itemSent,
                        'used' => 0
                    ]);
                }
            }
        } else {
            return new ApiProblem(400, 'Your balance is too low to buy '.$amount.' '.$itemInfo->label);
        }
        return true;
    }
}
