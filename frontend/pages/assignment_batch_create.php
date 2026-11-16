<?php
require_once __DIR__ . '/../../backend/includes/security.php'; st_start_session();
require_once __DIR__ . '/../../backend/includes/auth_check.php';
require_once __DIR__ . '/../../backend/config/database.php';
if (!in_array($_SESSION['role'] ?? '', ['Administrator','Asset Custodian'], true)) { http_response_code(403); exit('Access denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required'); }
require_csrf();
$po=trim((string)($_POST['po'] ?? '')); $ids=$_POST['asset_ids'] ?? [];
if ($po === '' || strlen($po)>100 || !is_array($ids) || count($ids)<2 || count($ids)>200) { http_response_code(400); exit('Select at least two eligible assets from one PO.'); }
$ids=array_values(array_unique(array_map('intval',$ids)));
if (count($ids)<2 || in_array(0,$ids,true)) { http_response_code(400); exit('Invalid selection'); }
try {
 $conn->beginTransaction();
 $placeholders=implode(',',array_fill(0,count($ids),'?'));
 $sql="SELECT a.id,a.asset_code,a.asset_name,a.asset_type,a.serial_number,a.current_user_id,u.full_name FROM assets a JOIN users u ON u.id=a.current_user_id WHERE a.id IN ($placeholders) AND a.purchase_number=? AND a.asset_source='New Purchase' AND a.status='Assigned' AND NOT EXISTS (SELECT 1 FROM accountability_batch_items bi WHERE bi.asset_id=a.id) AND (SELECT COUNT(*) FROM asset_history ah WHERE ah.asset_id=a.id AND ah.action='ASSIGNED')=1 AND EXISTS (SELECT 1 FROM asset_history ah WHERE ah.asset_id=a.id AND ah.action='ASSIGNED' AND ah.to_user_id=a.current_user_id) AND NOT EXISTS (SELECT 1 FROM asset_history ah WHERE ah.asset_id=a.id AND ah.action IN ('TRANSFERRED','RETURNED')) FOR UPDATE";
 $stmt=$conn->prepare($sql); $stmt->execute(array_merge($ids,[$po])); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
 if (count($rows)!==count($ids)) { throw new RuntimeException('Some assets are no longer eligible. Refresh the selection.'); }
 $stmt=$conn->prepare('INSERT INTO accountability_batches (po_number,created_by) VALUES (?,?)'); $stmt->execute([$po,(int)$_SESSION['user_id']]); $batchId=(int)$conn->lastInsertId();
 $stmt=$conn->prepare('INSERT INTO accountability_batch_items (batch_id,asset_id,asset_code,part_number,serial_number,employee_name,employee_id) VALUES (?,?,?,?,?,?,?)');
 foreach ($rows as $r) { $stmt->execute([$batchId,$r['id'],$r['asset_code'],$r['asset_type'] ?: $r['asset_name'],$r['serial_number'],$r['full_name'],$r['current_user_id']]); }
 $conn->commit(); header('Location: /st/frontend/pages/assignment_batch_print.php?id='.$batchId); exit;
} catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); http_response_code(409); exit('Could not create batch: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')); }
