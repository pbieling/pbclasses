<?php

namespace PbClasses;

use PbClasses\Conf;
use PbClasses\Exception\Messages;
use PbClasses\UserErr;

class User
{

    // @see handleGuestLogin();
    private const GUEST_RANDOM_START_UID = 10000;
    private const GUEST_RANDOM_END_UID = 99999;
    
    protected const AJAX_PSEUDO_PAGEID = 9999;
    protected const ONLINE_TIMEOUT = 720; // Session ends after 720 minutes of inactivity
    protected const MIN_USERNAME_LENGTH = 2;
    protected const MIN_PASSWORD_LENGTH = 4;
    protected const COOKIE_LIFETIME = 42000;

    protected ?string $loc;
    protected int $onlineTimeout;
    protected $db;
    protected ?int $pageId;
    protected string $onlineTable;
    protected ?array $P;
    protected $loginGroup = false;
    protected string $ipOrGroup;
    protected int $loginPageId = 1;
    protected string $logoutPage = '/';
    protected ?string $desiredPage = null;
    protected ?array $online = null;
    protected ?array $rslt = null;

    /**
     * Constructor
     * 
     * @param string $loc Location for redirects (e.g., to homepage or 'ajax')
     * @throws \Exception If PAGE_ID is missing
     */
    public function __construct(string $loc = "")
    {
        $this->db = Conf::get('db');
        $this->onlineTimeout = Conf::get(['sess_config', 'online_timeout']) ?? self::ONLINE_TIMEOUT;

        $this->P = \filter_input_array(\INPUT_POST, \FILTER_SANITIZE_SPECIAL_CHARS);

        if (isset(Conf::get('page_conf')['login_group'])) {
            $this->ipOrGroup = $this->loginGroup = Conf::get('page_conf')['login_group'];
            if (!$loc && isset(Conf::get('page_conf')['login_pid'])) {
                $this->loginPageId = Conf::get('page_conf')['login_pid'];
            }
        } else {
            $this->ipOrGroup = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        if (isset(Conf::get('page_conf')['logout_destination'])) {
            $this->logoutPage = Conf::get('page_conf')['logout_destination'];
        } elseif (Conf::get('logout_destination')) {
            $this->logoutPage = Conf::get('logout_destination');
        }

        $this->onlineTable = Conf::get('db_tables')['online'];
        $this->pageId = Conf::get('PAGE_ID');

        if (!$this->pageId && $loc === 'ajax') {
            $this->pageId = self::AJAX_PSEUDO_PAGEID;
        }

        if (!$this->pageId) {
            throw new \RuntimeException('Page-ID is missing');
        }

        $this->loc = $loc;
        $this->checkRights();
    }

    /**
     * Check user rights and session validity
     * 
     * @return void
     */
    protected function checkRights(): void
    {
        $where = ['SID' => session_id()];
        $res = $this->db->selectAssoc($this->onlineTable, '', $where, '', 'LIMIT 1');

        if (!$res) {
            $this->handleNoSession();
        } else {
            if (!isset($_SESSION['uid'])) {
                $this->logLogout('no uid');
            }
            $this->online = $res[0];
            $this->updateOnline();
        }

        $this->checkAccessPermissions();
    }

    /**
     * Handle case when no session exists
     * 
     * @return void
     */
    protected function handleNoSession(): void
    {
        if ($this->loc) {
            $this->gotoDestination();
        } elseif ($this->pageId != $this->loginPageId) {
            if (Conf::get('no_desired_forwarding') !== true) {
                $this->desiredPage = $_SERVER['REQUEST_URI'];
            }
            $this->logLogout('goto login');
        } else {
            $this->cleanDb();
            $this->insertNewUser();
        }
    }

    /**
     * Check access permissions based on configuration
     * 
     * @return void
     */
    protected function checkAccessPermissions(): void
    {
        if (empty($_SESSION['login'])) {
            return;
        }

        if (Conf::get('access_tables')) {
            $accObj = new \PbClasses\Access();
            if (!$accObj->hasAccess($_SESSION['uid'], $this->pageId)) {
                $this->logLogout('FORBIDDEN');
            }
        } elseif (!empty(Conf::get('page_conf')['required_user_level'])) {
            $requiredUserLevel = Conf::get('page_conf')['required_user_level'];
            if (empty($_SESSION['frg']) || $_SESSION['frg'] < $requiredUserLevel) {
                $this->logLogout('FORBIDDEN');
            }
        }
    }

    /**
     * Redirect to specified destination
     * 
     * @return void
     */
    protected function gotoDestination(): void
    {
        if ($this->loc === 'ajax') {
            $this->ajaxExit();
        }
        \header("Location: " . $this->loc);
        exit;
    }

    /**
     * Exit with AJAX error response
     * 
     * @return void
     */
    protected function ajaxExit(): void
    {
        $this->logOut();
        \header('Content-Type: application/json; charset=utf-8');
        $this->rslt = [
            'succ' => 'ERROR',
            'errortext' => 'Die Session wurde leider beendet. Bitte loggen Sie sich neu ein.',
            'sessionproblem' => true
        ];
        echo \json_encode($this->rslt);
        exit;
    }

    /**
     * Update online status timestamp
     * 
     * @return bool
     */
    protected function updateOnline(): bool
    {
        $keyval = [
            'Zeit' => time(),
            'PageID' => $this->pageId
        ];

        $where = ['SID' => session_id()];
        return $this->db->update($this->onlineTable, $keyval, "", $where);
    }

    /**
     * Destroy session and clear cookies
     * 
     * @return void
     */
    protected function logOut(): void
    {
        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            \setcookie(
                    session_name(),
                    '',
                    time() - self::COOKIE_LIFETIME,
                    '/',
                    '',
                    true, // secure
                    true  // httponly
            );
        }

        \session_destroy();
    }

    /**
     * Clean expired sessions from database
     * 
     * @return void
     */
    protected function cleanDb(): void
    {
        $limit = time() - ($this->onlineTimeout * 60);
        $where = 'Zeit < ' . $limit;
        $this->db->delete($this->onlineTable, $where);
    }

    /**
     * Log action to logging table
     * 
     * @param array $logging_arr Logging data
     * @return void
     */
    protected function logging(array $logging_arr): void
    {
        if (!Conf::get('logging_enabled')) {
            return;
        }

        $logging = Conf::get('db_tables')['logging'];
        if (!empty($logging)) {
            $this->db->insert($logging, $logging_arr);
        }
    }

    /**
     * Delete online entry for current session
     * 
     * @return void
     */
    protected function deleteOnlineEntry(): void
    {
        $where = ['SID' => session_id()];
        $this->db->delete($this->onlineTable, $where);
    }

    /**
     * Log logout action and redirect
     * 
     * @param string $action Logout reason
     * @return void
     */
    public function logLogout(string $action = 'out'): void
    {
        $uid = $_SESSION['uid'] ?? 0;

        $where = ['SID' => session_id()];
        $this->db->delete($this->onlineTable, $where);

        $log_arr = [
            'UID' => $uid,
            'IP' => $this->ipOrGroup,
            'Aktion' => $action,
            'PID' => $this->pageId
        ];
        $this->logging($log_arr);
        $this->logOut();

        if ($this->desiredPage) {
            session_start();
            $_SESSION['desired_page'] = $this->desiredPage;
        }

        header("Location: " . $this->logoutPage);
        exit;
    }

    /**
     * Insert new user session into database
     * 
     * @return bool
     */
    protected function insertNewUser(): bool
    {
        $uid = $this->getUid();

        if ($uid == 0) {
            $_SESSION['login'] = false;
            return false;
        }

        // Prevent double login - delete existing entries
        $where = [
            "UID" => $uid,
            'IP' => $this->ipOrGroup
        ];

        if ($this->db->delete($this->onlineTable, $where)) {
            $log_arr = [
                'UID' => $uid,
                'IP' => $this->ipOrGroup,
                'Aktion' => 'auto_logout',
                'PID' => $this->pageId
            ];
            $this->logging($log_arr);
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $keyval = [
            'SID' => session_id(),
            'UID' => $uid,
            'IP' => $this->ipOrGroup,
            'Zeit' => time(),
            'PageID' => $this->pageId
        ];

        if (!$this->db->insert($this->onlineTable, $keyval)) {
            throw new \RuntimeException("Failed to insert user data into online table");
        }

        $_SESSION['login'] = true;
        $_SESSION['uid'] = $uid;

        $log_arr = [
            'UID' => $uid,
            'IP' => $this->ipOrGroup,
            'Aktion' => 'login',
            'PID' => $this->pageId
        ];
        $this->logging($log_arr);

        if (!empty($_SESSION['desired_page'])) {
            $desiredPage = $_SESSION['desired_page'];
            unset($_SESSION['desired_page']);
            if (Conf::get('no_desired_forwarding') !== true) {
                header("Location: " . $desiredPage);
                exit;
            }
        }

        return true;
    }

    /**
     * Get user ID after authentication
     * 
     * @return int User ID or 0 on failure
     */
    protected function getUid(): int
    {
        // Check if another page was desired
        if (!empty($_SESSION['desired_page'])) {
            $this->desiredPage = $_SESSION['desired_page'];
        }

        // Clear session but preserve desired page
        $_SESSION = [];
        if (!empty($this->desiredPage)) {
            $_SESSION['desired_page'] = $this->desiredPage;
        }

        // Try auto-login
        $autoLoginFile = dirname(\PbClasses\Conf::getPbClassesPath()) . '/AutoLogin.php';
        if (Conf::get('no_auto_login') !== true && file_exists($autoLoginFile)) {
            require_once $autoLoginFile;
            $userPwdArr = \AutoLogin::getLocalUserPw();
            if (!$userPwdArr) {
                $this->logLogout('pwd_autologin error');
            }
            [$un, $pw] = $userPwdArr;
        } elseif (Conf::get('guest_mode')) {
            return $this->handleGuestLogin();
        }
        // Manual login
        elseif (!isset($this->P['username']) || !isset($this->P['password'])) {
            if ($this->pageId != $this->loginPageId) {
                $this->logLogout('pwd_error');
            }
            return 0;
        }

        if (empty($un)) {
            $un = \trim($this->P['username']);
            $pw = \trim($this->P['password']);
        }

        // Validate input length
        if (\mb_strlen($un) < self::MIN_USERNAME_LENGTH || \mb_strlen($pw) < self::MIN_PASSWORD_LENGTH) {
            UserErr::push(Messages::get("Invalid credentials"));
            // Sleep to prevent timing attacks
            \usleep(\random_int(100000, 500000));
            return 0;
        }



        return $this->authenticateUser($un, $pw);
    }

    /**
     * The guest_mode can be set in the project.conf.php
     *  'guest_mode' => true,
     *   or overwrite the session values 'user_realname' and 'frg' (means "Freigabelevel" = access level)
     *   
     */
    protected function handleGuestLogin()
    {
        $_SESSION['user_realname'] = Conf::get(['guest_mode', 'user_realname']) ?? 'Guest';
        $_SESSION['frg'] = Conf::get(['guest_mode', 'frg']) ?? 0;
        return random_int(self::GUEST_RANDOM_START_UID, self::GUEST_RANDOM_END_UID);
    }

    /**
     * Authenticate user credentials
     * 
     * @param string $username Username
     * @param string $password Password
     * @return int User ID or 0 on failure
     */
    protected function authenticateUser(string $username, string $password): int
    {
        $what = ['UID', 'RealName', 'Frg', 'Passw'];
        $where = ['Uname' => $username, 'ON_OFF' => 1];

        if (false !== $this->loginGroup) {
            $where['GroupName'] = $this->loginGroup;
            $usertable = 'groupuser';
        } else {
            $usertable = 'user';
        }

        $res = $this->db->selectAssoc(
                Conf::get('db_tables')[$usertable],
                $what,
                $where,
                '',
                'LIMIT 1'
        );

        // Always verify password even if user not found (timing attack prevention)
        $validPassword = false;
        if ($res && isset($res[0]['Passw'])) {
            $validPassword = \password_verify($password, $res[0]['Passw']);
        } else {
            // Dummy verification to maintain consistent timing
            \password_verify($password, '$2y$10$abcdefghijklmnopqrstuv');
        }

        if ($res && $validPassword) {
            $_SESSION['user_realname'] = $res[0]['RealName'];
            $_SESSION['frg'] = $res[0]['Frg'];
            return $res[0]['UID'];
        }

        if ($this->pageId != $this->loginPageId) {
            $this->logLogout('pwd_error');
        }

        UserErr::push(Messages::get("Invalid credentials"));
        // Sleep to prevent timing attacks
        \usleep(\random_int(100000, 500000));
        return 0;
    }
}
