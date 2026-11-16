# ST Asset Management System — Transaction Field & Indicator Guide

## Purpose
This guide explains what each important field and indicator means so users can make the correct transaction the first time.

## Required fields
A red `*` means the field is required. Do not submit until all required fields are correct.

## Asset registration fields
- **Asset Code** — unique identifier used to search, print, scan, and reference the asset.
- **Asset Name** — recognizable name/model of the asset.
- **Category** — high-level grouping such as IT Equipment, Office Supplies, or Vehicles.
- **Asset Source** — identifies whether the record is newly acquired or Legacy / Existing.
- **Condition** — current physical/operational condition when the record is created or updated.
- **Legacy / Previous Reference** — old property/SAP/reference number when available.
- **Date Added to ST** — date the record entered the ST system.
- **Asset Type** — specific type such as Laptop, Printer, Vehicle, Monitor, etc.
- **Serial Number / Plate Number** — manufacturer serial number or vehicle plate number used for physical identification.
- **Supplier** — vendor/supplier associated with procurement.
- **Purchase / PO Number** — purchase order or procurement reference.
- **Purchase Date / Cost** — procurement information for financial/reference purposes.
- **Status** — current lifecycle state. Normally use Available for a newly registered operational asset.
- **Asset Images** — physical evidence/reference photos; up to 8 images, JPG/PNG/WEBP, 5 MB each.
- **Description** — additional identifying or operational notes.

## Assignment
Use when an **Available** asset is issued to an active employee.

1. Select the correct asset.
2. Select the active employee.
3. Record the condition at issuance.
4. Add remarks when useful.
5. Submit.

Result: `Available → Assigned`, current employee is stored, and an `ASSIGNED` history record is created.

## Transfer
Use when an **Assigned** asset moves from Employee A to Employee B.

1. Verify the current accountability shown on screen.
2. Select the new employee.
3. Record condition at transfer.
4. Add the reason or notes if needed.
5. Submit.

Result: asset remains `Assigned`, the accountable employee changes, and a `TRANSFERRED` history record preserves the previous accountability.

## Turnover / Return
Use when the current employee returns an assigned asset.

1. Verify the current employee.
2. Inspect the asset.
3. Select returned condition.
4. Record missing accessories, defects, or observations in remarks.
5. Submit.

Result: `Assigned → Available`, current employee is cleared, and a `RETURNED` history record is created.

## Maintenance
Use for repair, preventive service, inspection, or other service work.

- **Asset** — must be unassigned and eligible for maintenance.
- **Issue or service title** — short, specific problem/service name.
- **Type** — Repair, Preventive, Inspection, or Other.
- **Priority** — Low, Normal, High, or Urgent.
- **Target date** — expected completion date.
- **Vendor / technician** — person/company performing the work.
- **Problem or requested service** — detailed description of the problem or requested work.
- **Attachment** — supporting PDF/image such as service report, quotation, receipt, or photo; maximum 5 MB.

Maintenance statuses:
- `Open` — created, not started.
- `In Progress` — actively being worked on.
- `Waiting for Parts` — paused pending parts/materials.
- `Completed` — service finished; asset normally returns to Available.
- `Cancelled` — service will not proceed; asset returns to its eligible prior state.

Rules: assigned assets must first be turned over; retired assets cannot start new work; one active work order is allowed per asset; active maintenance blocks normal assignment and bulk status/accountability changes.

## Clearance
Search the employee and check current assigned assets. If zero assets are currently assigned, the employee is cleared. If one or more assets remain assigned, resolve the accountability through Turnover or Transfer before printing final clearance.

## Status indicators
- **Available** — ready for assignment or eligible maintenance.
- **Assigned** — currently accountable to an employee.
- **Maintenance** — under an active work order.
- **Retired** — no longer in normal operational use.

## Common buttons
- **View** — inspect details without changing the record.
- **Edit** — update permitted asset information.
- **Assign** — issue an Available asset to an employee.
- **Transfer** — move accountability from one employee to another.
- **Turnover** — receive an asset back from the current employee.
- **Maintenance** — create or manage service work.
- **Print** — produce a transaction/accountability document.
- **History** — review the audit trail.
- **Export** — produce a spreadsheet/report for authorized operational use.

## Before every transaction
Verify: **Asset → Current status → Current employee → New employee (if applicable) → Condition → Remarks → Submit**.

A successful transaction changes the live record and creates history. Do not repeat a transaction just because you want to add a note; use the appropriate follow-up action and review History.
