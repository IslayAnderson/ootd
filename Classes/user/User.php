<?php
/**
 *
 ** User class it does user stuff
 *
 * @author Islay Anderson <me@islayanderson.co.uk>
 *
 * @since 0.1
 *
 */


class User
{
    /**
     * @var integer
     */
    public $user_id;
    /**
     * @var string
     */
    public $first_name;
    /**
     * @var string
     */
    public $last_name;
    /**
     * @var string
     */
    public $username;
    /**
     * @var integer permission id, see Functions/permission.php
     */
    public $permission;
    private $email;
    /**
     * @var string
     */
    private $password;

    /**
     * __construct
     *
     * @param integer $id
     */
    public function __construct($id = null)
    {

        if (!empty($id)) {
            $this->user_id = $id;
            $this->first_name = $this->get_name($id)[0];
            $this->last_name = $this->get_name($id)[1];
            $this->email = $this->get_email($id);
            $this->username = $this->get_username($id);
            $this->permission = $this->get_permission($id);
        }

    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'permission' => $this->permission,
            'email' => $this->email
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->user_id = $data['user_id'];
        $this->first_name = $data['first_name'];
        $this->last_name = $data['last_name'];
        $this->username = $data['username'] ?? null;
        $this->permission = $data['permission'] ?? null;
        $this->email = $data['email'];
    }


    /**
     * @param $email
     * @return mixed
     * @throws Exception
     */
    public function get_user_id($email = null)
    {
        if (empty($email) && empty($this->user_id)) {
            throw new Exception('Email variable missing');
        } elseif (empty($email) && !empty($this->user_id)) {
            return $this->user_id;
        }

        $params = array(':a' => $email);

        $sql = "SELECT `user_id` FROM `users` WHERE `email` = :a";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0]->user_id;

    }

    /**
     * @param $id
     * @return array
     * @throws Exception
     */
    public function get_name($id = null)
    {
        if (empty($id)) {
            $this->deauthenticate();
            throw new Exception('ID variable missing and name properties malformed');
        }

        $params = array(':a' => $id);

        $sql = "SELECT `first_name`, `last_name` FROM `users` WHERE `user_id` = :a";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return array($row[0]->first_name, $row[0]->last_name);

    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function get_email($id = null)
    {
        if (empty($id)) {
            $this->deauthenticate();
            throw new Exception('ID variable missing and email property malformed');
        }

        $params = array(':a' => $id);

        $sql = "SELECT `email` FROM `users` WHERE `user_id` = :a";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0]->email;

    }

    public function get_username($id = null)
    {
        if (empty($id)) {
            $this->deauthenticate();
            throw new Exception('ID variable missing and username property malformed');
        }

        $params = array(':a' => $id);

        $sql = "SELECT `username` FROM `users` WHERE `user_id` = :a";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0]->username;

    }

    /**
     * @param $id
     * @return int
     * @throws Exception
     */
    public function get_permission($id = null)
    {
        if (empty($id)) {
            throw new Exception('ID variable missing and permission property malformed');
        }

        $params = array(':a' => $id);

        $sql = "SELECT `permission` FROM `users` WHERE `user_id` = :a";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return (int)$row[0]->permission;

    }

    /**
     * @param $user
     * @return mixed
     * @throws Exception
     */
    private function get_password($user = null)
    {
        if (empty($user)) {
            throw new Exception('Empty user variable');
        }

        if (!empty($user['username'])) {
            $sql = "SELECT PASSWORD FROM `users` WHERE username = :a";
            $params = array(':a' => $user['username']);
        } elseif (!empty($user['id'])) {
            $sql = "SELECT PASSWORD FROM `users` WHERE user_id = :a";
            $params = array(':a' => $user['id']);
        } elseif (!empty($user['email'])) {
            $sql = "SELECT PASSWORD FROM `users` WHERE email = :a";
            $params = array(':a' => $user['email']);
        } else {
            throw new Exception('No valid user identifier provided');
        }

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        if ($row[0] == '00000') {
            return null;
        }

        return $row[0]->PASSWORD;
    }

    /**
     * @param $user_id
     * @return void
     */
    public function set_user_id($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * @param $first_name
     * @return void
     */
    public function set_first_name($first_name)
    {
        $this->first_name = $first_name;
    }

    /**
     * @param $last_name
     * @return void
     */
    public function set_last_name($last_name)
    {
        $this->last_name = $last_name;
    }

    /**
     * @param $email
     * @return void
     */
    public function set_email($email)
    {
        $this->email = $email;
    }

    /**
     * @param $password
     * @return void
     */
    private function set_password($password)
    {
        $this->password = $password;
    }

    /**
     * @param string $media_id new avatar, the old one is removed
     * @return void
     */
    public function set_image($media_id)
    {
        $db = new Mysql();
        $old = $db->Fetch("SELECT `media_id` FROM `users` WHERE `user_id` = :a", array(':a' => $this->user_id));

        $params = array(':a' => $media_id, ':b' => $this->user_id);
        $sql = "UPDATE `users` SET `media_id` = :a WHERE `user_id` = :b";
        $db->Fetch($sql, $params);

        if ($old[0] != '00000' && !empty($old[0]->media_id) && $old[0]->media_id != $media_id) {
            Media::remove($old[0]->media_id);
        }
    }

    /**
     * @param $id
     * @return string avatar url, or the default avatar
     */
    public function get_image($id = null)
    {
        $params = array(':a' => $id ?? $this->user_id);
        $sql = "SELECT `media_id` FROM `users` WHERE `user_id` = :a";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return media_url($row[0] != '00000' ? $row[0]->media_id : null, "thumb", "/Assets/images/avi.jpg");
    }

    /**
     * @param $request
     * @return bool
     * @throws Exception
     */
    public function authenticate($request = null)
    {
        if (empty($request)) {
            throw new Exception('Empty Request');
        }

        //Salts input with predefined seed
        $pwd_salted = hash_hmac("sha256", $request['password'], $_ENV['PWD_SEED']);
        //Gets hashed password from database
        $pwd_hashed = $this->get_password(array("email" => $request['email']));

        //Compare sated input with stored hash
        if (!empty($pwd_hashed) && password_verify($pwd_salted, $pwd_hashed)) {

            //new session id on login to prevent session fixation, stored so it can be reset from /admin
            session_regenerate_id(true);

            //Update Session id in the database
            $params = array(':a' => session_id(), ':b' => $request['email']);

            $sql = "UPDATE `users` SET `session`= :a WHERE `email` = :b";

            $db = new Mysql();
            $db->Fetch($sql, $params);

            //Set class properties with basic user info
            $user_id = $this->get_user_id($request['email']);
            $name = $this->get_name($user_id);
            $this->set_user_id($user_id);
            $this->set_first_name($name[0]);
            $this->set_last_name($name[1]);
            $this->set_email($request['email']);
            $this->set_password($pwd_hashed);

            $_SESSION['user_id'] = $user_id;
            //the pre-login token was never stored against the user, init.php makes a new one
            unset($_SESSION['CSRF']);
            $_SESSION['loggedin_time'] = time();

            session_write_close();

            return true;
        } else {
            session_destroy();
            return false;
        }

    }

    /**
     * @return void
     * @throws Exception
     */
    public function deauthenticate()
    {
        $params = array(':a' => null, ':b' => $this->get_user_id());

        $sql = "UPDATE `users` SET `session`= :a, `csrf`= :a WHERE `user_id` = :b;";

        $db = new Mysql();
        $db->Fetch($sql, $params);

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("location: /login");
        exit();
    }

    /**
     * End another user's session without touching the current one
     *
     * @param $user_id
     * @return void
     */
    public function reset_session($user_id)
    {
        $params = array(':a' => null, ':b' => $user_id);
        $sql = "UPDATE `users` SET `session`= :a, `csrf`= :a WHERE `user_id` = :b;";

        $db = new Mysql();
        $db->Fetch($sql, $params);
    }

    /**
     * @param $user_id
     * @param int $permission see Functions/permission.php
     * @return void
     * @throws Exception
     */
    public function set_permission($user_id, $permission)
    {
        if (!in_array((int)$permission, array(PERMISSION_ADMIN, PERMISSION_MODERATOR, PERMISSION_USER), true)) {
            throw new Exception('Unknown permission');
        }

        $params = array(':a' => (int)$permission, ':b' => $user_id);
        $sql = "UPDATE `users` SET `permission`= :a WHERE `user_id` = :b;";

        $db = new Mysql();
        $db->Fetch($sql, $params);
    }

    /**
     * @param $current
     * @param $new
     * @return void
     * @throws Exception
     */
    public function change_password($current, $new)
    {
        $pwd_hashed = $this->get_password(array("id" => $this->user_id));
        if (empty($pwd_hashed) || !password_verify(hash_hmac("sha256", (string)$current, $_ENV['PWD_SEED']), $pwd_hashed)) {
            throw new Exception('Current password is wrong');
        }
        if (strlen((string)$new) < 8) {
            throw new Exception('New password must be at least 8 characters');
        }

        $params = array(
            ':a' => password_hash(hash_hmac("sha256", $new, $_ENV['PWD_SEED']), PASSWORD_ARGON2ID),
            ':b' => $this->user_id
        );
        $sql = "UPDATE `users` SET `password`= :a WHERE `user_id` = :b;";

        $db = new Mysql();
        $db->Fetch($sql, $params);
    }

    /**
     * @return array every user, for the admin user table
     */
    public static function all_users(): array
    {
        $sql = "SELECT users.user_id, users.username, users.email, users.first_name, users.last_name, users.permission,
                       users.session IS NOT NULL AS has_session, permissions.permission AS permission_name
                FROM `users` LEFT JOIN `permissions` ON users.permission = permissions.permission_id
                ORDER BY users.user_id";

        $db = new Mysql();
        $row = $db->Fetch($sql, null);

        return $row[0] != '00000' ? $row : array();
    }

    /**
     * @return void
     */
    public function kill_all_sessions()
    {
        $params = array(':a' => null);

        $sql = "UPDATE `users` SET `session`= :a, `csrf`= :a WHERE `user_id` > 0;";

        $db = new Mysql();
        $db->Fetch($sql, $params);

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        ini_set('session.gc_max_lifetime', 0);

        session_destroy();
        header("location: /login");
        exit();
    }

    /**
     * @param $request
     * @param $admin
     * @return void
     * @throws Exception
     */
    public function create_user($request = null, $admin = false)
    {
        if (empty($request)) {
            throw new Exception('Empty Request');
        }

        if ($this->check_exists($request['username'])['username']) {
            throw new Exception('username exists');
        }

        if ($this->check_exists($request['email'])['email']) {
            throw new Exception('email exists');
        }

        $pwd_salted = hash_hmac("sha256", $request['password'], $_ENV['PWD_SEED']);
        $pwd_hashed = password_hash($pwd_salted, PASSWORD_ARGON2ID);

        $params = array(
            ":a" => $request['username'],
            ":b" => $request['email'],
            ":c" => $pwd_hashed,
            ":d" => $request['first_name'],
            ":e" => $request['last_name'],
            ":f" => PERMISSION_USER
        );

        if ($admin) {
            $params[":f"] = PERMISSION_ADMIN;
        }

        $sql = 'INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `permission`) VALUES (:a, :b, :c, :d, :e, :f)';

        $db = new Mysql();
        $db->Fetch($sql, $params);

    }

    /**
     * @param $input
     * @return false[]|true[]
     */
    public function check_exists($input)
    {
        $params = array(':a' => $input);
        $val = false;

        switch (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            case true:
                $sql = 'SELECT * FROM `users` WHERE `email` = :a';
                $val = true;
                break;
            case false:
                $sql = 'SELECT * FROM `users` WHERE `username` = :a';
                break;
        }

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        if ($val) {
            if ($row[0] != '00000') {
                return array('email' => true);
            } else {
                return array('email' => false);
            }
        } else {
            if ($row[0] != '00000') {
                return array('username' => true);
            } else {
                return array('username' => false);
            }
        }
    }

    public function get_user_id_from_username($input)
    {
        $params = array(':a' => $input);
        $sql = 'SELECT `user_id` FROM `users` WHERE `username` = :a';
        $db = new Mysql();
        $row = $db->Fetch($sql, $params);
        if ($row[0] == '00000') {
            return null;
        }
        return $row[0]->user_id;
    }

}