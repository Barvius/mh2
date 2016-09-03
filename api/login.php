<?php
header('Access-Control-Allow-Origin: *');  
header('Content-Type: application/json');

$db = file ('my.db');
if(isset($_GET['user']) and isset($_GET['password'])){
    foreach ($db as $full_line){
        $line = explode(':', $full_line);
        if ($line[0] == $_GET['user']){
            $pwd = str_replace(array("\n", "\r"), '', $line[1]);
                if ($pwd == $_GET['password']){
                    echo json_encode(array('code' => '200', 'user' => $_GET['user'], 'tooken' => hash('sha256', $_GET['password'])));
                } else {
                    echo json_encode(array('code' => '401'));
                }
            break;
            
        } 
    }   
}
?>