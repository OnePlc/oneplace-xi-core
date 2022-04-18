<?php
/**
 * BankController.php - Bank Controller
 *
 * Main Controller for Faucet Guild Bank
 *
 * @category Controller
 * @package Guild
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Guild\V1\Rpc\Bank;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\InventoryHelper;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Laminas\Session\Container;

class BankController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Guild Rank Table
     *
     * @var TableGateway $mGuildRankTbl
     * @since 1.0.0
     */
    protected $mGuildRankTbl;

    /**
     * Guild Item Table
     *
     * @var TableGateway $mGuildItemTbl
     * @since 1.0.0
     */
    protected $mGuildItemTbl;

    /**
     * User XP Level Table
     *
     * @var TableGateway $mXPLvlTbl
     */
    protected $mXPLvlTbl;

    /**
     * Guild Table User Table
     *
     * Relation between Guild and User
     * to determine if user has a guild and
     * if yes what guild it is
     *
     * @var TableGateway $mGuildUserTbl
     * @since 1.0.0
     */
    protected $mGuildUserTbl;

    /**
     * Guild Rank Permission Table
     *
     * @var TableGateway $mGuildRankPermTbl
     * @since 1.0.0
     */
    protected $mGuildRankPermTbl;

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
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Constructor
     *
     * BankController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mGuildRankTbl = new TableGateway('faucet_guild_rank', $mapper);
        $this->mGuildItemTbl = new TableGateway('faucet_item_guild', $mapper);
        $this->mXPLvlTbl = new TableGateway('user_xp_level', $mapper);
        $this->mGuildRankPermTbl = new TableGateway('faucet_guild_rank_permission', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mInventory = new InventoryHelper($mapper);
    }

    /**
     * Guildbank
     *
     * Withdraw and Deposit from Guildbank
     *
     * @return array|ApiProblemResponse
     * @since 1.0.0
     */
    public function bankAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);
        if(count($userHasGuild) == 0) {
            return new ApiProblemResponse(new ApiProblem(409, 'You are not part of a guild and so not eligable to deposit to a guildbank'));
        }

        # Load Guild Data
        $userHasGuild = $userHasGuild->current();
        $guild = $this->mGuildTbl->select(['Guild_ID' => $userHasGuild->guild_idfs]);
        if(count($guild) == 0) {
            return new ApiProblemResponse(new ApiProblem(404, 'Guild not found'));
        }
        $guild = $guild->current();

        # Get Request Data
        $request = $this->getRequest();
        if($request->isPut() || $request->isPost()) {
            $json = IndexController::loadJSONFromRequestBody(['amount','item'],$this->getRequest()->getContent());
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
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guildbank Withdraw',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
            }
            $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
            $item = filter_var($json->item, FILTER_SANITIZE_NUMBER_INT);
        }

        $rank = '-';
        $rankInfo = null;
        $rankDB = $this->mGuildRankTbl->select([
            'guild_idfs' => $guild->Guild_ID,
            'level' => $userHasGuild->rank,
        ]);
        if(count($rankDB) > 0) {
            $rankInfo = $rankDB->current();
            $rank = $rankInfo->label;
        }

        $guildXPPercent = 0;
        if ($guild->xp_current != 0) {
            $guildNextLvl = $this->mXPLvlTbl->select(['Level_ID' => ($guild->xp_level + 1)])->current();
            $guildXPPercent = round((100 / ($guildNextLvl->xp_total / $guild->xp_current)), 2);
        }

        /**
         * Execute Command based on Request Type
         */
        switch($request) {
            /**
             * Transactions and Balance
             */
            case $request->isGet():
                $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
                # check for attack vendors
                $secResult = $this->mSecTools->basicInputCheck([$_REQUEST['page']]);
                if($secResult !== 'ok') {
                    # ban user and force logout on client
                    $this->mUserSetTbl->insert([
                        'user_idfs' => $me->User_ID,
                        'setting_name' => 'user-tempban',
                        'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guildbank GET',
                    ]);
                    return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
                }

                $totalItems = $this->mTransaction->getGuildTransactionCount($guild->Guild_ID);
                $pages = (round($totalItems/10) > 0) ? round($totalItems/10) : 1;

                $dailyLimit = 0;
                if($rankInfo->daily_withdraw > 0) {
                    $dailyLimit = $rankInfo->daily_withdraw;
                }

                return [
                    'guild_token_balance' => $guild->token_balance,
                    'page_size' => 10,
                    'page' => $page,
                    'withdraw_limit' => $dailyLimit,
                    'page_count' => $pages,
                    'total_items' => $totalItems,
                    'transactions' => $this->mTransaction->getGuildTransactions($guild->Guild_ID, $page, 10),
                    'inventory' => $this->mInventory->getGuildInventory($guild->Guild_ID)
                ];
            /**
             * Withdraw
             */
            case $request->isPost():
                # check if user is allowed to withdraw from guildbank
                $withdrawAllowed = false;
                if($userHasGuild->rank == 0) {
                    $withdrawAllowed = true;
                } else {
                    if($rankInfo) {
                        if($rankInfo->daily_withdraw > 0) {
                            $lastWth = $this->mTransaction->findGuildTransaction($userHasGuild->guild_idfs,date('Y-m-d H:i:s', time()-(3600*24)),'member-wth', $me->User_ID);

                            if($lastWth !== false) {
                                return new ApiProblemResponse(new ApiProblem(409, 'You have already withdrawn from guildbank in the last 24hours'));
                            } else {
                                $withdrawAllowed = true;
                            }
                        }
                    }
                }
                if($withdrawAllowed) {
                    # check guild balance
                    if($this->mTransaction->checkGuildBalance($amount, $guild->Guild_ID)) {
                        if($amount > $rankInfo->daily_withdraw) {
                            return new ApiProblemResponse(new ApiProblem(409, 'You cannot withdraw more than '.$rankInfo->daily_withdraw.' Coins per Day'));
                        }
                        $newGuildBalance = $this->mTransaction->executeGuildTransaction($amount, true, $guild->Guild_ID, 0, 'member-wth','Withdraw from User '.$me->username, $me->User_ID);
                        if($newGuildBalance !== false) {
                            # move coins from guild to user
                            $newBalance = $this->mTransaction->executeTransaction($amount, false, $me->User_ID, $guild->Guild_ID, 'guild-withdraw', '');
                            if($newBalance !== false) {
                                return [
                                    'state' => 'success',
                                    'message' => $amount.' successfully withdrawn from Guildbank',
                                    'guild' => (object)[
                                        'id' => $guild->Guild_ID,
                                        'name' => $guild->label,
                                        'icon' => $guild->icon,
                                        'xp_level' => $guild->xp_level,
                                        'xp_total' => $guild->xp_total,
                                        'xp_current' => $guild->xp_current,
                                        'xp_percent' => $guildXPPercent,
                                        'rank' => (object)['id' => $userHasGuild->rank, 'name' => $rank],
                                        'token_balance' => $newGuildBalance,
                                    ],
                                    'token_balance' => $newBalance,
                                ];
                            } else {
                                return new ApiProblemResponse(new ApiProblem(500, 'There was an error with the transaction. please contact support'));
                            }
                        } else {
                            return new ApiProblemResponse(new ApiProblem(500, 'There was an error with the guild transaction. please contact support '));
                        }
                    } else {
                        return new ApiProblemResponse(new ApiProblem(409, 'Not enough funds to withdraw '.$amount.' coins from guildbank'));
                    }
                } else {
                    return new ApiProblemResponse(new ApiProblem(403, 'You are not allowed to withdraw from the guild bank'));
                }
            /**
             * Deposit
             */
            case $request->isPut():
                # Transfer item
                if($item != 0 && $amount == 0) {
                    # check if user has item and it is not used
                    // $this->mGuildItemTbl
                    if($this->mInventory->userHasItemActive($item, $me->User_ID)) {
                        if($this->mInventory->depositItemToGuildBank($item, $me->User_ID, $guild->Guild_ID)) {
                            return (object)[
                                'state' => 'success',
                            ];
                        } else {
                            return new ApiProblemResponse(new ApiProblem(500, 'There was an error with the guild transaction. please contact support'));
                        }
                    } else {
                        return new ApiProblemResponse(new ApiProblem(403, 'You do not have this item in your inventory'));
                    }
                } else {
                    # check if user has enough funds
                    if ($this->mTransaction->checkUserBalance($amount, $me->User_ID)) {
                        $newBalance = $this->mTransaction->executeTransaction($amount, true, $me->User_ID, $guild->Guild_ID, 'guild-deposit', '');
                        if ($newBalance !== false) {
                            # move coins from user to guild
                            $newGuildBalance = $this->mTransaction->executeGuildTransaction($amount, false, $guild->Guild_ID, 0, '', 'Deposit from User ' . $me->username, $me->User_ID);
                            if ($newGuildBalance !== false) {
                                return [
                                    'state' => 'success',
                                    'message' => $amount . ' successfully deposited to Guildbank',
                                    'guild' => (object)[
                                        'id' => $guild->Guild_ID,
                                        'name' => $guild->label,
                                        'icon' => $guild->icon,
                                        'xp_level' => $guild->xp_level,
                                        'xp_total' => $guild->xp_total,
                                        'xp_current' => $guild->xp_current,
                                        'xp_percent' => $guildXPPercent,
                                        'rank' => (object)['id' => $userHasGuild->rank, 'name' => $rank],
                                        'token_balance' => $newGuildBalance,
                                    ],
                                    'token_balance' => $newBalance,
                                ];
                            } else {
                                return new ApiProblemResponse(new ApiProblem(500, 'There was an error with the guild transaction. please contact support'));
                            }
                        } else {
                            return new ApiProblemResponse(new ApiProblem(500, 'There was an error with the transaction. please contact support'));
                        }
                    } else {
                        return new ApiProblemResponse(new ApiProblem(409, 'Not enough funds to deposit ' . $amount . ' coins to guildbank'));
                    }
                }
            default:
                return new ApiProblemResponse(new ApiProblem(405, 'Method not supported'));
        }
    }
}
