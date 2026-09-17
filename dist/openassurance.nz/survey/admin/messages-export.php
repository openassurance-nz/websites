<?php
require __DIR__ . '/../inc/lib.php';
oa_no_store();
oa_require_admin();

if (!oa_contact_open()) {
    http_response_code(503);
    exit('The messages table is not reachable.');
}
$db = oa_db();

/** Spreadsheets run a cell that starts with one of these as a formula, so defuse it. */
function oa_csv_cell($value): string
{
    $value = (string) $value;
    if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
        $value = "'" . $value;
    }
    return $value;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="openassurance-messages-' . gmdate('Ymd') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['id', 'received_utc', 'topic', 'role', 'message', 'name', 'organisation', 'email', 'reply_requested'], ',', '"', '');
$stmt = $db->query('SELECT id, created_at, topic, role, message, name, organisation, email, may_contact FROM contact_messages ORDER BY id');
foreach ($stmt as $row) {
    fputcsv($out, [
        $row['id'],
        $row['created_at'],
        oa_csv_cell($row['topic']),
        oa_csv_cell($row['role'] ?? ''),
        oa_csv_cell($row['message']),
        oa_csv_cell($row['name'] ?? ''),
        oa_csv_cell($row['organisation'] ?? ''),
        oa_csv_cell($row['email'] ?? ''),
        !empty($row['may_contact']) ? 'yes' : 'no',
    ], ',', '"', '');
}
fclose($out);
