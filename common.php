<?php
function read_config()
{
    $db_name = "rssextend";
    $db_user = "rssextend";
    $db_pass = "micro170422";
    $conf = array('db_name' => $db_name, 'db_user' => $db_user, 'db_pass' => $db_pass);
    return $conf;
}

function get_db_connection()
{
    $conf = read_config();
    $db_name = $conf['db_name'];
    $user = $conf['db_user'];
    $pass = $conf['db_pass'];
    return new PDO("mysql:host=localhost;dbname=" . $db_name, $user, $pass);
}

?>
