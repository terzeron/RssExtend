### Prerequisite
* PHP with rewrite module enabled
* composer
* Apache HTTPd
* Python
* Phantom.js

### Installation
1. ./initialize.sh
    * This script executes the following commands;
    1. `composer install`
    1. `mkdir cache logs`
    1. `chmod o+w cache/`
2. configuration
    1. change your dbname, username, password in batch.py and common.php

### Description
This web service read the partial RSS and convert it to a full RSS. It crawls the original web pages and make a full  RSS by injecting the content of the web pages to the <description> element of the partial RSS.

### Usage
* A example partial RSS is 'http://www.technologyreview.com'. You can use this RSS extender like the following;
    * `curl http://your.website.com/rss_extend/http://www.technologyreview.com`
* You should register a cron job for deleting files in 'cache' directory periodically.
    * `tmpwatch --atime 168 /your/installation/path/cache`

### Troubleshooting
* Fatal error: require(): Failed opening required 'vendor/autoload.php' (include_path='.:')
    * execute 'composer install'
* Error in executing 'extract.py'
  * add .bashrc and the PATH environment variable to the executing command
    * in function 'make_clean_file()'
    * `$cmd = "cat $readable_file_path | python extract.py > $path";`
    * `$cmd = ". /home/your_login_id/.bashrc; export PATH=$PATH:/usr/local/bin; cat $readable_file_path | python extract.py > $path";`
  * You should edit the /home/your_login_id as your home directory path and /usr/local/bin as python installation path

