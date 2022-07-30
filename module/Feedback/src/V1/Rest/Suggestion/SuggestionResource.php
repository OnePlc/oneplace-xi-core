<?php
namespace Feedback\V1\Rest\Suggestion;

use Faucet\Tools\ApiTools;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
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

        $this->mTokenVoteComment = 5;
        $this->mTokenCreate = 50;

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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # Check if user is verified
        if($me->email_verified == 0) {
            return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before submitting a suggestion.'));
        }

        $tokensBuys = $this->mTokenTbl->select(['sent' => 1, 'user_idfs' => $me->User_ID]);
        if($tokensBuys->count() == 0) {
            return new ApiProblemResponse(new ApiProblem(400, 'You need to own at least '.$this->mTokenCreate.' Token to comment or upvote a suggestion'));
        }
        $tokens = 0;
        foreach($tokensBuys as $tb) {
            $tokens += $tb->amount;
        }
        if($tokens < $this->mTokenCreate) {
            return new ApiProblemResponse(new ApiProblem(400, 'You need to own at least '.$this->mTokenCreate.' Token to comment or upvote a suggestion'));
        }

        # check inputs for malicious code
        $secResult = $this->mSecTools->basicInputCheck([
            $data->title,
            $data->description,
        ]);
        if($secResult !== 'ok') {
            return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
        }

        # sanitize inputs
        $title = filter_var($data->title, FILTER_SANITIZE_STRING);
        $description = filter_var($data->description, FILTER_SANITIZE_STRING);

        if(strlen($title) < 10) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid title'));
        }
        if(strlen($description) < 10) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid description'));
        }
        $file = null;

        if(isset($_FILES["file"]["tmp_name"])) {
            $secResult = $this->mSecTools->basicInputCheck([
                $_FILES["file"]["name"]
            ]);
            if($secResult !== 'ok') {
                return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
            }

            $tmpName = $_FILES["file"]["tmp_name"];
            $extension = strtolower(pathinfo($_FILES["file"]["name"],PATHINFO_EXTENSION));
            if($extension != 'png' && $extension != 'jpg') {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid file type'));
            }
            $check = getimagesize($_FILES["file"]["tmp_name"]);
            if($check !== false) {
                // file is image all good
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'File is not an image'));
            }
            if ($_FILES["file"]["size"] > 1000000) {
                return new ApiProblemResponse(new ApiProblem(400, 'File is too large ( 1MB max )'));
            }

            $path = __DIR__.'/../../../../data';
            $randName = substr(str_shuffle(MD5(microtime())), 0, 20);

            if (move_uploaded_file($tmpName, $path.'/'.$randName.'.'.$extension)) {
                $file = $randName.'.'.$extension;
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'Error while uploading image'));
            }
        }

        # check for existing post within 24hours
        $checkWh = new Where();
        $checkWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-24 hours')));
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $lastPost = $this->mFeedbackTbl->select($checkWh);

        if($lastPost->count() == 0) {
            # save feedback
            $this->mFeedbackTbl->insert([
                'title' => $title,
                'description' => $description,
                'image' => $file,
                'user_idfs' => $me->User_ID,
                'date' => date('Y-m-d H:i:s', time())
            ]);

            return true;
        } else {
            return new ApiProblemResponse(new ApiProblem(400, 'You can only post 1 suggestion per day'));
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        $feedbackId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        if($feedbackId <= 0 || empty($feedbackId)) {
            return new ApiProblemResponse(new ApiProblem(403, 'Invalid Feedback Id'));
        }

        $feedbackSel = new Select($this->mFeedbackTbl->getTable());
        $feedbackSel->where(['Feedback_ID' => $feedbackId]);
        $feedbackSel->join(['u' => 'user'],'u.User_ID = feedback.user_idfs', ['username']);

        $feedback = $this->mFeedbackTbl->selectWith($feedbackSel);
        if($feedback->count() == 0) {
            return new ApiProblemResponse(new ApiProblem(404, 'Feedback not found'));
        }
        $feed = $feedback->current();

        $comments = [];
        $comSel = new Select($this->mFeedbackCommentTbl->getTable());
        $comSel->where(['feedback_idfs' => $feedbackId, 'verified' => 1]);
        $comSel->join(['u' => 'user'],'u.User_ID = feedback_comment.user_idfs', ['username']);

        $verifiedComments = $this->mFeedbackCommentTbl->selectWith($comSel);

        $totalComments = 0;
        foreach($verifiedComments as $comment) {
            $comments[] = [
                'id' => $comment->Comment_ID,
                'author' => $comment->username,
                'comment' => utf8_decode($comment->comment),
                'date' => $comment->date
            ];
            $totalComments++;
        }

        $tagSel = new Select($this->mFeedbackTagTbl->getTable());
        $tagSel->join(['ft' => 'feedback_tag'], 'ft.Tag_ID = feedback_tag_feedback.tag_idfs', ['tag_name', 'tag_color']);
        $tagSel->where(['feedback_idfs' => $feedbackId]);

        $feedbackTags = $this->mFeedbackTagTbl->selectWith($tagSel);
        $tags = [];
        foreach($feedbackTags as $tag) {
            $tags[] = [
                'name' => $tag->tag_name,
                'color' => $tag->tag_color
            ];
        }

        $image = '';
        if($feed->image != null && $feed->image != '') {
            $image = $this->mApiTools->getApiURL().'/feedback/'.$feed->image;
        }

        return [
            'feedback' => [
                'id' => $feed->Feedback_ID,
                'title' => $feed->title,
                'description' => nl2br($feed->description),
                'date' => $feed->date,
                'votes' => $feed->votes,
                'author' => $feed->username,
                'comments' => [
                    'comments' => $comments,
                    'total_comments' => $totalComments
                ],
                'image' => $image,
                'tags' => $tags
            ],
        ];
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # Check if user is verified
        if($me->email_verified == 0) {
            return new ApiProblemResponse(new ApiProblem(400, 'Account is not verified. Please verify E-Mail before voting up or commenting a suggestion.'));
        }

        $tokensBuys = $this->mTokenTbl->select(['sent' => 1, 'user_idfs' => $me->User_ID]);
        if($tokensBuys->count() == 0) {
            return new ApiProblemResponse(new ApiProblem(400, 'You need to own at least '.$this->mTokenVoteComment.' Token to comment or upvote a suggestion'));
        }
        $tokens = 0;
        foreach($tokensBuys as $tb) {
            $tokens += $tb->amount;
        }
        if($tokens < $this->mTokenVoteComment) {
            return new ApiProblemResponse(new ApiProblem(400, 'You need to own at least '.$this->mTokenVoteComment.' Token to comment or upvote a suggestion'));
        }

        /**
         * Up-Voting for Feedbacks
         */
        if($data->cmd == 'vote') {
            $feedbackId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
            if($feedbackId <= 0 || empty($feedbackId)) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid feedback id'));
            }
            $feedback = $this->mFeedbackTbl->select(['Feedback_ID' => $feedbackId]);
            if($feedback->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'feedback not found'));
            }
            $feedback = $feedback->current();
            $voteCheck = $this->mFeedbackVoteTbl->select(['feedback_idfs' => $feedbackId, 'user_idfs' => $me->User_ID]);
            if($voteCheck->count() == 0) {
                $this->mFeedbackVoteTbl->insert([
                    'feedback_idfs' => $feedbackId,
                    'user_idfs' => $me->User_ID,
                    'date' => date('Y-m-d H:i:s', time())
                ]);
                $this->mFeedbackTbl->update(['votes' => $feedback->votes+1], ['Feedback_ID' => $feedbackId]);
                return [
                    'votes' => $feedback->votes+1
                ];
            } else {
                return new ApiProblemResponse(new ApiProblem(400, 'You have already voted for this suggestion'));
            }
        }

        /**
         * Commenting on Feedbacks
         */
        if($data->cmd == 'comment') {
            $feedbackId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
            if($feedbackId <= 0 || empty($feedbackId)) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid feedback id'));
            }
            $feedback = $this->mFeedbackTbl->select(['Feedback_ID' => $feedbackId, 'verified' => 1]);
            if($feedback->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'feedback not found'));
            }

            $comCheckWh = new Where();
            $comCheckWh->equalTo('feedback_idfs', $feedbackId);
            $comCheckWh->equalTo('user_idfs', $me->User_ID);
            $comCheckWh->greaterThanOrEqualTo('date', date('Y-m-d H:i:s', strtotime('-24 hours')));

            $comCheck = $this->mFeedbackCommentTbl->select($comCheckWh);
            if($comCheck->count() == 0) {
                # check inputs for malicious code
                $secResult = $this->mSecTools->basicInputCheck([
                    $data->comment
                ]);
                if($secResult !== 'ok') {
                    return new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye');
                }

                # Save Comment
                $comment = filter_var($data->comment, FILTER_SANITIZE_STRING);
                if(strlen($comment) < 30) {
                    return new ApiProblem(400, 'Please post a real comment not just a word or two');
                }
                $this->mFeedbackCommentTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'feedback_idfs' => $feedbackId,
                    'comment' => utf8_encode($comment),
                    'date' => date('Y-m-d H:i:s', time())
                ]);

                return true;
            } else {
                return new ApiProblem(400, 'You can only post 1 comment every 24 hours');
            }
        }

        return new ApiProblem(405, 'Invalid Action');
    }
}
