<?php
namespace Backend\V1\Rest\Banhammer;

use Faucet\Tools\SecurityTools;
use Faucet\Transaction\TransactionHelper;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;

class BanhammerResource extends AbstractResourceListener
{
    /**
     * Security Tools Helper
     *
     * @var SecurityTools $mSecTools
     * @since 1.0.0
     */
    protected $mSecTools;

    /**
     * @var TableGateway
     */
    private $mLogTbl;
    /**
     * @var TableGateway
     */
    private $mUserSettingsTbl;

    /**
     * @var TableGateway
     */
    private $mUserTbl;


    /**
     * Constructor
     *
     * UserResource constructor.
     * @param $mapper
     * @since 1.0.0
     */
    public function __construct($mapper)
    {
        $this->mLogTbl = new TableGateway('faucet_log', $mapper);
        $this->mUserSettingsTbl = new TableGateway('user_setting', $mapper);
        $this->mUserTbl = new TableGateway('user', $mapper);

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
        $me = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($me) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $me;
        }

        if((int)$me->is_employee !== 1) {
            return new ApiProblem(403, 'You have no permission to do that ('.$me->is_employee.')');
        }

        $ipWhiteList = $this->mSecTools->getCoreSetting('backend-ip-whitelist');
        $ipWhiteList = json_decode($ipWhiteList);
        if(!in_array($_SERVER['REMOTE_ADDR'], $ipWhiteList)) {
            return new ApiProblem(400, 'You are not allowed this access this api');
        }

        $checkListCl = $this->mLogTbl->select(['archived' => 0, 'log_type' => 'cl-multi-ip']);

        $checkCl = [];
        foreach($checkListCl as $cl) {
            $infoTmp = explode(':', $cl->log_info);
            $userIds = json_decode(trim($infoTmp[1]));
            $entry = ['ip' => $infoTmp[0], 'users' => []];
            if(is_array($userIds)) {
                foreach($userIds as $userId) {
                    $uInfo = $this->mUserTbl->select(['User_ID' => $userId]);
                    if($uInfo->count() > 0) {
                        $uInfo = $uInfo->current();
                        $entry['users'][] = ['id' => $userId, 'name' => $uInfo->username, 'email' => $uInfo->email, 'ref' => $uInfo->ref_user_idfs, 'level' => $uInfo->xp_level];
                    }
                }
            }
            $checkCl[] = $entry;
        }

        return (object)['list' => [
            'faucet' => $checkCl
        ]];
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
