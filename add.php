<?php
require 'vendor/autoload.php';
require 'common.php';

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
            
            if (preg_match_all('/<i?frame\s+[^>]*src=[\'\"]([^\'\"]+)[\'\"]/', $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $logger->info($match);
                    $url = $match;
                    if ($url != "about:blank") {
                        if (preg_match('/^\/\//', $url)) {
                            $url = "https:" . $url;
                        }
                        if (!preg_match('/^http/', $url)) {
                            $url = $url_prefix . $url;
                        }
                        $url = preg_replace('/&amp;/', '&', $url);
                        $logger->info("executing web client for subframe url '" . $url . "'");
                        $response = $client->request('GET', $url);
                        if ($response->getStatusCode() === 200) {
                            $logger->info("getting html from subframe url");
                            $subframe_text .= $response->getBody();
                        } else {
                            $logger->info($response->getStatusCode());
                            $logger->info($response->getBody());
                        }
                        //$url_prefix = parse_url($url)['scheme'] . "://" . parse_url($url)['host'];
                        //sleep(1);
                    }
                }
            }
            
            $path = "cache/" . $feed_id . "/" . $filename;
            $fp = fopen($path, "w");
            fwrite($fp, $text, strlen($text) + 1);
            fwrite($fp, $subframe_text, strlen($subframe_text) + 1);
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
    if ($fp) {
        while (!feof($fp)) {
            array_push($text_arr, fgets($fp));
        }
        fclose($fp);
    }
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
    $cmd = "cat $cache_file_path | env PATH=/home/terzeron/.pyenv/shims:/bin:/usr/bin:/usr/local/bin ./extract.py '' > $path 2> $path.error; [ -s \"$path.error\" ] || rm -f $path.error";
    $logger->info("cmd=$cmd");
    $output = shell_exec($cmd);
    if (filesize($path) <= 4) {
        $logger->info("remove too small file '" . $path . "'");
        unlink($path);
    }
    if (file_exists($path . ".error")) {
        $logger->info(file_get_contents($path . ".error"));
    }

    return get_html_from_file($clean_filename);
}

function get_readable_html_from_url($feed_id, $url)
{
    global $logger;

    $do_exist_cache = false;
    $do_exist_clean = false;
    $do_exist_readable = false;

    if (!is_dir("cache/" . $feed_id)) {
        mkdir("cache/" . $feed_id);
    }
    $cache_filename = $feed_id . "/" . get_short_md5_name($url);
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
    $logger->info("File '" . $cache_filename . "' existence: " . 
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

function extend_rss($text, $feed_id)
{
    global $logger;
    
    try {
        $xml = new SimpleXMLElement($text);
        if ($xml->{"channel"} && $xml->{"channel"}->{"item"}) {
            // RSS 2.0
            $items = $xml->{"channel"}->{"item"};
            $id_element_key = "guid";
            $link_element_key = "link";
            $desc_element_key = "description";
            $pubtime_element_key = "pubDate";
        } else {
            // ATOM
            $items = $xml->{"entry"};
            $id_element_key = "id";
            $link_element_key = "feedburner";
            $desc_element_key = "summary";
            $pubtime_element_key = "published";
        }
        $published_time = null;
        foreach ($items as $item) {
            $url = (string) $item->{$link_element_key};
            $temp_published_time = $item->{$pubtime_element_key}; // 2018-06-21T07:38:30.000Z or Thu, 26 Jan 2023 14:00:50 +0000
            $temp_published_time = new DateTime($temp_published_time);
            if ($published_time == null || $published_time < $temp_published_time) {
                $published_time = $temp_published_time;
            }
            
            if (preg_match("/^https?:\/\//", $url)) {
                $content = get_readable_html_from_url($feed_id, $url);
                if (str_starts_with($content, "<![CDATA[") && str_ends_with($content, "]]>")) {
                    $item->{$desc_element_key} = $content;
                } else {
                    $item->{$desc_element_key} = "<![CDATA[" . $content . "]]>";
                }
            } else {
                $url = (string) $item->{$id_element_key};
                if (preg_match("/^https?:\/\//", $url)) {
                    $content = get_readable_html_from_url($feed_id, $url);
                    if (str_starts_with($content, "<![CDATA[") && str_ends_with($content, "]]>")) {
                        $item->{$desc_element_key} = $content;
                    } else {
                        $item->{$desc_element_key} = "<![CDATA[" . $content . "]]>";
                    }
                }
            }

            if (!filter_var($item->{"author"}, FILTER_VALIDATE_EMAIL)) {
                $item->{"author"} = "unknown@somewhere.com (" . (string) $item->{"author"} . ")";
            }
        }
        if ($xml->{"channel"}->{"image"} && str_starts_with($xml->{"channel"}->{"image"}->{"url"}, "//")) {
            $xml->{"channel"}->{"image"}->{"url"} = "https:" . $xml->{"channel"}->{"image"}->{"url"};
        }
        print html_entity_decode($xml->asXML());
        return $published_time->format("Y-m-d H:i:s");
    } catch (Exception $e) {
        $logger->error("Invalid xml format: " . $text . ", " . $e);
        return null;
    }
}

function update_status($feed_url, $feed_id, $status, $published_time)
{
    global $logger;
    
    $conf = read_config();
    $db_name = $conf['db_name'];
    $user = $conf['db_user'];
    $pass = $conf['db_pass'];
    $dbh = new PDO("mysql:host=localhost;dbname=" . $db_name, $user, $pass, array(PDO::ATTR_AUTOCOMMIT => true));
    
    $query = "select * from rss where url = '" . $feed_url . "'";
    $rs = $dbh->query($query);
    if ($rs->rowCount() > 0) {
        // update
        $query = "update rss set mtime = now(), published_time = '" . $published_time . "', status = " . $status . " where url = '" . $feed_url . "'";
    } else {
        // insert
        $query = "insert into rss (feed_id, url, ctime, mtime, published_time, status) values ('" . $feed_id . "', '" . $feed_url . "', now(), now(), " . $published_time .  ", " . $status . ")";
    }
    $dbh->exec($query);
    $logger->info($query);
}

function register_new_url()
{
    global $logger;
    
    $url = $_SERVER['REQUEST_URI'];
    $feed_url = preg_replace('/.*\/(https?:\/\/.*)/', '$1', $url);
    if ($feed_url == "") {
        exit(0);
    }

    $logger->info("Processing " . $feed_url);
    $feed_id = get_short_md5_name($feed_url);
    $logger->info("md5 hash id of this feed: " . $feed_id);
    if (!is_dir("cache")) {
        mkdir("cache");
    }
    
    list($text, $status) = read_url($feed_url);
    
    $published_time = extend_rss($text, $feed_id);
    if ($published_time) {
        update_status($feed_url, $feed_id, $status, $published_time);
    }
}

header("Content-type: application/rss+xml");
register_new_url();
?>
