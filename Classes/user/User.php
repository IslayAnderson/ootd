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
    public $user_id;
    public $first_name;
    public $last_name;
    public $email;

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

    public function get_user_id($email = null)
    {
        if(empty($email)){
            throw new Exception('Email variable missing');
        }

    }
    
    public function get_name($id = null)
    {
        if(empty($id) && empty($this->$first_name) && empty($this->$last_name)){ //this will crash if user inserts a blank name, this is intended behaviour 
            $this->deauthenticate();
            throw new Exception('ID variable missing and name properties malformed');
        }

    }
    
    public function get_email($id = null)
    {
        if(empty($id) && empty($this->$email)){
            $this->deauthenticate();
            throw new Exception('ID variable missing and email property malformed');
        }

    }

    private function get_password($user = null)
    {
        if(empty($user)){
            throw new Exception('Empty user variable');
        }

        if(!empty($user->username)){
            $val = new Validate($user->username);
            if(!$val->basic()['state']){
                throw new Exception('Input could not be validated');
            }

            $sql = "SELECT PASSWORD FROM `users` WHERE username = '".$user->username."'";

        } elseif(!empty($user->id)){
            $val = new Validate($user->id);
            if(!$val->basic()['state']){
                throw new Exception('Input could not be validated');
            }

            $sql = "SELECT PASSWORD FROM `users` WHERE user_id = '".$user->id."'";

        }

        $db = newMysql();
        return $db->Fetch($sql);
    }

    public function authenticate($request = null)
    {
        if(empty($request)){
            throw new Exception('Empty Request');
        }

        $pwd_salted = hash_hmac("sha256", $request['password'], $_ENV['pwd_seed']);
        $pwd_hashed = $this->get_password(array("username"=>$request['username']));

        if(password_verify($pwd_salted, $pwd_hashed)){
            return true;
        } else {
            return false;
        }

    }

    public function deauthenticate()
    {
        echo "look maa i did something";
    }

    public function create_user($request = null)
    {
        if(empty($request)){
            throw new Exception('Empty Request');
        }
        
        $pwd_salted = hash_hmac("sha256", $request['password'], $_ENV['PWD_SEED']);
        $pwd_hashed = password_hash($pwd_salted, PASSWORD_ARGON2ID);

        $params = array(
            ":a" => $request['username'],
            ":b" => $request['email'],
            ":c" => $pwd_hashed,
            ":d" => $request['first_name'],
            ":e" => $request['last_name']
        );

        $sql = 'INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`) VALUES (:a, :b, :c, :d, :e)';

        $db = new Mysql();
        $db->Fetch($sql, $params);

    }

}