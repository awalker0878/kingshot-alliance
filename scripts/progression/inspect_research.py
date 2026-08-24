#!/usr/bin/env python3
import re
import requests
from bs4 import BeautifulSoup

url = 'https://kingshotdata.com/research/'
response = requests.get(url, timeout=45, headers={'User-Agent':'kingshot-alliance-factual-progression/2.0'})
response.raise_for_status()
response.encoding = response.apparent_encoding or 'utf-8'
soup = BeautifulSoup(response.text, 'html.parser')

details = []
for node in soup.find_all('details'):
    summary = node.find('summary')
    if not summary or 'Max Level' not in summary.get_text(' ', strip=True):
        continue
    strong = summary.find('strong')
    meta = summary.find(string=re.compile(r'Max Level\s+\d+', re.I))
    name = strong.get_text(' ', strip=True) if strong else summary.get_text(' ', strip=True)
    match = re.search(r'Max Level\s+(\d+)', str(meta) if meta else summary.get_text(' ', strip=True), re.I)
    max_level = int(match.group(1)) if match else None
    table = node.find('table', class_=lambda value: value and 'data' in value)
    row_count = 0
    if table:
        body = table.find('tbody')
        row_count = len(body.find_all('tr', recursive=False)) if body else max(0, len(table.find_all('tr')) - 1)
    details.append((name, max_level, row_count, table is not None))

print('bytes=', len(response.content), 'details=', len(details), 'tables=', len(soup.find_all('table')))
print('detail_rows=', sum(row[2] for row in details), 'declared_max_levels=', sum(row[1] or 0 for row in details))
print('without_table=', [row for row in details if not row[3]])
print('row_mismatch_first20=', [row for row in details if row[1] is not None and row[1] != row[2]][:20])
print('first=', details[:3])
print('last=', details[-3:])
