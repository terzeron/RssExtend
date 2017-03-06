<?php
require 'vendor/autoload.php';

Logger::configure("log4php.conf.xml");
$logger = Logger::getLogger("index.php");

function get_short_md5_name($url)
{
    return substr(md5($url), 0, 7);
}

function get_html_from_url_and_save_to_cache($url, $filename)
{
    global $logger;
    
    $client = new \GuzzleHttp\Client();
    try {
        $res = $client->request('GET', $url);
        if ($res->getStatusCode() == 200) {
            $text = (string) $res->getBody();
            
            if (strstr($text, "<frameset")) {
                // phantomjs
                $cmd = "phantomjs render.js $url";
                $p = popen($cmd, "r");
                $text_arr = array();
                while (!feof($p)) {
                    array_push($text_arr, fgets($p));
                }
                pclose($p);
                $text = "".implode($text_arr);
            }
            
            $path = "cache/" . $filename;
            $fp = fopen($path, "w");
            fwrite($fp, $text, strlen($text) + 1);
            fclose($fp);

            return $text;
        }
    } catch (Exception $e) {
        $logger->error("Unreachable document in " . $url);
    }
}

function check_file_existence($filename)
{
    $path = "cache/" . $filename;
    if (file_exists($path) && filesize($path) > 0) {
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

function make_readable_file($cache_filename, $html)
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
    $clean_filename = $cache_filename . ".clean";
    $readable_file_path = "cache/" . $cache_filename . ".readable";
    $path = "cache/" . $clean_filename;
    $cmd = "cat $readable_file_path | extract.py '' > $path 2>&1";
    //print "cmd=$cmd<br>\n";
    shell_exec($cmd);

    return get_html_from_file($clean_filename);
}

function get_readable_html_from_url($url)
{
    global $logger;

    $cache_filename = get_short_md5_name($url);
    $logger->info($url . " -> " . $cache_filename);
    $readable_filename = $cache_filename . ".readable";
    $clean_filename = $cache_filename . ".clean";
    //print "url=$url<br>\n";
    //print "cache_filename=$cache_filename, clean_filename=$clean_filename<br>\n";

    if (check_file_existence($cache_filename)) {
        $html = get_html_from_file($cache_filename);
    } else {
        $html = get_html_from_url_and_save_to_cache($url, $cache_filename);
    }
    
    $readability = new Readability\Readability($html, $url);
    $result = $readability->init();
    if ($result) {
        $html = $readability->getContent()->innerHTML;
        if (check_file_existence($readable_filename)) {
            //print("$readable_filename exists<br>\n");
            $html = get_html_from_file($readable_filename);
        } else {
            //print("$readable_filename don't exist<br>\n");
            make_readable_file($cache_filename, $html);
        }
        
        if (check_file_existence($clean_filename)) {
            //print("$clean_filename exists<br>\n");
            $html = get_html_from_file($clean_filename);
        } else {
            //print("$clean_filename don't exist<br>\n");
            $html = make_clean_file($cache_filename);
        }
    }
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
            return $text;
        } else {
            $logger->error("Failure in getting " . $url);
        }
    } catch (Exception $e) {
        $logger->error("Invalid url: " . $url);
    }
}

function extend_rss($text)
{
    global $logger;
    
    try {
        $xml = new SimpleXMLElement($text);;
        $items = $xml->{"channel"}->{"item"};
        foreach ($items as $item) {
            $url = (string) $item->{"guid"};
            if (preg_match("/^https?:\/\//", $url)) {
                $item->{"description"} = get_readable_html_from_url($url);
            } else {
                $url = (string) $item->{"link"};
                if (preg_match("/^https?:\/\//", $url)) {
                    $item->{"description"} = get_readable_html_from_url($url);
                }
            }
        }
        print $xml->asXML();
    } catch (Exception $e) {
        $logger->error("Invalid xml format: " . $text);
    }
}

function main()
{
    global $logger;
    
    $url = $_SERVER['REQUEST_URI'];
    $remote_url = preg_replace('/.*\/(https?:\/\/.*)/', '$1', $url);
    if ($remote_url == "") {
        exit(0);
    }
    $logger->info("Processing " . $remote_url);
    
    $text = read_url($remote_url);
    
    extend_rss($text);
}

main();
?>
