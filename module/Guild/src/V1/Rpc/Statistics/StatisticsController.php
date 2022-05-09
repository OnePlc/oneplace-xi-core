<?php
namespace Guild\V1\Rpc\Statistics;

use Faucet\Tools\SecurityTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class StatisticsController extends AbstractActionController
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
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

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

        $this->mSecTools = new SecurityTools($mapper);
    }

    public function statisticsAction()
    {
        # Get Request Data
        $request = $this->getRequest();
        if($request->isGet()) {
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
                return new ApiProblemResponse(new ApiProblem(409, 'You are not part of a guild'));
            }
            $guildId = $userHasGuild->current()->guild_idfs;

            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            $gWh->like('ufs.stat_key', 'shdone-m-'.date('n-Y', time()));

            /**
             * Get Top 10 Shortlinks
             */
            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $shortsByUserId = [];
            foreach($shortStats as $sh) {
                $shortsByUserId[$sh->user_idfs] = ['amount' => $sh->stat_data,'id' => $sh->user_idfs, 'name' => $sh->username];
            }
            arsort($shortsByUserId);

            $top10SH = [];
            $count = 1;
            foreach($shortsByUserId as $topSh) {
                $top10SH[] = $topSh;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 Offerwalls
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            $gWh->like('ufs.stat_key', 'ofdone-m-'.date('n-Y', time()));

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $offersByUserId = [];
            foreach($shortStats as $sh) {
                $offersByUserId[$sh->user_idfs] = ['amount' => $sh->stat_data,'id' => $sh->user_idfs, 'name' => $sh->username];
            }
            arsort($offersByUserId);

            $top10OF = [];
            $count = 1;
            foreach($offersByUserId as $topOf) {
                $top10OF[] = $topOf;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            /**
             * Get Top 10 Claimers
             */
            $gWh = new Where();
            $gWh->notLike('date_joined', '0000-00-00 00:00:00');
            $gWh->equalTo('guild_idfs', $guildId);
            $gWh->like('ufs.stat_key', 'fclaim-m-web-'.date('n-Y', time()));

            $gSel = new Select($this->mGuildUserTbl->getTable());
            $gSel->join(['u' => 'user'],'faucet_guild_user.user_idfs = u.User_ID',['username']);
            $gSel->join(['ufs' => 'user_faucet_stat'],'ufs.user_idfs = faucet_guild_user.user_idfs',['stat_data']);
            $gSel->where($gWh);
            $shortStats = $this->mGuildUserTbl->selectWith($gSel);

            $claimsByUserId = [];
            foreach($shortStats as $sh) {
                $claimsByUserId[$sh->user_idfs] = ['amount' => $sh->stat_data,'id' => $sh->user_idfs, 'name' => $sh->username];
            }
            arsort($claimsByUserId);

            $top10Cl = [];
            $count = 1;
            foreach($claimsByUserId as $topCl) {
                $top10Cl[] = $topCl;
                if($count == 10) {
                    break;
                }
                $count++;
            }

            return [
                'shortlink' => $top10SH,
                'offerwall' => $top10OF,
                'faucet' => $top10Cl
            ];
        }
        return new ApiProblemResponse(new ApiProblem(403, 'Not allowed'));
    }
}
