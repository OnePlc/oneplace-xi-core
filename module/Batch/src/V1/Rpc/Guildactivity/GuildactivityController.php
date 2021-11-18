<?php
namespace Batch\V1\Rpc\Guildactivity;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;

class GuildactivityController extends AbstractActionController
{
    /**
     * User Table
     *
     * @var TableGateway $mUserTbl
     * @since 1.0.0
     */
    protected $mUserTbl;

    /**
     * Guild Table
     *
     * @var TableGateway $mGuildTbl
     * @since 1.0.0
     */
    protected $mGuildTbl;

    /**
     * Stat Table
     *
     * @var TableGateway $mStatTbl
     * @since 1.0.0
     */
    protected $mStatTbl;

    /**
     * SH Done Table
     *
     * @var TableGateway $mShortsTbl
     * @since 1.0.0
     */
    protected $mShortsTbl;

    /**
     * Offer Done Table
     *
     * @var TableGateway $mOffersTbl
     * @since 1.0.0
     */
    protected $mOffersTbl;

    /**
     * Miner Table
     *
     * @var TableGateway $mMinerTbl
     * @since 1.0.0
     */
    protected $mMinerTbl;

    /**
     * Guild Tasks Table
     *
     * @var TableGateway $mGtaskTbl
     * @since 1.0.0
     */
    protected $mGtaskTbl;

    /**
     * Guild Member Table
     *
     * @var TableGateway $mGuildUserTbl
     * @since 1.0.0
     */
    protected $mGuildUserTbl;

    /**
     * Guild Member Table
     *
     * @var TableGateway $mTransTbl
     * @since 1.0.0
     */
    protected $mTransTbl;

    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mUserTbl = new TableGateway('user', $mapper);
        $this->mGuildTbl = new TableGateway('faucet_guild', $mapper);
        $this->mStatTbl = new TableGateway('faucet_statistic', $mapper);
        $this->mShortsTbl = new TableGateway('shortlink_link_user', $mapper);
        $this->mOffersTbl = new TableGateway('offerwall_user', $mapper);
        $this->mMinerTbl = new TableGateway('faucet_miner', $mapper);
        $this->mGuildUserTbl = new TableGateway('faucet_guild_user', $mapper);
        $this->mGtaskTbl = new TableGateway('faucet_guild_weekly_claim', $mapper);
        $this->mTransTbl = new TableGateway('faucet_transaction', $mapper);
    }

    public function guildactivityAction()
    {
        $request = $this->getRequest();

        if($request->isGet()) {
            if (isset($_REQUEST['authkey'])) {
                if ($_REQUEST['authkey'] == 'refresh') {
                    $membersWh = new Where();
                    $membersWh->notEqualTo('date_joined', '0000-00-00 00:00:00');

                    $guildMembers = $this->mGuildUserTbl->select($membersWh);
                    $activityByTime = [];
                    $transWh = new Where();
                    $transWh->nest();
                    $iCount = 0;
                    $totalMembers = $guildMembers->count();
                    $membersByGuild = [];
                    foreach($guildMembers as $gm) {
                        if(!array_key_exists($gm->guild_idfs,$membersByGuild)) {
                            $membersByGuild[$gm->guild_idfs] = [];
                        }
                        $membersByGuild[$gm->guild_idfs][] = $gm->user_idfs;
                        /**
                        $transWh->equalTo('user_idfs', $gm->user_idfs);

                        if($iCount < ($totalMembers-1)) {
                            $transWh->or;
                        }
                        $iCount++;
                         * **/
                    }

                    foreach(array_keys($membersByGuild) as $guildId) {
                        $members = $membersByGuild[$guildId];
                        $guildWh = new Where();
                        $guildWh->nest();
                        $i = 0;
                        foreach($members as $member) {
                            if($i < count($members)-1) {

                            } else {
                                //$guildWh->or->equalTo('user_idfs', $member)->unnest();
                            }
                            $i++;
                        }
                        //$guildWh->unnest();
                    }

                    return [
                        'members' => $membersByGuild
                    ];
                    //$transWh->unnest();


                    $transSel = new Select($this->mTransTbl->getTable());
                    //$transSel->where($transWh);
                    $transSel->order('date DESC');
                    $transSel->limit(5);

                    $userTrans = $this->mTransTbl->selectWith($transSel);

                    foreach($userTrans as $t) {
                        if(!array_key_exists($gm->guild_idfs,$activityByTime)) {
                            $activityByTime[$gm->guild_idfs] = [];
                        }
                        $activityByTime[$gm->guild_idfs][strtotime($t->date).$t->user_idfs] = $t;
                    }

                    return [
                        'activity' => $activityByTime
                    ];
                }
            }
        }

        return new ApiProblem(405, 'Method not allowed');

    }
}
