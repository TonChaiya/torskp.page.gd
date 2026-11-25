/* Fetch the six existing DOCX exports, merge locally in the browser.
   The original documents and their POST handlers remain untouched. */
(() => {
  'use strict';
  const root = document.getElementById('main-content');
  if (!root) return;
  const csrf = root.dataset.bundleCsrf || '';
  const steps = [
    {page:'principle_approval_memo.php', form:'#principle_case_form', action:'word', label:'อนุมัติหลักการ'},
    {page:'committee_scope_invitation.php', form:'#committee_scope_form', action:'word', label:'เชิญกรรมการ'},
    {page:'tor_company_purchase.php', form:'form.space-y-5[method="post"]', action:'word', label:'จัดทำ TOR'},
    {page:'scope_price_approval_memo.php', form:'#scope-form', action:'word', label:'ขออนุมัติใช้คุณลักษณะฯ'},
    {page:'committee_scope_result_memo.php', form:'#memo-form', action:'word_document', label:'รายงานผลการพิจารณาฯ'},
    {page:'meeting_report.php', form:'#meeting_report_form', action:'word', label:'รายงานการประชุม'},
  ];
  let running = false;

  function setStatus(button, value, kind = '') {
    const out = document.getElementById('merge-status-' + button.dataset.caseId);
    if (!out) return;
    out.textContent = value;
    out.hidden = !value;
    out.classList.toggle('is-error', kind === 'error');
    out.classList.toggle('is-ok', kind === 'ok');
  }

  async function request(url, body, wantBlob = false) {
    const response = await fetch(url, {method:'POST',body,credentials:'same-origin',cache:'no-store',redirect:'follow'});
    if (wantBlob) {
      if (!response.ok || (new URL(response.url)).pathname.endsWith('/login.php')) {
        throw new Error('สร้าง Word ไม่สำเร็จ หรือเซสชันหมดอายุ กรุณาเปิดหน้าเอกสารและเข้าสู่ระบบอีกครั้ง');
      }
      const blob = await response.blob();
      const prefix = new Uint8Array(await blob.slice(0,4).arrayBuffer());
      if (prefix.length !== 4 || prefix[0] !== 0x50 || prefix[1] !== 0x4b || prefix[2] !== 0x03 || prefix[3] !== 0x04) {
        throw new Error('ระบบสร้าง Word ส่งข้อความผิดพลาดแทนไฟล์ DOCX (โปรดตรวจข้อมูลของหัวข้อนี้)');
      }
      return blob;
    }
    const type = response.headers.get('content-type') || '';
    if (!type.includes('application/json')) {
      throw new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง อาจหมดเวลาเข้าสู่ระบบหรือไฟล์ case_word_merge.php ยังไม่ได้วางทับ');
    }
    const json = await response.json();
    if (!response.ok || !json.ok) throw new Error(json.error || 'เกิดข้อผิดพลาดระหว่างประมวลผล Word');
    return json;
  }

  function command(action, caseId, token = '', extra = {}) {
    const data = new FormData();
    data.set('bundle_action', action);
    data.set('case_id', String(caseId));
    data.set('csrf_token', csrf);
    if (token) data.set('job_token', token);
    Object.entries(extra).forEach(([key,value]) => data.set(key,value));
    return data;
  }

  /** Parse an actual JSON literal from the TOR page without evaluating any script. */
  function readPageJson(source, variable) {
    const marker = 'const ' + variable + ' = ';
    const begin = source.indexOf(marker);
    if (begin < 0) throw new Error('ไม่พบข้อมูล TOR ที่บันทึกไว้ (' + variable + ')');
    let i = begin + marker.length;
    while (/\s/.test(source[i] || '')) i++;
    if (source[i] !== '{' && source[i] !== '[') throw new Error('โครงสร้างข้อมูล TOR ไม่ถูกต้อง');
    const stack = [];
    let quote = false, slash = false;
    const start = i;
    for (; i < source.length; i++) {
      const c = source[i];
      if (quote) {
        if (slash) slash = false;
        else if (c === '\\') slash = true;
        else if (c === '"') quote = false;
        continue;
      }
      if (c === '"') {quote = true; continue;}
      if (c === '{' || c === '[') stack.push(c);
      else if (c === '}' || c === ']') {
        const last = stack.pop();
        if (last !== (c === '}' ? '{' : '[')) throw new Error('ข้อมูล TOR ไม่สมบูรณ์');
        if (!stack.length) return JSON.parse(source.slice(start,i+1));
      }
    }
    throw new Error('อ่านรายการยา TOR ไม่ครบถ้วน');
  }

  function inputValue(form, selector, value) {
    const field = form.querySelector(selector);
    if (field) field.value = value == null ? '' : String(value);
  }

  function hydrateTor(form, html, caseId) {
    const histories = readPageJson(html,'histories');
    const keys = readPageJson(html,'historySnapshotKeys');
    const meds = readPageJson(html,'snapshotMedicines');
    const saved = readPageJson(html,'savedTorHistoryIds');
    const matches = Object.values(histories || {}).filter(row => String(row.case_id || '') === String(caseId));
    matches.sort((a,b) => Number(b.id || 0) - Number(a.id || 0));
    const wanted = saved[String(caseId)];
    const history = wanted ? matches.find(h => String(h.id) === String(wanted)) : matches[0];
    if (!history) throw new Error('ไม่พบ TOR ที่บันทึกไว้ในชุดเอกสารนี้');
    let items, committee;
    try {
      items = JSON.parse(history.items_json || '[]');
      committee = JSON.parse(history.committee_json || '[]');
    } catch (_) {throw new Error('ข้อมูลรายการยา TOR ที่บันทึกไว้ไม่ถูกต้อง');}
    if (!Array.isArray(items) || !items.length) throw new Error('ไม่พบรายการยาใน TOR ที่บันทึกไว้');
    inputValue(form, '[name="report_id"]', history.id);
    inputValue(form, '[name="case_id"]', caseId);
    inputValue(form, '[name="price_source"]', history.price_source || '');
    for (let i=1;i<=3;i++) inputValue(form, '[name="committee_' + i + '"]', committee[i-1] || '');
    const tbody = form.querySelector('#medicine_rows');
    const template = tbody && tbody.querySelector('tr');
    if (!template) throw new Error('ไม่พบช่องกรอกรายการยาในหน้า TOR');
    const rows = document.createDocumentFragment();
    let usesPackage = false;
    items.forEach((item,index) => {
      const row = template.cloneNode(true);
      const medId = keys[history.id]?.[index];
      if (!medId || !meds[medId] || meds[medId].error) throw new Error('Snapshot TOR แถว ' + (index+1) + ' ไม่สมบูรณ์ กรุณาตรวจข้อมูลประวัติก่อนรวม Word');
      inputValue(row, '[name="medicine_id[]"]', medId);
      inputValue(row, '[name="medicine_label[]"]', item.name);
      inputValue(row, '[name="quantity[]"]', item.quantity);
      inputValue(row, '[name="requested_quantity[]"]', item.requested_quantity ?? (Number(item.package_size || 0)>0 ? Number(item.quantity)*Number(item.package_size) : item.quantity));
      inputValue(row, '[name="package_size[]"]', item.package_size ?? '');
      inputValue(row, '[name="reference_price[]"]', item.reference_price ?? '');
      inputValue(row, '[name="offer_price[]"]', item.offer_price ?? '');
      if (Number(item.package_size || 0) > 0) usesPackage = true;
      rows.appendChild(row);
    });
    tbody.replaceChildren(rows);
    inputValue(form, '[name="package_mode"]', usesPackage ? '1':'0');
  }

  async function originalDocument(spec, caseId) {
    const sourceUrl = spec.page + '?case_id=' + encodeURIComponent(caseId);
    const sourceRes = await fetch(sourceUrl,{credentials:'same-origin',cache:'no-store'});
    if (!sourceRes.ok || sourceRes.url.includes('login.php')) throw new Error('เปิดหน้าเอกสาร “' + spec.label + '” ไม่สำเร็จ');
    const html = await sourceRes.text();
    const doc = new DOMParser().parseFromString(html,'text/html');
    const form = doc.querySelector(spec.form);
    if (!form) throw new Error('ไม่พบฟอร์มในหน้า “' + spec.label + '” กรุณาตรวจว่าไฟล์ PHP เป็นรุ่นล่าสุด');
    if (spec.page === 'tor_company_purchase.php') hydrateTor(form, html, caseId);
    const params = new FormData(form);
    params.set('action',spec.action);
    params.set('case_id',String(caseId));
    params.set('csrf_token',csrf);
    try {
      return await request(spec.page,params,true);
    } catch (error) {
      throw new Error('ขั้นตอน “' + spec.label + '”: ' + error.message);
    }
  }

  async function merge(button) {
    const caseId = Number(button.dataset.caseId || 0);
    if (!caseId) return;
    if (button.dataset.complete !== '1') {
      setStatus(button,'ยังบันทึกไม่ครบทั้ง 6 ขั้นตอน กรุณาเปิด “ทุกหัวข้อ” ตรวจรายการที่ขาดก่อน','error');
      return;
    }
    if (running) return;
    running = true;
    document.querySelectorAll('[data-merge-word]').forEach(item => item.disabled = true);
    try {
      if (!window.TorskpDocxMerge) throw new Error('ไม่พบไฟล์ตัวรวม Word รุ่นใหม่ กรุณาวางทับ assets ให้ครบแล้วกด Ctrl+F5');
      setStatus(button,'กำลังตรวจชุด #' + caseId + ' ...');
      const started = await request('case_word_merge.php',command('prepare',caseId));
      const files=[];
      for (let i=0;i<steps.length;i++) {
        const step=steps[i];
        setStatus(button,'สร้าง Word ' + (i+1) + '/6 · ' + step.label);
        files.push(await originalDocument(step,caseId));
      }
      setStatus(button,'กำลังประกอบ Section ทั้ง 6 หัวข้อ ...');
      const result=await window.TorskpDocxMerge.combine(files);
      const url = URL.createObjectURL(result.blob);
      const link = document.createElement('a');
      link.href=url; link.download=started.filename || ('ชุด'+String(caseId).padStart(4,'0')+'-รวมเอกสาร01-06.docx');
      document.body.appendChild(link); link.click(); link.remove();
      setTimeout(()=>URL.revokeObjectURL(url),60000);
      setStatus(button,'ดาวน์โหลดแล้ว · ตรวจหน้ากระดาษใน Word Desktop','ok');
    } catch (e) {
      setStatus(button, e instanceof Error ? e.message : 'ประมวลผล Word ไม่สำเร็จ', 'error');
    } finally {
      document.querySelectorAll('[data-merge-word]').forEach(item => item.disabled = false);
      running=false;
    }
  }
  document.querySelectorAll('[data-merge-word]').forEach(button => button.addEventListener('click',()=>merge(button)));
})();
