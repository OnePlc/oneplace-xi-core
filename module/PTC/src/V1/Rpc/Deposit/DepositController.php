<?php
namespace PTC\V1\Rpc\Deposit;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\Client;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class DepositController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * PTC Deposit Table
     *
     * @var TableGateway $mDepositTbl
     * @since 1.0.0
     */
    protected $mDepositTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * Faucet Wallets Table
     *
     * @var TableGateway $mWalletTbl
     * @since 1.0.0
     */
    protected $mWalletTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mDepositTbl = new TableGateway('ptc_deposit', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
    }

    /**
     * PTC Deposit History and Payment
     *
     * @return ApiProblemResponse|ViewModel
     * @since 1.0.0
     */
    public function depositAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $creditValue = (float)$this->mSecTools->getCoreSetting('ptc-credit-value');
        if($creditValue <= 0) {
            return new ApiProblemResponse(new ApiProblem(500, 'Could not load Credit Value'));
        }

        $request = $this->getRequest();

        $coinPrice = 20;

        # get price per token in crypto
        $tokenValue = $this->mTransaction->getTokenValue();
        $coinInfoBCH = $this->mWalletTbl->select(['coin_sign' => 'BCH'])->current();
        $amountCrypto = $coinPrice*$tokenValue;
        if($coinInfoBCH->dollar_val > 0) {
            $amountCryptoBCH = number_format($amountCrypto/$coinInfoBCH->dollar_val,8,'.','');
        } else {
            $amountCryptoBCH = number_format($amountCrypto*$coinInfoBCH->dollar_val,8,'.','');
        }

        $coinInfoLTC = $this->mWalletTbl->select(['coin_sign' => 'LTC'])->current();
        if($coinInfoLTC->dollar_val > 0) {
            $amountCryptoLTC = number_format($amountCrypto/$coinInfoLTC->dollar_val,8,'.','');
        } else {
            $amountCryptoLTC = number_format($amountCrypto*$coinInfoLTC->dollar_val,8,'.','');
        }

        $coinInfoZEN = $this->mWalletTbl->select(['coin_sign' => 'ZEN'])->current();
        if($coinInfoZEN->dollar_val > 0) {
            $amountCryptoZEN = number_format($amountCrypto/$coinInfoZEN->dollar_val,8,'.','');
        } else {
            $amountCryptoZEN = number_format($amountCrypto*$coinInfoZEN->dollar_val,8,'.','');
        }


        $currentPrices = (object)[
            'bch' => $amountCryptoBCH,
            'ltc' => $amountCryptoLTC,
            'zen' => $amountCryptoZEN,
            'coins' => $coinPrice,
        ];

        if($request->isGet()) {
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $pageSize = 25;

            $myDeposits = [];
            $historySel = new Select($this->mDepositTbl->getTable());
            $checkWh = new Where();
            $checkWh->equalTo('user_idfs', $me->User_ID);
            $historySel->where($checkWh);
            $historySel->order('date DESC');
            # Create a new pagination adapter object
            $oPaginatorAdapter = new DbSelect(
            # our configured select object
                $historySel,
                # the adapter to run it against
                $this->mDepositTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $historyPaginated = new Paginator($oPaginatorAdapter);
            $historyPaginated->setCurrentPageNumber($page);
            $historyPaginated->setItemCountPerPage($pageSize);
            foreach($historyPaginated as $history) {
                # skip empty entries
                $myDeposits[] = (object)[
                    'id' => $history->Deposit_ID,
                    'date' => $history->date,
                    'currency' => $history->coin,
                    'wallet_receive' => $history->wallet_receive,
                    'amount' => $history->amount,
                    'price' => $history->price,
                    'sent' => $history->sent,
                    'received' => $history->received,
                    'cancelled' => $history->cancelled,
                ];
            }

            $totalDeposits = $this->mDepositTbl->select($checkWh)->count();

            return new ViewModel([
                'deposit' => [
                    'items' => $myDeposits,
                    'total_items' => $totalDeposits,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'page_count' => (round($totalDeposits / $pageSize) > 0) ? round($totalDeposits / $pageSize) : 1,
                    'show_info' => false,
                    'show_info_msg' => ""
                ],
                'price' => $currentPrices
            ]);
        }

        if($request->isPost()) {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['amount','currency'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            # Check if user is verified
            if($me->email_verified == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before buying credits'));
            }

            $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
            if($amount <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Amount'));
            }

            $currency = filter_var($json->currency, FILTER_SANITIZE_STRING);
            if($currency != 'COINS' && $currency != 'BCH' && $currency != 'LTC' && $currency != 'ZEN') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Currency'));
            }

            if(($me->credit_balance + $amount) > 100000) {
                return new ApiProblemResponse(new ApiProblem(400, 'You cannot have more than 100k Credit Balance.'));
            }

            # get price per token in crypto
            $tokenValue = $this->mTransaction->getTokenValue();
            $tokenPrice = 20;
            $amountCrypto = ($amount*$tokenPrice)*$tokenValue;


            $walletReceive = "";
            switch(strtolower($currency)) {
                case 'bch':
                    $sBCHNodeUrl = $this->mSecTools->getCoreSetting('bchnode-rpcurl');
                    if($sBCHNodeUrl) {
                        $client = new Client();
                        $client->setUri($sBCHNodeUrl);
                        $client->setMethod('POST');
                        $client->setRawBody('{"jsonrpc":"2.0","id":"curltext","method":"getnewaddress","params":[]}');
                        $response = $client->send();
                        $googleResponse = json_decode($response->getBody());
                        $walletReceive = $googleResponse->result;
                    }
                    $coinInfoBCH = $this->mWalletTbl->select(['coin_sign' => 'BCH'])->current();
                    if($coinInfoBCH->dollar_val > 0) {
                        $price = number_format($amountCrypto/$coinInfoBCH->dollar_val,8,'.','');
                    } else {
                        $price = number_format($amountCrypto*$coinInfoBCH->dollar_val,8,'.','');
                    }
                    break;
                case 'ltc':
                    $sLTCNodeUrl = $this->mSecTools->getCoreSetting('ltcnode-rpcurl');
                    if($sLTCNodeUrl) {
                        $client = new Client();
                        $client->setUri($sLTCNodeUrl);
                        $client->setMethod('POST');
                        $client->setRawBody('{"jsonrpc":"2.0","id":"curltext","method":"getnewaddress","params":[]}');
                        $response = $client->send();
                        $googleResponse = json_decode($response->getBody());
                        $walletReceive = $googleResponse->result;
                    }
                    $coinInfoLTC = $this->mWalletTbl->select(['coin_sign' => 'LTC'])->current();
                    if($coinInfoLTC->dollar_val > 0) {
                        $price = number_format($amountCrypto/$coinInfoLTC->dollar_val,8,'.','');
                    } else {
                        $price = number_format($amountCrypto*$coinInfoLTC->dollar_val,8,'.','');
                    }
                    break;
                case 'zen':
                    $sZENNodeUrl = $this->mSecTools->getCoreSetting('zennode-rpcurl');
                    if($sZENNodeUrl) {
                        $client = new Client();
                        $client->setUri($sZENNodeUrl);
                        $client->setMethod('POST');
                        $client->setRawBody('{"jsonrpc":"2.0","id":"curltext","method":"getnewaddress","params":[]}');
                        $response = $client->send();
                        $googleResponse = json_decode($response->getBody());
                        $walletReceive = $googleResponse->result;
                    }
                    $coinInfoZEN = $this->mWalletTbl->select(['coin_sign' => 'ZEN'])->current();
                    if($coinInfoZEN->dollar_val > 0) {
                        $price = number_format($amountCrypto/$coinInfoZEN->dollar_val,8,'.','');
                    } else {
                        $price = number_format($amountCrypto*$coinInfoZEN->dollar_val,8,'.','');
                    }
                    break;
                case 'coins':
                    $walletReceive = $me->User_ID;
                    $price = $tokenPrice*$amount;
                    break;
                default:
                    break;
            }

            $this->mDepositTbl->insert([
                'user_idfs' => $me->User_ID,
                'date' => date('Y-m-d H:i:s', time()),
                'coin' => $currency,
                'wallet_receive' => $walletReceive,
                'amount' => $amount,
                'price' => $price,
                'received' => 0,
                'sent' => 0,
            ]);

            return [
                'amount' => $amount,
                'currency' => $currency,
                'wallet' => $walletReceive,
                'credit_balance' => $me->credit_balance,
                'token_balance' => $me->token_balance,
                'price' => $price,
            ];
        }

        if($request->isPut()) {
            # Get Data from Request Body
            $json = IndexController::loadJSONFromRequestBody(['amount','currency'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Response Body (missing required fields)'));
            }

            # Check if user is verified
            if($me->email_verified == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before buying credits'));
            }

            $amount = filter_var($json->amount, FILTER_SANITIZE_NUMBER_INT);
            if($amount <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Amount'));
            }

            $currency = filter_var($json->currency, FILTER_SANITIZE_STRING);
            if($currency != 'COINS' && $currency != 'BCH' && $currency != 'LTC' && $currency != 'ZEN') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Currency'));
            }

            if(($me->credit_balance + $amount) > 100000) {
                return new ApiProblemResponse(new ApiProblem(400, 'You cannot have more than 100k Credit Balance.'));
            }

            $deposit = $this->mDepositTbl->select([
                'user_idfs' => $me->User_ID,
                'received' => 0,
                'sent' => 0,
                'cancelled' => 0,
                'coin' => 'COINS',
                'amount' => $amount
            ]);

            if(count($deposit) == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'No open coin deposit found for user.'));
            }
            $deposit = $deposit->current();

            $tokenPrice = 20;

            if (!$this->mTransaction->checkUserBalance(($amount * $tokenPrice), $me->User_ID)) {
                return new ApiProblemResponse(new ApiProblem(400, 'Your balance is too low to buy ' . $amount . ' tokens'));
            }
            $newBalance = $this->mTransaction->executeTransaction(($amount * $tokenPrice), true, $me->User_ID, $amount, 'token-buy', 'Bought '.$amount.' Tokens with COINS');
            /**
             * Send Coins to Admins - do not Burn
             */
            if($newBalance !== false) {
                # Burn the Coins
                $newCreditBalance = $this->mTransaction->executeCreditTransaction($amount, false, $me->User_ID, $deposit->Deposit_ID, 'deposit', 'Bought '.$amount.' PTC Credits with COINS');
                if($newCreditBalance !== false) {
                    $me->credit_balance = $newCreditBalance;
                    # Burn Coins - and recreate for PTC View
                    $this->mDepositTbl->update([
                        'sent' => 1,
                        'received' => 1
                    ],['Deposit_ID' => $deposit->Deposit_ID]);
                    return [
                        'credit_balance' => $me->credit_balance,
                    ];
                } else {
                    return new ApiProblemResponse(new ApiProblem(500, 'Error during transaction'));
                }
            } else {
                return new ApiProblemResponse(new ApiProblem(500, 'Error during transaction'));
            }
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
