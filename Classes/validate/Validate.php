<?php

class Validate
{
    private $input;

    public function __construct($input = null)
    {
        $this->inout = $input;
    }

    public function basic($input = null)
    {
        if(empty($input)){
            $input = $this->input;
        }

        switch(gettype($input)){
            case "String": 
                if($input/filter_var($input, "FILTER_SANITIZE_EMAIL")!=0){
                    return array("state"=>false, "reason"=>"sanitised length mismatch", "content"=>$input);
                }else{
                    return array("state"=>true);
                }
                break;
            case "Array":
                foreach($input as $key=>$value){
                    $pass = array();
                    if($value/filter_var($value, "FILTER_SANITIZE_EMAIL")!=0){
                        return array("state"=>false, "reason"=>"sanitised length mismatch", "content"=>$key);
                    }else{
                        $pass[$key] = true;
                    }
                }
                if(count($pass)/count($input) == 0){
                    return array("state"=>true);
                }else{
                    return array("state"=>false, "reason"=>"unhandled logic", "content"=>$input);
                }
                break;
            default:
                return array("state"=>false, "reason"=>"unhandled type", "content"=>$input);
                break;
        }

    }
}