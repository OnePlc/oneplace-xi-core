<?php
namespace Support\V1\Rpc\MailClaim;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class MailClaimController extends AbstractActionController
{
    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;


    /**
     * Support Request Table
     *
     * @var TableGateway $mSupportTbl
     * @since 1.0.0
     */
    protected $mSupportTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * @var TableGateway
     */
    private $mUserTbl;

    /**
     * @var TransactionHelper
     */
    private $mTransaction;

    /**
     * Constructor
     *
     * SupportController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        # Init Tables for this API
        $this->mSupportTbl = new TableGateway('user_request', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);

    }

    public function mailClaimAction()
    {
        $request = $this->getRequest();

        /**
         * User Support History
         */
        if($request->isGet()) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);

            $claimKey = filter_var($_REQUEST['ck'], FILTER_SANITIZE_STRING);

            if(strlen($claimKey) == 32) {
                $user = $this->mUserTbl->select(['mailclaim_key' => $claimKey]);
                if($user->count() > 0) {
                    $user = $user->current();

                    $this->mTransaction->executeTransaction(100, false, $user->User_ID, 8, 'mail', 'Received Free Coins from E-Mail', 1, false);
                    // mail_unsub
                    echo '<div class="container">';
                        echo '100 Coins are now added to your Balance!<br/>';
                        echo '<a href="https://swissfaucet.io/dashboard" class="btn btn-success">Go to Swissfaucet.io</a>';
                    echo '</div>';

                    $this->mUserTbl->update(['mailclaim_key' => null], ['User_ID' => $user->User_ID]);

                    return false;
                }

                return new ApiProblemResponse(new ApiProblem(403, 'Invalid Claimkey'));
            }

            return new ApiProblemResponse(new ApiProblem(403, 'Invalid Claimkey'));
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));
    }
}
