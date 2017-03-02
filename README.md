### Prerequisite
* PHP with rewrite module enabled
* composer
* Apache HTTPd

### Installation
1. composer install
1. chmod o+w cache/

### Description
This web service read the requested partial summary RSS to make full summary RSS. It crawls the original web pages and make a fully summary RSS by injecting the content of the web pages to the <description> element of the partial summary RSS.

### Usage
A example partial summary RSS is 'http://www.technologyreview.com'.
You can use this RSS extender like the following;
 `curl http://your.website.com/rss_extend/http://www.technologyreview.com`
