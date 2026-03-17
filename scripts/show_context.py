from pathlib import Path
p=Path(r'c:/Users/USER/Downloads/jkuat-housing-portal-20260204T072537Z-3-001/jkuat-housing-portal/php/manage_applicants.php')
s=p.read_text()
pos=5352
start=max(0,pos-100)
end=min(len(s),pos+200)
seg=s[start:end]
line_no = s.count('\n',0,pos)+1
print('line',line_no)
print(seg)
