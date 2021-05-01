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

        # Insert Transaction
        if(TransactionHelper::$mTransTbl->insert([
            'Transaction_ID' => $sTransactionID,
            'amount' => $amount,
            //'token_balance' => 0,
            //'token_balance_new' => 0,
            'is_output' => ($isOutput) ? 1 : 0,
            'date' => date('Y-m-d H:i:s', time()),
            'ref_idfs' => $refId,
            'ref_type' => $refType,
            'comment' => $description,
            'user_idfs' => $userId,
            'created_by' => ($createdBy == 0) ? $userId : $createdBy,
        ])) {
            return true;
        } else {
            return false;
        }
    }
}