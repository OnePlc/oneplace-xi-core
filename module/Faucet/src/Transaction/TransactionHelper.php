<?php
/**
 * TransactionHelper.php - Token Transaction Helper
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

use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class TransactionHelper {

    /**
     * User Transaction Table
     *
     * @var TableGateway $mTransTbl
     * @since 1.0.0
     */
    private static $mTransTbl;

    /**
     * Guild Transaction Table
     *
     * @var TableGateway $mGuildTransTbl
     * @since 1.0.0
     */
    private static $mGuildTransTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    private static $mUserTbl;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    private static $mGuildTbl;

    /**
     * Settings Table
     *
     * @var TableGateway $mSettingsTbl
     * @since 1.0.0
     */
    private static $mSettingsTbl;

    /**
     * Token USD Value
     *
     * @var Float $mTokenValue
     * @since 1.0.0
     */
    private static $mTokenValue;

    /**
     * Faucet Wallets Table
     *
     * @var TableGateway $mWalletTbl
     * @since 1.0.0
     */
    private static $mWalletTbl;

    /**
     * PTC Transaction Table
     *
     * @var TableGateway $mPTCTbl
     * @since 1.0.0
     */
    private static $mPTCTbl;

    /**
     * Constructor
     *
     * LoginController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        TransactionHelper::$mTransTbl = new TableGateway('faucet_transaction', $mapper);
        TransactionHelper::$mPTCTbl = new TableGateway('ptc_transaction', $mapper);
        TransactionHelper::$mUserTbl = new TableGateway('user', $mapper);
        TransactionHelper::$mGuildTbl = new TableGateway('faucet_guild', $mapper);
        TransactionHelper::$mSettingsTbl = new TableGateway('settings', $mapper);
        TransactionHelper::$mGuildTransTbl = new TableGateway('faucet_guild_transaction', $mapper);
        TransactionHelper::$mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        TransactionHelper::$mTokenValue = TransactionHelper::$mSettingsTbl->select(['settings_key' => 'token-value'])->current()->settings_value;
    }

    /**
     * Execute Faucet Token Transaction for User
     *
     * @param float $amount - Amount of Token to transfer
     * @param bool $isInput - Defines if Transaction is Output
     * @param int $userId - Target User ID
     * @param int $refId - Reference ID for Transaction
     * @param string $refType - Reference Type for Transaction
     * @param string $description - Detailed Description for Transaction
     * @param int $createdBy (optional) - Source User ID
     * @since 1.0.0
     */
    public function executeTransaction(float $amount, bool $isOutput, int $userId, int $refId, string $refType, string $description, int $createdBy = 0)
    {
        # no negative transactions allowed
        if($amount < 0) {
            return false;
        }

        # Do not allow zero for update
        if($userId == 0) {
            return false;
        }

        # Generate Transaction ID
        try {
            $sTransactionID = $bytes = random_bytes(5);
        } catch(\Exception $e) {
            # Fallback if random bytes fails
            $sTransactionID = time();
        }
        $sTransactionID = hash("sha256",$sTransactionID);

        # Get user from database
        $userInfo = TransactionHelper::$mUserTbl->select(['User_ID' => $userId]);
        if(count($userInfo) > 0) {
            $userInfo = $userInfo->current();
            $txCheck = TransactionHelper::$mTransTbl->select([
                'amount' => $amount,
                'date' => date('Y-m-d H:i:s', time()),
                'ref_idfs' => $refId,
                'ref_type' => $refType,
                'comment' => $description,
                'user_idfs' => $userId,
            ]);
            if($txCheck->count() == 0) {
                # calculate new balance
                $newBalance = ($isOutput) ? $userInfo->token_balance-$amount : $userInfo->token_balance+$amount;
                # Insert Transaction
                if(TransactionHelper::$mTransTbl->insert([
                    'Transaction_ID' => $sTransactionID,
                    'amount' => $amount,
                    'token_balance' => $userInfo->token_balance,
                    'token_balance_new' => $newBalance,
                    'is_output' => ($isOutput) ? 1 : 0,
                    'date' => date('Y-m-d H:i:s', time()),
                    'ref_idfs' => $refId,
                    'ref_type' => $refType,
                    'comment' => $description,
                    'user_idfs' => $userId,
                    'created_by' => ($createdBy == 0) ? $userId : $createdBy,
                ])) {
                    # update user balance
                    TransactionHelper::$mUserTbl->update([
                        'token_balance' => $newBalance,
                        'last_action' => date('Y-m-d H:i:s', time()),
                    ],[
                        'User_ID' => $userId
                    ]);
                    return $newBalance;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Execute Faucet Guild Token Transaction for User
     *
     * @param float $amount - Amount of Token to transfer
     * @param bool $isInput - Defines if Transaction is Output
     * @param int $guildId - Target Guild ID
     * @param int $refId - Reference ID for Transaction
     * @param string $refType - Reference Type for Transaction
     * @param string $description - Detailed Description for Transaction
     * @param int $createdBy (optional) - Source User ID
     * @since 1.0.0
     */
    public function executeGuildTransaction(float $amount, bool $isOutput, int $guildId, int $refId,
                                       string $refType, string $description, int $createdBy)
    {
        # no negative transactions allowed
        if($amount < 0) {
            return false;
        }

        # Do not allow zero for update
        if($guildId == 0) {
            return false;
        }

        # Generate Transaction ID
        try {
            $sTransactionID = $bytes = random_bytes(5);
        } catch(\Exception $e) {
            # Fallback if random bytes fails
            $sTransactionID = time();
        }
        $sTransactionID = hash("sha256",$sTransactionID);

        # Get user from database
        $guildInfo = TransactionHelper::$mGuildTbl->select(['Guild_ID' => $guildId]);
        if(count($guildInfo) > 0) {
            $guildInfo = $guildInfo->current();
            # calculate new balance
            $newBalance = ($isOutput) ? $guildInfo->token_balance-$amount : $guildInfo->token_balance+$amount;
            # Insert Transaction
            if(TransactionHelper::$mGuildTransTbl->insert([
                'Transaction_ID' => $sTransactionID,
                'amount' => $amount,
                'token_balance' => $guildInfo->token_balance,
                'token_balance_new' => $newBalance,
                'is_output' => ($isOutput) ? 1 : 0,
                'date' => date('Y-m-d H:i:s', time()),
                'ref_idfs' => $refId,
                'ref_type' => $refType,
                'comment' => utf8_encode($description),
                'guild_idfs' => $guildId,
                'created_by' => $createdBy,
            ])) {
                # update user balance
                TransactionHelper::$mGuildTbl->update([
                    'token_balance' => $newBalance,
                ],[
                    'Guild_ID' => $guildId
                ]);
                return $newBalance;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Execute Faucet PTC Credit Transaction for User
     *
     * @param float $amount - Amount of Token to transfer
     * @param bool $isInput - Defines if Transaction is Output
     * @param int $userId - Target User ID
     * @param int $refId - Reference ID for Transaction
     * @param string $refType - Reference Type for Transaction
     * @param string $description - Detailed Description for Transaction
     * @param int $createdBy (optional) - Source User ID
     * @since 1.0.0
     */
    public function executeCreditTransaction(float $amount, bool $isOutput, int $userId, int $refId,
                                       string $refType)
    {
        # no negative transactions allowed
        if($amount < 0) {
            return false;
        }

        # Do not allow zero for update
        if($userId == 0) {
            return false;
        }

        # Generate Transaction ID
        try {
            $sTransactionID = $bytes = random_bytes(5);
        } catch(\Exception $e) {
            # Fallback if random bytes fails
            $sTransactionID = time();
        }
        $sTransactionID = hash("sha256",$sTransactionID);

        # Get user from database
        $userInfo = TransactionHelper::$mUserTbl->select(['User_ID' => $userId]);
        if(count($userInfo) > 0) {
            $userInfo = $userInfo->current();
            # calculate new balance
            $newBalance = ($isOutput) ? $userInfo->credit_balance-$amount : $userInfo->credit_balance+$amount;
            # Insert Transaction
            if(TransactionHelper::$mPTCTbl->insert([
                'Transaction_ID' => $sTransactionID,
                'amount' => $amount,
                'credit_balance' => $userInfo->credit_balance,
                'credit_balance_new' => $newBalance,
                'is_output' => ($isOutput) ? 1 : 0,
                'date' => date('Y-m-d H:i:s', time()),
                'ref_idfs' => $refId,
                'ref_type' => $refType,
                'user_idfs' => $userId,
            ])) {
                # update user balance
                TransactionHelper::$mUserTbl->update([
                    'credit_balance' => $newBalance,
                    'last_action' => date('Y-m-d H:i:s', time()),
                ],[
                    'User_ID' => $userId
                ]);
                return $newBalance;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Check if user has enough funds for transaction
     *
     * @param $amount
     * @param $userId
     * @return bool
     * @since 1.0.0
     */
    public function checkUserBalance($amount,$userId)
    {
        $userinfo = TransactionHelper::$mUserTbl->select(['User_ID' => $userId]);
        if(count($userinfo) > 0) {
            $userinfo = $userinfo->current();
            if($userinfo->token_balance >= $amount) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has enough funds for transaction
     *
     * @param $amount
     * @param $userId
     * @return bool
     * @since 1.0.0
     */
    public function checkUserCreditBalance($amount,$userId)
    {
        $userinfo = TransactionHelper::$mUserTbl->select(['User_ID' => $userId]);
        if(count($userinfo) > 0) {
            $userinfo = $userinfo->current();
            if($userinfo->credit_balance >= $amount) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if guild has enough funds for transaction
     *
     * @param $amount
     * @param $guildId
     * @return bool
     * @since 1.0.0
     */
    public function checkGuildBalance($amount,$guildId)
    {
        $guildinfo = TransactionHelper::$mGuildTbl->select(['Guild_ID' => $guildId]);
        if(count($guildinfo) > 0) {
            $guildinfo = $guildinfo->current();
            if($guildinfo->token_balance >= $amount) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Total Count of Guild Transactions
     *
     * @param $guildId
     * @return int
     * @since 1.0.0
     */
    public function getGuildTransactionCount($guildId) {
        return TransactionHelper::$mGuildTransTbl->select(['guild_idfs' => $guildId])->count();
    }

    /**
     * Get paginated Guild Transaction Log
     *
     * @param $guildId
     * @param $page
     * @param $itemsPerPage
     * @return Paginator
     * @since 1.0.0
     */
    public function getGuildTransactions($guildId,$page,$itemsPerPage)
    {
        # Compile list of all guilds
        $transactions = [];
        $transactionsSel = new Select(TransactionHelper::$mGuildTransTbl->getTable());
        $transactionsSel->where(['guild_idfs' => $guildId]);
        $transactionsSel->order('date DESC');
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $transactionsSel,
            # the adapter to run it against
            TransactionHelper::$mGuildTransTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $transactionsPaginated = new Paginator($oPaginatorAdapter);
        $transactionsPaginated->setCurrentPageNumber($page);
        $transactionsPaginated->setItemCountPerPage($itemsPerPage);

        foreach($transactionsPaginated as $trans) {
            $transactions[] = $trans;
        }

        return $transactions;
    }

    public function getTokenValue()
    {
        return TransactionHelper::$mTokenValue;
    }

    public function getCryptoBalance($balance, $user) {
        $coinInfo = TransactionHelper::$mWalletTbl->select(['coin_sign' => $user->prefered_coin]);
        $cryptoBalance = 0;
        if(count($coinInfo) > 0) {
            $coinInfo = $coinInfo->current();
            $cryptoBalance = $balance*$this->getTokenValue();
            if($coinInfo->dollar_val > 0) {
                $cryptoBalance = $cryptoBalance/$coinInfo->dollar_val;
            } else {
                $cryptoBalance = $cryptoBalance*$coinInfo->dollar_val;
            }
            $cryptoBalance = number_format($cryptoBalance,8,'.','');
        }

        return $cryptoBalance;
    }

    public function findGuildTransaction($guildId, $date, $refType, $userId = 0)
    {
        $transWh = new Where();
        $transWh->equalTo('guild_idfs', $guildId);
        $transWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime($date)));
        $transWh->like('ref_type', $refType);
        if($userId != 0) {
            $transWh->equalTo('created_by', $userId);
        }
        $transaction = TransactionHelper::$mGuildTransTbl->select($transWh);
        if($transaction->count() > 0) {
            return true;
        } else {
            return false;
        }
    }
}