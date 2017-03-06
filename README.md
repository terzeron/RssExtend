### Prerequisite
* PHP with rewrite module enabled
* composer
* Apache HTTPd
* Python
* Phantom.js

### Installation
1. `composer install`
1. `chmod o+w cache/`
1. Determine your top module for RssExtender.
    * http://yoursite.com/rss_extend
    * If you choose another top module, you use the new name instead of 'rss_extend'.

### Description
This web service read the requested partial summary RSS to make full summary RSS. It crawls the original web pages and make a fully summary RSS by injecting the content of the web pages to the <description> element of the partial summary RSS.

### Usage
* A example partial summary RSS is 'http://www.technologyreview.com'. You can use this RSS extender like the following;
    * `curl http://your.website.com/rss_extend/http://www.technologyreview.com`
        * Rewrite 'yournewtopmodulename' with the new name which you chose.
* You should register a cron job for deleting files in 'cache' directory periodically.
    * `tmpwatch --atime 168 /installation/path/cache`

### Troubleshooting
* Fatal error: require(): Failed opening required 'vendor/autoload.php' (include_path='.:')
    * execute 'composer install'
* Error in executing 'extract.py'
  * add .bashrc and the PATH environment variable to the executing command
    * in function 'make_clean_file()'
    * `$cmd = "cat $readable_file_path | python extract.py > $path";`
    * `$cmd = ". /home1/myid/.bashrc; export PATH=$PATH:/usr/local/bin; cat $readable_file_path | python extract.py > $path";`
  * You should edit the /home1/myid as your home directory path and /usr/local/bin as python installation path
* Fatal error: Uncaught GuzzleHttp\Exception\RequestException: cURL error 1: Protocol "/rss/https" not supported or disabled in libcurl
    * You should modify the index.php like the following;
        * original code: `$remote_url = preg_replace('/^\/rss_extend\/(.*)/', '$1', $url);`
        * modified code: `$remote_url = preg_replace('/^\/yournewtopmodulename\/(.*)/', '$1', $url);`
            * Rewrite 'yournewtopmodulename' with the new name which you chose.
