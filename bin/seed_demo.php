<?php
declare(strict_types=1);

/**
 * Optional demo data seeder (development only).
 *
 *   php bin/seed_demo.php          # seed only if no files exist yet
 *   php bin/seed_demo.php --force  # seed even if files already exist
 *
 * Inserts a handful of eOffice/OspynDocs records, attachments (metadata
 * only — no files are written to disk), and update-log entries so the
 * Dashboard's stats and activity feed have something to show.
 *
 * Safe to delete in production. Does NOT touch schema.sql.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;

$force = in_array('--force', $argv, true);
$pdo = Database::connection();

$existing = (int) $pdo->query('SELECT COUNT(*) c FROM files')->fetch()['c'];
if ($existing > 0 && !$force) {
    fwrite(STDOUT, "Files already present ({$existing}). Use --force to seed anyway.\n");
    exit(0);
}

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetch()['id'] ?? 0);
if ($adminId === 0) {
    fwrite(STDERR, "No admin user found. Apply schema.sql first.\n");
    exit(1);
}

$now   = new DateTimeImmutable('now');
$thisM = $now->format('Y-m-08 10:15:00');
$prevM = $now->modify('-2 months')->format('Y-m-12 14:30:00');

$insFile = $pdo->prepare(
    'INSERT INTO files (source_app, reference_no, status, file_note, uploaded_by, upload_date, last_updated_by, last_updated_on, is_deleted, created_at)
     VALUES (:app, :ref, :status, :note, :uby, :udate, :luby, :luon, :del, :created)'
);
$insEo = $pdo->prepare(
    'INSERT INTO eoffice_metadata (file_id, file_ref_no, subject, department, file_category, date_of_document, tags, remarks)
     VALUES (:id, :ref, :subject, :dept, :cat, :ddate, :tags, :remarks)'
);
$insOp = $pdo->prepare(
    'INSERT INTO ospyndocs_metadata (file_id, document_id, document_name, document_type, project_module, version, date_of_creation, tags, remarks)
     VALUES (:id, :did, :name, :type, :proj, :ver, :cdate, :tags, :remarks)'
);
$insAtt = $pdo->prepare(
    'INSERT INTO file_attachments (file_id, original_filename, stored_path, mime_type, file_size_bytes, uploaded_by, uploaded_at, is_deleted)
     VALUES (:fid, :fn, :path, :mime, :size, :uby, :uat, 0)'
);
$insLog = $pdo->prepare(
    'INSERT INTO file_update_log (file_id, updated_by, update_source, updated_at, fields_changed, import_batch_id)
     VALUES (:fid, :uby, :src, :uat, :fc, NULL)'
);

$pdo->beginTransaction();

$eoffice = [
    ['ref'=>'EO/2026/001','status'=>'Open','subject'=>'Annual Budget Proposal 2026-27','dept'=>'Finance','cat'=>'Proposal','created'=>$thisM],
    ['ref'=>'EO/2026/002','status'=>'Pending','subject'=>'Procurement of IT Equipment','dept'=>'Administration','cat'=>'Notice','created'=>$thisM],
    ['ref'=>'EO/2026/003','status'=>'Under Review','subject'=>'Staff Training Programme','dept'=>'HR','cat'=>'Circular','created'=>$thisM],
    ['ref'=>'EO/2025/044','status'=>'Closed','subject'=>'Previous Year Audit Note','dept'=>'Finance','cat'=>'Report','created'=>$prevM],
];
$ospyndocs = [
    ['did'=>'OD-1001','name'=>'System Design Specification','type'=>'Specification','proj'=>'Portal','ver'=>'1.2','created'=>$thisM],
    ['did'=>'OD-1002','name'=>'User Acceptance Test Report','type'=>'Report','proj'=>'Portal','ver'=>'1.0','created'=>$thisM],
    ['did'=>'OD-1003','name'=>'Deployment Runbook','type'=>'Manual','proj'=>'Infra','ver'=>'2.1','created'=>$prevM],
    ['did'=>'OD-1004','name'=>'Requirements Specification','type'=>'Specification','proj'=>'Mobile','ver'=>'0.9','created'=>$prevM],
];

$eoIds = [];
foreach ($eoffice as $f) {
    $insFile->execute([
        'app'=>'eoffice','ref'=>$f['ref'],'status'=>$f['status'],'note'=>'<p>Demo file note for '.$f['ref'].'.</p>',
        'uby'=>$adminId,'udate'=>$f['created'],'luby'=>$adminId,'luon'=>$f['created'],'del'=>0,'created'=>$f['created'],
    ]);
    $id = (int) $pdo->lastInsertId();
    $eoIds[] = $id;
    $insEo->execute(['id'=>$id,'ref'=>$f['ref'],'subject'=>$f['subject'],'dept'=>$f['dept'],'cat'=>$f['cat'],
        'ddate'=>substr($f['created'],0,10),'tags'=>'demo','remarks'=>'Seeded demo record']);
}

$opIds = [];
foreach ($ospyndocs as $f) {
    $insFile->execute([
        'app'=>'ospyndocs','ref'=>$f['did'],'status'=>'Active','note'=>'<p>Demo document note for '.$f['did'].'.</p>',
        'uby'=>$adminId,'udate'=>$f['created'],'luby'=>$adminId,'luon'=>$f['created'],'del'=>0,'created'=>$f['created'],
    ]);
    $id = (int) $pdo->lastInsertId();
    $opIds[] = $id;
    $insOp->execute(['id'=>$id,'did'=>$f['did'],'name'=>$f['name'],'type'=>$f['type'],'proj'=>$f['proj'],
        'ver'=>$f['ver'],'cdate'=>substr($f['created'],0,10),'tags'=>'demo','remarks'=>'Seeded demo record']);
}

// Attachments (metadata only)
$insAtt->execute(['fid'=>$eoIds[0],'fn'=>'budget.pdf','path'=>'demo/budget.pdf','mime'=>'application/pdf','size'=>1572864,'uby'=>$adminId,'uat'=>$thisM]);
$insAtt->execute(['fid'=>$eoIds[0],'fn'=>'annexure.xlsx','path'=>'demo/annexure.xlsx','mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','size'=>524288,'uby'=>$adminId,'uat'=>$thisM]);
$insAtt->execute(['fid'=>$eoIds[1],'fn'=>'quote.pdf','path'=>'demo/quote.pdf','mime'=>'application/pdf','size'=>2097152,'uby'=>$adminId,'uat'=>$thisM]);
$insAtt->execute(['fid'=>$opIds[0],'fn'=>'design.pdf','path'=>'demo/design.pdf','mime'=>'application/pdf','size'=>3670016,'uby'=>$adminId,'uat'=>$thisM]);

// Update-log entries (show as "Updated" in the activity feed)
$insLog->execute(['fid'=>$eoIds[0],'uby'=>$adminId,'src'=>'MANUAL_EDIT','uat'=>$thisM,'fc'=>json_encode(['status'=>['old'=>'Draft','new'=>'Open']])]);
$insLog->execute(['fid'=>$opIds[0],'uby'=>$adminId,'src'=>'MANUAL_EDIT','uat'=>$thisM,'fc'=>json_encode(['version'=>['old'=>'1.1','new'=>'1.2']])]);

$pdo->commit();

fwrite(STDOUT, "Seeded " . count($eoIds) . " eOffice + " . count($opIds) . " OspynDocs demo records.\n");
fwrite(STDOUT, "Visit /dashboard to see populated stats and activity.\n");
