#!/usr/bin/env python3

import io
import sys
import urllib.request
import mysql.connector


def get_rss_list_from_db():
    rss_url_list = []
    db_name = "yourdbname"
    db_user = "yourusername"
    db_pass = "yourpassword"
    config = { 'user': db_user, 'password': db_pass, 'host': 'localhost', 'database': db_name }

    cnx = mysql.connector.connect(**config)
    cur = cnx.cursor()
    cur.execute('select url from rss where enabled = true')
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

   
