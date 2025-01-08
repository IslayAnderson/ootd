<?php
    function CSRF_genrate()
    {
        if(!isset($_SESSION['CSRF']['expiry'])){
            $_SESSION['CSRF']['expiry'] = time();
        }
        if(time() < mktime($_SESSION['CSRF']['expiry'])){
            return false;
        }

        $alphanumeric = array_merge(
            range('0', '9'), 
            range('A', 'Z'), 
            range('a', 'z')  
        );
        
        $seedgen = '';
        $seedgen_0 = '';

        for($i = 0; $i < 32; $i++){
            $seedgen .= $alphanumeric[random_int(0, count($alphanumeric)-1)];
            $seedgen_0 .= $alphanumeric[random_int(0, count($alphanumeric)-1)];
        }

        $randomsalt = hash_hmac("sha256", $seedgen, $seedgen_0);
        $CSRF = password_hash($randomsalt, PASSWORD_DEFAULT);

        $params = array( ':a'=> $CSRF, ':b' =>  $_SESSION['user_id']);

        $sql = "UPDATE `users` SET `csrf`= :a WHERE `user_id` = :b";

        if(isset($_SESSION['user_id'])){
            $db = new Mysql();
            $db->Fetch($sql, $params);
        }

        $_SESSION['CSRF'] = array("token"=>$CSRF, "expiry"=>strtotime("+5 minutes"));
    }

    function CSRF_validate($input = null)
    {
        if(empty($input)){
            if($_SESSION['user_id']){
                $user = new user($_SESSION['user_id']);
                $user->deauthenticate();
            }
            throw new Exception('CSRF token missing');
        }

        $params = array( ':a' =>  $_SESSION['user_id']);
        $sql = "SELECT `csrf` FROM `users` WHERE `user_id` = :a";
        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        $CSRF = $row[0]->csrf;

        if($input == $CSRF){
            return true;
        }else{
            $user = new user();
            $user->kill_all_sessions();
            throw new Exception('CSRF token missmatch');
        }
    }