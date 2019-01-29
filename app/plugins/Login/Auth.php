<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Login;

use Piwik\AuthResult;
use Piwik\Auth\Password;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\Model;
use Piwik\Plugins\UsersManager\UsersManager;

class Auth implements \Piwik\Auth
{
    protected $login;
    protected $token_auth;
    protected $hashedPassword;

    /**
     * @var Model
     */
    private $userModel;

    /**
     * @var Password
     */
    private $passwordHelper;

    public function __construct()
    {
        $this->userModel      = new Model();
        $this->passwordHelper = new Password();
    }

    /**
     * Authentication module's name, e.g., "Login"
     *
     * @return string
     */
    public function getName()
    {
        return 'Login';
    }

    /**
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        if (!empty($this->hashedPassword)) {
            return $this->authenticateWithPassword($this->login, $this->getTokenAuthSecret());
        } elseif (is_null($this->login)) {
            return $this->authenticateWithToken($this->token_auth);
        } elseif (!empty($this->login)) {
            return $this->authenticateWithLoginAndToken($this->token_auth, $this->login);
        }

        return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
    }

    private function authenticateWithPassword($login, $passwordHash)
    {
        $user = $this->userModel->getUser($login);

        if (empty($user['login'])) {
            return new AuthResult(AuthResult::FAILURE, $login, null);
        }

        if ($this->passwordHelper->verify($passwordHash, $user['password'])) {
            if ($this->passwordHelper->needsRehash($user['password'])) {
                $newPasswordHash = $this->passwordHelper->hash($passwordHash);

                $this->userModel->updateUser($login, $newPasswordHash, $user['email'], $user['alias'], $user['token_auth']);
            }

            return $this->authenticationSuccess($user);
        }

        return new AuthResult(AuthResult::FAILURE, $login, null);
    }

    private function authenticateWithToken($token)
    {
        $user = $this->userModel->getUserByTokenAuth($token);

        if (!empty($user['login'])) {
            return $this->authenticationSuccess($user);
        }

        return new AuthResult(AuthResult::FAILURE, null, $token);
    }

    private function authenticateWithLoginAndToken($token, $login)
    {
        $user = $this->userModel->getUser($login);

        if (!empty($user['token_auth'])
            // authenticate either with the token or the "hash token"
            && ((SessionInitializer::getHashTokenAuth($login, $user['token_auth']) === $token)
                || $user['token_auth'] === $token)
        ) {
            return $this->authenticationSuccess($user);
        }

        return new AuthResult(AuthResult::FAILURE, $login, $token);
    }

    private function authenticationSuccess(array $user)
    {
        $this->setTokenAuth($user['token_auth']);

        $isSuperUser = (int) $user['superuser_access'];
        $code = $isSuperUser ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

        return new AuthResult($code, $user['login'], $user['token_auth']);
    }

    /**
     * Returns the login of the user being authenticated.
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * Accessor to set login name
     *
     * @param string $login user login
     */
    public function setLogin($login)
    {
        $this->login = $login;
    }

    /**
     * Returns the secret used to calculate a user's token auth.
     *
     * @return string
     */
    public function getTokenAuthSecret()
    {
        return $this->hashedPassword;
    }

    /**
     * Accessor to set authentication token
     *
     * @param string $token_auth authentication token
     */
    public function setTokenAuth($token_auth)
    {
        $this->token_auth = $token_auth;
    }

    /**
     * Sets the password to authenticate with.
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        if (empty($password)) {
            $this->hashedPassword = null;
        } else {
            $this->hashedPassword = UsersManager::getPasswordHash($password);
        }
    }

    /**
     * Sets the password hash to use when authentication.
     *
     * @param string $passwordHash The password hash.
     */
    public function setPasswordHash($passwordHash)
    {
        if ($passwordHash === null) {
            $this->hashedPassword = null;
            return;
        }

        // check that the password hash is valid (sanity check)
        UsersManager::checkPasswordHash($passwordHash, Piwik::translate('Login_ExceptionPasswordMD5HashExpected'));

        $this->hashedPassword = $passwordHash;
    }

    // for tests
    public function getTokenAuth()
    {
        return $this->token_auth;
    }
}
