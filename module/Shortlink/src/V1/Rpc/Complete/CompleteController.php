<?php
namespace Shortlink\V1\Rpc\Complete;

use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;

class CompleteController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Api Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * User Tools Helper
     *
     * @var UserTools $mUserTools
     * @since 1.0.0
     */
    protected $mUserTools;

    /**
     * Shortlink Provider Table
     *
     * @var TableGateway $mShortProviderTbl
     * @since 1.0.0
     */
    protected $mShortProviderTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * Shortlink Table User Table
     *
     * Relation between Shortlink and User
     * to determine if user has completed a Shortlink
     *
     * @var TableGateway $mShortDoneTbl
     * @since 1.0.0
     */
    protected $mShortDoneTbl;

    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Constructor
     *
     * ConfirmController constructor.
     * @param $mapper
     * @param $viewRenderer
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mShortProviderTbl = new TableGateway('shortlink', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mApiTools = new ApiTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
    }

    public function completeAction()
    {
        $request = $this->getRequest();

        /**
         * Verify E-Mail Address
         *
         * @since 1.0.0
         */
        if($request->isGet()) {
            $token = filter_var($this->params()->fromRoute('token', ''), FILTER_SANITIZE_STRING);

            $shFound = $this->mShortDoneTbl->select(['link_id' => $token]);
            if(count($shFound) == 0) {
                echo 'shortlink not found ';
            } else {
                $shFound = $shFound->current();
                $linkInfo = $this->mShortProviderTbl->select(['Shortlink_ID' => $shFound->shortlink_idfs]);
                if(count($linkInfo) == 0) {
                    echo 'shortlink not found';
                    return false;
                }
                $linkInfo = $linkInfo->current();
                $bHostFound = stripos($_SERVER['HTTP_REFERER'], $linkInfo->refer_check);
                //$bCanSkip = ($_SERVER['HTTP_REFERER'] == NULL && $linkInfo->refer_check == NULL);
                $bCanSkip = false;
                if($bHostFound === false && !$bCanSkip) {
                    echo 'invalid referer '.$_SERVER['HTTP_REFERER'].' != '.$linkInfo->refer_check;
                } else {
                    if($shFound->date_completed == '0000-00-00 00:00:00') {
                        $this->mShortDoneTbl->update([
                            'date_completed' => date('Y-m-d H:i:s', time()),
                            'date_claimed' =>  date('Y-m-d H:i:s', time()),
                        ],[
                            'user_idfs' => $shFound->user_idfs,
                            'shortlink_idfs' => $shFound->shortlink_idfs,
                            'link_id' => $shFound->link_id,
                            'date_claimed' => '0000-00-00 00:00:00',
                            'date_completed' => '0000-00-00 00:00:00'
                        ]);

                        $newBalance = $this->mTransaction->executeTransaction($linkInfo->reward, false, $shFound->user_idfs, $shFound->shortlink_idfs, 'shortlink-complete', 'Shortlink '.$shFound->link_id.' completed');
                        if($newBalance !== false) {
                            $xpInfo = $this->mUserTools->addXP('shortlink-claim', $shFound->user_idfs);
                            $redirectUrl = $this->mSecTools->getCoreSetting('sh-complete-url');
                            return $this->redirect()->toUrl($redirectUrl);
                        }
                    } else {
                        echo 'already done';
                    }
                }
            }
        }

        return false;
    }
}
