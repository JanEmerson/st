#!/usr/bin/env python3
"""Non-destructive source/package checks; does not connect to a database."""
from pathlib import Path
import re, subprocess, sys
root = Path(__file__).resolve().parents[1]
checks=[]
def record(label, ok, detail=''):
    checks.append((label,ok,detail))
php_files=list(root.rglob('*.php'))
failed=[]
for path in php_files:
    result=subprocess.run(['php','-l',str(path)],capture_output=True,text=True)
    if result.returncode: failed.append(str(path.relative_to(root)))
record('PHP syntax',not failed,f'{len(php_files)} files; failures: {failed}')
js=root/'frontend/js/app.js'
r=subprocess.run(['node','--check',str(js)],capture_output=True,text=True)
record('Application JavaScript syntax',r.returncode==0,r.stderr.strip())
sql=(root/'database/st_db_install.sql').read_text()
commands=re.sub(r'(?m)^\s*--.*$', '',sql)
record('Fresh installer contains no destructive DROP commands',not bool(re.search(r'\bDROP\s+(TABLE|DATABASE|SCHEMA)\b',commands,re.I)))
ht=(root/'uploads/.htaccess').read_text()
record('Upload Apache deny-execution rule', 'FilesMatch' in ht and 'Require all denied' in ht and 'ExecCGI' in ht)
record('Upload directory index disabled', 'Indexes' in ht)
record('CSRF primitives available',all(s in (root/'backend/includes/security.php').read_text() for s in ['hash_equals','random_bytes','require_csrf']))
record('Database development credentials unchanged',all(s in (root/'backend/config/database.php').read_text() for s in ['$username = "root"','$password = ""']))
record('Supported /st/ path documented', '/st/' in (root/'README.md').read_text())
print('ST Asset Management — non-destructive static release checks')
for label,ok,detail in checks: print(f'{"PASS" if ok else "FAIL"} | {label}' + (f' | {detail}' if detail else ''))
print(f'\n{sum(x[1] for x in checks)}/{len(checks)} checks passed. No live database/browser verification performed.')
sys.exit(0 if all(x[1] for x in checks) else 1)
