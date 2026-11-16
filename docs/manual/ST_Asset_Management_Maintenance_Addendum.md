# ST Asset Management System
## Maintenance Work Orders Addendum

This addendum supplements the existing ST Asset Management User Manual with the maintenance work-order workflow.

### Access

Maintenance work orders are available to users with the **Administrator** or **Asset Custodian** role from **Transactions → Maintenance**. Viewers and HR / Clearance Officers do not have permission to create or update work orders.

### Open a work order

1. Select **New Work Order**.
2. Choose an unassigned asset with status **Available** or **Maintenance**.
3. Enter an issue or service title. Select the maintenance type and priority.
4. Optionally add a target date, vendor or technician, description, and a PDF or image attachment.
5. Select **Create Work Order**. ST assigns a work-order number, records who opened it, and changes the asset status to **Maintenance**.

An asset can have only one active work order at a time. Assigned assets must be returned through **Turnovers** before maintenance. Retired assets cannot be selected for a new work order.

### Update service and close the order

Open a work order to record its progress. Active statuses are **Open**, **In Progress**, and **Waiting for Parts**. Add diagnosis or findings, work performed, vendor or technician, target date, and labor and parts costs as details become known. Changes and status transitions are recorded in the work-order history with the user and time.

Set the status to **Completed** when service is finished. The asset becomes **Available** unless it was retired while the order was active, in which case it stays **Retired**. Set the status to **Cancelled** if the work will not proceed; cancellation restores the asset's prior eligible status. Completed and cancelled orders are closed and cannot be reopened or edited.

### Attachments

PDF, JPG, PNG, and WEBP attachments are accepted up to 5 MB. Attachments are stored with generated filenames and are downloaded through an authenticated page. Adding a replacement attachment removes the previous file.

### Assignment and import safeguards

The status of an asset with an active work order is locked in Asset Edit, although its other details can still be changed. Such assets are excluded from the assignment list and rejected by the assignment save operation. Excel imports cannot change the status or accountability of an asset with an active work order. Close or cancel the work order before changing the asset's assignment through the normal transaction workflow.

### Searching long dropdowns

Asset, employee, and category pickers combine search and a dropdown arrow in one field. Type part of an asset code or name, employee name or ID, department, or category to narrow the options, then tap the arrow and select the desired option. Clearance already provides its own employee search.

### Asset images

In Add Asset and Edit Asset, use **Choose Images** to open the device's image picker. On mobile, **Take Photo** opens the camera. The selected-image count is shown beside the controls; uploads continue through the existing asset image form.

### Dashboard and history

The dashboard shows the active work-order count separately from the number of assets whose current status is Maintenance. The Maintenance list can be searched and filtered by order status. Each order retains its service details, costs, attachment, and change history.

### Installation requirement

An Administrator must apply `database/st_db_maintenance_work_orders_update.sql` to the existing `st_db` database before using this module. See [Maintenance Work Orders Setup](../setup/MAINTENANCE_WORK_ORDERS_SETUP.txt) for installation and workflow notes. Back up the database before applying migrations.