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
        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
