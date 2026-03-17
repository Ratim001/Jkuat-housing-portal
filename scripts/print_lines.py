from pathlib import Path
p=Path(r'c:/Users/USER/Downloads/jkuat-housing-portal-20260204T072537Z-3-001/jkuat-housing-portal/php/manage_applicants.php')
s=p.read_text().splitlines()
for i,line in enumerate(s, start=1):
    if 80 <= i <= 110:
        print(f"{i:4}: {line}")
