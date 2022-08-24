<?php
namespace Support\V1\Rpc\MailUnsub;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class MailUnsubController extends AbstractActionController
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
    }

    public function mailUnsubAction()
    {
        $request = $this->getRequest();

        /**
         * User Support History
         */
        if($request->isGet()) {
            $claimKey = filter_var($_REQUEST['ck'], FILTER_SANITIZE_STRING);

            if(strlen($claimKey) == 32) {
                $user = $this->mUserTbl->select(['unsub_key' => $claimKey]);
                if($user->count() > 0) {
                    $user = $user->current();

                    $this->mUserTbl->update([
                        'mail_unsub' => 1
                    ],['User_ID' => $user->User_ID]);

                    echo '<div class="container">';
                        echo 'You are now unsubscribed from any E-Mail Campaigns of Swissfaucet';
                    echo '</div>';

                    return false;
                }
            }
        }

        return new ApiProblemResponse(new ApiProblem(403, 'Invalid Key'));
    }
}
