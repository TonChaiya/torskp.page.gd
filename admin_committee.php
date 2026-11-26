<?php
declare(strict_types=1);

// Admin-only management of the existing committee master templates.
// Never writes to procurement_cases, procurement_documents, tor_histories or Word/PDF data.
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';
requireAdmin(); // Existing authentication, approval, forced-password and POST CSRF checks.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

const TOR_COMMITTEE_FIELDS = ['title', 'chair_name', 'chair_position', 'member1_name', 'member1_position', 'member2_name', 'member2_position'];

function torCommitteeReply(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

function torCommitteeFingerprint(array $row): string
{
    $values = [(string) $row['id']];
    foreach (TOR_COMMITTEE_FIELDS as $key) $values[] = (string) $row[$key];
    $values[] = (string) ($row['updated_at'] ?? '');
    return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function torCommitteePublicRow(array $row): array
{
    $item = ['id' => (int) $row['id']];
    foreach (TOR_COMMITTEE_FIELDS as $key) $item[$key] = (string) $row[$key];
    $item['updated_at'] = (string) ($row['updated_at'] ?? '');
    $item['fingerprint'] = torCommitteeFingerprint($row);
    return $item;
}

function torCommitteePost(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    if (!is_string($value)) throw new InvalidArgumentException('ข้อมูลที่ส่งมาไม่ถูกต้อง');
    return trim($value);
}

function torCommitteeFind(PDO $pdo, int $id, bool $lock = false): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, chair_name, chair_position, member1_name, member1_position, member2_name, member2_position, updated_at FROM committee_sets WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$pdo = null;
try {
    requireDatabaseTables(['committee_sets']);
    $pdo = db();
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET') {
        $rows = $pdo->query('SELECT id, title, chair_name, chair_position, member1_name, member1_position, member2_name, member2_position, updated_at FROM committee_sets ORDER BY title, id')->fetchAll(PDO::FETCH_ASSOC);
        torCommitteeReply(['ok' => true, 'items' => array_map('torCommitteePublicRow', $rows)]);
    }
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        torCommitteeReply(['ok' => false, 'error' => 'ไม่รองรับวิธีส่งคำขอนี้'], 405);
    }
    $action = torCommitteePost('action');
    if (!in_array($action, ['save', 'delete'], true)) throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
    $id = filter_var(torCommitteePost('id', '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
    if ($id === false || ($action === 'delete' && $id === 0)) throw new InvalidArgumentException('รหัสชุดกรรมการไม่ถูกต้อง');
    $values = [];
    if ($action === 'save') {
        foreach (TOR_COMMITTEE_FIELDS as $key) {
            $value = torCommitteePost($key);
            if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > 255) throw new InvalidArgumentException('แต่ละช่องต้องมีความยาวไม่เกิน 255 ตัวอักษร');
            $values[] = $value;
        }
        if ($values[0] === '') throw new InvalidArgumentException('กรุณากรอกชื่อชุดกรรมการ');
    }
    // Guard against accidental changes on a non-transactional or drifted master schema.
    $engine = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $engine->execute(['committee_sets']);
    if (strcasecmp((string) $engine->fetchColumn(), 'InnoDB') !== 0) throw new RuntimeException('โครงสร้างตารางชุดกรรมการไม่รองรับการบันทึกแบบปลอดภัย');
    $pdo->beginTransaction();
    $row = null;
    if ($id > 0) {
        $row = torCommitteeFind($pdo, $id, true);
        if (!$row || !hash_equals(torCommitteeFingerprint($row), torCommitteePost('fingerprint'))) {
            $pdo->rollBack();
            torCommitteeReply(['ok' => false, 'error' => 'ชุดกรรมการถูกแก้ไขหรือลบจากหน้าต่างอื่น กรุณาโหลดข้อมูลล่าสุดก่อนดำเนินการ'], 409);
        }
    }
    if ($action === 'delete') {
        if (torCommitteePost('confirm_title') !== (string) $row['title']) throw new InvalidArgumentException('กรุณายืนยันชื่อชุดกรรมการให้ตรงกัน');
        // Current schema stores committee names/positions as snapshots, not template FKs.
        // Block deletion if a deployment has introduced any incoming FK, especially CASCADE.
        $fk = $pdo->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?');
        $fk->execute(['committee_sets']);
        if ((int) $fk->fetchColumn() !== 0) throw new RuntimeException('พบการอ้างอิงชุดกรรมการในฐานข้อมูล ไม่อนุญาตให้ลบ กรุณาตรวจสอบก่อน');
        $stmt = $pdo->prepare('DELETE FROM committee_sets WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('ลบชุดกรรมการไม่สำเร็จ');
        $pdo->commit();
        auditEvent('committee_set_admin_delete', (int) (currentUser()['id'] ?? 0), ['committee_set_id' => $id]);
        torCommitteeReply(['ok' => true, 'message' => 'ลบชุดกรรมการจากรายการต้นแบบแล้ว']);
    }
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE committee_sets SET title = ?, chair_name = ?, chair_position = ?, member1_name = ?, member1_position = ?, member2_name = ?, member2_position = ? WHERE id = ?');
        $stmt->execute([...$values, $id]);
        $event = 'committee_set_admin_update';
        $message = 'บันทึกการแก้ไขชุดกรรมการแล้ว';
    } else {
        // Unlike the old form's optional upsert, explicit Add never overwrites another template.
        $stmt = $pdo->prepare('INSERT INTO committee_sets (title, chair_name, chair_position, member1_name, member1_position, member2_name, member2_position) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute($values);
        $id = (int) $pdo->lastInsertId();
        $event = 'committee_set_admin_create';
        $message = 'เพิ่มชุดกรรมการแล้ว';
    }
    $item = torCommitteeFind($pdo, $id);
    if (!$item) throw new RuntimeException('ไม่พบข้อมูลที่บันทึก');
    $public = torCommitteePublicRow($item);
    $pdo->commit();
    auditEvent($event, (int) (currentUser()['id'] ?? 0), ['committee_set_id' => $id]);
    torCommitteeReply(['ok' => true, 'message' => $message, 'item' => $public]);
} catch (InvalidArgumentException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    torCommitteeReply(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (PDOException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Committee admin database failure: ' . $e->getMessage());
    if ((string) $e->getCode() === '23000') torCommitteeReply(['ok' => false, 'error' => 'ชื่อชุดซ้ำหรือมีข้อมูลอ้างอิง กรุณาตรวจสอบก่อนบันทึกหรือลบ'], 409);
    torCommitteeReply(['ok' => false, 'error' => 'จัดการชุดกรรมการไม่สำเร็จ กรุณาตรวจสอบข้อมูลล่าสุดก่อนลองใหม่'], 500);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Committee admin failure: ' . $e->getMessage());
    // Only explicit, non-sensitive guard messages may be exposed.
    $allowed = ['โครงสร้างตารางชุดกรรมการไม่รองรับการบันทึกแบบปลอดภัย', 'พบการอ้างอิงชุดกรรมการในฐานข้อมูล ไม่อนุญาตให้ลบ กรุณาตรวจสอบก่อน'];
    torCommitteeReply(['ok' => false, 'error' => in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'ไม่สามารถจัดการชุดกรรมการได้ กรุณาตรวจสอบฐานข้อมูลและข้อมูลล่าสุด'], 500);
}
