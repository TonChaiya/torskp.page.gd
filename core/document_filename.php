<?php
declare(strict_types=1);

/** Pure filename helpers. Do not alter document content, numbering or persistence. */
function torFilenameDigits(string $value): string
{
    return strtr($value, ['๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4','๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9']);
}

function torFilenameClean(string $value): string
{
    $value = trim(torFilenameDigits($value));
    $value = preg_replace('~[\\/:*?"<>|\x{0000}-\x{001F}\x{007F}]+~u', '-', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value, " .-\t\n\r\0\x0B");
}

function torFilenameDate(string $input): string
{
    $date = trim(torFilenameDigits($input));
    if ($date === '') return 'ไม่ระบุวันที่';
    $months = [
        'มกราคม'=>1,'กุมภาพันธ์'=>2,'มีนาคม'=>3,'เมษายน'=>4,'พฤษภาคม'=>5,'มิถุนายน'=>6,
        'กรกฎาคม'=>7,'กรกฏาคม'=>7,'สิงหาคม'=>8,'กันยายน'=>9,'ตุลาคม'=>10,'พฤศจิกายน'=>11,'ธันวาคม'=>12,
    ];
    $day = 0; $month = 0; $year = 0;
    foreach ($months as $name=>$number) {
        if (preg_match('/(?<!\d)(\d{1,2})\s*' . preg_quote($name, '/') . '\s*(\d{4})(?!\d)/u', $date, $match)) {
            $day = (int)$match[1]; $month=$number; $year=(int)$match[2]; break;
        }
    }
    if ($year === 0 && preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $date, $m)) {
        $year=(int)$m[1]; $month=(int)$m[2]; $day=(int)$m[3];
    } elseif ($year === 0 && preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $date, $m)) {
        $day=(int)$m[1]; $month=(int)$m[2]; $year=(int)$m[3];
    } elseif ($year === 0 && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $date, $m)) {
        $year=(int)$m[1]; $month=(int)$m[2]; $day=(int)$m[3];
    }
    if ($year >= 1900 && $year <= 2400) $year += 543;
    $gregorian = $year - 543;
    if ($year < 2400 || $year > 2900 || $gregorian < 1 || !checkdate($month, $day, $gregorian)) return 'ไม่ระบุวันที่';
    return sprintf('%04d%02d%02d', $year, $month, $day);
}

function torFilenameCut(string $value, int $limit): string
{
    preg_match_all('/./us', $value, $characters);
    return implode('', array_slice($characters[0] ?? [], 0, $limit));
}

function torFilenameNumber(string $input): string
{
    $number = trim(torFilenameDigits($input));
    // The user enters the official number after the slash; only use what is actually entered.
    if (str_contains($number, '/')) $number = (string)substr($number, strrpos($number, '/') + 1);
    $number = (string)(preg_replace('/^\s*(?:เลขที่|ที่)\s*/u', '', $number) ?? $number);
    return torFilenameCut(torFilenameClean($number), 48);
}

function torFilenameSupplier(string $fullName): string
{
    $name = trim($fullName);
    $name = (string)(preg_replace('/^(?:บริษัท\s*|บจก\.?\s*|ห้างหุ้นส่วนจำกัด\s*|หจก\.?\s*)/u', '', $name) ?? $name);
    $name = (string)(preg_replace('/\s*จำกัด\s*(?:\(มหาชน\)|มหาชน)?\s*$/u', '', $name) ?? $name);
    $name = torFilenameClean($name);
    return $name === '' ? 'ไม่ระบุบริษัท' : torFilenameCut($name, 55);
}

function torDocumentFilename(int $caseId, int $step, string $date, string $memoNumber, string $supplier, string $extension='docx'): string
{
    $titles = [1=>'อนุมัติหลักการ', 2=>'เชิญกรรมการ', 3=>'TOR', 4=>'อนุมัติคุณลักษณะและราคากลาง', 5=>'รายงานผลการพิจารณา', 6=>'รายงานการประชุม'];
    if (!isset($titles[$step])) throw new InvalidArgumentException('Unknown TOR document step');
    $parts = [
        $caseId > 0 ? 'ชุด' . str_pad((string)$caseId, 4, '0', STR_PAD_LEFT) : 'ชุดใหม่',
        str_pad((string)$step, 2, '0', STR_PAD_LEFT),
        torFilenameDate($date),
    ];
    $number = torFilenameNumber($memoNumber);
    if ($number !== '') $parts[] = $number; // No invented memo numbers.
    $parts[] = $titles[$step];
    $parts[] = torFilenameSupplier($supplier);
    return implode('-', $parts) . '.' . ($extension === 'pdf' ? 'pdf' : 'docx');
}

/** Reusable list label. Full legal name is kept in the database. */
function torCaseSupplierLabel(array $case): string
{
    $name = trim((string)($case['supplier_name'] ?? ''));
    return $name !== '' ? $name : 'ยังไม่ระบุบริษัท';
}
