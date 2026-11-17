<?php
declare(strict_types=1);

require dirname(__DIR__) . '/core/word_export_lib.php';

$templateDirectory = dirname(__DIR__) . '/templates/docx';
$principleTemplate = $templateDirectory . '/principle_approval_memo.docx';

outputPrincipleApprovalMemoDocx([
    'department' => '${department}',
    'memo_no_suffix' => '${memo_no_suffix}',
    'doc_date' => '${doc_date}',
    'fiscal_year' => '${fiscal_year}',
    'plan_revision_clause' => '${plan_revision_clause}',
    'plan_approved_date' => '${plan_approved_date}',
    'budget_amount' => '${budget_amount}',
    'budget_amount_text' => '${budget_amount_text}',
    'network_count' => '${network_count}',
    'item_count' => '${item_count}',
    'items' => [[
        'item_no' => '${item_no}',
        'item_name' => '${item_name}',
        'item_quantity' => '${item_quantity}',
        'item_unit' => '${item_unit}',
    ]],
    'committee' => [[
        'committee_no' => '${committee_no}',
        'committee_name' => '${committee_name}',
        'committee_position' => '${committee_position}',
        'committee_role' => '${committee_role}',
    ]],
    'signer_name' => '${signer_name}',
    'signer_position' => '${signer_position}',
    'director_name' => '${director_name}',
    'director_position_lines' => [
        '${director_position_1}',
        '${director_position_2}',
        '${director_position_3}',
    ],
], 'principle_approval_memo.docx', $principleTemplate);

$invitationTemplate = $templateDirectory . '/committee_scope_invitation.docx';
outputCommitteeScopeInvitationDocx([
    'department'=>'${department}','memo_no_suffix'=>'${memo_no_suffix}','doc_date'=>'${doc_date}',
    'subject'=>'${subject}','item_count'=>'${item_count}','fiscal_year'=>'${fiscal_year}',
    'principle_no_suffix'=>'${principle_no_suffix}','principle_date'=>'${principle_date}',
    'meeting_date'=>'${meeting_date}','meeting_time'=>'${meeting_time}','location'=>'${location}',
    'signer_signature'=>'${signer_signature}','signer_name'=>'${signer_name}',
    'signer_position_lines'=>['${signer_position_1}','${signer_position_2}','${signer_position_3}'],
], 'committee_scope_invitation.docx', $invitationTemplate);

fwrite(STDOUT, $principleTemplate . PHP_EOL);
fwrite(STDOUT, $invitationTemplate . PHP_EOL);
