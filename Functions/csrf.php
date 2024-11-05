<?php
    function CSRF_genrate()
    {

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

        $_SESSION['CSRF'] = $CSRF;
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
        if($input == $_SESSION['CSRF']){
            return true;
        }else{
            $user = new user();
            $user->kill_all_sessions();
            throw new Exception('CSRF token missmatch');
        }
    }