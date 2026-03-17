from pathlib import Path
import re
p = Path(__file__).resolve().parent.parent / 'php' / 'applicant_profile.php'
text = p.read_text(encoding='utf-8')
lines = text.splitlines()
balance = 0
for i, line in enumerate(lines, start=1):
    s = re.sub(r"'(?:[^'\\]|\\.)*'", "", line)
    s = re.sub(r'\"(?:[^"\\]|\\.)*\"', '', s)
    for ch in s:
        if ch == '{': balance += 1
        elif ch == '}': balance -= 1
    if balance < 0:
        print('Balance negative at line', i)
        break
else:
    print('Final balance', balance, 'at line', len(lines))
    if balance != 0:
        print('\nShowing last 40 lines:')
        for j in range(max(1,len(lines)-40), len(lines)+1):
            print(f"{j}: {lines[j-1]}")
