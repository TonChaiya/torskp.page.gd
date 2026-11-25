<?php
declare(strict_types=1);

/** A saved meeting or TOR item needs every field used by its document renderer. */
function meetingTorUsableItems($rows): bool
{
    if (!is_array($rows) || !array_is_list($rows) || !$rows) return false;
    foreach ($rows as $row) {
        if (!is_array($row)) return false;
        foreach (['name', 'unit', 'specification'] as $key) {
            if (!isset($row[$key]) || !is_string($row[$key])) return false;
        }
        if (trim($row['name']) === '') return false;
        foreach (['quantity', 'reference_price', 'offer_price'] as $key) {
            if (!isset($row[$key]) || !is_numeric($row[$key]) || !is_finite((float) $row[$key])) return false;
        }
        foreach (['requested_quantity', 'package_size', 'package_count', 'offer_total'] as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && (!is_numeric($row[$key]) || !is_finite((float) $row[$key]))) return false;
        }
    }
    return true;
}

/** Only a history actually belonging to this case may be explicitly refreshed. */
function meetingTorRefreshItems(?array $history, int $caseId): ?array
{
    if ($caseId <= 0 || !$history || (int) ($history['case_id'] ?? 0) !== $caseId || (int) ($history['id'] ?? 0) <= 0) return null;
    $rows = json_decode((string) ($history['items_json'] ?? ''), true);
    return meetingTorUsableItems($rows) ? $rows : null;
}

function meetingTorFingerprint(int $caseId, int $historyId, string $source, array $items, int $linkedHistoryId = 0, string $priceBasis = 'offer'): string
{
    return hash('sha256', json_encode([$caseId, $historyId, $source, $items, $linkedHistoryId, $priceBasis], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/** Update the conventional drug-count phrase only; not arbitrary minutes or prices typed by hand. */
function meetingTorUpdateCount(string $text, string $count): string
{
    return preg_replace_callback('/(จัด(?:หาการจัด)?ซื้อยา\s+จำนวน\s*)[0-9๐-๙,]+(\s*รายการ)/u', static fn(array $m): string => $m[1] . $count . $m[2], $text) ?? $text;
}

/** Existing meeting snapshots predating this field rendered offer prices. Preserve them. */
function meetingTorPriceBasis(string $itemsSource, array $meetingExtra, array $torExtra, int $historyId): string
{
    if ($itemsSource === 'saved') return ($meetingExtra['price_basis'] ?? null) === 'reference' ? 'reference' : 'offer';
    // Step 3 defaults to reference; its mode belongs only to its linked history.
    $linked = (int) ($torExtra['history_id'] ?? 0);
    if ($linked > 0 && $linked !== $historyId) return 'offer'; // older manually selected history has no mode metadata
    return ($torExtra['price_basis'] ?? null) === 'offer' ? 'offer' : 'reference';
}

function meetingTorUnitPrice(array $row, string $basis): float
{
    return (float) ($row[$basis === 'reference' ? 'reference_price' : 'offer_price'] ?? 0);
}

/** The TOR stores quantity as the priced quantity (boxes in packaging mode). */
function meetingTorReferenceTotal(array $row): float
{
    $quantity = (float) ($row['quantity'] ?? 0);
    if ((float) ($row['package_size'] ?? 0) > 0 && (float) ($row['package_count'] ?? 0) > 0) {
        $quantity = (float) $row['package_count'];
    }
    return meetingTorUnitPrice($row, 'reference') * $quantity;
}
