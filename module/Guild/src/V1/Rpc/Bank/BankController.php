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
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;

class BankController extends AbstractActionController
{
    /**
     * User Session
     *
     * @var Container $mSession
     * @since 1.0.0
     */
    protected $mSession;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

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
        $this->mGuildRankPermTbl = new TableGateway('faucet_guild_rank_permission', $mapper);
        $this->mSession = new Container('webauth');
        $this->mTransaction = new TransactionHelper($mapper);
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
        # Check if user is logged in
        if(!isset($this->mSession->auth)) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSession->auth;

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
        $json = IndexController::loadJSONFromRequestBody(['amount'],$this->getRequest()->getContent());
        if(!$json) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
        }
        $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);

        /**
         * Execute Command based on Request Type
         */
        switch($request) {
            /**
             * Withdraw
             */
            case $request->isPost():
                # check if user is allowed to withdraw from guildbank
                $withdrawAllowed = false;
                if($userHasGuild->rank == 0) {
                    $withdrawAllowed = true;
                } else {
                    $rankHasPerm = $this->mGuildRankPermTbl->select([
                        'guild_idfs' => $guild->Guild_ID,
                        'rank_idfs' => $userHasGuild->rank,
                        'permission' => 'withdraw',
                    ]);
                    if(count($rankHasPerm) > 0) {
                        $withdrawAllowed = true;
                    }
                }
                if($withdrawAllowed) {
                    # check guild balance
                    if($this->mTransaction->checkGuildBalance($amount, $guild->Guild_ID)) {
                        $newGuildBalance = $this->mTransaction->executeGuildTransaction($amount, true, $guild->Guild_ID, 0, '','Deposit from User '.$me->username, $me->User_ID);
                        if($newGuildBalance !== false) {
                            # move coins from guild to user
                            $newBalance = $this->mTransaction->executeTransaction($amount, false, $me->User_ID, $guild->Guild_ID, 'guild-deposit', '');
                            if($newBalance !== false) {
                                return [
                                    'state' => 'success',
                                    'message' => $amount.' successfully withdrawn from Guildbank',
                                    'guild_token_balance' => $newGuildBalance,
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
                # check if user has enough funds
                if($this->mTransaction->checkUserBalance($amount,$me->User_ID)) {
                    $newBalance = $this->mTransaction->executeTransaction($amount, true, $me->User_ID, $guild->Guild_ID, 'guild-deposit', '');
                    if($newBalance !== false) {
                        # move coins from user to guild
                        $newGuildBalance = $this->mTransaction->executeGuildTransaction($amount, false, $guild->Guild_ID, 0, '','Deposit from User '.$me->username, $me->User_ID);
                        if($newGuildBalance !== false) {
                            return [
                                'state' => 'success',
                                'message' => $amount.' successfully deposited to Guildbank',
                                'guild_token_balance' => $newGuildBalance,
                                'token_balance' => $newBalance,
                            ];
                        } else {
                            return new ApiProblemResponse(new ApiProblem(500, 'There was an error with the guild transaction. please contact support'));
                        }
                    } else {
                        return new ApiProblemResponse(new ApiProblem(500, 'There was an error with the transaction. please contact support'));
                    }
                } else {
                    return new ApiProblemResponse(new ApiProblem(409, 'Not enough funds to deposit '.$amount.' coins to guildbank'));
                }
            default:
                return new ApiProblemResponse(new ApiProblem(405, 'Method not supported'));
        }
    }
}
