<?php
namespace Profession\V1\Rest\Professions;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class ProfessionsResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * Profession Table
     *
     * @var TableGateway $mProfTbl
     * @since 1.0.0
     */
    protected $mProfTbl;

    /**
     * Profession Level Table
     *
     * @var TableGateway $mProfLvlTbl
     * @since 1.0.0
     */
    protected $mProfLvlTbl;

    /**
     * Profession Skill Table
     *
     * @var TableGateway $mProfSkillTbl
     * @since 1.0.0
     */
    protected $mProfSkillTbl;

    /**
     * Profession User Table
     *
     * @var TableGateway $mProfUsrTbl
     * @since 1.0.0
     */
    protected $mProfUsrTbl;

    /**
     * Profession Skill User Table
     *
     * @var TableGateway $mProfSkillUsrTbl
     * @since 1.0.0
     */
    protected $mProfSkillUsrTbl;

    /**
     * Profession Skill Item Table
     *
     * @var TableGateway $mProfSkillItemTbl
     * @since 1.0.0
     */
    protected $mProfSkillItemTbl;

    /**
     * Item Table
     *
     * @var TableGateway $mItemTbl
     * @since 1.0.0
     */
    protected $mItemTbl;

    /**
     * Item User Table
     *
     * @var TableGateway $mItemUserTbl
     * @since 1.0.0
     */
    protected $mItemUserTbl;

    /**
     * Transaction Helper
     *
     * @var TransactionHelper $mTransaction
     * @since 1.0.0
     */
    protected $mTransaction;

    /**
     * Constructor
     *
     * MailboxController constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mProfTbl = new TableGateway('faucet_profession', $mapper);
        $this->mProfLvlTbl = new TableGateway('faucet_profession_level', $mapper);
        $this->mProfSkillTbl = new TableGateway('faucet_profession_skill', $mapper);
        $this->mProfUsrTbl = new TableGateway('faucet_profession_user', $mapper);
        $this->mProfSkillUsrTbl = new TableGateway('faucet_profession_skill_user', $mapper);
        $this->mProfSkillItemTbl = new TableGateway('faucet_profession_skill_item', $mapper);
        $this->mItemTbl = new TableGateway('faucet_item', $mapper);
        $this->mItemUserTbl = new TableGateway('faucet_item_user', $mapper);

        $this->mSecTools = new SecurityTools($mapper);
        $this->mTransaction = new TransactionHelper($mapper);
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
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $profId = filter_var($data->id, FILTER_SANITIZE_STRING);
        $profInfo = $this->mProfTbl->select(['url' => $profId]);
        if($profInfo->count() == 0) {
            return new ApiProblem(404, 'Profession not found');
        }
        $profInfo = $profInfo->current();

        # check if user already has unlocked profession
        $userProf = $this->mProfUsrTbl->select(['profession_idfs' => $profInfo->Profession_ID, 'user_idfs' => $user->User_ID]);
        if($userProf->count() == 0) {
            if($this->mTransaction->checkUserBalance($profInfo->price_unlock, $user->User_ID)) {
                $fNewBalance = $this->mTransaction->executeTransaction($profInfo->price_unlock, 1, $user->User_ID, $profInfo->Profession_ID, 'learn-prof', 'Learned Profession '.$profInfo->label);
                if($fNewBalance !== false) {
                    $date = date('Y-m-d H:i:s', time());

                    $this->mProfUsrTbl->insert([
                        'user_idfs' => $user->User_ID,
                        'profession_idfs' => $profInfo->Profession_ID,
                        'level' => 1,
                        'skill' => 1,
                        'date_learned' => $date
                    ]);

                    return [
                        'token_balance' => $fNewBalance,
                        'level' => 1,
                        'skill' => 1,
                        'date_learned' => $date
                    ];
                }
            } else {
                return new ApiProblem(400, 'Your balance is too low to learn this profession');
            }
        } else {
            return new ApiProblem(400, 'You have already learned this profession');
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
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $profUrl = filter_var($id, FILTER_SANITIZE_STRING);

        $profInfo = $this->mProfTbl->select(['url' => $profUrl]);
        if($profInfo->count() == 0) {
            return new ApiProblem(404, 'Profession not found');
        }
        $profInfo = $profInfo->current();

        $skills = [];
        $skSel = new Select($this->mProfSkillTbl->getTable());
        $skSel->where(['profession_idfs' => $profInfo->Profession_ID]);
        $skSel->order('skill ASC');
        $profSkills = $this->mProfSkillTbl->selectWith($skSel);

        # get learned skills
        $myProfSkills = $this->mProfSkillUsrTbl->select(['user_idfs' => $user->User_ID, 'profession_idfs' => $profInfo->Profession_ID]);
        $myProfSkillsById = [];
        if($myProfSkills->count() > 0) {
            foreach($myProfSkills as $mysk) {
                $myProfSkillsById[$mysk->skill_idfs] = true;
            }
        }

        $mode = 'learn';
        if(isset($_REQUEST['mode'])) {
            $modeSet = filter_var($_REQUEST['mode'], FILTER_SANITIZE_STRING);
            if($modeSet == 'use') {
                $mode = 'use';
            }
        }

        foreach($profSkills as $sk) {
            if($mode == 'learn') {
                # only show skills not learned yet
                if(!array_key_exists($sk->Skill_ID, $myProfSkillsById)) {
                    $skills[] = (object)[
                        'id' => $sk->Skill_ID,
                        'name' => $sk->label,
                        'description' => $sk->description,
                        'skill' => $sk->skill,
                        'price' => $sk->price,
                        'icon' => $sk->icon
                    ];
                }
            } else {
                # only show skills not learned yet
                if(array_key_exists($sk->Skill_ID, $myProfSkillsById)) {
                    $matSel = new Select($this->mProfSkillItemTbl->getTable());
                    $matSel->join(['item' => 'faucet_item'],'item.Item_ID = faucet_profession_skill_item.item_idfs');
                    $matSel->where(['faucet_profession_skill_item.skill_idfs' => $sk->Skill_ID]);
                    $mats = $this->mProfSkillItemTbl->selectWith($matSel);
                    $skillItems = [];
                    if($mats->count() > 0) {
                        // mItemUserTbl
                        foreach($mats as $mat) {
                            $invWh = new Where();
                            $invWh->greaterThan('amount', 0);
                            $invWh->equalTo('item_idfs', $mat->Item_ID);
                            $invWh->equalTo('user_idfs', $user->User_ID);
                            $itemInInventory = $this->mItemUserTbl->select($invWh);
                            $inInventory = 0;
                            if($itemInInventory->count() > 0) {
                                foreach($itemInInventory as $inv) {
                                    $inInventory+=$inv->amount;
                                }
                            }
                            $skillItems[] = (object)[
                                'id' => $mat->Item_ID,
                                'name' => $mat->label,
                                'icon' => $mat->icon,
                                'amount' => $mat->amount,
                                'inventory' => $inInventory,
                            ];
                        }
                    }

                    $skills[] = (object)[
                        'id' => $sk->Skill_ID,
                        'name' => $sk->label,
                        'description' => $sk->description,
                        'skill' => $sk->skill,
                        'price' => $sk->price,
                        'icon' => $sk->icon,
                        'materials' => $skillItems
                    ];
                }
            }
        }

        $mySkill = null;
        $levelSel = 1;
        $userProf = $this->mProfUsrTbl->select(['profession_idfs' => $profInfo->Profession_ID, 'user_idfs' => $user->User_ID]);
        if($userProf->count() != 0) {
            $userProf = $userProf->current();
            $levelSel = $userProf->level;
            $mySkill = (object)[
                'level' => $userProf->level,
                'skill' => $userProf->skill,
                'date_learned' => $userProf->date_learned,
            ];
        }

        $maxSkill = 0;
        $profLlvl = $this->mProfLvlTbl->select(['level' => $levelSel, 'profession_idfs' => $profInfo->Profession_ID]);
        if($profLlvl->count() > 0) {
            $profLlvl = $profLlvl->current();
            $maxSkill = $profLlvl->skill_end;
        }

        return [
            'profession' => (object)[
                'id' => $profInfo->Profession_ID,
                'name' => $profInfo->label,
                'url' => $profInfo->url,
                'price_unlock' => $profInfo->price_unlock,
                'description' => $profInfo->description,
                'max_skill' => $maxSkill,
                'level' => $levelSel,
            ],
            'skills' => $skills,
            'user_skill' => $mySkill
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
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $myProfessions = [];
        $userProfs = $this->mProfUsrTbl->select(['user_idfs' => $user->User_ID]);
        if($userProfs->count() > 0) {
            foreach($userProfs as $prof) {
                $profInfo = $this->mProfTbl->select(['Profession_ID' => $prof->profession_idfs]);
                if($profInfo->count() > 0) {
                    $profInfo = $profInfo->current();
                    $myProfessions[] = (object)[
                        'id' => $profInfo->Profession_ID,
                        'name' => $profInfo->label,
                        'description' => $profInfo->description,
                        'url' => $profInfo->url,
                        'level' => $prof->level,
                        'skill' => $prof->skill
                    ];
                }
            }
        }

        return $myProfessions;
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $skillInfo = (array)$data['skill'];
        $amountInfo = (array)$data['amount'];

        $skillId = filter_var($skillInfo[0], FILTER_SANITIZE_NUMBER_INT);
        $amount = filter_var($amountInfo[0], FILTER_SANITIZE_NUMBER_INT);

        # get skill
        $profSkill = $this->mProfSkillTbl->select(['Skill_ID' => $skillId]);
        if($profSkill->count() == 0) {
            return new ApiProblem(404, 'Skill not found --'.$skillId);
        }
        $profSkill = $profSkill->current();

        # check user has profession
        $userProf = $this->mProfUsrTbl->select(['profession_idfs' => $profSkill->profession_idfs, 'user_idfs' => $user->User_ID]);
        if($userProf->count() == 0) {
            return new ApiProblem(404, 'You have not unlocked the profession for this skill');
        }
        $userProf = $userProf->current();

        # check user has learned skill
        $skillLearned = $this->mProfSkillUsrTbl->select(['user_idfs' => $user->User_ID, 'skill_idfs' => $skillId]);
        if($skillLearned->count() == 0) {
            return new ApiProblem(404, 'You have not learned this skill yet test');
        }

        # get profession item info
        $profItem = $this->mItemTbl->select(['Item_ID' => $profSkill->item_idfs]);
        if($profItem->count() == 0) {
            return new ApiProblem(404, 'Profession Item not found');
        }
        $profItem = $profItem->current();

        # get skill items
        $matSel = new Select($this->mProfSkillItemTbl->getTable());
        $matSel->join(['item' => 'faucet_item'],'item.Item_ID = faucet_profession_skill_item.item_idfs');
        $matSel->where(['faucet_profession_skill_item.skill_idfs' => $skillId]);
        $mats = $this->mProfSkillItemTbl->selectWith($matSel);
        $skillItems = [];
        if($mats->count() > 0) {
            # check if user has all mats needed
            foreach($mats as $mat) {
                $invWh = new Where();
                $invWh->greaterThan('amount', 0);
                $invWh->equalTo('item_idfs', $mat->Item_ID);
                $invWh->equalTo('user_idfs', $user->User_ID);
                $itemInInventory = $this->mItemUserTbl->select($invWh);
                $inInventory = 0;
                if($itemInInventory->count() > 0) {
                    foreach($itemInInventory as $inv) {
                        $inInventory+=$inv->amount;
                    }
                }

                if($inInventory < ($mat->amount*$amount)) {
                    return new ApiProblem(400, 'You dont have enough materials to craft '.$amount.'x '.$profSkill->abel);
                }
                $skillItems[] = (object)[
                    'id' => $mat->Item_ID,
                    'amount' => ($mat->amount*$amount),
                ];
            }
        }

        # use items
        var_dump($skillItems);

        foreach($skillItems as $useItem) {
            $invWh = new Where();
            $invWh->greaterThan('amount', 0);
            $invWh->equalTo('item_idfs', $useItem->id);
            $invWh->equalTo('user_idfs', $user->User_ID);
            $itemInventory = $this->mItemUserTbl->select($invWh);
            $itemsToUse = $useItem->amount;
            foreach($itemInventory as $inv) {
                $amountLeft = $inv->amount - $itemsToUse;
                if($amountLeft < 0) {
                    $amountLeft = 0;
                    $itemsToUse = $itemsToUse - $inv->amount;
                } else {
                    $itemsToUse = 0;
                }
                echo 'update amount to '.$amountLeft;
                $this->mItemUserTbl->update([
                    'amount' => $amountLeft,
                ],[
                    'item_idfs' => $inv->item_idfs,
                    'user_idfs' => $user->User_ID,
                    'amount' => $inv->amount,
                    'used' => 0,
                    'date_created' => $inv->date_created,
                    'created_by' => $inv->created_by
                ]);
                if($itemsToUse == 0) {
                    break;
                }
            }
        }

        # check if there is already a slot in inventory for this item
        $invSlotWh = new Where();
        $invSlotWh->equalTo('item_idfs', $profSkill->item_idfs);
        $invSlotWh->equalTo('user_idfs', $user->User_ID);
        $invSlotWh->equalTo('created_by', $user->User_ID);
        $invSlotWh->lessThanOrEqualTo('amount', $profItem->stack_size - $amount);
        $invSlotItem = $this->mItemUserTbl->select($invSlotWh);
        if($invSlotItem->count() == 0) {
            $this->mItemUserTbl->insert([
                'item_idfs' => $profSkill->item_idfs,
                'user_idfs' => $user->User_ID,
                'date_created' => date('Y-m-d H:i:s', time()),
                'date_received' => date('Y-m-d H:i:s', time()),
                'comment' => 'Created by '.$user->username,
                'hash' => password_hash($profSkill->item_idfs.$user->User_ID.time(), PASSWORD_DEFAULT),
                'created_by' => $user->User_ID,
                'received_from' =>  $user->User_ID,
                'used' => 0,
                'amount' => (float)$amount,
            ]);
        } else {
            $invSlotItem = $invSlotItem->current();
            $this->mItemUserTbl->update([
                'amount' => (float)$invSlotItem->amount + $amount
            ],[
                'item_idfs' => $invSlotItem->item_idfs,
                'user_idfs' => $user->User_ID,
                'amount' => $invSlotItem->amount,
                'date_created' => $invSlotItem->date_created,
                'created_by' => $invSlotItem->created_by
            ]);
        }

        return true;
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

        $profUrl = filter_var($id, FILTER_SANITIZE_STRING);

        $profInfo = $this->mProfTbl->select(['url' => $profUrl]);
        if($profInfo->count() == 0) {
            return new ApiProblem(404, 'Profession not found');
        }
        $profInfo = $profInfo->current();

        $skillId = filter_var($data->skill, FILTER_SANITIZE_NUMBER_INT);

        $profSkill = $this->mProfSkillTbl->select(['Skill_ID' => $skillId, 'profession_idfs' => $profInfo->Profession_ID]);
        if($profSkill->count() == 0) {
            return new ApiProblem(404, 'Skill not found');
        }
        $profSkill = $profSkill->current();

        $userProf = $this->mProfUsrTbl->select(['profession_idfs' => $profInfo->Profession_ID, 'user_idfs' => $user->User_ID]);
        if($userProf->count() == 0) {
            return new ApiProblem(404, 'You have not unlocked the profession for this skill');
        }
        $userProf = $userProf->current();

        if($userProf->skill < $profSkill->skill) {
            return new ApiProblem(400, 'Your profession skill is too low to learn this skill');
        }

        # check if user has already learned this skill
        $skillLearned = $this->mProfSkillUsrTbl->select(['user_idfs' => $user->User_ID, 'skill_idfs' => $skillId]);
        if($skillLearned->count() == 0) {
            if($this->mTransaction->checkUserBalance($profSkill->price, $user->User_ID)) {
                $fNewBalance = $this->mTransaction->executeTransaction($profSkill->price, 1, $user->User_ID, $profSkill->Skill_ID, 'prof-skill', 'Learned Skill ' . $profSkill->label . ' for ' . $profInfo->label);
                if ($fNewBalance !== false) {
                    $this->mProfSkillUsrTbl->insert([
                        'user_idfs' => $user->User_ID,
                        'skill_idfs' => $skillId,
                        'profession_idfs' => $profInfo->Profession_ID,
                        'date_learned' => date('Y-m-d H:i:s', time())
                    ]);

                    return true;
                } else {
                    return new ApiProblem(400, 'Transaction Error. Please contact admin');
                }
            } else {
                return new ApiProblem(400, 'Your balance is too low to learn this skill');
            }
        } else {
            return new ApiProblem(400, 'Skill already learned');
        }

        return new ApiProblem(405, 'The PUT method has not been defined for individual resources');
    }
}
