<?php
/**
 * JoinController.php - Join Controller
 *
 * Main Controller for Managing Guild Join Requests
 *
 * @category Controller
 * @package Guild
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.0
 * @since 1.1.1
 */

namespace Guild\V1\Rpc\Join;

use Application\Controller\IndexController;
use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ViewModel;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;

class JoinController extends AbstractActionController
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

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
     * Guild Rank Table
     *
     * @var TableGateway $mGuildRankTbl
     * @since 1.0.0
     */
    protected $mGuildRankTbl;

    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * User Settings Table
     *
     * @var TableGateway $mUserSetTbl
     * @since 1.0.0
     */
    protected $mUserSetTbl;

    /**
     * Guild Rank Permission Table
     *
     * @var TableGateway $mRankPermTbl
     * @since 1.0.0
     */
    protected $mRankPermTbl;

    /**
     * Constructor
     *
     * BankController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        # Init Tables for this API
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mUserSetTbl = new TableGateway('user_setting', $mapper);
        $this->mGuildRankTbl = new TableGateway('faucet_guild_rank', $mapper);
        $this->mRankPermTbl = new TableGateway('faucet_guild_rank_permission', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function joinAction()
    {
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblemResponse(new ApiProblem(401, 'Not logged in'));
        }
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return new ApiProblemResponse($me);
        }

        # check if user already has joined or created a guild
        $checkWh = new Where();
        $checkWh->equalTo('user_idfs', $me->User_ID);
        $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
        $userHasGuild = $this->mGuildUserTbl->select($checkWh);
        if(count($userHasGuild) == 0) {
            return new ApiProblemResponse(new ApiProblem(409, 'You are not part of a guild and so not eligable to deposit to a guildbank'));
        }
        $userHasGuild = $userHasGuild->current();
        if($userHasGuild->rank != 0) {
            $invitePerm = $this->mRankPermTbl->select(['rank_idfs' => $userHasGuild->rank,'guild_idfs' => $userHasGuild->guild_idfs,'permission' => 'invite']);
            if($invitePerm->count() == 0) {
                return new ApiProblemResponse(new ApiProblem(409, 'You must be guildmaster or have the invite permission to manage invites'));
            }
        }

        # Load Guild Data
        $guild = $this->mGuildTbl->select(['Guild_ID' => $userHasGuild->guild_idfs]);
        if(count($guild) == 0) {
            return new ApiProblemResponse(new ApiProblem(404, 'Guild not found'));
        }
        $guild = $guild->current();

        # Get Request Data
        $request = $this->getRequest();
        if($request->isGet()) {
            /**
             * Load Guild Members List (paginated)
             */
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $guildMembers = [];
            $pageSize = 25;
            $memberSel = new Select($this->mGuildUserTbl->getTable());
            $checkWh = new Where();
            $checkWh->equalTo('guild_idfs', $guild->Guild_ID);
            $checkWh->like('date_joined', '0000-00-00 00:00:00');
            $checkWh->like('date_declined', '0000-00-00 00:00:00');
            $memberSel->where($checkWh);
            # Create a new pagination adapter object
            $oPaginatorAdapter = new DbSelect(
            # our configured select object
                $memberSel,
                # the adapter to run it against
                $this->mGuildUserTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $membersPaginated = new Paginator($oPaginatorAdapter);
            $membersPaginated->setCurrentPageNumber($page);
            $membersPaginated->setItemCountPerPage($pageSize);
            foreach($membersPaginated as $guildMember) {
                $member = $this->mUserTbl->select(['User_ID' => $guildMember->user_idfs]);
                if(count($member) > 0) {
                    $member = $member->current();
                    $guildMembers[] = (object)[
                        'id' => $member->User_ID,
                        'name' => $member->username,
                        'date_requested' => $guildMember->date_requested,
                        'xp_level' => $member->xp_level,
                    ];
                }
            }
            $totalMembers = $this->mGuildUserTbl->select($checkWh)->count();

            return [
                'request' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'page_count' => (round($totalMembers/$pageSize) > 0) ? round($totalMembers/$pageSize) : 1,
                    'items' => $guildMembers,
                    'total_items' => $totalMembers,
                ],
            ];
        }

        if($request->isPost()) {
            $json = IndexController::loadJSONFromRequestBody(['user_id','accept'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }
            # check for attack vendors
            $secResult = $this->mSecTools->basicInputCheck([$json->amount]);
            if($secResult !== 'ok') {
                # ban user and force logout on client
                $this->mUserSetTbl->insert([
                    'user_idfs' => $me->User_ID,
                    'setting_name' => 'user-tempban',
                    'setting_value' => 'Potential '.$secResult.' Attack @ '.date('Y-m-d H:i:s').' Guild Join',
                ]);
                return new ApiProblemResponse(new ApiProblem(418, 'Potential '.$secResult.' Attack - Goodbye'));
            }

            /**
             * Check if User exists
             */
            $userId = filter_var($json->user_id, FILTER_SANITIZE_NUMBER_INT);
            $userFound = $this->mUserTbl->select(['User_ID' => $userId]);
            if(count($userFound) == 0) {
                return new ApiProblemResponse(new ApiProblem(404, 'User not found'));
            }
            $userFound = $userFound->current();

            $accept = filter_var($json->accept, FILTER_SANITIZE_NUMBER_INT);
            if($accept != 0 && $accept != 1) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid command'));
            }

            # Accept or Decline Join
            $actionLabel = 'accepted';
            if($accept == 0) {
                $actionLabel = 'declined';
                $this->mGuildUserTbl->update([
                    'date_declined' => date('Y-m-d H:i:s', time()),
                ], [
                    'guild_idfs' => $guild->Guild_ID,
                    'user_idfs' => $userId
                ]);
            } else {
                $this->mGuildUserTbl->update([
                    'date_joined' => date('Y-m-d H:i:s', time()),
                ], [
                    'guild_idfs' => $guild->Guild_ID,
                    'user_idfs' => $userId
                ]);
            }

            /**
             * Load Guild Members List (paginated)
             */
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $guildMembers = [];
            $pageSize = 25;
            $memberSel = new Select($this->mGuildUserTbl->getTable());
            $checkWh = new Where();
            $checkWh->equalTo('guild_idfs', $guild->Guild_ID);
            $checkWh->like('date_joined', '0000-00-00 00:00:00');
            $checkWh->like('date_declined', '0000-00-00 00:00:00');
            $memberSel->where($checkWh);
            # Create a new pagination adapter object
            $oPaginatorAdapter = new DbSelect(
            # our configured select object
                $memberSel,
                # the adapter to run it against
                $this->mGuildUserTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $membersPaginated = new Paginator($oPaginatorAdapter);
            $membersPaginated->setCurrentPageNumber($page);
            $membersPaginated->setItemCountPerPage($pageSize);
            foreach($membersPaginated as $guildMember) {
                $member = $this->mUserTbl->select(['User_ID' => $guildMember->user_idfs]);
                if(count($member) > 0) {
                    $member = $member->current();
                    $guildMembers[] = (object)[
                        'id' => $member->User_ID,
                        'name' => $member->username,
                        'date_requested' => $guildMember->date_requested,
                        'xp_level' => $member->xp_level,
                    ];
                }
            }
            $totalMembers = $this->mGuildUserTbl->select($checkWh)->count();

            return [
                'state' => 'success',
                'message' => 'Successfully '.$actionLabel.' Join Request for User '.$userFound->username,
                'request' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'page_count' => (round($totalMembers/$pageSize) > 0) ? round($totalMembers/$pageSize) : 1,
                    'items' => $guildMembers,
                    'total_items' => $totalMembers,
                ],
            ];
        }

        if($request->isPut()) {
            $json = IndexController::loadJSONFromRequestBody(['user_id','new_rank'],$this->getRequest()->getContent());
            if(!$json) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }

            $userId = filter_var($json->user_id, FILTER_SANITIZE_NUMBER_INT);
            $newRankId = filter_var($json->new_rank, FILTER_SANITIZE_NUMBER_INT);

            $this->mGuildUserTbl->update([
                'rank' => $newRankId
            ],[
                'user_idfs' => $userId,
                'guild_idfs' => $guild->Guild_ID
            ]);

            $guildRanks = [];
            $guildRanksDB = $this->mGuildRankTbl->select(['guild_idfs' => $guild->Guild_ID]);
            if(count($guildRanksDB) > 0) {
                foreach($guildRanksDB as $rank) {
                    $guildRanks[$rank->level] = $rank->label;
                }
            }

            /**
             * Load Guild Members List (paginated)
             */
            $pageSize = 25;
            $page = (isset($_REQUEST['page'])) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $guildMembers = [];
            $memberSel = new Select($this->mGuildUserTbl->getTable());
            $checkWh = new Where();
            $checkWh->equalTo('guild_idfs', $guild->Guild_ID);
            $checkWh->notLike('date_joined', '0000-00-00 00:00:00');
            $memberSel->where($checkWh);
            $memberSel->order('rank ASC');
            # Create a new pagination adapter object
            $oPaginatorAdapter = new DbSelect(
            # our configured select object
                $memberSel,
                # the adapter to run it against
                $this->mGuildUserTbl->getAdapter()
            );
            # Create Paginator with Adapter
            $membersPaginated = new Paginator($oPaginatorAdapter);
            $membersPaginated->setCurrentPageNumber($page);
            $membersPaginated->setItemCountPerPage($pageSize);
            foreach($membersPaginated as $guildMember) {
                $member = $this->mUserTbl->select(['User_ID' => $guildMember->user_idfs]);
                if(count($member) > 0) {
                    $member = $member->current();
                    $guildMembers[] = (object)[
                        'id' => $member->User_ID,
                        'name' => $member->username,
                        'xp_level' => $member->xp_level,
                        'rank' => (object)[
                            'id' => $guildMember->rank,
                            'name'=> $guildRanks[$guildMember->rank]
                        ]
                    ];
                }
            }
            $totalMembers = $this->mGuildUserTbl->select($checkWh)->count();

            return [
                'page' => $page,
                'page_size' => $pageSize,
                'page_count' => (round($totalMembers/$pageSize) > 0) ? round($totalMembers/$pageSize) : 1,
                'items' => $guildMembers,
                'total_items' => $totalMembers,
            ];
        }

        if($request->isDelete()) {
            $ban = filter_var($_REQUEST['ban'], FILTER_SANITIZE_NUMBER_INT);
            if($ban != 1 && $ban != 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'Invalid JSON Body'));
            }

            $userId = filter_var($_REQUEST['user_id'], FILTER_SANITIZE_NUMBER_INT);

            $gCheck = $this->mGuildUserTbl->select([
                'user_idfs' => $userId,
                'guild_idfs' => $guild->Guild_ID
            ]);

            if(count($gCheck) == 0) {
                return new ApiProblemResponse(new ApiProblem(400, 'User is not in guild'));
            }

            if($ban == 1) {
                $this->mGuildUserTbl->update([
                    'date_joined' => '0000-00-00 00:00:00',
                    'date_declined' => date('Y-m-d H:i:s', time()),
                ],[
                    'user_idfs' => $userId,
                    'guild_idfs' => $guild->Guild_ID
                ]);
            } else {
                $this->mGuildUserTbl->delete([
                    'user_idfs' => $userId,
                    'guild_idfs' => $guild->Guild_ID
                ]);
            }

            return [
                'state' => 'done'
            ];
        }

        return new ApiProblemResponse(new ApiProblem(405, 'Method not allowed'));
    }
}
