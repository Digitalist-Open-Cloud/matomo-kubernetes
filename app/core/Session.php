<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik;

use Exception;
use Piwik\Container\StaticContainer;
use Piwik\Exception\MissingFilePermissionException;
use Piwik\Session\SaveHandler\DbTable;
use Zend_Session;

/**
 * Session initialization.
 */
class Session extends Zend_Session
{
    const SESSION_NAME = 'PIWIK_SESSID';

    public static $sessionName = self::SESSION_NAME;

    protected static $sessionStarted = false;

    /**
     * Are we using file-based session store?
     *
     * @return bool  True if file-based; false otherwise
     */
    public static function isFileBasedSessions()
    {
        $config = Config::getInstance();
        return !isset($config->General['session_save_handler'])
        || $config->General['session_save_handler'] === 'files';
    }

    /**
     * Start the session
     *
     * @param array|bool $options An array of configuration options; the auto-start (bool) setting is ignored
     * @return void
     * @throws Exception if starting a session fails
     */
    public static function start($options = false)
    {
        if (headers_sent()
            || self::$sessionStarted
            || (defined('PIWIK_ENABLE_SESSION_START') && !PIWIK_ENABLE_SESSION_START)
        ) {
            return;
        }
        self::$sessionStarted = true;

        $config = Config::getInstance();

        // use cookies to store session id on the client side
        @ini_set('session.use_cookies', '1');

        // prevent attacks involving session ids passed in URLs
        @ini_set('session.use_only_cookies', '1');

        // advise browser that session cookie should only be sent over secure connection
        if (ProxyHttp::isHttps()) {
            @ini_set('session.cookie_secure', '1');
        }

        // advise browser that session cookie should only be accessible through the HTTP protocol (i.e., not JavaScript)
        @ini_set('session.cookie_httponly', '1');

        // don't use the default: PHPSESSID
        @ini_set('session.name', self::$sessionName);

        // proxies may cause the referer check to fail and
        // incorrectly invalidate the session
        @ini_set('session.referer_check', '');

        // to preserve previous behavior piwik_auth provided when it contained a token_auth, we ensure
        // the session data won't be deleted until the cookie expires.
        @ini_set('session.gc_maxlifetime', $config->General['login_cookie_expire']);

        $currentSaveHandler = ini_get('session.save_handler');

        if (self::isFileBasedSessions()) {
            // Note: this handler doesn't work well in load-balanced environments and may have a concurrency issue with locked session files

            // for "files", use our own folder to prevent local session file hijacking
            $sessionPath = self::getSessionsDirectory();
            // We always call mkdir since it also chmods the directory which might help when permissions were reverted for some reasons
            Filesystem::mkdir($sessionPath);

            @ini_set('session.save_handler', 'files');
            @ini_set('session.save_path', $sessionPath);
        } elseif ($config->General['session_save_handler'] === 'dbtable'
            || in_array($currentSaveHandler, array('user', 'mm'))
        ) {
            // We consider these to be misconfigurations, in that:
            // - user  - we can't verify that user-defined session handler functions have already been set via session_set_save_handler()
            // - mm    - this handler is not recommended, unsupported, not available for Windows, and has a potential concurrency issue

            $config = array(
                'name'           => Common::prefixTable('session'),
                'primary'        => 'id',
                'modifiedColumn' => 'modified',
                'dataColumn'     => 'data',
                'lifetimeColumn' => 'lifetime',
            );

            $saveHandler = new DbTable($config);
            if ($saveHandler) {
                self::setSaveHandler($saveHandler);
            }
        }

        // garbage collection may disabled by default (e.g., Debian)
        if (ini_get('session.gc_probability') == 0) {
            @ini_set('session.gc_probability', 1);
        }

        try {
            parent::start();
            register_shutdown_function(array('Zend_Session', 'writeClose'), true);
        } catch (Exception $e) {
            Log::error('Unable to start session: ' . $e->getMessage());

            $enableDbSessions = '';
            if (DbHelper::isInstalled()) {
                $enableDbSessions = "<br/>If you still experience issues after trying these changes,
			            			we recommend that you <a href='https://matomo.org/faq/how-to-install/#faq_133' rel='noreferrer noopener' target='_blank'>enable database session storage</a>.";
            }

            $pathToSessions = Filechecks::getErrorMessageMissingPermissions(self::getSessionsDirectory());
            $message = sprintf("Error: %s %s %s\n<pre>Debug: the original error was \n%s</pre>",
                Piwik::translate('General_ExceptionUnableToStartSession'),
                $pathToSessions,
                $enableDbSessions,
                $e->getMessage()
            );

            $ex = new MissingFilePermissionException($message, $e->getCode(), $e);
            $ex->setIsHtmlMessage();

            throw $ex;
        }
    }

    /**
     * Returns the directory session files are stored in.
     *
     * @return string
     */
    public static function getSessionsDirectory()
    {
        return StaticContainer::get('path.tmp') . '/sessions';
    }

    public static function close()
    {
        parent::writeClose();
    }

    public static function isSessionStarted()
    {
        return self::$sessionStarted;
    }
}
