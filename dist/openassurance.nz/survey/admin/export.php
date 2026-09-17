<?php
require __DIR__ . '/../inc/lib.php';
oa_no_store();
oa_require_admin();

$db = oa_db();
if ($db === null) {
    http_response_code(503);
    exit('The database is not reachable.');
}

/** Spreadsheets run a cell that starts with one of these as a formula, so defuse it. */
function oa_csv_cell($value): string
{
    $value = (string) $value;
    if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
        $value = "'" . $value;
    }
    return $value;
}

$questions = survey_questions();
$columns = [];
foreach ($questions as $id => $q) {
    if ($q['type'] !== 'text') {
        $columns[] = $id;
    }
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="openassurance-survey-' . gmdate('Ymd') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, array_merge(['id', 'received_utc', 'survey_version'], $columns, ['comment', 'contact']), ',', '"', '');
$stmt = $db->query('SELECT id, created_at, survey_version, answers, comment, contact FROM survey_responses ORDER BY id');
foreach ($stmt as $row) {
    $answers = json_decode((string) $row['answers'], true) ?: [];
    $line = [$row['id'], $row['created_at'], $row['survey_version']];
    foreach ($columns as $id) {
        $value = $answers[$id] ?? '';
        $line[] = oa_csv_cell(is_array($value) ? implode('|', $value) : $value);
    }
    $line[] = oa_csv_cell($row['comment'] ?? '');
    $line[] = oa_csv_cell($row['contact'] ?? '');
    fputcsv($out, $line, ',', '"', '');
}
fclose($out);
