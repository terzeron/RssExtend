<?php
function read_config()
{
    $data = json_decode(file_get_contents("db_conf.json"));
    return array('db_host' => $data->{'host'}, 'db_name' => $data->{'database'}, 'db_user' => $data->{'user'}, 'db_pass' => $data->{'password'});
}

function get_db_connection()
{
    $conf = read_config();
    $db_host = $conf['db_host'];
    $db_name = $conf['db_name'];
    $db_user = $conf['db_user'];
    $db_pass = $conf['db_pass'];
    return new PDO("mysql:host=" . $db_host . ";dbname=" . $db_name, $db_user, $db_pass);
}

?>
