<?php
namespace Feedback\V1\Rest\Suggestion;

use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class SuggestionResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * API Tools Helper
     *
     * @var ApiTools $mApiTools
     * @since 1.0.0
     */
    protected $mApiTools;

    /**
     * Feedback Table
     *
     * @var TableGateway $mFeedbackTbl
     * @since 1.0.0
     */
    protected $mFeedbackTbl;

    /**
     * Feedback Vote Table
     *
     * @var TableGateway $mFeedbackVoteTbl
     * @since 1.0.0
     */
    protected $mFeedbackVoteTbl;

    /**
     * Feedback Comment Table
     *
     * @var TableGateway $mFeedbackCommentTbl
     * @since 1.0.0
     */
    protected $mFeedbackCommentTbl;

    /**
     * Feedback Tag Table
     *
     * @var TableGateway $mFeedbackTagTbl
     * @since 1.0.0
     */
    protected $mFeedbackTagTbl;

    /**
     * Token Table
     *
     * @var TableGateway $mTokenTbl
     * @since 1.0.0
     */
    protected $mTokenTbl;

    /**
     * Token Requirement for Voting and Commenting
     * @var integer $mTokenVoteComment
     * @since 2.0.0
     */
    protected $mTokenVoteComment;

    /**
     * Token Requirement create a new Suggestion
     * @var integer $mTokenCreate
     * @since 2.0.0
     */
    protected $mTokenCreate;

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
        $this->mFeedbackTbl = new TableGateway('feedback', $mapper);
        $this->mFeedbackVoteTbl = new TableGateway('feedback_vote', $mapper);
        $this->mFeedbackCommentTbl = new TableGateway('feedback_comment', $mapper);
        $this->mFeedbackTagTbl = new TableGateway('feedback_tag_feedback', $mapper);
        $this->mTokenTbl = new TableGateway('faucet_tokenbuy', $mapper);

        $this->mTokenVoteComment = 1;
        $this->mTokenCreate = 1;

        $this->mSecTools = new SecurityTools($mapper);
        $this->mApiTools = new ApiTools($mapper);
    }

    /**
     * Create a resource
     *
     * @param  mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
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
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # Get page
        $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
        $pageSize = 50;

        # prepare sql query
        $feedbackSel = new Select($this->mFeedbackTbl->getTable());
        $feedbackSel->order('date DESC');
        $feedbackSel->where(['verified' => 1]);
        $feedbackSel->join(['u' => 'user'],'u.User_ID = feedback.user_idfs', ['username']);

        # get paginated results
        $oPaginatorAdapter = new DbSelect(
        # our configured select object
            $feedbackSel,
            # the adapter to run it against
            $this->mFeedbackTbl->getAdapter()
        );
        # Create Paginator with Adapter
        $feedbackPaginated = new Paginator($oPaginatorAdapter);
        $feedbackPaginated->setCurrentPageNumber($page);
        $feedbackPaginated->setItemCountPerPage($pageSize);

        # get total number for pages
        $totalFeedback = $this->mFeedbackTbl->select(['verified' => 1])->count();

        # load tags
        $tagSel = new Select($this->mFeedbackTagTbl->getTable());
        $tagSel->join(['ft' => 'feedback_tag'], 'ft.Tag_ID = feedback_tag_feedback.tag_idfs', ['tag_name', 'tag_color']);
        $feedbackTags = $this->mFeedbackTagTbl->selectWith($tagSel);
        $feedbackTagsById = [];
        foreach($feedbackTags as $tag) {
            if(!array_key_exists($tag->feedback_idfs, $feedbackTagsById)) {
                $feedbackTagsById[$tag->feedback_idfs] = [];
            }
            $feedbackTagsById[$tag->feedback_idfs][] = [
                'name' => $tag->tag_name,
                'color' => $tag->tag_color
            ];
        }

        # generate list for response
        $feedback = [];
        if($feedbackPaginated->count() > 0) {
            foreach ($feedbackPaginated as $feed) {
                $tags = [];
                if(array_key_exists($feed->Feedback_ID, $feedbackTagsById)) {
                    $tags = $feedbackTagsById[$feed->Feedback_ID];
                }
                $feedback[] = [
                    'id' => $feed->Feedback_ID,
                    'title' => $feed->title,
                    'description' => $feed->description,
                    'date' => $feed->date,
                    'votes' => $feed->votes,
                    'author' => $feed->username,
                    'tags' => $tags
                ];
            }
        }

        # response
        return (object)[
            'feedback' => $feedback,
            'total_items' => $totalFeedback,
            'token' => [
                'vote_comment' => $this->mTokenVoteComment,
                'create' => $this->mTokenCreate
            ],
            'page_count' => ceil($totalFeedback/$pageSize)
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
