<?php
/*
 * ST Asset Management System
 * File: backend/includes/user_asset_report.php
 * Purpose: Shared queries for viewing/exporting the assets currently assigned to a user.
 * Debugging: If the User > View Assets page or Excel export shows incorrect data,
 *             check this file first. The source of truth is assets.current_user_id.
 */

/**
 * Get one user by database ID.
 *
 * @param PDO $conn Active database connection.
 * @param int $userId User primary key.
 * @return array|false
 */
function st_get_user_for_asset_report(PDO $conn, int $userId)
{
    $stmt = $conn->prepare("
        SELECT
            id,
            employee_id,
            username,
            full_name,
            department,
            position,
            role,
            status
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $userId]);

    return $stmt->fetch();
}

/**
 * Get the user's CURRENTLY assigned assets.
 *
 * This intentionally uses assets.current_user_id instead of historical rows.
 * Historical assignments/transfers remain in asset_history and are not mixed
 * into the current accountability list.
 *
 * @param PDO $conn Active database connection.
 * @param int $userId User primary key.
 * @return array<int,array<string,mixed>>
 */
function st_get_user_assigned_assets(PDO $conn, int $userId): array
{
    $stmt = $conn->prepare("
        SELECT
            a.id,
            a.asset_code,
            a.asset_name,
            a.asset_type,
            a.serial_number,
            a.asset_source,
            a.condition_status,
            a.legacy_reference,
            a.date_added,
            a.supplier_name,
            a.purchase_number,
            a.purchase_date,
            a.purchase_cost,
            a.status,
            a.current_user_id,
            a.description,
            c.name AS category_name,
            h.transaction_date AS assignment_date,
            h.action AS assignment_action,
            h.condition_status AS assignment_condition,
            h.remarks AS assignment_remarks,
            performer.full_name AS assigned_by
        FROM assets a
        INNER JOIN categories c
            ON c.id = a.category_id
        LEFT JOIN asset_history h
            ON h.id = (
                SELECT ah.id
                FROM asset_history ah
                WHERE ah.asset_id = a.id
                  AND ah.to_user_id = a.current_user_id
                  AND ah.action IN ('ASSIGNED', 'TRANSFERRED')
                ORDER BY ah.transaction_date DESC, ah.id DESC
                LIMIT 1
            )
        LEFT JOIN users performer
            ON performer.id = h.performed_by
        WHERE a.current_user_id = :user_id
        ORDER BY a.asset_code ASC, a.id ASC
    ");

    $stmt->execute([':user_id' => $userId]);

    return $stmt->fetchAll();
}

/**
 * Convert assigned assets into a simple Excel-friendly row structure.
 *
 * @param array<int,array<string,mixed>> $assets
 * @param array<string,mixed> $user
 * @return array<int,array<int,mixed>>
 */
function st_user_asset_excel_rows(array $assets, array $user): array
{
    $rows = [
        [
            'Asset Code',
            'Asset Name',
            'Category',
            'Asset Type',
            'Serial / Plate Number',
            'Asset Source',
            'Condition',
            'Status',
            'Supplier Name',
            'Purchase Number',
            'Purchase Date',
            'Purchase Cost',
            'Date Added',
            'Assignment / Transfer Date',
            'Assignment Action',
            'Assigned By',
            'Assignment Condition',
            'Assignment Remarks',
            'Description'
        ]
    ];

    foreach ($assets as $asset) {
        $rows[] = [
            $asset['asset_code'],
            $asset['asset_name'],
            $asset['category_name'],
            $asset['asset_type'],
            $asset['serial_number'] ?? '',
            $asset['asset_source'] ?? '',
            $asset['condition_status'] ?? '',
            $asset['status'] ?? '',
            $asset['supplier_name'] ?? '',
            $asset['purchase_number'] ?? '',
            $asset['purchase_date'] ?? '',
            $asset['purchase_cost'] !== null ? (float)$asset['purchase_cost'] : '',
            $asset['date_added'] ?? '',
            $asset['assignment_date'] ?? '',
            $asset['assignment_action'] ?? '',
            $asset['assigned_by'] ?? '',
            $asset['assignment_condition'] ?? '',
            $asset['assignment_remarks'] ?? '',
            $asset['description'] ?? ''
        ];
    }

    return $rows;
}
