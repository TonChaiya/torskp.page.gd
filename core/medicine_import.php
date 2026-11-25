<?php
declare(strict_types=1);

function torMedicineRow(array $cells): array
{
    [$name,$unit,$spec] = array_pad(array_slice($cells,0,3),3,'');
    $errors = [];
    if ($name === '' || $unit === '') $errors[] = 'ต้องระบุชื่อยาและหน่วย';
    if (preg_match('/[\x00-\x1F\x7F]/', $name.$unit) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $spec)) $errors[] = 'พบอักขระควบคุมในข้อความ';
    if (torChars($name)>255 || torChars($unit)>100 || strlen($spec)>65535) $errors[] = 'ข้อความยาวเกินขนาดคอลัมน์ฐานข้อมูล';
    return [['name'=>$name,'unit'=>$unit,'specification'=>$spec],$errors];
}

function torMedicineDecision(array $new, ?array $old): array
{
    if ($old !== null && $old['unit'] !== $new['unit']) return ['conflict','ชื่อยาซ้ำแต่หน่วยต่างกัน ไม่เปลี่ยนหน่วยเดิม',$new];
    if ($new['specification'] === '') {
        $new['specification'] = $old === null ? $new['name'] : $old['specification'];
        return ['warning',$old === null ? 'คุณลักษณะว่าง จะใช้ชื่อยาแทนตามระบบเดิม' : 'คุณลักษณะว่าง จะรักษาข้อมูลเดิม',$new];
    }
    return [$old === null ? 'new' : ($old['specification'] === $new['specification'] ? 'same' : 'update'), '', $new];
}
