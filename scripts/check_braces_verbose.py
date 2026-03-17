from pathlib import Path
import re
p = Path(__file__).resolve().parent.parent / 'php' / 'applicant_profile.php'
text = p.read_text(encoding='utf-8')
lines = text.splitlines()
balance = 0
for i, line in enumerate(lines, start=1):
    s = re.sub(r"'(?:[^'\\]|\\.)*'", "", line)
    s = re.sub(r'\"(?:[^"\\]|\\.)*\"', '', s)
    delta = s.count('{') - s.count('}')
    balance_before = balance
    balance += delta
    if i <= 340:
        print(f"{i:4d} | {balance_before:3d} -> {balance:3d} | delta={delta:2d} | {line.rstrip()}")
    if balance < 0:
        print('>>> Balance went negative at line', i)
        break
