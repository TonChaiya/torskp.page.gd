/* TOR SKP file-name preview for browser Print / Save as PDF.
   This updates only document.title, never modifies the document preview or print CSS. */
(function () {
  'use strict';
  const digits = {'๐':'0','๑':'1','๒':'2','๓':'3','๔':'4','๕':'5','๖':'6','๗':'7','๘':'8','๙':'9'};
  const month = {'มกราคม':1,'กุมภาพันธ์':2,'มีนาคม':3,'เมษายน':4,'พฤษภาคม':5,'มิถุนายน':6,
    'กรกฎาคม':7,'กรกฏาคม':7,'สิงหาคม':8,'กันยายน':9,'ตุลาคม':10,'พฤศจิกายน':11,'ธันวาคม':12};
  const names = {1:'อนุมัติหลักการ',2:'เชิญกรรมการ',3:'TOR',4:'อนุมัติคุณลักษณะและราคากลาง',5:'รายงานผลการพิจารณา',6:'รายงานการประชุม'};
  const arabic = value => String(value || '').replace(/[๐-๙]/g, v => digits[v] || v);
  const clean = value => arabic(value).replace(/[\/:*?"<>|\x00-\x1f]+/g, '-').replace(/\s+/g,' ').replace(/^[ .-]+|[ .-]+$/g,'');
  function dateValue(input) {
    const s = arabic(input).trim();
    let d=0, m=0, y=0;
    const monthMatch = s.match(/(\d{1,2})\s*(มกราคม|กุมภาพันธ์|มีนาคม|เมษายน|พฤษภาคม|มิถุนายน|กรกฎาคม|กรกฏาคม|สิงหาคม|กันยายน|ตุลาคม|พฤศจิกายน|ธันวาคม)\s*(\d{4})/u);
    if(monthMatch){d=Number(monthMatch[1]);m=month[monthMatch[2]];y=Number(monthMatch[3]);}
    else {
      let match=s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/);
      if(match){y=Number(match[1]);m=Number(match[2]);d=Number(match[3]);}
      else {
        match=s.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/);
        if(match){d=Number(match[1]);m=Number(match[2]);y=Number(match[3]);}
        else {match=s.match(/^(\d{4})(\d{2})(\d{2})$/);if(match){y=Number(match[1]);m=Number(match[2]);d=Number(match[3]);}}
      }
    }
    if (y >=1900 && y<=2400) y+=543;
    const greg=y-543, test=new Date(greg,m-1,d);
    if (y <2400 || y>2900 || greg<1 || test.getFullYear()!==greg || test.getMonth()!==m-1 || test.getDate()!==d) return 'ไม่ระบุวันที่';
    return String(y).padStart(4,'0') + String(m).padStart(2,'0') + String(d).padStart(2,'0');
  }
  function supplierName(value){
    return clean(String(value||'').trim().replace(/^(บริษัท\s*|บจก\.?\s*|ห้างหุ้นส่วนจำกัด\s*|หจก\.?\s*)/u,'').replace(/\s*จำกัด\s*(?:\(มหาชน\)|มหาชน)?\s*$/u,'')).slice(0,55)||'ไม่ระบุบริษัท';
  }
  function numberValue(value){let s=arabic(value).trim();if(s.includes('/'))s=s.slice(s.lastIndexOf('/')+1);return clean(s.replace(/^\s*(?:เลขที่|ที่)\s*/u,'')).slice(0,48);}
  function findValue(name, fallBack) {
    const el = document.querySelector('[name="' + name + '"]');
    return el ? String(el.value||'') : String(fallBack||'');
  }
  function init(settings) {
    if(!settings || !names[settings.step]) return;
    function update() {
      const chooser=document.getElementById('procurement_case_id');
      const selection=chooser && chooser.selectedOptions && chooser.selectedOptions[0];
      const caseId = selection && selection.value ? Number(selection.value) : Number(settings.caseId||0);
      const supplier = document.querySelector('input[name="supplier_name"]')?.value ??
        ((selection && selection.dataset.supplier) || settings.supplier || '');
      const when=findValue(settings.dateField,settings.date);
      const number=settings.numberField ? findValue(settings.numberField,settings.number) : '';
      const parts=[caseId>0?'ชุด'+String(caseId).padStart(4,'0'):'ชุดใหม่',String(settings.step).padStart(2,'0'),dateValue(when)];
      const official=numberValue(number);
      if(official)parts.push(official);
      parts.push(names[settings.step],supplierName(supplier));
      document.title=parts.join('-');
    }
    update();
    document.addEventListener('input', e=>{
      if(e.target.matches('[name="supplier_name"], [name="memo_no_suffix"], [name="doc_date"], [name="meeting_date"], [name="tor_doc_date"]'))update();
    },true);
    document.addEventListener('change',update,true);
    document.addEventListener('click',e=>{
      if(e.target.closest('button[onclick*="print("], a[onclick*="print("]'))update();
    },true);
    window.addEventListener('beforeprint',update);
  }
  window.TorDocumentPrintName={init};
})();
