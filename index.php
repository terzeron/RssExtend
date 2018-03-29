<?php
require 'vendor/autoload.php';
require 'common.php';

Logger::configure("log4php.conf.xml");
$logger = Logger::getLogger("index.php");

$dbh = get_db_connection();
$query = "select * from rss order by enabled desc, mtime desc, url asc";
$rs = $dbh->query($query);
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>RSS Extender</title>
        <link href="/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet"/>
        <link href="/bootstrap-honoka/dist/css/bootstrap.min.css" rel="stylesheet"/>
        <link href="style.css" rel="stylesheet"/>
        <script src="/jquery/dist/jquery.min.js"></script>
    </head>
    
    <body>
        <div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>URL</th>
                        <th>Extended RSS</th>
                        <th>Registration time</th>
                        <th>Last modified time</th>
                        <th>Status</th>
                        <th>Enabled / Disabled</th>
                    </tr>
                </thead>
                <tbody>
                    <?foreach ($rs as $row) {?>
                        <tr>
                            <td><?=$row['id']?></td>
                            <td>
                                <a href="<?=$row['url']?>"><?=$row['url']?></a>
                            </td>
                            <td>
                                <?$extended_rss_url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . $row['url'];?>
                                <?if ($row['enabled']) {?>
                                    <a href="<?=$extended_rss_url?>"><?=$extended_rss_url?></a>
                                <?} else {?>
                                    <?=$extended_rss_url?>
                                <?}?>
                            </td>
                            <td><?=$row['ctime']?></td>
                            <td><?=$row['mtime']?></td>
                            <td>
                                <button type="button" class="btn btn-xs btn-<?=($row['status'] ? "success" : "danger")?>"><?=($row['status'] ? "Ok" : "Not Ok")?></button>
                            </td>
                            <td>
                                <input class="toggle-event" type="checkbox" data-toggle="toggle" <?=($row['enabled'] ? "checked" : "")?> data-size="mini">
                            </td>
                        </tr>
                    <?}?>
                </tbody>
            </table>
        </div>

        <script>
         $(function() {
             $('.toggle-event').change(function(evt) {
                 var clicked_obj = $(evt.target);
                 var feed_url_node = clicked_obj.parent().siblings()[1];
                 var feed_url = feed_url_node.getElementsByTagName("a")[0].href;
                 console.log(feed_url_node);
                 $.post(
                     "exec.php",
                     {
                         "command": ($(this).prop('checked') ? "enable_feed" : "disable_feed"),
                         "feed_url": feed_url
                     },
                     function(data, textStatus, jqXHR) {
                         try {
                             res = jQuery.parseJSON(data);
                         } catch (err) {
                             $("body").append(data);
                         }
                         console.log(res);
                     }
                 );
             })
         })
        </script>
    </body>
</html>
