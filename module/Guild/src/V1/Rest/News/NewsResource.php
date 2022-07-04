<?php
namespace Guild\V1\Rest\News;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class NewsResource extends AbstractResourceListener
{
    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Guild News Table
     *
     * @var TableGateway $mGuildNewsTbl
     * @since 1.0.0
     */
    protected $mGuildNewsTbl;

    /**
     * Guild Table User Table
     *
     * Relation between Guild and User
     * to determine if user has a guild and
     * if yes what guild it is
     *
     * @var TableGateway $mGuildUserTbl
     * @since 1.0.0
     */
    protected $mGuildUserTbl;

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
     * AchievementResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mGuildNewsTbl = new TableGateway('faucet_guild_news', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
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

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) == 0) {
            return new ApiProblem(404, 'User is not part of any guild.');
        } else {
            # only guildmaster is allowed to see this info
            $userGuildInfo = $userHasGuild->current();
            $guildId = $userGuildInfo->guild_idfs;
            if ($userGuildInfo->rank == 0) {
                $newsTitle = filter_var($data->news_title, FILTER_SANITIZE_STRING);
                $newsContent = filter_var($data->news_content, FILTER_SANITIZE_STRING);

                $checkWh = new Where();
                $checkWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-24 hours')));
                $checkWh->equalTo('guild_idfs', $guildId);
                $lastNews = $this->mGuildNewsTbl->select($checkWh);

                if($lastNews->count() == 0) {
                    $this->mGuildNewsTbl->insert([
                        'guild_idfs' => $guildId,
                        'user_idfs' => $me->User_ID,
                        'title' => $newsTitle,
                        'content' => $newsContent,
                        'date' => date('Y-m-d H:i:s', time())
                    ]);

                    return [
                        'state' => 'done'
                    ];
                } else {
                    return new ApiProblem(403, 'You can post news only once per day ( 24 hours ).');
                }
            } else {
                return new ApiProblem(403, 'You must be a guildmaster to add news.');
            }
        }
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

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);

        if(count($userHasGuild) == 0) {
            return new ApiProblem(404, 'User is not part of any guild.');
        } else {
            # only guildmaster is allowed to see this info
            $userGuildInfo = $userHasGuild->current();
            $guildId = $userGuildInfo->guild_idfs;

            $newsSel = new Select($this->mGuildNewsTbl->getTable());
            $newsSel->order('date DESC');
            $newsSel->limit(10);
            $newsSel->where(['guild_idfs' => $guildId]);
            $newsSel->join(['u' => 'user'],'u.User_ID = faucet_guild_news.user_idfs', ['username']);

            $recentNews = $this->mGuildNewsTbl->selectWith($newsSel);

            $news = [];
            if($recentNews->count() > 0) {
                foreach ($recentNews as $new) {
                    $news[] = [
                        'id' => $new->News_ID,
                        'author' => $new->username,
                        'title' => $new->title,
                        'content' => $new->content,
                        'date' => $new->date
                    ];
                }
            }

            return [
                'news' => $news
            ];
        }
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
