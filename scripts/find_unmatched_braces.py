from pathlib import Path
p=Path('php/manage_applicants.php')
s=p.read_text()
cnt=0
positions=[]
for i,ch in enumerate(s):
    if ch=='{':
        cnt+=1
        positions.append(i)
    elif ch=='}':
        if cnt>0:
            cnt-=1
            positions.pop()
        else:
            print('Extra closing brace at index',i)
            break
if cnt>0:
    print('Unmatched opens count',cnt)
    for pos in positions[-10:]:
        line_no = s.count('\n',0,pos)+1
        start=max(0,pos-60)
        end=min(len(s),pos+60)
        seg=s[start:end]
        print('---')
        print('pos',pos,'line',line_no)
        print(seg)
else:
    print('Balanced')
