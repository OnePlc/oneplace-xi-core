<?php
namespace News\V1\Rpc\Newsletter;

use Faucet\Tools\EmailTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Where;
use Laminas\Mvc\Controller\AbstractActionController;

class NewsletterController extends AbstractActionController
{
    /**
     * News Table
     *
     * @var TableGateway $mNewsTbl
     * @since 1.0.0
     */
    protected $mNewsTbl;

    /**
     * User Table
     *
     * @var TableGateway $mNewsTbl
     * @since 1.0.$mUserTbl
     */
    protected $mUserTbl;

    /**
     * E-Mail Helper
     *
     * @var EmailTools $mMailTools
     * @since 1.0.0
     */
    protected $mMailTools;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper, $viewRenderer)
    {
        $this->mNewsTbl = new TableGateway('faucet_news', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mMailTools = new EmailTools($mapper, $viewRenderer);
    }

    public function newsletterAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            # send verification email
            echo 'done';
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));

    }
}
