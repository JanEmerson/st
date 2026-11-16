# Batch PO correction fix — 2026-11-16

## What changed
The previous filter required exactly one asset_history row. A normal asset edit could add another history row and incorrectly hide an asset from initial batch selection. Both the batch selection and batch creation validation now require exactly one ASSIGNED history event, matching the current assignee, and no TRANSFERRED or RETURNED events. Other history rows no longer disqualify a newly assigned asset.

## What did not change
- Previously generated batch documents remain immutable snapshots. Editing an asset will NOT silently rewrite BAA documents already issued.
- An asset already included in accountability_batch_items remains ineligible for another initial batch.
- Individual accountability and the rest of the lifecycle are unchanged.
- No database migration is required for this change.

## Test
1. Back up the database and application.
2. Open PO 530000427; identify an asset not already in a batch.
3. Edit its purchase date; verify it still appears if it has exactly one initial assignment and no transfer/return.
4. Verify previously batched assets stay excluded and old BAA reprints remain unchanged.
5. Create a batch of two eligible assets; check printed values and verify no duplicates.

If the asset was already included in BAA-000001 or BAA-000002, correcting its purchase date will not automatically modify those issued snapshots. That case requires a separately designed audited document correction/revision workflow.
