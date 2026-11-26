(() => {
  'use strict';
  const titles={medicines:{add:'เพิ่มรายการยา',edit:'แก้ไขรายการยา'},prices:{add:'เพิ่มราคากลาง',edit:'แก้ไขราคากลางยา'}};
  const fields={medicines:{id:'id',name:'name',unit:'unit',specification:'specification'},prices:{id:'id',medicine_name:'medicine_name',dosage_form_strength:'dosage_form_strength',price_unit:'unit',reference_price:'reference_price'}};
  const opener=new WeakMap();
  function show(dialog, trigger){if(!dialog||dialog.open)return;opener.set(dialog,trigger||document.activeElement);dialog.showModal();document.documentElement.classList.add('tor-modal-open');}
  function edit(kind,row,trigger){
    const dialog=document.getElementById(kind+'-edit-box');if(!dialog||!fields[kind])return;
    const form=dialog.querySelector('form');form.reset();const error=dialog.querySelector('.tor-modal-error');if(error)error.hidden=true;
    for(const [name,key] of Object.entries(fields[kind]))form.elements.namedItem(name).value=row?String(row[key]??''):(name==='id'?'0':'');
    dialog.querySelector('h2').textContent=titles[kind][row?'edit':'add'];
    show(dialog,trigger);
  }
  const clearControllers=new Map();
  for(const [kind,prefix,phrase] of [['prices','price','ล้างราคากลาง'],['medicines','medicine','ล้างรายการยา']]){
    const dialog=document.getElementById(kind+'-clear-box'),form=document.getElementById(prefix+'-clear-form');
    if(!dialog||!form)continue;
    let generation=0,submitting=false,blocked=false;
    const count=document.getElementById(prefix+'-clear-count'),error=document.getElementById(prefix+'-clear-error');
    const ready=()=>!blocked&&form.elements.snapshot_hash.value.length===64&&Number(form.elements.expected_count.value)>0&&form.elements.backup_ack.checked&&form.elements.confirm_phrase.value.trim()===phrase;
    const sync=()=>{form.querySelector('[type="submit"]').disabled=submitting||!ready();};
    async function load(){
      const ticket=++generation;submitting=false;blocked=true;form.reset();form.elements.snapshot_hash.value='';form.elements.expected_count.value='';sync();
      count.textContent='กำลังตรวจสอบ…';error.hidden=true;const warning=document.getElementById(prefix+'-clear-warning');if(warning)warning.hidden=true;
      try{
        const response=await fetch('admin.php?action='+prefix+'_clear_preview',{cache:'no-store',credentials:'same-origin'});
        const data=await response.json();
        if(ticket!==generation||!dialog.open)return;
        if(!response.ok||!data.ok||!Number.isSafeInteger(data.count)||data.count<0||!/^[a-f0-9]{64}$/.test(data.hash))throw new Error(data.error||'ตรวจสอบข้อมูลไม่สำเร็จ');
        blocked=Boolean(data.blocked)||data.count===0;
        form.elements.snapshot_hash.value=data.hash;form.elements.expected_count.value=String(data.count);
        count.textContent=data.count.toLocaleString('th-TH')+' รายการ';
        if(blocked){error.textContent=data.blocked||'ไม่มีข้อมูลให้ล้าง';error.hidden=false;}
        if(warning&&data.deferred>0){warning.textContent='มี '+data.deferred+' รายการในหัวข้อ 1 ที่ไม่มีคุณลักษณะตรง TOR เดิม แต่เอกสารที่บันทึกใช้ข้อมูลของตนเองครบแล้ว หากสร้าง TOR ใหม่จากหัวข้อ 1 ต้องเลือกยาจากคลังใหม่';warning.hidden=false;}
      }catch(e){if(ticket!==generation||!dialog.open)return;count.textContent='ไม่ทราบจำนวน';error.textContent='ตรวจสอบข้อมูลไม่สำเร็จ กรุณาปิดแล้วเปิดหน้าต่างใหม่';error.hidden=false;}
      sync();
    }
    form.addEventListener('input',sync);form.addEventListener('change',sync);
    form.addEventListener('submit',e=>{if(submitting||!ready()){e.preventDefault();return;}submitting=true;sync();dialog.querySelectorAll('[data-modal-close]').forEach(b=>b.disabled=true);});
    clearControllers.set(dialog,{load,busy:()=>submitting});
  }
  function requestClose(dialog){
    if(clearControllers.get(dialog)?.busy())return;
    const root=dialog.querySelector('.tor-import');
    if(!root){dialog.close();return;}
    if(root.dataset.importBusy==='1')return;
    if(root.dataset.importPending==='1'){
      if(['unknown','applying','done'].includes(root.dataset.importState)){
        if(confirm('สถานะการบันทึกยังไม่แน่นอน ต้องตรวจข้อมูลจริงก่อนเริ่มงานใหม่ ต้องการปิดหน้าต่างโดยไม่ยกเลิกงานหรือไม่?'))dialog.close();
        return;
      }
      if(confirm('ยกเลิกงานนำเข้าและล้างข้อมูล Preview ที่พักไว้หรือไม่?'))root.dispatchEvent(new CustomEvent('tor-import-request-cancel'));
      return;
    }
    dialog.close();
  }
  document.addEventListener('click',e=>{
    const target=e.target.closest('[data-modal-add],[data-modal-open],[data-modal-edit],[data-modal-close]');if(!target)return;
    if(target.hasAttribute('data-modal-add')){edit(target.dataset.modalAdd,null,target);return;}
    if(target.hasAttribute('data-modal-open')){const dialog=document.getElementById(target.dataset.modalOpen);show(dialog,target);clearControllers.get(dialog)?.load();return;}
    if(target.hasAttribute('data-modal-edit')){
      if(e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;
      try{const row=JSON.parse(target.dataset.row);if(!row||!Number(row.id))return;e.preventDefault();edit(target.dataset.modalEdit,row,target);}catch{/* Keep existing edit URL as fallback. */}return;
    }
    const dialog=target.closest('dialog');if(dialog)requestClose(dialog);
  });
  for(const dialog of document.querySelectorAll('.tor-admin-modal')){
    dialog.addEventListener('cancel',e=>{e.preventDefault();requestClose(dialog);});
    dialog.addEventListener('close',()=>{if(!document.querySelector('.tor-admin-modal[open]'))document.documentElement.classList.remove('tor-modal-open');const target=opener.get(dialog);if(target?.isConnected)target.focus({preventScroll:true});});
    dialog.addEventListener('tor-import-cancelled',()=>dialog.close());
    const root=dialog.querySelector('.tor-import');
    if(root){const close=dialog.querySelector('.tor-modal-x');const update=()=>{close.disabled=root.dataset.importBusy==='1';close.title=close.disabled?'กำลังประมวลผล กรุณารอจนเสร็จ':'ปิดหน้าต่าง';};new MutationObserver(update).observe(root,{attributes:true,attributeFilter:['data-import-busy']});update();}
  }
  // Preserve search parameters when saving a modal form; never retain legacy edit IDs.
  // The hidden POST field name="action" shadows HTMLFormElement.action.
  // Access the HTML attribute, not the named form property, to keep POST on admin.php.
  for(const form of document.querySelectorAll('.tor-modal-form'))form.addEventListener('submit',e=>{
    if(e.defaultPrevented)return;
    const action=new URL(form.getAttribute('action')||'admin.php',location.href);
    const current=new URLSearchParams(location.search);
    for(const key of ['medicine_search','price_search']){
      if(current.has(key))action.searchParams.set(key,current.get(key));
      else action.searchParams.delete(key);
    }
    for(const key of ['edit_medicine','edit_price'])action.searchParams.delete(key);
    form.setAttribute('action',action.href);
  });
  const initial=document.querySelector('.tor-admin-modal[data-auto-open]');if(initial)show(initial);
})();

/* TOR SKP admin workspace, 2026-11-26.
 * The complete original modal controller above is byte-for-byte unchanged.
 * Enhance the existing admin.php DOM in place; never clone or rebuild old forms.
 */
(() => {
  'use strict';
  const main = document.querySelector('main.compact-admin-main');
  const grid = main?.querySelector(':scope > div.grid');
  const nav = main?.querySelector('.compact-section-nav');
  if (!main || !grid || !nav || !document.body.classList.contains('admin-backoffice') ||
      typeof HTMLDialogElement === 'undefined' || typeof HTMLDialogElement.prototype.showModal !== 'function') { main?.classList.add('tor-workspace-fallback'); return; }
  const definitions = [
    {key:'medicine-catalog', title:'รายการยาและคุณลักษณะ', icon:'Rx', description:'ค้นหา เพิ่ม แก้ไข นำเข้า และส่งออกข้อมูลยา', type:'catalog'},
    {key:'price-references', title:'รายการราคากลาง', icon:'฿', description:'ค้นหา แก้ไข และนำเข้าราคากลางยา', type:'catalog'},
    {key:'committee-sets', title:'ชุดกรรมการ', icon:'คน', description:'เพิ่ม แก้ไข อัปเดต และลบชุดกรรมการต้นแบบ', type:'committee'},
    {key:'document-signers', title:'ผู้เสนอและผู้อนุมัติเอกสาร', icon:'ลง', description:'กำหนดค่าเริ่มต้นผู้ลงนามสำหรับชุดเอกสารใหม่', type:'signers'}
  ];
  const original = new Map(definitions.filter(d => d.type !== 'committee').map(d => [d.key, document.getElementById(d.key)]));
  const csrf = main.querySelector('input[name="csrf_token"]')?.value || '';
  if (!csrf || [...original.values()].some(s => !s || !grid.contains(s) || !s.querySelector('details.admin-panel > summary'))) { main.classList.add('tor-workspace-fallback'); return; }
  // If the new stylesheet is missing, retain the working, original page unchanged.
  const style = document.createElement('link');
  style.rel = 'stylesheet'; style.href = 'assets/admin-workspace.css?v=20261126b';
  style.addEventListener('load', enhance, {once:true});
  style.addEventListener('error', () => main.classList.add('tor-workspace-fallback'), {once:true});
  document.head.append(style);

  function element(tag, cls, content) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (content !== undefined) el.textContent = content;
    return el;
  }
  function button(label, cls, handler) {
    const b = element('button', cls, label); b.type = 'button';
    if (handler) b.addEventListener('click', handler);
    return b;
  }
  // Preserve original nodes and the listeners attached by admin.php. Reorganize only
  // the visual structure, including tool groups and signer fields.
  function redesignSection(def, section, details) {
    if (def.type === 'catalog') {
      const find = details.querySelector('.compact-find');
      const tools = details.querySelector('.tor-catalog-tools');
      const table = details.querySelector('.compact-table-viewport');
      const pager = details.querySelector('.compact-pager');
      if (!find || !tools || !table || !pager) return;
      const toolbar = element('div', 'tor-catalog-toolbar');
      const primary = element('div', 'tor-catalog-primary');
      const utility = element('div', 'tor-catalog-utility');
      const mainActions = element('div', 'tor-catalog-action-group');
      const secondaryActions = element('div', 'tor-catalog-action-group');
      const dangerActions = element('div', 'tor-catalog-danger-group');
      for (const control of [...tools.children]) {
        if (control.matches('[data-modal-add], [data-modal-open$="-import-box"]')) mainActions.append(control);
        else if (control.matches('.tor-clear-trigger, [data-modal-open$="-clear-box"]')) dangerActions.append(control);
        else secondaryActions.append(control);
      }
      primary.append(find, mainActions);
      utility.append(secondaryActions, dangerActions);
      tools.append(utility);
      toolbar.append(primary, tools);
      const results = element('div', 'tor-catalog-results');
      const resultHeading = element('div', 'tor-catalog-results-heading');
      resultHeading.append(element('strong', '', def.key === 'medicine-catalog' ? 'รายการยาในระบบ' : 'รายการราคากลางในระบบ'),
        element('small', '', 'เลื่อนเพื่อดูรายการเพิ่มเติม'));
      results.append(resultHeading, table, pager);
      details.append(toolbar, results);
      const rows = table.querySelector('tbody');
      if (rows) new MutationObserver(() => {
        const scrollBody = section.closest('.tor-workspace-body');
        if (scrollBody?.scrollTop > 0) scrollBody.scrollTop = 0;
      }).observe(rows, {childList:true});
      return;
    }
    if (def.type !== 'signers') return;
    const form = details.querySelector('form');
    if (!form) return;
    const fields = ['proposer_name','proposer_position','approver_name','approver_position'].map(name => form.elements.namedItem(name)?.closest('label'));
    if (fields.some(field => !field) || new Set(fields).size !== 4) return;
    const submit = form.querySelector('button:not([type]),button[type="submit"]');
    if (!submit) return;
    const oldGrid = form.querySelector('.grid');
    const oldFooter = submit.parentElement;
    const layout = element('div', 'tor-signers-layout');
    for (const [offset,title,subtitle] of [[0,'ผู้เสนอเอกสาร','ชื่อและตำแหน่งผู้เสนอ'],[2,'ผู้อนุมัติเอกสาร','ชื่อและตำแหน่งผู้อนุมัติ']]) {
      const group=element('div','tor-signer-group');
      const header=element('div','tor-signer-group-heading');
      header.append(element('strong','',title),element('small','',subtitle));
      group.append(header, fields[offset],fields[offset+1]); layout.append(group);
    }
    if (oldGrid && !oldGrid.children.length) oldGrid.remove();
    const footer=element('div','tor-modal-actions tor-signers-footer');
    submit.classList.add('tor-modal-primary');
    footer.append(submit);
    if (oldFooter && oldFooter !== form && !oldFooter.children.length) oldFooter.remove();
    form.append(layout,footer);
  }
  function enhance() {
    if (main.classList.contains('tor-workspace-ready')) return;
    const dialogs = new Map(), launchers = new Map();
    let committee = null;
    const currentKey = () => location.hash.slice(1);
    const setHash = key => {
      try { const url = new URL(location.href); url.hash = key || ''; history.replaceState(history.state, '', url.pathname + url.search + url.hash); } catch (_) {}
    };
    const unlock = () => {
      if (!document.querySelector('.tor-admin-modal[open]')) document.documentElement.classList.remove('tor-modal-open');
    };
    const closeWorkspace = key => {
      const dlg = dialogs.get(key);
      if (!dlg?.open) return false;
      if (key === 'committee-sets' && !committee?.canClose()) return false;
      dlg.close();
      return true;
    };
    function makeDialog(def) {
      const dialog = element('dialog', 'tor-admin-modal tor-workspace-dialog tor-workspace-' + def.type);
      dialog.id = 'tor-workspace-' + def.key;
      dialog.setAttribute('aria-labelledby', dialog.id + '-title');
      const head = element('header', 'tor-modal-header');
      const heading = element('div');
      const h = element('h2', '', def.title); h.id = dialog.id + '-title';
      heading.append(element('small', '', 'TOR SKP · จัดการข้อมูลหลังบ้าน'), h);
      const x = button('×', 'tor-modal-x', () => closeWorkspace(def.key));
      x.setAttribute('aria-label', 'ปิดหน้าต่าง ' + def.title); x.dataset.workspaceClose = '1';
      head.append(heading, x);
      const body = element('div', 'tor-modal-body tor-workspace-body');
      dialog.append(head, body);
      dialog.addEventListener('cancel', e => { e.preventDefault(); closeWorkspace(def.key); });
      dialog.addEventListener('close', () => {
        if (def.type === 'committee') committee?.onClose();
        if (currentKey() === def.key) setHash('');
        unlock();
        const launcher = launchers.get(def.key);
        if (launcher?.isConnected) launcher.focus({preventScroll:true});
      });
      return {dialog, body, x};
    }
    function openWorkspace(key) {
      const dlg = dialogs.get(key);
      if (!dlg || dlg.open) return;
      // An original edit/import/clear dialog takes precedence; do not cover it.
      const child = document.querySelector('.tor-admin-modal[open]:not(.tor-workspace-dialog)');
      if (child) return;
      for (const [other, open] of dialogs) if (open.open && !closeWorkspace(other)) return;
      dlg.showModal();
      document.documentElement.classList.add('tor-modal-open');
      setHash(key);
      if (key === 'committee-sets') committee?.onOpen();
    }
    const menu = element('div', 'tor-workspace-menu');
    const addGroup = (label, keys) => {
      const group = element('div', 'tor-workspace-group');
      group.append(element('h2', 'tor-workspace-group-title', label));
      const list = element('div', 'tor-workspace-group-items');
      for (const key of keys) {
        const def = definitions.find(d => d.key === key);
        const b = button('', 'tor-workspace-item', () => openWorkspace(key));
        b.setAttribute('aria-haspopup','dialog'); b.setAttribute('aria-controls','tor-workspace-' + key);
        const icon = element('span', 'tor-workspace-icon', def.icon); icon.setAttribute('aria-hidden','true');
        const desc = element('span', 'tor-workspace-item-copy');
        desc.append(element('strong', '', def.title), element('small', '', def.description));
        const action = element('span', 'tor-workspace-item-action', 'จัดการ ›'); action.setAttribute('aria-hidden','true');
        b.append(icon, desc, action); list.append(b); launchers.set(key,b);
      }
      group.append(list); menu.append(group);
    };
    addGroup('ข้อมูลยาและราคากลาง', ['medicine-catalog','price-references']);
    addGroup('บุคลากรและผู้ลงนามเอกสาร', ['committee-sets','document-signers']);

    // Existing DOM nodes (IDs, form fields, tokens, listeners, pagination and table rows) are moved, not copied.
    const prepared = definitions.map(def => [def, makeDialog(def)]);
    for (const [def, parts] of prepared) {
      dialogs.set(def.key, parts.dialog);
      if (def.type === 'committee') committee = buildCommittee(parts.body, parts.x);
      else {
        const section = original.get(def.key);
        const details = section.querySelector('details.admin-panel');
        details.open = true;
        section.classList.add('tor-workspace-original');
        redesignSection(def, section, details);
        parts.body.append(section);
        // Native POST from a list row or signer form must return to its original section.
        // Keep all legacy GET search parameters and the original action field untouched.
        section.addEventListener('submit', e => {
          const form = e.target;
          if (e.defaultPrevented || form.getAttribute('method')?.toLowerCase() !== 'post') return;
          const target = new URL(form.getAttribute('action') || 'admin.php', location.href);
          const current = new URLSearchParams(location.search);
          for (const param of ['medicine_search','price_search']) {
            if (current.has(param)) target.searchParams.set(param,current.get(param));
          }
          for (const param of ['edit_medicine','edit_price','workspace_reload']) target.searchParams.delete(param);
          target.hash = def.key;
          form.setAttribute('action',target.href);
        });
      }
      // Keep within main so the existing back-office styles still apply to original fields/tables.
      main.append(parts.dialog);
    }
    grid.append(menu);
    for (const link of nav.querySelectorAll('a')) {
      const hash = link.getAttribute('href');
      if (hash && original.has(hash.slice(1)) && hash.startsWith('#')) link.hidden = true;
    }
    const navLabel = element('span', 'tor-workspace-nav-label', 'เลือกหมวดที่ต้องการจัดการ');
    nav.prepend(navLabel);
    main.classList.add('tor-workspace-ready');
    // The old success refresh uses a fragment-only URL. With a workspace fragment already open,
    // that can be a same-document navigation. Force a fresh GET only on the existing success-refresh button.
    document.addEventListener('click', e => {
      const refresh = e.target.closest('.tor-import .ti-refresh');
      if (!refresh || refresh.disabled || refresh.closest('.ti-result')?.hidden !== false) return;
      const kind = refresh.closest('.tor-import').dataset.importKind;
      if (!['medicines','prices'].includes(kind)) return;
      e.preventDefault(); e.stopImmediatePropagation();
      const url = new URL('admin.php',location.href);
      url.searchParams.set('workspace_reload',String(Date.now()));
      url.hash = kind === 'medicines' ? 'medicine-catalog' : 'price-references';
      location.assign(url.href);
    },true);
    window.addEventListener('hashchange', () => { if (dialogs.has(currentKey())) openWorkspace(currentKey()); });
    const initialChild = document.querySelector('.tor-admin-modal[open]:not(.tor-workspace-dialog)');
    let initialKey = currentKey();
    if (!dialogs.has(initialKey) && initialChild) {
      if (initialChild.id === 'medicines-edit-box') initialKey = 'medicine-catalog';
      if (initialChild.id === 'prices-edit-box') initialKey = 'price-references';
    }
    if (dialogs.has(initialKey)) {
      if (initialChild) initialChild.addEventListener('close',() => openWorkspace(initialKey),{once:true});
      else openWorkspace(initialKey);
    }
  }

  function buildCommittee(body, closeButton) {
    const fields = ['title','chair_name','chair_position','member1_name','member1_position','member2_name','member2_position'];
    const status = element('p','tor-workspace-notice'); status.setAttribute('role','status'); status.setAttribute('aria-live','polite'); status.hidden=true;
    const list = element('div','tor-committee-list');
    const description = element('p','tor-workspace-note','ใช้ชุดกรรมการต้นแบบเดียวกับหน้าขออนุมัติในหลักการ (หัวข้อ 1) การเปลี่ยนแปลงต้นแบบไม่แก้รายชื่อในเอกสารที่บันทึกไว้แล้ว');
    const tools = element('div','tor-committee-tools');
    const searchLabel = element('label','tor-committee-search');
    searchLabel.append(element('span','','ค้นหาชุดกรรมการ'));
    const search = element('input'); search.type='search'; search.placeholder='ชื่อชุด หรือชื่อกรรมการ'; search.autocomplete='off'; searchLabel.append(search);
    const add = button('＋ เพิ่มชุดกรรมการ','tor-modal-primary',() => openEditor(null));
    const reload = button('โหลดข้อมูลล่าสุด','tor-modal-secondary',() => load());
    tools.append(searchLabel, add, reload);
    const count = element('p','tor-workspace-note'); count.setAttribute('aria-live','polite');
    const tableWrap = element('div','tor-committee-table-wrap');
    const table = element('table','tor-committee-table');
    const head=element('thead'), hr=element('tr');
    for (const title of ['ชื่อชุดกรรมการ','รายชื่อกรรมการ','จัดการ']) {const th=element('th','',title);th.scope='col';hr.append(th);}
    head.append(hr); const rows=element('tbody'); table.append(head,rows);tableWrap.append(table);
    list.append(description,tools,count,tableWrap);
    const editor = element('div','tor-committee-editor'); editor.hidden=true;
    const editorTitle = element('h3','tor-workspace-subtitle');
    const editorForm = element('form','tor-committee-form');
    const fieldNodes = new Map();
    function inputField(name,label,required=false) {
      const wrap=element('label','tor-committee-field');wrap.append(element('span','',label));
      const input=element('input'); input.type='text';input.name=name;input.maxLength=255;input.required=required;input.autocomplete='off';
      wrap.append(input);fieldNodes.set(name,input);return wrap;
    }
    editorForm.append(inputField('title','ชื่อชุดกรรมการ',true));
    for(const [prefix,label] of [['chair','ประธานกรรมการ'],['member1','กรรมการคนที่ 1'],['member2','กรรมการคนที่ 2']]){
      const block=element('div','tor-committee-person');block.append(element('h4','',label));
      const pair=element('div','tor-committee-fields');pair.append(inputField(prefix+'_name','ชื่อ–นามสกุล'),inputField(prefix+'_position','ตำแหน่ง'));block.append(pair);editorForm.append(block);
    }
    const editorHint=element('p','tor-workspace-note','จำนวนกรรมการและช่องข้อมูลใช้ตามระบบเดิม (ประธาน 1 คน และกรรมการ 2 คน) กรุณาตรวจชื่อและตำแหน่งก่อนเลือกใช้กับเอกสาร');
    const editorActions=element('div','tor-modal-actions');
    const back=button('กลับรายการ','tor-modal-secondary',() => backToList());
    const save=element('button','tor-modal-primary','บันทึก');save.type='submit';
    editorActions.append(back,save);editorForm.append(editorHint,editorActions);editor.append(editorTitle,editorForm);
    const deletion=element('div','tor-committee-deletion');deletion.hidden=true;
    const deleteTitle=element('h3','tor-workspace-subtitle','ยืนยันลบชุดกรรมการ');
    const deleteName=element('p','tor-committee-delete-name');
    const deleteHint=element('p','tor-workspace-note','ลบเฉพาะชุดต้นแบบออกจากตัวเลือกสำหรับงานใหม่ ไม่ลบชุดเอกสารหรือรายชื่อที่บันทึกอยู่ในประวัติเดิม');
    const deleteForm=element('form','tor-committee-form');
    const deleteLabel=element('label','tor-committee-field');deleteLabel.append(element('span','','พิมพ์ชื่อชุดกรรมการเพื่อยืนยัน'));
    const confirmInput=element('input');confirmInput.type='text';confirmInput.required=true;confirmInput.maxLength=255;confirmInput.autocomplete='off';deleteLabel.append(confirmInput);
    const deleteActions=element('div','tor-modal-actions');
    const cancelDelete=button('ยกเลิก','tor-modal-secondary',() => backToList());
    const remove=element('button','tor-modal-danger','ยืนยันลบ');remove.type='submit';remove.disabled=true;
    deleteActions.append(cancelDelete,remove);deleteForm.append(deleteLabel,deleteActions);deletion.append(deleteTitle,deleteName,deleteHint,deleteForm);
    body.append(status,list,editor,deletion);
    let items=[], active=null, view='list', generation=0, busy=false, loading=false, ready=false, dirty=false, uncertain=false, loadController=null;
    function notice(text='', error=false) {status.textContent=text;status.hidden=!text;status.dataset.error=error?'1':'0';}
    function setView(next) {view=next;list.hidden=next!=='list';editor.hidden=next!=='editor';deletion.hidden=next!=='delete';body.scrollTop=0;}
    function setBusy(value) {
      busy=value;closeButton.disabled=value;back.disabled=value;cancelDelete.disabled=value;
      for(const input of fieldNodes.values())input.disabled=value;
      confirmInput.disabled=value;save.disabled=value||uncertain;
      remove.disabled=value||uncertain||!active||confirmInput.value.trim()!==active.title;
      add.disabled=value||loading||!ready;reload.disabled=value||loading;search.disabled=value||loading||!ready;
      for(const b of rows.querySelectorAll('button'))b.disabled=value||loading||!ready;
    }
    async function request(options={}) {
      const response=await fetch('admin_committee.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},...options});
      let result;
      try{result=await response.json();}catch(_){const err=new Error('ไม่ได้รับผลจากระบบ กรุณาตรวจสอบสิทธิ์หรือเข้าสู่ระบบใหม่');err.uncertain=true;throw err;}
      if(!response.ok||!result.ok){const err=new Error(result.error||'ดำเนินการไม่สำเร็จ');err.uncertain=response.status>=500;throw err;}
      return result;
    }
    function render() {
      const q=search.value.trim().toLocaleLowerCase();
      const matches=items.filter(item=>!q||fields.some(key=>String(item[key]||'').toLocaleLowerCase().includes(q)));
      rows.replaceChildren();
      count.textContent=ready?`พบ ${matches.length.toLocaleString('th-TH')} จาก ${items.length.toLocaleString('th-TH')} ชุด`:loading?'กำลังโหลดชุดกรรมการ…':'ยังไม่ได้โหลดข้อมูล';
      if(!matches.length){const tr=element('tr'),td=element('td','tor-committee-empty',loading?'กำลังโหลด…':!ready?'กรุณาโหลดข้อมูลล่าสุด':q?'ไม่พบชุดที่ตรงกับคำค้นหา':'ยังไม่มีชุดกรรมการ');td.colSpan=3;tr.append(td);rows.append(tr);return;}
      for(const item of matches){
        const tr=element('tr');const title=element('td');title.append(element('strong','',item.title));
        if(item.updated_at)title.append(element('small','tor-committee-updated','อัปเดต '+item.updated_at));
        const people=element('td');
        for(const [prefix,label] of [['chair','ประธาน'],['member1','กรรมการ 1'],['member2','กรรมการ 2']]){
          const person=element('div','tor-committee-person-line');person.append(element('span','',label+' : '),element('strong','',item[prefix+'_name']||'—'));
          if(item[prefix+'_position'])person.append(element('small','',item[prefix+'_position']));people.append(person);
        }
        const actions=element('td','tor-committee-row-actions');
        const edit=button('แก้ไข','tor-modal-secondary',()=>openEditor(item));const del=button('ลบ','tor-modal-danger',()=>openDelete(item));
        edit.disabled=del.disabled=busy||loading||!ready;actions.append(edit,del);tr.append(title,people,actions);rows.append(tr);
      }
    }
    async function load(keepNotice=false) {
      const ticket=++generation;loadController?.abort();loadController=new AbortController();
      loading=true;ready=false;items=[];active=null;dirty=false;uncertain=false;setView('list');
      if(!keepNotice)notice();render();setBusy(false);
      try{const data=await request({signal:loadController.signal});if(ticket!==generation)return;
        if(!Array.isArray(data.items))throw new Error('รูปแบบข้อมูลชุดกรรมการไม่ถูกต้อง');
        items=data.items;ready=true;
      }catch(error){if(ticket!==generation||error.name==='AbortError')return;notice(error.message,true);}
      finally{if(ticket===generation){loading=false;render();setBusy(false);}}
    }
    function openEditor(item) {
      if(!ready||busy||loading)return;active=item;dirty=false;uncertain=false;notice();
      editorTitle.textContent=item?'แก้ไขชุดกรรมการ':'เพิ่มชุดกรรมการ';save.textContent=item?'บันทึกการแก้ไข':'เพิ่มชุดกรรมการ';
      for(const [key,input] of fieldNodes)input.value=item?.[key]||'';
      setView('editor');setBusy(false);fieldNodes.get('title').focus();
    }
    function openDelete(item) {
      if(!ready||busy||loading)return;active=item;dirty=false;uncertain=false;notice();deleteName.textContent=item.title;confirmInput.value='';setView('delete');setBusy(false);confirmInput.focus();
    }
    function backToList() {
      if(busy)return;
      if(dirty&&!uncertain&&!confirm('ยกเลิกการแก้ไขที่ยังไม่ได้บันทึกหรือไม่?'))return;
      load();
    }
    async function mutate(action, extra) {
      if(busy||uncertain)return;setBusy(true);notice();
      const data=new FormData();data.set('csrf_token',csrf);data.set('action',action);data.set('id',String(active?.id||0));
      if(active)data.set('fingerprint',active.fingerprint);
      for(const [key,value] of Object.entries(extra))data.set(key,value);
      try{
        const result=await request({method:'POST',body:data});dirty=false;active=null;search.value='';notice(result.message||'บันทึกสำเร็จ');await load(true);
      }catch(error){uncertain=error.uncertain===true||error instanceof TypeError;notice(error.message+(uncertain?' — กรุณากลับรายการและตรวจข้อมูลล่าสุดก่อนลองใหม่':''),true);}
      finally{setBusy(false);}
    }
    search.addEventListener('input',render);
    editorForm.addEventListener('input',()=>{dirty=true;});
    editorForm.addEventListener('submit', e=>{e.preventDefault();if(!editorForm.reportValidity()||busy||uncertain)return;const values={};for(const [key,input] of fieldNodes)values[key]=input.value;mutate('save',values);});
    confirmInput.addEventListener('input',()=>{remove.disabled=busy||uncertain||!active||confirmInput.value.trim()!==active.title;});
    deleteForm.addEventListener('submit',e=>{e.preventDefault();if(!active||busy||uncertain||confirmInput.value.trim()!==active.title)return;mutate('delete',{confirm_title:confirmInput.value});});
    return {
      onOpen:()=>load(),
      onClose:()=>{generation++;loadController?.abort();loading=false;dirty=false;uncertain=false;active=null;},
      canClose:()=>!busy&&(!dirty||uncertain||confirm('ปิดหน้าต่างโดยไม่บันทึกการแก้ไขหรือไม่?'))
    };
  }
})();
