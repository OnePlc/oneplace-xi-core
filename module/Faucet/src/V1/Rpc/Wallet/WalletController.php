<?php
/**
 * WithdrawController.php - User Wallet Controller
 *
 * Main Controller for Faucet User Wallets
 *
 * @category Controller
 * @package Faucet
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace Faucet\V1\Rpc\Wallet;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Select;
use Laminas\Mvc\Controller\AbstractActionController;

class WalletController extends AbstractActionController
{
    /**
     * User Withdrawal Table
     *
     * @var TableGateway $mWithdrawTbl
     * @since 1.0.0
     */
    protected $mWithdrawTbl;

    /**
     * Faucet Wallets Table
     *
     * @var TableGateway $mWalletTbl
     * @since 1.0.0
     */
    protected $mWalletTbl;

    /**
     * User Basic Tools
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    protected $mLinkAccTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mWithdrawTbl = new TableGateway('faucet_withdraw', $mapper);
        $this->mWalletTbl = new TableGateway('faucet_wallet', $mapper);
        $this->mLinkAccTbl = new TableGateway('user_linked_account', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
    }

    /**
     * Get User Public Wallet Addresses
     * or add a new one
     *
     * @return ApiProblemResponse|ViewModel
     * @since 1.2.0
     */
    public function walletAction()
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

        $myWallets = [];
        if($request->isGet()) {
            $oWh = new Where();
            $oWh->equalTo('user_idfs', $me->User_ID);
            $oWh->notLike('state', 'cancel');
            $myWithdrawals = $this->mWithdrawTbl->select($oWh);

            if(count($myWithdrawals) > 0) {
                foreach($myWithdrawals as $wth) {
                    if(!array_key_exists($wth->wallet, $myWallets)) {
                        $myWallets[$wth->wallet] = ['count' => 0,'coin' => $wth->currency,'address' => $wth->wallet];
                    }
                    $myWallets[$wth->wallet]['count']++;
                }
            }

            $myWalletsAPI = [];
            foreach($myWallets as $wal) {
                $myWalletsAPI[] = $wal;
            }

            $linkedAccs = [];
            $links = $this->mLinkAccTbl->select(['user_idfs' => $me->User_ID]);
            if($links->count() > 0) {
                foreach ($links as $link) {
                    $linkedAccs[] = $link;
                }
            }

            return new ViewModel([
                'wallet' => $myWalletsAPI,
                'linked' => $linkedAccs
            ]);
        }
    }
}
