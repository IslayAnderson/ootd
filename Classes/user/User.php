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
    private $user_id;
    private $first_name;
    private $last_name;
    private $email;
    private $password;

    /**
     * __construct
     *
     * @param mixed $id
     */
    public function __construct($id = null)
    {

        if(!empty($id)){
            $this->user_id = $id;
            $this->first_name = $this->get_name($id)[0];
            $this->last_name = $this->get_name($id)[1];
            $this->email = $this->get_email($id);
        }

    }

    public function __serialize(): array
    {
        return [
            'user_id'       => $this->user_id,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'email'         => $this->email
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->user_id      = $data['id'];
        $this->first_name   = $data['get_name'];
        $this->last_name    = $data['get_name'];
        $this->email        = $data['get_email'];
    }


    public function get_user_id($email = null)
    {
        if(empty($email) && empty($this->user_id)){
            throw new Exception('Email variable missing');
        }elseif(empty($email) && !empty($this->user_id)){
            return $this->user_id;
        }

        $params = array( ':a'=> $email);
    
        $sql = "SELECT `user_id` FROM `users` WHERE `email` = :a";
    
        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0]->user_id;

    }
    
    public function get_name($id = null)
    {
        if(empty($id)){ 
            $this->deauthenticate();
            throw new Exception('ID variable missing and name properties malformed');
        }

        $params = array( ':a'=> $id);
    
        $sql = "SELECT `first_name`, `last_name` FROM `users` WHERE `user_id` = :a";
    
        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return array($row[0]->first_name, $row[0]->last_name);

    }
    
    public function get_email($id = null)
    {
        if(empty($id)){
            $this->deauthenticate();
            throw new Exception('ID variable missing and email property malformed');
        }

        $params = array( ':a'=> $id);
    
        $sql = "SELECT `email` FROM `users` WHERE `user_id` = :a";
    
        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0]->email;

    }

    private function get_password($user = null)
    {
        if(empty($user)){
            throw new Exception('Empty user variable');
        }

        if(!empty($user['username'])){
            $val = new Validate($user['username']);
            if(!$val->basic()['state']){
                throw new Exception('Input could not be validated');
            }

            $sql = "SELECT PASSWORD FROM `users` WHERE username = '".$user['username']."'";

        } elseif(!empty($user['id'])){
            $val = new Validate($user['id']);
            if(!$val->basic()['state']){
                throw new Exception('Input could not be validated');
            }

            $sql = "SELECT PASSWORD FROM `users` WHERE user_id = '".$user['id']."'";

        } elseif(!empty($user['email'])){
            // $val = new Validate($user['email']);
            // if(!$val->basic()['state']){
            //     throw new Exception('Input could not be validated');
            // }

            $sql = "SELECT PASSWORD FROM `users` WHERE email = '".$user['email']."'";

        }

        $db = new Mysql();
        $row = $db->Fetch($sql);

        return $row[0]->PASSWORD;
    }

    public function set_user_id($user_id)
    {
        $this->$user_id = $user_id;
    }
    public function set_first_name($first_name)
    {
        $this->$first_name = $first_name;
    }
    public function set_last_name($last_name)
    {
        $this->$last_name = $last_name;
    }
    public function set_email($email)
    {
        $this->$email = $email;
    }
    private function set_password($password)
    {
        $this->$password = $password;
    }

    public function authenticate($request = null)
    {
        if(empty($request)){
            throw new Exception('Empty Request');
        }

        $pwd_salted = hash_hmac("sha256", $request['password'], $_ENV['PWD_SEED']);
        $pwd_hashed = $this->get_password(array("email"=>$request['email']));

        if(password_verify($pwd_salted, $pwd_hashed)){
    
            $params = array( ':a'=> session_id(), ':b'=> $request['email']);
    
            $sql = "UPDATE `users` SET `session`= :a WHERE `email` = :b";
    
            $db = new Mysql();
            $db->Fetch($sql, $params);
            
            $this->set_user_id($this->get_user_id($request['email']));
            $this->set_first_name($request['first_name']);
            $this->set_last_name($request['last_name']);
            $this->set_email($request['email']);
            $this->set_password($pwd_hashed);

            $_SESSION['user_id'] = $this->get_user_id($request['email']);
            $_SESSION['loggedin_time'] = time();

            session_write_close();

            return true;
        } else {
            session_destroy();
            return false;
        }

    }

    public function deauthenticate()
    {
        $params = array( ':a'=> null, ':b'=> $this->get_user_id());
    
        $sql = "UPDATE `users` SET `session`= :a WHERE `user_id` = :b";
    
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
        session_regenerate_id();
        header("location: /");
        exit();
    }

    public function kill_all_sessions(){
        $params = array( ':a'=> null );
    
        $sql = "UPDATE `users` SET `session`= :a WHERE `user_id` > 0";
    
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
        session_regenerate_id();
        header("location: /");
        exit();
    }

    public function create_user($request = null, $admin = false)
    {
        if(empty($request)){
            throw new Exception('Empty Request');
        }

        if($this->check_exists($request['username'])['username']){
             throw new Exception('username exists');
        }

        if($this->check_exists($request['email'])['email']){
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
            ":f" => 1
        );

        if($admin){
            $params[":f"] = 0;
        }

        $sql = 'INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `permission`) VALUES (:a, :b, :c, :d, :e, :f)';

        $db = new Mysql();
        $db->Fetch($sql, $params);

    }

    public function check_exists($input)
    {
        $params = array(':a'=>$input);
        $val = false;

        switch(filter_var($input, FILTER_VALIDATE_EMAIL)){
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

        if($val){
            if($row[0] != '00000' ){
                return array('email'=>true);
            }else{
                return array('email'=>false);
            }
        }else{
            if($row[0] != '00000'){
                return array('username'=>true);
            }else{
                return array('username'=>false);
            }
        }
    }

}