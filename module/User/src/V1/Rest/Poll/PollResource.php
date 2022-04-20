<?php
namespace User\V1\Rest\Poll;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;

class PollResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Poll Table
     *
     * @var TableGateway $mPollTbl
     * @since 1.0.0
     */
    protected $mPollTbl;

    /**
     * Poll Choice Table
     *
     * @var TableGateway $mPollChoiceTbl
     * @since 1.0.0
     */
    protected $mPollChoiceTbl;

    /**
     * Poll Vote Table
     *
     * @var TableGateway $mPollVoteTbl
     * @since 1.0.0
     */
    protected $mPollVoteTbl;

    /**
     * User Tools
     *
     * @var UserTools $mUsrTools
     * @since 1.0.0
     */
    protected $mUsrTools;

    /**
     * Constructor
     *
     * PollResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mPollTbl = new TableGateway('poll', $mapper);
        $this->mPollVoteTbl = new TableGateway('poll_vote', $mapper);
        $this->mPollChoiceTbl = new TableGateway('poll_choice', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mUsrTools = new UserTools($mapper);
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
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $openPolls = [];
        $archive = [];
        $polls = $this->mPollTbl->select();
        foreach($polls as $poll) {
            if($poll->xp_level > $user->xp_level) {
                continue;
            }
            $userVote = $this->mPollVoteTbl->select(['user_idfs' => $user->User_ID, 'poll_idfs' => $poll->Poll_ID]);
            $choices = [];
            $results = [];
            $voted = false;
            $totalVotes = 0;

            if($userVote->count() == 0 && $user->User_ID != 335874987 && $poll->archived == 0) {
                $pollChoices = $this->mPollChoiceTbl->select(['poll_idfs' => $poll->Poll_ID]);
                if(count($pollChoices) > 0) {
                    foreach($pollChoices as $choice) {
                        $choices[] = (object)[
                            'id' => $choice->choice_id,
                            'name' => $choice->choice_label
                        ];
                    }
                }
            } else {
                $voted = true;
                $pollVotes = $this->mPollVoteTbl->select(['poll_idfs' => $poll->Poll_ID]);
                $totalVotes = $pollVotes->count();
                if($totalVotes > 0) {
                    $byChoice = [];
                    foreach($pollVotes as $vote) {
                        if(!array_key_exists($vote->vote,$byChoice)) {
                            $choice = $this->mPollChoiceTbl->select(['poll_idfs' => $poll->Poll_ID,'choice_id' => $vote->vote])->current();
                            $byChoice[$vote->vote] = ['id' => $vote->vote,'name' => $choice->choice_label,'votes' => 0];
                        }
                        $byChoice[$vote->vote]['votes']++;
                    }
                    foreach($byChoice as $bC) {
                        $perc = 0;
                        if($bC['votes'] > 0) {
                            $perc = round(100*($bC['votes']/$totalVotes));
                        }
                        $bC['percent'] = $perc;
                        $results[] = $bC;
                    }
                }
            }
            if($poll->archived == 0) {
                $openPolls[] = (object)[
                    'id' => $poll->Poll_ID,
                    'question' => $poll->question,
                    'choices' => $choices,
                    'results' => $results,
                    'voted' => $voted,
                    'total' => $totalVotes
                ];
            } else {
                $archive[] = (object)[
                    'id' => $poll->Poll_ID,
                    'question' => $poll->question,
                    'choices' => $choices,
                    'results' => $results,
                    'voted' => $voted,
                    'comment' => $poll->comment,
                    'total' => $totalVotes
                ];
            }

        }

        return [
            'poll' => $openPolls,
            'archive' => $archive,
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
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $pollId = filter_var($id, FILTER_SANITIZE_NUMBER_INT);
        $vote = filter_var($data->vote, FILTER_SANITIZE_NUMBER_INT);

        $pollInfo = $this->mPollTbl->select(['Poll_ID' => $pollId]);
        if($pollInfo->count() == 0) {
            return new ApiProblem(404, 'Poll not found');
        }
        $pollInfo = $pollInfo->current();
        if($pollInfo->archived == 1) {
            return new ApiProblem(400, 'Poll is closed');
        }

        $choice = $this->mPollChoiceTbl->select(['poll_idfs' => $pollId,'choice_id' => $vote]);
        if($choice->count() == 0) {
            return new ApiProblem(400, 'Invalid choice');
        }

        $userVote = $this->mPollVoteTbl->select(['user_idfs' => $user->User_ID, 'poll_idfs' => $pollId]);
        if($userVote->count() == 0) {
            $this->mPollVoteTbl->insert([
                'user_idfs' => $user->User_ID,
                'poll_idfs' => $pollId,
                'vote' => $vote,
                'date' => date('Y-m-d H:i:s', time())
            ]);

            $xpInfo = $this->mUsrTools->addXP('poll-vote', $user->User_ID);

            return [
                'xp_info' => $xpInfo
            ];
        } else {
            return new ApiProblem(400, 'You have already voted for this poll');
        }
    }
}
