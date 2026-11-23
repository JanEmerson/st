<?php
$page_title="Local Backup";
require_once "../../backend/includes/header.php";
require_role(["Administrator"]);
?>
<h1>Local Backup &amp; Recovery</h1>
<div class="table-card" style="padding:24px;max-width:850px">
<p>Back up the database and uploaded files together using the included Windows PowerShell script. Backups are saved outside the web-accessible XAMPP htdocs directory.</p>
<h2>Backup</h2>
<p>Open PowerShell in <code>C:\xampp\htdocs\st</code> and run:</p>
<pre style="white-space:pre-wrap;overflow-wrap:anywhere;padding:14px;background:#111;color:#fff">powershell -ExecutionPolicy Bypass -File .\tools\backup-st.ps1</pre>
<p>The script creates a timestamped ZIP in <code>Documents\ST-Backups</code>, including a database dump and uploaded attachments.</p>
<h2>Restore</h2>
<p>Restore is intentionally not exposed as a web button. Stop editing assets, back up your current data, and follow <code>docs/setup/BACKUP_RESTORE.md</code> for a controlled manual restore.</p>
<p><strong>Important:</strong> Copy backups to another drive periodically. Test restoring to a separate test database before relying on a backup.</p>
</div>
<?php require_once "../../backend/includes/footer.php"; ?>
