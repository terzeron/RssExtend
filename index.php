<?php
require 'vendor/autoload.php';


function get_short_md5_name($url)
{
    return substr(md5($url), 0, 7);
}

function get_html_from_url_and_save_to_cache($url, $filename)
{
    $client = new \GuzzleHttp\Client();
    try {
        $res = $client->request('GET', $url);
        if ($res->getStatusCode() == 200) {
            $text = (string) $res->getBody();
            
            $path = "cache/" . $filename;
            $fp = fopen($path, "w");
            fwrite($fp, $text, strlen($text)+1);
            fclose($fp);
            
            return $text;
        }
    } catch (Exception $e) {
        print("url=$url<br>\n");
    }
    throw new Exception("Unreachable document");
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
        $line = fgets($fp);
        array_push($text_arr, $line);
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
    $cmd = ". /Users/terzeron/.bashrc; export PATH=/usr/local/bin:/usr/bin:/bin; eval \"\$(pyenv  init -)\"; pyenv shell v3.5.2; pyenv activate --quiet; export FEED_MAKER_HOME=/Users/terzeron/workspace/fmd; export PATH=\$FEED_MAKER_HOME/bin:/Users/terzeron/.pyenv/shims/python3:\$PATH; export PYTHONPATH=\$FEED_MAKER_HOME/bin; cat $readable_file_path | extract.py '' > $path 2>&1";
    //print "cmd=$cmd<br>\n";
    shell_exec($cmd);

    return get_html_from_file($clean_filename);
}

function get_readable_html_from_url($url)
{
    $cache_filename = get_short_md5_name($url);
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
        return $html;
    } else {
        throw new Exception("Unreadable document");
    }
}

function read_url($url)
{
    $client = new \GuzzleHttp\Client();
    $res = $client->request('GET', $url);
    if ($res->getStatusCode() == 200) {
        $text = (string) $res->getBody();
        return $text;
    } else {
        throw new Exception("Unreachable document");
    }
}

function extend_rss($text)
{
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
}

function main()
{
    $url = $_SERVER['REQUEST_URI'];
    $remote_url = preg_replace('/^\/rss_extend\/(.*)/', '$1', $url);

    $text = read_url($remote_url);
    
    extend_rss($text);
}

main();
?>
