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
      count.textContent='กำลังตรวจสอบ…';error.hidden=true;
      try{
        const response=await fetch('admin.php?action='+prefix+'_clear_preview',{cache:'no-store',credentials:'same-origin'});
        const data=await response.json();
        if(ticket!==generation||!dialog.open)return;
        if(!response.ok||!data.ok||!Number.isSafeInteger(data.count)||data.count<0||!/^[a-f0-9]{64}$/.test(data.hash))throw new Error(data.error||'ตรวจสอบข้อมูลไม่สำเร็จ');
        blocked=Boolean(data.blocked)||data.count===0;
        form.elements.snapshot_hash.value=data.hash;form.elements.expected_count.value=String(data.count);
        count.textContent=data.count.toLocaleString('th-TH')+' รายการ';
        if(blocked){error.textContent=data.blocked||'ไม่มีข้อมูลให้ล้าง';error.hidden=false;}
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
