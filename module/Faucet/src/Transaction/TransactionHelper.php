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

use Laminas\Db\TableGateway\TableGateway;

class TransactionHelper {

    private static $mTransTbl;
    private static $mUserTbl;

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
        TransactionHelper::$mUserTbl = new TableGateway('user', $mapper);
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
    public function executeTransaction(float $amount, bool $isOutput, int $userId, int $refId,
                                              string $refType, string $description, int $createdBy = 0)
    {
        # Generate Transaction ID
        try {
            $sTransactionID = $bytes = random_bytes(5);
        } catch(\Exception $e) {
            # Fallback if random bytes fails
            $sTransactionID = time();
        }
        $sTransactionID = hash("sha256",$sTransactionID);

        # Do not allow zero for update
        if($userId == 0) {
            return false;
        }

        # Get user from database
        $userInfo = TransactionHelper::$mUserTbl->select(['User_ID' => $userId]);
        if(count($userInfo) > 0) {
            $userInfo = $userInfo->current();
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
}