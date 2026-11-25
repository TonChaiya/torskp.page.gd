<?php
declare(strict_types=1);

// Decimal string, no float rounding; matches DECIMAL(15,4).
function torPriceDecimal(string $value): ?string
{
    if (!preg_match('/^(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]+)?$/D', $value)) return null;
    $parts = explode('.', str_replace(',', '', $value),2);
    $whole = ltrim($parts[0],'0'); $whole = $whole === '' ? '0' : $whole;
    $fraction = rtrim($parts[1] ?? '', '0');
    if (strlen($whole)>11 || strlen($fraction)>4) return null;
    return $whole.'.'.str_pad($fraction,4,'0');
}

// Import-only rounding to the existing DECIMAL(15,4) scale. Never use a
// binary float; the original value remains visible in the preview warning.
function torPriceImportDecimal(string $raw): ?string
{
    if (!preg_match('/^(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]+)?$/D', $raw)) return null;
    [$whole, $fraction] = array_pad(explode('.', str_replace(',', '', $raw), 2), 2, '');
    $whole = ltrim($whole, '0') ?: '0';
    if (strlen($whole) > 11) return null;
    $digits = $whole . str_pad(substr($fraction, 0, 4), 4, '0');
    if (strlen($fraction) > 4 && ord($fraction[4]) >= ord('5')) {
        for ($i = strlen($digits)-1; $i >= 0; $i--) {
            if ($digits[$i] !== '9') { $digits[$i] = chr(ord($digits[$i])+1); break; }
            $digits[$i] = '0';
        }
        if ($i < 0) $digits = '1'.$digits;
    }
    if (strlen($digits)>15) return null;
    return substr($digits, 0, -4).'.'.substr($digits, -4);
}

function torPriceRow(array $cells): array
{
    [$name,$strength,$unit,$raw] = array_pad(array_slice($cells,0,4),4,'');
    $errors = []; $price = torPriceImportDecimal($raw);
    // Official reference files can contain line breaks within name/strength/unit.
    // Preserve LF as part of the original key; reject other control characters.
    if (preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $name.$strength.$unit)) $errors[] = 'ชื่อยา ความแรง หรือหน่วยมีอักขระควบคุมที่ไม่รองรับ';
    if ($name === '' || $strength === '' || $unit === '') $errors[] = 'ต้องระบุรายการยา รูปแบบและความแรง และหน่วย';
    if (torChars($name)>255 || torChars($strength)>255 || torChars($unit)>100) $errors[] = 'ข้อความยาวเกินขนาดคอลัมน์ฐานข้อมูล';
    if ($price === null) $errors[] = 'ราคาไม่ถูกต้อง ต้องเป็นตัวเลขไม่ติดลบและไม่เกินขนาด DECIMAL(15,4) หลังปัดเศษ';
    $new = ['name'=>$name,'strength'=>$strength,'unit'=>$unit,'price'=>$price ?? $raw];
    if ($price !== null && torPriceDecimal($raw) === null) $new['original_price'] = $raw;
    return [$new,$errors];
}

function torPriceDecision(array $new, ?array $old): array
{
    $warnings = [];
    if (isset($new['original_price'])) $warnings[] = 'ทศนิยมเกิน 4 ตำแหน่ง: '.$new['original_price'].' → '.$new['price'].' บาท (ปัดเศษ)';
    if ($new['price'] === '0.0000') $warnings[] = 'ราคา 0 บาท กรุณาตรวจสอบก่อนยืนยัน';
    if ($warnings) return ['warning',implode('; ', $warnings),$new];
    return [$old === null ? 'new' : ($old['price'] === $new['price'] ? 'same' : 'update'),'', $new];
}
