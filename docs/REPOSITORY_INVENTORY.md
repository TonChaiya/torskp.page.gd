> บัญชีไฟล์นี้เป็น snapshot ประวัติ ณ HEAD 986c48cd ก่อน docs และ DB baseline ไม่ใช่รายการไฟล์ปัจจุบัน; schema/ignore/docs เปลี่ยนหลังจากนั้นตาม CHANGELOG. รายการ dump ที่เคย track ใช้เพื่อระบุไฟล์ที่จะ untrack ไม่ใช่คำสั่งให้เก็บหรือเผยแพร่ข้อมูล.

# Repository inventory — audited baseline

GitHub main: `986c48cdcfe76116742a3a8cccffac2c0e75eef6` (v1.1.0 + archive deletion). Tree: `2bfba773fd1c561ce904b5e856c72dfb5baf7716`. [ดู tree](https://github.com/TonChaiya/torskp.page.gd/tree/986c48cdcfe76116742a3a8cccffac2c0e75eef6).

รายการด้านล่างคือไฟล์ของ baseline ก่อนเพิ่มเอกสารชุดนี้ โดยเทียบ Git blob SHA จาก recursive tree กับสำเนาในเครื่องตรวจสอบ (normalize CRLF สำหรับ text). ขนาดคือ Git blob bytes ไม่ใช่ขนาดบน Windows. SHA ยืนยันไฟล์ที่ใช้วิเคราะห์ ไม่ใช่การรับรองว่าโค้ดทุกบรรทัดปลอดภัย/ผ่าน QA. ไม่แสดงเนื้อหา SQL dump หรือส่งต่อ binary. ไฟล์ migration supplier และ tools/check_invitation_export.php ในสำเนาท้องถิ่นไม่ได้อยู่ใน HEAD จึงไม่นับ.

จำนวนไฟล์ baseline: **69**

| Path | Bytes | Git blob SHA-1 |
|---|---:|---|
| `.gitattributes` | 66 | `dfe0770424b2a19faf507a501ebfc23be8f54e7b` |
| `.gitignore` | 340 | `54d891777b7194a930dd7bb74591b4ef261d5510` |
| `.htaccess` | 560 | `3496d0145f7065c640f11e6869d285a2170a9bb1` |
| `README.md` | 2,760 | `7cf51854e6cebd76dbafb15e5392479b55c4b7f0` |
| `admin.php` | 68,623 | `5fd4122390b156b1e9da598447af69bd2f220395` |
| `assets/admin-compact.css` | 10,575 | `f41aa9ac82f3de17ff74645d195e6c7c910f7b32` |
| `assets/app-theme.css` | 12,278 | `2bad34c678cb10e4f08aa0b19b84fc32ba8c9046` |
| `assets/auth-pages.css` | 7,653 | `967de7fdb26c5280cc28de99f60246a9112ecb83` |
| `assets/backoffice-blue.css` | 15,570 | `46e7950702b0bb6d8ff487ed249fb5b5fa0b49ea` |
| `assets/document-workspace.css` | 12,761 | `c68365a1d6965ddf2fd2d5a6184edd4fd93f309a` |
| `assets/document-workspace.js` | 7,425 | `98dfc02e163ba2add61aae9cf1ed52ee2d53203d` |
| `assets/fonts/README.md` | 749 | `f813a6d4a65a04d8d66b93a763f0b0824cdd681a` |
| `assets/fonts/THSarabunIT9-Bold.ttf` | 104,688 | `e8651045c8a7ffa8acf4b5195c92361d18fa7fd3` |
| `assets/fonts/THSarabunIT9-BoldItalic.ttf` | 101,488 | `b105a1b538e6cb680a9e70db8ab2cd41b388c345` |
| `assets/fonts/THSarabunIT9-Italic.ttf` | 101,708 | `cb58ca2a4e672f27bed63a5214e217a2b2b6b759` |
| `assets/fonts/THSarabunIT9-Regular.ttf` | 97,312 | `d5d56bfd92bcd496fa8e5329bbbf09abadb86b56` |
| `assets/history-blue.css` | 15,391 | `fc3161bfd10aec457a5bba95ef3683cf2e2b3d2e` |
| `assets/history-bundle.js` | 11,296 | `e42771c723dff2e4d2ee0813a08240a6a17f25d0` |
| `assets/history-docx-merge.js` | 29,691 | `e5e16a47f277bec8742c7ce1003c56e7e00e0e4e` |
| `assets/home-blue.css` | 7,408 | `e4774bae15be078809edd111f5d4a1835c7d9da4` |
| `assets/js/word_export.js` | 13,596 | `9fbd1e6122ce363d9dc880676a08c14e54ba3642` |
| `assets/nav-blue.css` | 4,802 | `9ea58d8493e1bd9c67b6ce30ac026ac8fb177a9f` |
| `assets/tor-document-print-name.js` | 4,774 | `a2fbfdf57dd72702cd56df08480fcdae6ff32d1f` |
| `case_word_merge.php` | 2,978 | `01d7b282a19ea8b147cfa9c6c5874da1f76937e0` |
| `change_password.php` | 38,165 | `5706d8aeeaa4a64a15c909ad54ec3781e32f3ea3` |
| `committee_scope_invitation.php` | 57,248 | `bed01d1a7d5ec42941b614d9932ae41e3212fec6` |
| `committee_scope_result_memo.php` | 57,331 | `c47a4476b139de807e2fb53817bfb3898224f824` |
| `components/navbar.php` | 4,156 | `ffdb317c425ca20857903dcc17a1ca973d2b7d46` |
| `components/workflow_steps.php` | 3,139 | `f8170dd496ccf9fe8e17085784f22a8551120654` |
| `composer.json` | 486 | `b63f7967cdeae19013a3071d32e41bdda5f93a70` |
| `composer.lock` | 23,523 | `dbe93cb0ad3dc3f2cdbf82f75cfe0e1a849e606b` |
| `core/.htaccess` | 100 | `a4a6694e9485681da8831f533657c66dcb83a720` |
| `core/auth.php` | 7,726 | `94dacf87565dee661115d3ceb0620bf77a5fb7aa` |
| `core/config.php` | 1,545 | `2ebc146e473536eaecae99bf8e562dd937eab8ef` |
| `core/document_filename.php` | 4,706 | `22c088cdf3f575cbbd625ad52917f27fa23a19ad` |
| `core/document_signer_defaults.php` | 2,506 | `4008da570a21d1c0bb540fc558febb49acaedf98` |
| `core/procurement_case.php` | 9,333 | `9324caa4db4d09f32bc832e63e89662b829771de` |
| `core/scope_price_approval_store.php` | 2,563 | `81116662f2a46518f3514e8a4f1f4de41f42d735` |
| `core/scope_price_template_export.php` | 11,411 | `2342f0c68ae4f42a80b7fda192146c928928d759` |
| `core/scope_price_thai_breaker.php` | 3,455 | `7cc7c6bfbfe11c0bdf74880ea49f125d1e6189fb` |
| `core/scope_price_thai_words.txt` | 5,386 | `cebef2128e77aeb1b583db4fe5e8f16653ede4d3` |
| `core/word_export_lib.php` | 90,141 | `a48671d0405613b929fcb85f82da393066105dbd` |
| `database/.htaccess` | 100 | `a4a6694e9485681da8831f533657c66dcb83a720` |
| `database/backup-laragon-if0_40414720_tor_skp.sql` | 1,338,159 | `3f4810201639ac9a40e14c287e637b4340444352` |
| `database/migrations/20260821_document_signer_defaults.sql` | 1,308 | `2db76eaa354960d1fb8244144e3258da048eaf92` |
| `database/migrations/20260821_security_hardening.sql` | 4,351 | `5e16c2c1cb8098128010b505883c12cff14ee2af` |
| `database/migrations/20261124_registration_approval.sql` | 942 | `4647a3daf64dd7c65bad688cb04b98eb66e8aab1` |
| `database/migrations/20261124_scope_price_approval_memos.sql` | 1,060 | `215b1045ba12f8fcffda52aec5c091248f43658e` |
| `database/schema.sql` | 12,454 | `96966350941f54e793436c65ef53fecc1e9dd91a` |
| `database/tor_skp-full-localhost-ready-for-import-25690821-163909.sql` | 1,253,697 | `5bb93c5003386d4b2cdbf832e2fe11cc8bb36f2c` |
| `document_history.php` | 23,204 | `daaffe4e4020fec8ba197c9a10540458ebbc92bb` |
| `index.php` | 7,949 | `5ed6ffdb76498eeb56d616adb92775bc7f68d2fb` |
| `login.php` | 8,939 | `43f62bbce5100dd5850d6a3048be60d629f75a11` |
| `logout.php` | 705 | `bc77044e98ebbe6b1dc8fb543aec779aed159bd7` |
| `meeting_report.php` | 112,876 | `64fbfe5e3b146368577f9dd1524655623dc73d38` |
| `picture/picture-1.png` | 6,104 | `3b6e53b8c162e9cb7c5f00edbbd441b381e0faab` |
| `principle_approval_memo.php` | 125,793 | `571ee002bb50cd10589a5c6ac5dcc3477194e5e4` |
| `register.php` | 11,183 | `e7aad153f89e4a1dae41bcc37f1ecfe8835f6b57` |
| `scope_price_approval_memo.php` | 81,827 | `1e550478397dd26d3e0f806831a8cd0f829ae8c3` |
| `storage/.htaccess` | 100 | `a4a6694e9485681da8831f533657c66dcb83a720` |
| `storage/tmp/.gitkeep` | 1 | `8b137891791fe96927ad78e64b0aad7bded08bdc` |
| `storage/tmp/.htaccess` | 14 | `3a42882788717c9ed1d5c2fcc3277d21ec13152b` |
| `templates/docx/README.md` | 1,249 | `a2c116d1809225238f3a2b796e9bdc1a0718e9e2` |
| `templates/docx/committee_scope_invitation.docx` | 187,550 | `65d519b530d5fae987b66dda68038828ab6772f7` |
| `templates/docx/principle_approval_memo.docx` | 1,885,856 | `e55e9ccb680847cc72aa7350a5cb6a7308523df6` |
| `templates/docx/scope_price_approval_memo.docx` | 1,877,215 | `f0d6b25f95058684d5b3f3433737ae0b604c6fbe` |
| `tools/build_docx_templates.php` | 2,274 | `44562e02c84644699231654b2997e8edd57afd9a` |
| `tor_company_purchase.php` | 137,343 | `8d2c2acbf2ea3b4984430f51c024f0996a16e875` |
| `word_export.php` | 923 | `4cee96756e8f6cd8beb449b2bb566491f2bd692d` |

## ตรวจใหม่ในอนาคต

```bash
git rev-parse HEAD
git ls-tree -r -l HEAD
git log --oneline --all
```

เปรียบเทียบ path และ hash กับ baseline เฉพาะส่วนที่ได้รับผลกระทบ; หลัง docs commit จำนวนไฟล์จะเพิ่มขึ้นตามปกติ. อย่าอัปเดต baseline โดยไม่ได้ตรวจโค้ดจริง.

## หมายเหตุ baseline รุ่นถัดมา
บัญชี SHA ด้านบนเป็น inventory ในอดีตตามวันที่หัวเอกสาร ไม่ใช่รายการปัจจุบันทั้งหมด. v1.2.0 HEAD `fced437f022f07924ca163141b02a14c4f176b5c`; v1.3.0 candidate เพิ่ม `core/document_snapshot.php`, `catalog_snapshot_review.php`, `tests/document_snapshot_test.php`, `docs/DOCUMENT_SNAPSHOT.md`, แก้จุดเชื่อมต่อที่ระบุในเอกสารนี้. ตรวจ tree/HEAD ใหม่เมื่อ Push; ห้ามแก้ hash ในประวัติเพื่อแสร้งว่าตรงกับรุ่นใหม่.
