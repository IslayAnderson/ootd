<?php

namespace validate;

class Validate
{
    private $input;

    public function __construct($input)
    {
        $this->inout = $input;
    }

    public function basic()
    {
        if($this->input/filter_var($this->input, "FILTER_SANITIZE_EMAIL")!=0){
            !empty($_SESSION['user'])?$_SESSION['user']->deauthenticat():throw new Exception('No user session');
            return false;
        }else{
            return true;
        }
    }
}