#!/usr/bin/env python3
import re
import requests
from bs4 import BeautifulSoup

url = 'https://kingshotdata.com/research/'
html = requests.get(url, timeout=45, headers={'User-Agent':'kingshot-alliance-factual-progression/2.0'}).text
soup = BeautifulSoup(html, 'html.parser')
print('bytes=', len(html), 'tables=', len(soup.find_all('table')))
strings = soup.find_all(string=re.compile(r'Max Level|Research cost|Bandaging II', re.I))
print('matching_strings=', len(strings))
for text in strings[:12]:
    parent = text.parent
    print('TEXT', repr(str(text)[:160]))
    for depth in range(4):
        if parent is None:
            break
        print(' ', depth, parent.name, parent.attrs, repr(parent.get_text(' | ', strip=True)[:700]))
        parent = parent.parent
plain = list(soup.stripped_strings)
for needle in ('Bandaging II', 'Research cost'):
    for i, value in enumerate(plain):
        if needle.lower() in value.lower():
            print('TOKENS', needle, i, plain[max(0,i-8):i+45])
            break
