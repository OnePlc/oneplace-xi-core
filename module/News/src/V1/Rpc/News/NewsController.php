<?php
/**
 * NewsController.php - News Controller
 *
 * Main Resource for Faucet News
 *
 * @category Controller
 * @package News
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */
namespace News\V1\Rpc\News;

use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class NewsController extends AbstractActionController
{
    /**
     * News Table
     *
     * @var TableGateway $mNewsTbl
     * @since 1.0.0
     */
    protected $mNewsTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mNewsTbl = new TableGateway('faucet_news', $mapper);
    }

    /**
     * Get current News
     *
     * @return array
     * @since 1.0.0
     */
    public function newsAction()
    {
        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $website = (isset($_REQUEST['website'])) ? filter_var($_REQUEST['website'], FILTER_SANITIZE_STRING) : 'sf';
        if($website != 'sf' && $website != 'ca') {
            $website = 'sf';
        }
        $lang = (isset($_REQUEST['lang'])) ? filter_var($_REQUEST['lang'], FILTER_SANITIZE_STRING) : 'en';


        $pageSize = 25;
        $news = [];
        $memberSel = new Select($this->mNewsTbl->getTable());
        if($lang != 'en') {
            $memberSel->join(['nt' => 'faucet_news_translation'],'nt.news_idfs = faucet_news.News_ID');
            $memberSel->where(['website' => $website,'nt.language' => $lang]);
        } else {
            $memberSel->where(['website' => $website]);
        }
        $memberSel->order('date DESC');
        # Create a new pagination adapter object
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $memberSel,
            # the adapter to run it against
            $this->mNewsTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $newsPaginated = new Paginator($oPaginatorAdapter);
        $newsPaginated->setCurrentPageNumber($page);
        $newsPaginated->setItemCountPerPage($pageSize);
        foreach($newsPaginated as $article) {
            if($lang != 'en') {
                $news[] = (object)[
                    'id' => $article->News_ID,
                    'title' => $article->t_title,
                    'description' => str_replace(['##','- '],['<br/>##','<br/>- '],$article->t_description),
                    'date' => $article->date,
                ];
            } else {
                $news[] = (object)[
                    'id' => $article->News_ID,
                    'title' => $article->title,
                    'description' => str_replace(['##','- '],['<br/>##','<br/>- '],$article->description),
                    'date' => $article->date,
                ];
            }
        }
        $newsCount = $this->mNewsTbl->select()->count();

        return [
            '_links' => (object)['self' => (object)['href' => 'https://xi.api.swissfaucet.io/news']],
            'total_items' => $newsCount,
            'page_size' => $pageSize,
            'page_count' => (round($newsCount/$pageSize) > 0) ? round($newsCount/$pageSize) : 1,
            'page' => $page,
            'news' => $news,
        ];
    }
}
