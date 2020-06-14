<?php

function startsWith ($string, $startString){ 
    $len = strlen($startString); 
    return (substr($string, 0, $len) === $startString); 
}

function escmd($text){
    $text = str_replace("*", "\*", $text);
    $text = str_replace("_", "\_", $text);
    $text = str_replace("[", "\[", $text);
    $text = str_replace("`", "", $text);

    return $text;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function generateBookID(){
    return generateRandomString(6);
}
?>