<?php
require_once __DIR__ . "/../config/database.php";


/*
|--------------------------------------------------------------------------
| Asset Counts
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM assets
");

$total_assets = (int) $stmt->fetch()["total"];


$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM assets
    WHERE status = 'Available'
");

$available_assets = (int) $stmt->fetch()["total"];


$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM assets
    WHERE status = 'Assigned'
");

$assigned_assets = (int) $stmt->fetch()["total"];


$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM assets
    WHERE status = 'Maintenance'
");

$maintenance_assets = (int) $stmt->fetch()["total"];

$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM maintenance_records
    WHERE status IN ('Open', 'In Progress', 'Waiting for Parts')
");

$open_maintenance_orders = (int) $stmt->fetch()["total"];


$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM assets
    WHERE status = 'Retired'
");

$retired_assets = (int) $stmt->fetch()["total"];


$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM assets
    WHERE asset_source = 'Legacy / Existing'
");

$legacy_assets = (int) $stmt->fetch()["total"];


/*
|--------------------------------------------------------------------------
| User Count
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE status = 'Active'
");

$total_users = (int) $stmt->fetch()["total"];


/*
|--------------------------------------------------------------------------
| Category Summary
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        c.id,
        c.name,

        COUNT(a.id) AS total,

        SUM(
            CASE
                WHEN a.status = 'Assigned'
                THEN 1
                ELSE 0
            END
        ) AS assigned,

        SUM(
            CASE
                WHEN a.status = 'Available'
                THEN 1
                ELSE 0
            END
        ) AS available,

        -- Count assets that have been retired.
        -- This is calculated directly from the assets.status field so the
        -- Dashboard always reflects the current database state.
        SUM(
            CASE
                WHEN a.status = 'Retired'
                THEN 1
                ELSE 0
            END
        ) AS retired

    FROM categories c

    LEFT JOIN assets a
        ON a.category_id = c.id

    GROUP BY
        c.id,
        c.name

    ORDER BY
        c.name ASC
");

$category_summary = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Recent Asset History
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT

        h.id,

        h.action,

        h.transaction_date,

        h.condition_status,

        h.remarks,

        a.asset_code,

        a.asset_name,

        from_user.full_name AS from_user_name,

        to_user.full_name AS to_user_name,

        performed_by.full_name AS performed_by_name

    FROM asset_history h

    INNER JOIN assets a
        ON a.id = h.asset_id

    LEFT JOIN users from_user
        ON from_user.id = h.from_user_id

    LEFT JOIN users to_user
        ON to_user.id = h.to_user_id

    INNER JOIN users performed_by
        ON performed_by.id = h.performed_by

    ORDER BY
        h.transaction_date DESC

    LIMIT 10
");

$recent_history = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Dashboard Attention Center
|--------------------------------------------------------------------------
*/

$attention_orders = [];
try {
    $stmt = $conn->query("
        SELECT
            m.id,
            m.work_order_no,
            m.title,
            m.priority,
            m.status,
            m.due_date,
            a.id AS asset_id,
            a.asset_code,
            a.asset_name
        FROM maintenance_records m
        INNER JOIN assets a ON a.id = m.asset_id
        WHERE m.status IN ('Open', 'In Progress', 'Waiting for Parts')
          AND (
                m.priority IN ('High', 'Urgent')
                OR (m.due_date IS NOT NULL AND m.due_date < CURDATE())
                OR (m.due_date = CURDATE())
              )
        ORDER BY
            CASE m.priority WHEN 'Urgent' THEN 0 WHEN 'High' THEN 1 ELSE 2 END,
            CASE WHEN m.due_date IS NULL THEN 1 ELSE 0 END,
            m.due_date ASC,
            m.opened_at ASC
        LIMIT 8
    ");
    $attention_orders = $stmt->fetchAll();
} catch (Throwable $e) {
    // Maintenance migration may not yet be installed on older deployments.
    $attention_orders = [];
}

$attention_count = count($attention_orders);

?>
