<br>
<?php
include($_SERVER['DOCUMENT_ROOT']."/init.php");

//var_dump(get_declared_classes());

$user = new User();
$validate = new Validate();

$request = array(
    "username"      =>  $_POST['username'],
    "email"         =>  $_POST['email'],
    "password"      =>  $_POST['password'],
    "first_name"    =>  $_POST['first_name'],
    "last_name"     =>  $_POST['last_name']
);

$state = $validate->basic($request);

if($state){
    echo json_encode(array(
        "state" => $user->create_user($request))
    );
}else{
    echo json_encode(array(
        "state" => "brokey"
    ));
}