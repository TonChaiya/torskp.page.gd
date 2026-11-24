/* TOR SKP: screen-only document workspace. Do not mutate forms or print layout. */
(function () {
  'use strict';
  const body = document.body;
  if (!body || !body.classList.contains('docws')) return;
  const layout = document.querySelector('[data-docws-layout]');
  const preview = document.querySelector('[data-docws-preview]');
  const editor = document.querySelector('[data-docws-editor]');
  const isTor = body.classList.contains('docws-tor-page');
  const shortScreen = () => window.matchMedia('(max-width:1039px), (max-height:599px)').matches;
  let pages = [];
  let scheduled = false;

  function addTabBar() {
    if (!layout || !preview || !editor) return;
    const bar = document.createElement('div');
    bar.className = 'docws-tabbar no-print';
    bar.setAttribute('role', 'tablist');
    bar.setAttribute('aria-label', 'สลับพื้นที่ทำงาน');
    const buttons = [
      {id:'edit',title:'กรอกข้อมูล'},
      {id:'preview',title:'ตัวอย่างเอกสาร'}
    ];
    for (const item of buttons) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = item.title;
      button.dataset.docwsTarget = item.id;
      button.setAttribute('role', 'tab');
      button.addEventListener('click', function () {
        layout.dataset.docwsTab = item.id;
        for (const child of bar.querySelectorAll('button')) {
          child.setAttribute('aria-selected', child.dataset.docwsTarget === item.id ? 'true' : 'false');
        }
        if (item.id === 'preview') { scheduleLayout(); window.dispatchEvent(new Event('resize')); }
      });
      bar.appendChild(button);
    }
    layout.insertBefore(bar,layout.firstChild);
    const preferred = new URLSearchParams(location.search).get('view') === 'preview' ? 'preview' : 'edit';
    layout.dataset.docwsTab = preferred;
    bar.querySelectorAll('button').forEach(button => button.setAttribute('aria-selected', button.dataset.docwsTarget === preferred ? 'true' : 'false'));
  }

  // Prepare only the A4 pages. No floating preview toolbar or manual zoom controls.
  function preparePaper() {
    if (!preview || body.classList.contains('docws-result-page')) return;
    if (body.classList.contains('docws-meeting-page')) {
      pages = Array.from(preview.querySelectorAll('.report-page'));
    } else if (body.classList.contains('docws-scope-page')) {
      pages = Array.from(preview.querySelectorAll('.scope-paper'));
    } else {
      pages = Array.from(preview.querySelectorAll('.memo-page'));
    }
    pages.forEach(page => page.classList.add('docws-preview-pages'));
  }

  function fitPages() {
    if (!preview || !pages.length || getComputedStyle(preview).display === 'none') return;
    const styles = getComputedStyle(preview);
    const horizontalInset = parseFloat(styles.paddingLeft || 0) + parseFloat(styles.paddingRight || 0);
    const available = Math.max(260, preview.clientWidth - horizontalInset - 12);
    // Always measure against the unscaled A4 paper. Do not multiply the old
    // principle memo 0.82 zoom by another automatic shrink factor.
    pages.forEach(page => { page.style.zoom = '1'; });
    const first = pages[0].querySelector('.memo-page,.scope-page,.report-page') || pages[0];
    const natural = Math.max(1, first.offsetWidth || 794);
    const scale = Math.min(1, available / natural);
    const safe = Math.max(0.45, Math.round(scale * 1000) / 1000);
    pages.forEach(page => { page.style.zoom = String(safe); });
    preview.dataset.docwsZoom = 'fit';
    preview.dataset.docwsHorizontal = 'center';
    preview.style.overflowX = 'hidden';
  }

  function enablePaperWheelScroll() {
    if (!preview) return;
    preview.addEventListener('wheel', function (event) {
      // Do not hijack browser zoom, horizontal trackpad gestures or form controls.
      if (!body.classList.contains('docws-desktop') || event.ctrlKey || event.metaKey || event.shiftKey) return;
      if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
      if (event.target instanceof Element && event.target.closest('button,input,textarea,select,[contenteditable="true"],.docws-preview-bar')) return;
      if (preview.scrollHeight <= preview.clientHeight + 2) return;
      const factor = event.deltaMode === 1 ? 17 : event.deltaMode === 2 ? preview.clientHeight : 1;
      const distance = event.deltaY * factor;
      const next = Math.min(preview.scrollHeight - preview.clientHeight,
        Math.max(0, preview.scrollTop + distance));
      if (Math.abs(next - preview.scrollTop) > 0.2) {
        preview.scrollTop = next;
        event.preventDefault();
      }
    }, {passive:false});
  }

  function sizeDesktop() {
    if (!layout || !preview || !editor || isTor) return;
    const desktop = !shortScreen();
    body.classList.toggle('docws-desktop',desktop);
    document.documentElement.classList.toggle('docws-desktop',desktop);
    if (desktop) {
      const top = Math.max(0,layout.getBoundingClientRect().top);
      const remaining = Math.max(340,window.innerHeight-top-12);
      layout.style.setProperty('--docws-height',remaining+'px');
      // Editor is a single scroll container; result memo has an internal shell.
    } else {
      layout.style.removeProperty('--docws-height');
    }
  }

  function scheduleLayout() {
    if (scheduled) return;
    scheduled=true;
    requestAnimationFrame(function () {scheduled=false;sizeDesktop();fitPages();});
  }

  function addTorShortcuts() {
    const torMain=document.querySelector('.docws-tor');
    if (!torMain) return;
    const anchors = [
      {id:'procurement_case_id',title:'ชุดเอกสาร'},
      {id:'committee_set_id',title:'คณะกรรมการ'},
      {id:'medicine_table_wrap',title:'รายการยา'},
      {id:'price_source',title:'ราคากลาง'},
      {id:'reference_total',title:'สรุปราคา'}
    ];
    const nav=document.createElement('nav');nav.className='docws-tor-nav no-print';
    nav.setAttribute('aria-label','ไปยังส่วนของแบบฟอร์ม TOR');
    for (const item of anchors) {
      const target=document.getElementById(item.id);
      if (!target) continue;
      const link=document.createElement('a');link.href='#'+item.id;link.textContent=item.title;
      link.addEventListener('click',function(e){e.preventDefault();target.scrollIntoView({behavior:'smooth',block:'center'});if(target.matches('input,select,textarea'))target.focus({preventScroll:true});});
      nav.appendChild(link);
    }
    if (nav.children.length) torMain.insertBefore(nav,torMain.firstChild);
  }

  if (layout) { addTabBar(); preparePaper(); enablePaperWheelScroll(); }
  if (isTor) addTorShortcuts();
  scheduleLayout();
  window.addEventListener('resize',scheduleLayout,{passive:true});
  window.addEventListener('beforeprint',function(){
    pages.forEach(p=>p.style.removeProperty('zoom'));
    document.documentElement.classList.remove('docws-desktop');
    body.classList.remove('docws-desktop');
  });
  window.addEventListener('afterprint',scheduleLayout);
  if ('ResizeObserver' in window && preview) {
    let previousWidth=-1;
    new ResizeObserver(function () {const width=Math.round(preview.getBoundingClientRect().width);if(width!==previousWidth){previousWidth=width;scheduleLayout();}}).observe(preview);
  }
})();
