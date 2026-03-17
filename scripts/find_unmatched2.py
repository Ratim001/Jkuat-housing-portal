from pathlib import Path
p=Path(r'c:/Users/USER/Downloads/jkuat-housing-portal-20260204T072537Z-3-001/jkuat-housing-portal/php/manage_applicants.php')
s=p.read_text()
cnt=0
stack=[]
for i,ch in enumerate(s):
    if ch=='{':
        cnt+=1
        stack.append(i)
    elif ch=='}':
        if stack:
            stack.pop()
            cnt-=1
        else:
            print('extra closing at idx',i)
            break
if stack:
    print('Unmatched opens count',len(stack))
    for pos in stack[-5:]:
        line_no = s.count('\n',0,pos)+1
        start=max(0,pos-50)
        end=min(len(s),pos+50)
        seg=s[start:end]
        print('--- pos',pos,'line',line_no)
        print(seg)
else:
    print('Balanced')
