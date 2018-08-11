#!/usr/bin/env python

import io
import sys
import json
import urllib.request
import MySQLdb


def get_rss_list_from_db():
    rss_url_list = []
    config = json.load(open("db_conf.json"))

    cnx = MySQLdb.connect(**config)
    cur = cnx.cursor()
    cur.execute('select url from rss where enabled = 1')
    for row in cur.fetchall():
        rss_url_list.append(row[0])
    return rss_url_list


def main():
    rss_url_list = get_rss_list_from_db()
    for rss_url in rss_url_list:
        try:
            print(rss_url)
            urllib.request.urlopen("https://terzeron.net/rss_extend/" + rss_url)
        except RemoteDisconnected:
            # retry
            urllib.request.urlopen("https://terzeron.net/rss_extend/" + rss_url)

        
if __name__ == "__main__":
   main()

   
