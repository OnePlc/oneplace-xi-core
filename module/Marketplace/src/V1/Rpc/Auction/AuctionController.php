<?php
namespace Marketplace\V1\Rpc\Auction;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class AuctionController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

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
     * Constructor
     *
     * MailboxController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mItemTbl = new TableGateway('faucet_item', $mapper);
        $this->mItemCatTbl = new TableGateway('faucet_item_category', $mapper);
        $this->mAuctionTbl = new TableGateway('marketplace_item', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
    }

    public function auctionAction()
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
            $itemId = filter_var($_REQUEST['item'], FILTER_SANITIZE_NUMBER_INT);

            $item = $this->mItemTbl->select(['Item_ID' => $itemId]);
            if($item->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'Item not found'));
            }
            $itemInfo = $item->current();

            $auctions = [];
            $totalAmount = 0;
            $aucSel = new Select($this->mAuctionTbl->getTable());
            $aucSel->where(['item_idfs' => $itemId]);
            $aucSel->order('price_per_unit ASC');
            $itemAuctions = $this->mAuctionTbl->selectWith($aucSel);
            if($itemAuctions->count() > 0) {
                foreach($itemAuctions as $auction) {
                    $seller = $this->mUserTbl->select(['User_ID' => $auction->created_by]);
                    if($seller->count() > 0) {
                        $seller = $seller->current();
                        $totalAmount+=$auction->amount;
                        $auctions[] = (object)[
                            'id' => $auction->Auction_ID,
                            'amount' => $auction->amount,
                            'price_per_unit' => $auction->price_per_unit,
                            'seller' => [
                                'id' => $seller->User_ID,
                                'name' => $seller->username
                            ]
                        ];
                    }
                }
            }

            $baseFee = $itemInfo->auction_fee;

            return [
                'item' => [
                    'id' => $itemInfo->Item_ID,
                    'name' => $itemInfo->label,
                    'icon' => $itemInfo->icon
                ],
                'auction' => $auctions,
                'available' => $totalAmount,
                'deposit_fee' => [
                    '12hour' => $baseFee,
                    '24hour' => round($baseFee*1.2,0),
                    '48hour' => round($baseFee*1.5,0),
                ]
            ];
        }
    }
}