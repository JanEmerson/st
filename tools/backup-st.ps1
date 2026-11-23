# Run from any directory. Uses the default Windows XAMPP installation.
$ErrorActionPreference = "Stop"
$project = Split-Path -Parent $PSScriptRoot
$mysqlDump = "C:\xampp\mysql\bin\mysqldump.exe"
if (!(Test-Path $mysqlDump)) { throw "mysqldump.exe not found in C:\xampp\mysql\bin" }
$backupRoot = Join-Path ([Environment]::GetFolderPath("MyDocuments")) "ST-Backups"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$work = Join-Path $backupRoot ("ST-" + $stamp)
New-Item -ItemType Directory -Path $work | Out-Null
try {
  $sqlFile = Join-Path $work "st_db.sql"
  # Defaults match the local XAMPP development database. If credentials change,
  # supply a protected option file with --defaults-extra-file instead.
  $process = Start-Process -FilePath $mysqlDump -ArgumentList @("--user=root","--host=localhost","--single-transaction","--routines","--triggers","--result-file=$sqlFile","st_db") -Wait -PassThru -NoNewWindow
  if ($process.ExitCode -ne 0 -or !(Test-Path $sqlFile) -or (Get-Item $sqlFile).Length -eq 0) { throw "Database export failed." }
  $uploads = Join-Path $project "uploads"
  if (Test-Path $uploads) { Copy-Item $uploads (Join-Path $work "uploads") -Recurse -Force }
  @"
Backup: $stamp
Database: st_db
Project: $project
Restore: see docs/setup/BACKUP_RESTORE.md
"@ | Set-Content -Path (Join-Path $work "README.txt")
  $zip = Join-Path $backupRoot ("ST-" + $stamp + ".zip")
  Compress-Archive -Path (Join-Path $work "*") -DestinationPath $zip -CompressionLevel Optimal
  if (!(Test-Path $zip)) { throw "ZIP creation failed." }
  Write-Host "Backup created: $zip"
} finally {
  if (Test-Path $work) { Remove-Item $work -Recurse -Force }
}
