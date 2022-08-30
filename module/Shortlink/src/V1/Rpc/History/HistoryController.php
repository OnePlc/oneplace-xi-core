<?php
namespace Shortlink\V1\Rpc\History;

use Application\Controller\IndexController;
use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Laminas\View\View;

class HistoryController extends AbstractActionController
{
    /**
     * Api Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * Shortlink Provider Table
     *
     * @var TableGateway $mShortProviderTbl
     * @since 1.0.0
     */
    protected $mShortProviderTbl;

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
        $this->mShortProviderTbl = new TableGateway('shortlink', $mapper);
        $this->mShortDoneTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mApiTools = new ApiTools($mapper);
        $this->mSecTools = new SecurityTools($mapper);
    }

    public function historyAction()
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

        if($request->isGet()) {
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            if($page <= 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid Page'));
            }
            $pageSize = 10;

            $provSel = new Select($this->mShortProviderTbl->getTable());
            $provSel->where(['active' => 1]);
            $provSel->order('sort_id ASC');
            $shortlinksDB = $this->mShortProviderTbl->selectWith($provSel);
            $shortlinksById = [];
            foreach($shortlinksDB as $sh) {
                # get links for provider
                $shortlinksById[$sh->Shortlink_ID] = ['name' => $sh->label, 'reward' => $sh->reward];
            }

            # Compile history
            $history = [];
            $historySel = new Select($this->mShortDoneTbl->getTable());
            $historySel->where(['user_idfs' => $me->User_ID]);
            $historySel->order('date_started DESC');
            # Create a new pagination adapter object
            $oPaginatorAdapter = new DbSelect(
            # our configured select object
                $historySel,
                # the adapter to run it against
                $this->mShortDoneTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $offersPaginated = new Paginator($oPaginatorAdapter);
            $offersPaginated->setCurrentPageNumber($page);
            $offersPaginated->setItemCountPerPage($pageSize);
            foreach($offersPaginated as $offer) {
                $history[] = (object)[
                    'date_start' => $offer->date_started,
                    'date_done' => $offer->date_completed,
                    'reward' => $shortlinksById[$offer->shortlink_idfs]['reward'],
                    'name' => $shortlinksById[$offer->shortlink_idfs]['name'],
                    'shortlink' => $shortlinksById[$offer->shortlink_idfs]['name'],
                    'status' => ($offer->date_completed == '0000-00-00 00:00:00') ? 'started' : 'done',
                ];
            }

            $totalDoneWh = new Where();
            $totalDoneWh->equalTo('user_idfs', $me->User_ID);
            $totalLinksDone = $this->mShortDoneTbl->select($totalDoneWh)->count();

            return new ViewModel([
                'history' => [
                    'items' => $history,
                    'total_items' => $totalLinksDone,
                    'page_size' => $pageSize,
                    'page' => $page,
                    'page_count' => (round($totalLinksDone/$pageSize) > 0) ? round($totalLinksDone/$pageSize) : 1,
                ]
            ]);
        }
    }
}
