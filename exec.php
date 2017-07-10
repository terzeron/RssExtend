<?php
require("vendor/autoload.php");
require("common.php");

header("Cache-Control: no-cache; must-revalidate;");
error_reporting(E_ALL);

Logger::configure("log4php.conf.xml");
$logger = Logger::getLogger("exec.php");

$message = "";

function disable_feed($feed_url)
{
    $dbh = get_db_connection();
    $query = "update rss set enabled = false where url = '" . $feed_url . "'";
    if ($dbh->exec($query) < 1) {
        return -1;
    }
    return 0;
}

function enable_feed($feed_url)
{
    $dbh = get_connection();
    $query = "update rss set enabled = true where url = '" . $feed_url . "'";
    if ($dbh->exec($query) < 1) {
        return -1;
    }
    return 0;
}

function exec_command()
{
    global $message;
    
    if ($_SERVER["REQUEST_METHOD"] != "POST"){
        $message = "can't accept method '" . $_SERVER["REQUEST_METHOD"] . "'";
        return -1;
    }
    
    $command = $_POST["command"];
    $feed_url = $_POST["feed_url"];
    
    if ($command == "enable_feed") {
        return enable_feed($feed_url);
    } else if ($command == "disable_feed") {
        return disable_feed($feed_url);
    } else {
        $message = "can't identify the command";
        return -1;
    }
    
    return 1;
}

$result = exec_command();
?>
{ "result" : "<?=$result?>", "message" : <?=json_encode($message)?> }
