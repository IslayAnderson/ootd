<?php

namespace user;

class User
{
    private $user_id;
    private $first_name;
    private $last_name;
    private $email;

    public function __construct($id)
    {
        $this->user_id = $id;
        $this->first_name = $this->get_name($id)[0];
        $this->last_name = $this->get_name($id)[1];
        $this->email = $this->get_email($id);
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
            $sql = "SELECT PASSWORD FROM `users` WHERE username = '"+$user->username+"'";
        } elseif(!empty($user->id)){
            $sql = "SELECT PASSWORD FROM `users` WHERE user_id = '"+$user->id+"'";
        }

        $db = new Sql();
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

    }

    public function create_user($request = null)
    {
        if(empty($request)){
            throw new Exception('Empty Request');
        }
        
        $pwd_salted = hash_hmac("sha256", $request['password'], $_ENV['pwd_seed']);
        $pwd_hashed = password_hash($pwd_peppered, PASSWORD_ARGON2ID);

    }

}