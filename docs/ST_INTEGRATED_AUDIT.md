# Integrated ST Asset Management audit and implementation

Source: user-uploaded `st-complete-latest.zip` (uncommitted working files included).

## Existing application map
- PHP/PDO/MySQL XAMPP localhost application (`/st`), `st_db`.
- Existing asset registration, assignment, transfer, turnover, clearance,
  maintenance work orders, QR scanning, QR printing, exports and dashboard.
- `assets.qr_token` is the QR identifier; `assets.asset_code` is the human-readable code.
- Maintenance has priority, due date, labor/parts cost, status and attachments already.
  This upgrade reuses these fields instead of replacing the maintenance module.
- Existing QR print modes include A4 20, A4 4 large, 4R 4, 2-inch sticker.
  These are preserved unchanged.

## Changes in this integrated build
1. Physical inventory session snapshot, QR-token/code verification, discrepancy report.
2. Dashboard quick actions and a maintenance alerts page (overdue, urgent).
3. Maintenance enhancement by reusing work-order data and surfacing alerts.
4. Unified print-center navigation to existing print pages (no rewrites).
5. Windows local backup script for database plus uploads; manual recovery guide.
6. Navigation and installation notes.

## Known limitations and follow-up checks
- QR tokens embedded in full URLs are accepted by the updated inventory
  input; enter the token or asset code (USB keyboard-style scanner works if it emits
  that code). Mobile-camera direct integration remains pending.
- Inventory session snapshots all non-retired assets; no location/department scope.
- A pending inventory item means 'not verified', not automatically 'missing'.
- Backup automation via Windows Task Scheduler is not configured by this build.
- Restore is manual, intentionally not a destructive web action.
- Printing still depends on browser/printer support for custom paper sizes.
- Existing QR image generation may depend on an external service.
- Database integration, UI, backup and physical printing must be tested on XAMPP.
- This is an integrated first-pass build, not a claim of production-complete audit.
