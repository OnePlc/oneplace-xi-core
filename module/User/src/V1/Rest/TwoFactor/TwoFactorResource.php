<?php
namespace User\V1\Rest\TwoFactor;

use Faucet\Tools\SecurityTools;
use Faucet\Tools\UserTools;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\Db\TableGateway\TableGateway;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;

class TwoFactorResource extends AbstractResourceListener
{
    /**
     * @var TableGateway
     */
    private $mUserTbl;

    /**
     * @var SecurityTools
     */
    private $mSecTools;

    /**
     * @var UserTools
     */
    private $mUserTools;

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

        $this->mSecTools = new SecurityTools($mapper);
        $this->mUserTools = new UserTools($mapper);
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

        // generate 2FA Secret
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        $has2FA = $this->mUserTools->getSetting($user->User_ID, '2fa-secret');
        if($has2FA) {
            $text = $google2fa->getQRCodeUrl(
                'swissfaucet.io',
                $user->username,
                $has2FA
            );
            $image_url = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl='.$text;

            $tfaEnabled = $this->mUserTools->getSetting($user->User_ID, '2fa-enabled-code');
            $isTwoFactorEnabled = 0;
            if($tfaEnabled) {
                $isTwoFactorEnabled = 1;
            }

            if($isTwoFactorEnabled == 1) {
                return (object)[
                    'tfa_enabled' => 1,
                ];
            } else {
                return (object)[
                    'tfa_secret' => $has2FA,
                    'tfa_enabled' => 0,
                    'qr_code' => $image_url
                ];
            }
        } else {
            try {
                $secret = $google2fa->generateSecretKey();
            } catch (IncompatibleWithGoogleAuthenticatorException $e) {
                return new ApiProblem(400, 'Error while generating your 2FA Key');
            } catch (InvalidCharactersException $e) {
                return new ApiProblem(400, 'Error while generating your 2FA Key');
            } catch (SecretKeyTooShortException $e) {
                return new ApiProblem(400, 'Error while generating your 2FA Key');
            }

            $this->mUserTools->setSetting($user->User_ID, '2fa-secret', $secret);
            $text = $google2fa->getQRCodeUrl(
                'swissfaucet.io',
                $user->username,
                $secret
            );
            $image_url = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl='.$text;

            return (object)[
                'tfa_secret' => $secret,
                'tfa_enabled' => 0,
                'qr_code' => $image_url
            ];
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $has2FA = $this->mUserTools->getSetting($user->User_ID, '2fa-secret');
        if($has2FA) {
            $tfaEnabled = $this->mUserTools->getSetting($user->User_ID, '2fa-enabled-code');
            if($tfaEnabled) {
                $code = trim(filter_var($id, FILTER_SANITIZE_STRING));
                $code = preg_replace('/\s+/', '', $code);
                if(strlen($code) !== 6) {
                    return new ApiProblem(403, 'Invalid Code. Please enter a valid 6 Digit Code from your Authenticator App - '.$code);
                }

                $google2fa = new \PragmaRX\Google2FA\Google2FA();
                if ($google2fa->verifyKey($has2FA, $code)) {
                    $this->mUserTools->removeSetting($user->User_ID, '2fa-enabled-code');
                    $this->mUserTools->removeSetting($user->User_ID, '2fa-secret');

                    return true;
                } else {
                    return new ApiProblem(403, 'Invalid Code, please try again with a new Code - '.$code);
                }
            } else {
                return new ApiProblem(405, '2FA is not enabled on your Account');
            }
        } else {
            return new ApiProblem(405, '2FA is not enabled on your Account');
        }
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

        $has2FA = $this->mUserTools->getSetting($user->User_ID, '2fa-secret');
        if($has2FA) {
            $google2fa = new \PragmaRX\Google2FA\Google2FA();
            $text = $google2fa->getQRCodeUrl(
                'swissfaucet.io',
                $user->username,
                $has2FA
            );
            $image_url = 'https://chart.googleapis.com/chart?cht=qr&chs=250x250&chl='.$text;

            $tfaEnabled = $this->mUserTools->getSetting($user->User_ID, '2fa-enabled-code');
            $isTwoFactorEnabled = 0;
            if($tfaEnabled) {
                $isTwoFactorEnabled = 1;
            }

            if($isTwoFactorEnabled == 1) {
                return (object)[
                    'tfa_enabled' => 1,
                ];
            } else {
                return (object)[
                    'tfa_secret' => $has2FA,
                    'tfa_enabled' => 0,
                    'qr_code' => $image_url
                ];
            }
        } else {
            return (object)[
                'tfa_secret' => 'disabled'
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
        # Prevent 500 error
        if(!$this->getIdentity()) {
            return new ApiProblem(401, 'Not logged in');
        }
        $user = $this->mSecTools->getSecuredUserSession($this->getIdentity()->getName());
        if(get_class($user) == 'Laminas\\ApiTools\\ApiProblem\\ApiProblem') {
            return $user;
        }

        $has2FA = $this->mUserTools->getSetting($user->User_ID, '2fa-secret');
        if($has2FA) {
            $tfaEnabled = $this->mUserTools->getSetting($user->User_ID, '2fa-enabled-code');
            if($tfaEnabled) {
                return new ApiProblem(403, '2FA is already active on your account');
            } else {
                $code = trim(filter_var($data->code, FILTER_SANITIZE_STRING));
                $code = preg_replace('/\s+/', '', $code);
                if(strlen($code) !== 6) {
                    return new ApiProblem(403, 'Invalid Code. Please enter a valid 6 Digit Code from your Authenticator App - '.$code);
                }

                $google2fa = new \PragmaRX\Google2FA\Google2FA();
                if ($google2fa->verifyKey($has2FA, $code)) {
                    $this->mUserTools->setSetting($user->User_ID, '2fa-enabled-code', $code);

                    return true;
                } else {
                    return new ApiProblem(403, 'Invalid Code, please try again with a new Code - '.$code);
                }
            }
        } else {
            return new ApiProblem(403, 'You have not enabled 2FA yet');
        }
    }
}
