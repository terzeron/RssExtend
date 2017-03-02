#!/usr/bin/env python

import re
import sys

def main():
    text_arr = []
    for line in sys.stdin:
        text_arr.append(line)

    text = "".join(text_arr)

    text = re.sub(r'<(\w+)(\s+[^>]*)>', r'<\1>', text)
    print(text)
    

if __name__ == "__main__":
    main()
