<?php
require 'vendor/autoload.php';
require 'common.php';
use JonnyW\PhantomJs\Client;

Logger::configure("log4php.conf.xml");
$logger = Logger::getLogger("add.php");

function get_short_md5_name($url)
{
    return substr(md5($url), 0, 7);
}

function get_html_from_url_and_save_to_cache($url, $filename)
{
    global $logger;
    
    $client = new \GuzzleHttp\Client();
    try {
        $url_prefix = parse_url($url)['scheme'] . "://" . parse_url($url)['host'];
        $res = $client->request('GET', $url);
        if ($res->getStatusCode() == 200) {
            $text = (string) $res->getBody();
            
            while (true) {
                if (strstr($text, "<frameset") and preg_match('/<frame\s+[^>]*src=[\'\"]([^\'\"]+)[\'\"]/', $text, $matches)) {
                    $url = $matches[1];
                    if (!preg_match('/^http/', $url)) {
                        $url = $url_prefix . $url;
                    }
                    $url = preg_replace('/&amp;/', '&', $url);
                    $logger->info("executing phantomjs for subframe url '" . $url . "'");
                    $client = Client::getInstance();
                    $request = $client->getMessageFactory()->createRequest($url, 'GET');
                    $response = $client->getMessageFactory()->createResponse();
                    $client->send($request, $response);
                    if ($response->getStatus() === 200) {
                        $text = $response->getContent();
                    }
                    
                    $url_prefix = parse_url($url)['scheme'] . "://" . parse_url($url)['host'];
                    sleep(1);
                } else {
                    break;
                }
            }
            
            $path = "cache/" . $filename;
            $fp = fopen($path, "w");
            fwrite($fp, $text, strlen($text) + 1);
            fclose($fp);

            return array($text, true);
        }
    } catch (Exception $e) {
        $logger->error("Unreachable document in " . $url);
        return array("", false);
    }
}

function check_file_existence($filename)
{
    $path = "cache/" . $filename;
    if (file_exists($path) && filesize($path) >= 512) {
        return true;
    } else {
        return false;
    }
}

function get_html_from_file($filename)
{
    $path = "cache/" . $filename;
    $text_arr = array();
    $fp = fopen($path, "r");
    while (!feof($fp)) {
        array_push($text_arr, fgets($fp));
    }
    fclose($fp);
    return implode("", $text_arr);
}

function save_readable_file($cache_filename, $html)
{
    $readable_filename = $cache_filename . ".readable";
    $path = "cache/" . $readable_filename;
    $fp = fopen($path, 'w');
    fwrite($fp, $html);
    fclose($fp);

    return get_html_from_file($readable_filename);
}

function make_clean_file($cache_filename)
{
    global $logger;
    
    $clean_filename = $cache_filename . ".clean";
    $cache_file_path = "cache/" . $cache_filename;
    $path = "cache/" . $clean_filename;
    $cmd = "cat $cache_file_path | env LC_ALL=ko_KR.UTF-8 LANG=ko_KR.UTF-8 PATH=/home/terzeron/.pyenv/shims python ./extract.py '' > $path 2>&1";
    $logger->info("cmd=$cmd<br>\n");
    shell_exec($cmd);

    return get_html_from_file($clean_filename);
}

function get_readable_html_from_url($url)
{
    global $logger;

    $do_exist_cache = false;
    $do_exist_clean = false;
    $do_exist_readable = false;

    $cache_filename = get_short_md5_name($url);
    $logger->info($url . " -> " . $cache_filename);
    $readable_filename = $cache_filename . ".readable";
    $clean_filename = $cache_filename . ".clean";
    //print "url=$url<br>\n";
    //print "cache_filename=$cache_filename, clean_filename=$clean_filename<br>\n";

    if (check_file_existence($cache_filename)) {
        $do_exist_cache = true;
        $html = get_html_from_file($cache_filename);
    } else {
        list($html, $status) = get_html_from_url_and_save_to_cache($url, $cache_filename);
    }
    
    if (check_file_existence($clean_filename)) {
        $do_exist_clean = true;
        $html = get_html_from_file($clean_filename);
    } else {
        $html = make_clean_file($cache_filename);
    }

    if (check_file_existence($readable_filename)) {
        $do_exist_readable = true;
        $html = get_html_from_file($readable_filename);
    } else {
        $readability = new Readability\Readability($html);
        $result = $readability->init();
        if ($result) {
            $html = $readability->getContent()->innerHTML;
            save_readable_file($cache_filename, $html);
        }
    }
    $logger->info("File existence: " . 
                  "cache[" . ($do_exist_cache ? "O" : "X") . "], " .
                  "clean[" . ($do_exist_clean ? "O" : "X") . "], " .
                  "readable[" . ($do_exist_readable ? "O" : "X") . "]");
    return $html;
}

function read_url($url)
{
    global $logger;

    try {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url);
        if ($res->getStatusCode() == 200) {
            $text = (string) $res->getBody();
            return array($text, true);
        } else {
            $logger->error("Failure in getting " . $url);
            return array("", false);
        }
    } catch (Exception $e) {
        $logger->error("Invalid url: " . $url);
        return array("", false);
    }
}

function extend_rss($text)
{
    global $logger;
    
    try {
        $xml = new SimpleXMLElement($text);;
        if ($xml->{"channel"} && $xml->{"channel"}->{"item"}) {
            // RSS 2.0
            $items = $xml->{"channel"}->{"item"};
            $id_element_key = "guid";
            $link_element_key = "link";
            $desc_element_key = "description";
        } else {
            // ATOM
            $items = $xml->{"entry"};
            $id_element_key = "id";
            $link_element_key = "feedburner";
            $desc_element_key = "summary";
        }
        foreach ($items as $item) {
            $url = (string) $item->{$link_element_key};
            if (preg_match("/^https?:\/\//", $url)) {
                $item->{$desc_element_key} = get_readable_html_from_url($url);
            } else {
                $url = (string) $item->{$id_element_key};
                if (preg_match("/^https?:\/\//", $url)) {
                    $item->{$desc_element_key} = get_readable_html_from_url($url);
                }
            }
        }
        print $xml->asXML();
        return true;
    } catch (Exception $e) {
        $logger->error("Invalid xml format: " . $text);
        return false;
    }
}

function update_status($remote_url, $status)
{
    global $logger;
    
    $conf = read_config();
    $db_name = $conf['db_name'];
    $user = $conf['db_user'];
    $pass = $conf['db_pass'];
    $dbh = new PDO("mysql:host=localhost;dbname=" . $db_name, $user, $pass, array(PDO::ATTR_AUTOCOMMIT => true));
    
    $query = "select * from rss where url = '" . $remote_url . "'";
    $rs = $dbh->query($query);
    if ($rs->rowCount() > 0) {
        // update
        $query = "update rss set mtime = now(), status = " . $status . " where url = '" . $remote_url . "'";
    } else {
        // insert
        $query = "insert into rss (url, ctime, mtime, status) values ('" . $remote_url . "', now(), now(), " . $status . ")";
    }
    $dbh->exec($query);
    $logger->info($query);
}

function register_new_url()
{
    global $logger;
    
    $url = $_SERVER['REQUEST_URI'];
    $remote_url = preg_replace('/.*\/(https?:\/\/.*)/', '$1', $url);
    if ($remote_url == "") {
        exit(0);
    }
    $logger->info("Processing " . $remote_url);
    
    list($text, $status) = read_url($remote_url);
    
    extend_rss($text);

    update_status($remote_url, $status);
}

register_new_url();
?>
