# Optional initial batch accountability

Run `database/st_db_batch_accountability_update.sql` **once** on your existing database before opening the new feature. Back up your database first. The migration only creates two tables; it does not drop or modify existing asset records.

Assignments > Select New Assets by PO# > enter PO > select 2+ eligible new-purchase assets > Create Initial Batch Document. You may continue using individual forms instead. Batches are immutable snapshots and can be reprinted by PO. Existing transfers, returns, and other lifecycle actions are unchanged. Photos are omitted.

Eligibility requires asset_source = New Purchase, currently Assigned, exactly one history entry (initial ASSIGNED), and no earlier batch entry. Older records without assignment history are intentionally excluded. This avoids incorrectly treating legacy or transferred assets as new.

Part/model uses asset_type (fallback asset_name); no dedicated supplier part number field exists. Signature and date are handwritten. This implementation has static syntax validation only; live MySQL/browser acceptance tests remain necessary.
