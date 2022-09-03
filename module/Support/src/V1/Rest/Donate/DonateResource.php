<?php
namespace Support\V1\Rest\Donate;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class DonateResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * @var TableGateway
     */
    private $mDonateTbl;

    /**
     * @var TableGateway
     */
    private $mDonatePayTbl;

    /**
     * Constructor
     *
     * PTCResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
        $this->mDonateTbl = new TableGateway('donation', $mapper);
        $this->mDonatePayTbl = new TableGateway('donation_payment', $mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        # Check if user is verified
        if($me->email_verified == 0) {
            return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify your Account before donating Coins.'));
        }

        $secResult = $this->mSecTools->basicInputCheck([
            $data->amount
        ]);
        if($secResult !== 'ok') {
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }

        $amount = filter_var($data->amount, FILTER_SANITIZE_NUMBER_INT);
        if($amount <= 0) {
            return new ApiProblem(400, 'Please choose a valid amount for a donation');
        }

        # check balance
        if($this->mTransaction->checkUserCreditBalance($amount, $me->User_ID)) {
            $this->mDonateTbl->insert([
                'user_idfs' => $me->User_ID,
                'amount' => $amount,
                'date' => date('Y-m-d H:i:s', time()),
            ]);
            $donationId = $this->mDonateTbl->lastInsertValue;

            # save donation
            $newBalance = $this->mTransaction->executeTransaction($amount, true, $me->User_ID, $donationId, 'donation', 'Donated for eatBCH');
            if($newBalance !== false) {
                return [
                    'token_balance' => $newBalance
                ];
            }
        } else {
            return new ApiProblem(400, 'Your Balance is too low to donate '.$amount.' Coins');
        }

        return new ApiProblem(405, 'The POST method has not been defined');
    }

    /**
     * Delete a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function delete($id)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for individual resources');
    }

    /**
     * Delete a collection, or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function deleteList($data)
    {
        return new ApiProblem(405, 'The DELETE method has not been defined for collections');
    }

    /**
     * Fetch a resource
     *
     * @param  mixed $id
     * @return ApiProblem|mixed
     */
    public function fetch($id)
    {
        return new ApiProblem(405, 'The GET method has not been defined for individual resources');
    }

    /**
     * Fetch all or a subset of resources
     *
     * @param  array $params
     * @return ApiProblem|mixed
     */
    public function fetchAll($params = [])
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        # get current open donations
        $dWh = new Where();
        $dWh->isNull('payment_idfs');

        $openDonations = $this->mDonateTbl->select($dWh);
        $totalOpen = 0;
        foreach($openDonations as $don) {
            $totalOpen+=$don->amount;
        }

        return (object)[
            'donation_week' => $totalOpen
        ];
    }

    /**
     * Patch (partial in-place update) a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patch($id, $data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for individual resources');
    }

    /**
     * Patch (partial in-place update) a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function patchList($data)
    {
        return new ApiProblem(405, 'The PATCH method has not been defined for collections');
    }

    /**
     * Replace a collection or members of a collection
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function replaceList($data)
    {
        return new ApiProblem(405, 'The PUT method has not been defined for collections');
    }

    /**
     * Update a resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function update($id, $data)
    {
        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
