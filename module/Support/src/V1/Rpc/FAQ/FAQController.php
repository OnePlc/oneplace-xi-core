<?php
namespace Support\V1\Rpc\FAQ;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class FAQController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * FAQ Table
     *
     * @var TableGateway $mFAQTbl
     * @since 1.0.0
     */
    protected $mFAQTbl;

    /**
     * FAQ Category Table
     *
     * @var TableGateway $mFAQCatTbl
     * @since 1.0.0
     */
    protected $mFAQCatTbl;

    /**
     * Constructor
     *
     * FAQController constructor.
     * @param Adapter $mapper
     * @since 1.0.0
     */
    public function __construct(Adapter $mapper)
    {
        # Init Tables for this API
        $this->mSecTools = new SecurityTools($mapper);
        $this->mFAQTbl = new TableGateway('faucet_faq', $mapper);
        $this->mFAQCatTbl = new TableGateway('faucet_faq_category', $mapper);
    }

    /**
     * Get FAQ by Category
     *
     * @return array|ApiProblemResponse
     * @since 1.0.0
     */
    public function fAQAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            $categoryName = filter_var($_REQUEST['category'], FILTER_SANITIZE_STRING);

            $categoryFound = $this->mFAQCatTbl->select(['url' => $categoryName]);
            if(count($categoryFound) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'Category not found'));
            }
            $category = $categoryFound->current();

            $faqCompiled = [];
            $faqSel = new Select($this->mFAQTbl->getTable());
            $faqSel->where(['category_idfs' => $category->Category_ID]);
            $faqSel->order('sort_id ASC');
            $faqFound = $this->mFAQTbl->selectWith($faqSel);
            if(count($faqFound) > 0) {
                foreach($faqFound as $faq) {
                    $faqCompiled[] = (object)[
                      'id' => $faq->FAQ_ID,
                      'question' => $faq->question,
                      'answer' => utf8_decode($faq->answer),
                      'sort_id' => $faq->sort_id
                    ];
                }
            }

            return [
                'faq' => $faqCompiled
            ];
        }
    }
}
