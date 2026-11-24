/* TOR SKP: section-aware OOXML assembly. No external library/network/Word service.
   Native DOCX bodies are copied as XML; HTML-only exports are imported once by Word.
   Original files are never modified. */
(() => {
'use strict';
const W='http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const R='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
const P='http://schemas.openxmlformats.org/package/2006/relationships';
const C='http://schemas.openxmlformats.org/package/2006/content-types';
const MC='http://schemas.openxmlformats.org/markup-compatibility/2006';
const XMLNS='http://www.w3.org/2000/xmlns/';
const enc=new TextEncoder(), dec=new TextDecoder('utf-8',{fatal:true});
const fail=m=>{throw new Error(m);};
const crcTable=Uint32Array.from({length:256},(_,n)=>{for(let k=0;k<8;k++)n=(n&1)?0xedb88320^(n>>>1):n>>>1;return n>>>0;});
function crc(bytes){let n=0xffffffff;for(const b of bytes)n=crcTable[(n^b)&255]^(n>>>8);return (n^0xffffffff)>>>0;}
const safePath=s=>{if(!s||s.includes('\\')||s.includes('\0')||s.startsWith('/')||s.split('/').some(x=>x==='..'||x==='.'))fail('ชื่อส่วนประกอบ DOCX ไม่ถูกต้อง');return s;};
async function unzip(blob){
 const b=new Uint8Array(await blob.arrayBuffer()),v=new DataView(b.buffer);if(b.length>24*1024*1024)fail('DOCX ต้นฉบับใหญ่เกินกำหนด');
 let e=-1;for(let p=b.length-22;p>=Math.max(0,b.length-65557);p--)if(v.getUint32(p,true)===0x06054b50&&p+22+v.getUint16(p+20,true)===b.length){e=p;break;}
 if(e<0||v.getUint16(e+4,true)||v.getUint16(e+6,true))fail('ZIP ของ Word ไม่สมบูรณ์');
 const count=v.getUint16(e+10,true);let at=v.getUint32(e+16,true), total=0;const end=at+v.getUint32(e+12,true),map=new Map();
 if(count>3000||end>e)fail('ZIP ของ Word มีโครงสร้างไม่รองรับ');
 for(let i=0;i<count;i++){
  if(at+46>end||v.getUint32(at,true)!==0x02014b50)fail('รายการ ZIP ไม่ถูกต้อง');
  const flags=v.getUint16(at+8,true),method=v.getUint16(at+10,true),checksum=v.getUint32(at+16,true),compressed=v.getUint32(at+20,true),size=v.getUint32(at+24,true),nl=v.getUint16(at+28,true),xl=v.getUint16(at+30,true),cl=v.getUint16(at+32,true),off=v.getUint32(at+42,true);
  if((flags&1)||at+46+nl+xl+cl>end||off+30>b.length||v.getUint32(off,true)!==0x04034b50)fail('ZIP เข้ารหัสหรือไม่สมบูรณ์');
  const name=safePath(dec.decode(b.subarray(at+46,at+46+nl)));at+=46+nl+xl+cl;if(name.endsWith('/'))continue;
  total+=size;if(total>90*1024*1024||size>24*1024*1024||map.has(name))fail('ZIP ขนาดหรือชื่อไฟล์ไม่รองรับ');
  const start=off+30+v.getUint16(off+26,true)+v.getUint16(off+28,true);if(start+compressed>b.length)fail('ข้อมูล ZIP ถูกตัด');
  let raw=b.slice(start,start+compressed);
  if(method===8){if(typeof DecompressionStream!=='function')fail('เบราว์เซอร์นี้ไม่รองรับการรวม Word กรุณาใช้ Chrome หรือ Edge รุ่นใหม่');const reader=new Blob([raw]).stream().pipeThrough(new DecompressionStream('deflate-raw')).getReader();let chunks=[],n=0;try{while(true){const x=await reader.read();if(x.done)break;n+=x.value.length;if(n>size)fail('ZIP ขนาดไม่ถูกต้อง');chunks.push(x.value);} }finally{reader.releaseLock();}raw=new Uint8Array(n);let p=0;for(const x of chunks){raw.set(x,p);p+=x.length;}}
  else if(method!==0)fail('วิธีบีบอัด ZIP ไม่รองรับ');
  if(raw.length!==size||crc(raw)!==checksum)fail('ตรวจสอบความสมบูรณ์ของ DOCX ไม่ผ่าน');map.set(name,raw);
 }
 if(at!==end)fail('ดัชนี ZIP ไม่สมบูรณ์');return map;
}
function zip(map){
 const pieces=[],central=[];let offset=0,centralSize=0;
 for(const [name,bytes] of map){const n=enc.encode(name),sum=crc(bytes),h=new Uint8Array(30+n.length),d=new DataView(h.buffer);d.setUint32(0,0x04034b50,true);d.setUint16(4,20,true);d.setUint16(6,0x800,true);d.setUint16(12,33,true);d.setUint32(14,sum,true);d.setUint32(18,bytes.length,true);d.setUint32(22,bytes.length,true);d.setUint16(26,n.length,true);h.set(n,30);pieces.push(h,bytes);
 const c=new Uint8Array(46+n.length),q=new DataView(c.buffer);q.setUint32(0,0x02014b50,true);q.setUint16(4,20,true);q.setUint16(6,20,true);q.setUint16(8,0x800,true);q.setUint16(14,33,true);q.setUint32(16,sum,true);q.setUint32(20,bytes.length,true);q.setUint32(24,bytes.length,true);q.setUint16(28,n.length,true);q.setUint32(42,offset,true);c.set(n,46);central.push(c);centralSize+=c.length;offset+=h.length+bytes.length;}
 const e=new Uint8Array(22),v=new DataView(e.buffer);v.setUint32(0,0x06054b50,true);v.setUint16(8,map.size,true);v.setUint16(10,map.size,true);v.setUint32(12,centralSize,true);v.setUint32(16,offset,true);return new Blob([...pieces,...central,e],{type:'application/vnd.openxmlformats-officedocument.wordprocessingml.document'});
}
function parse(bytes,name){if(!bytes)fail('ไม่พบ '+name);const s=typeof bytes==='string'?bytes:dec.decode(bytes);if(/<!DOCTYPE|<!ENTITY/i.test(s))fail('XML มี DTD ที่ไม่รองรับ: '+name);const x=new DOMParser().parseFromString(s,'application/xml');if(x.getElementsByTagName('parsererror').length)fail('XML ไม่ถูกต้อง: '+name);return x;}
const serialize=x=>enc.encode(new XMLSerializer().serializeToString(x));
const list=(x,ns,tag)=>Array.from(x.getElementsByTagNameNS(ns,tag));
const child=(x,tag)=>Array.from(x.children).find(y=>y.namespaceURI===W&&y.localName===tag);
const w=(doc,tag,val)=>{const x=doc.createElementNS(W,'w:'+tag);if(val!==undefined)x.setAttributeNS(W,'w:val',String(val));return x;};
const wa=(x,k)=>x.getAttributeNS(W,k);
const setW=(x,k,v)=>x.setAttributeNS(W,'w:'+k,String(v));
const relPath=p=>{const k=p.lastIndexOf('/');return p.slice(0,k+1)+'_rels/'+p.slice(k+1)+'.rels';};
function target(base,target){if(!target||/[?#\\]/.test(target)||/^[a-z]+:/i.test(target))fail('ตำแหน่งส่วนประกอบ Word ไม่รองรับ');const raw=target.startsWith('/')?target.slice(1):base.slice(0,base.lastIndexOf('/')+1)+target;const out=[];for(const s of raw.split('/')){if(s==='..'){if(!out.length)fail('ตำแหน่ง Word นอกแพ็กเกจ');out.pop();}else if(s!=='.'&&s!=='')out.push(s);}return safePath(out.join('/'));}
function readPackage(map){
 const types=parse(map.get('[Content_Types].xml'),'Content_Types'), document=parse(map.get('word/document.xml'),'document.xml'), styles=parse(map.get('word/styles.xml'),'styles.xml');
 const rels=parse(map.get('word/_rels/document.xml.rels'),'document.xml.rels');if(!child(document.documentElement,'body'))fail('ไม่พบเนื้อหา Word');
 const override=new Map(list(types,C,'Override').map(x=>[x.getAttribute('PartName').slice(1),x.getAttribute('ContentType')])),defaults=new Map(list(types,C,'Default').map(x=>[x.getAttribute('Extension').toLowerCase(),x.getAttribute('ContentType')]));
 return {map,types,document,styles,rels,override,defaults,body:child(document.documentElement,'body')};
}
function partType(src,p){return src.override.get(p)||src.defaults.get(p.split('.').pop().toLowerCase());}
function addType(out,p,t){if(!t)fail('ไม่ทราบประเภทส่วนประกอบ '+p);if(out.override.get(p)===t)return;const x=out.types.createElementNS(C,'Override');x.setAttribute('PartName','/'+p);x.setAttribute('ContentType',t);out.types.documentElement.appendChild(x);out.override.set(p,t);}
function rel(doc,id,type,tgt,mode){const x=doc.createElementNS(P,'Relationship');x.setAttribute('Id',id);x.setAttribute('Type',type);x.setAttribute('Target',tgt);if(mode)x.setAttribute('TargetMode',mode);doc.documentElement.appendChild(x);return x;}
function newRels(){return parse(`<Relationships xmlns="${P}"/>`,'relationships');}
function copyNamespaces(dst,src){const root=dst.documentElement,other=src.documentElement;for(const a of other.attributes){if(a.namespaceURI!==XMLNS||a.name==='xmlns')continue;const current=root.getAttributeNS(XMLNS,a.localName);if(!current)root.setAttributeNS(XMLNS,a.name,a.value);else if(current!==a.value)fail('namespace ของ Word ไม่ตรงกัน');}const ign=new Set([...(root.getAttributeNS(MC,'Ignorable')||'').split(/\s+/),...(other.getAttributeNS(MC,'Ignorable')||'').split(/\s+/)].filter(Boolean));if(ign.size)root.setAttributeNS(MC,'mc:Ignorable',[...ign].join(' '));}
const refs=new Set(['pStyle','rStyle','tblStyle','basedOn','next','link','styleLink','numStyleLink']);
function mapRefs(root,styles,nums,rels){for(const x of [root,...list(root,'*','*')]){if(x.namespaceURI===W&&refs.has(x.localName)){const val=wa(x,'val');if(styles.has(val))setW(x,'val',styles.get(val));}if(x.namespaceURI===W&&x.localName==='numId'&&nums.has(wa(x,'val')))setW(x,'val',nums.get(wa(x,'val')));for(const a of Array.from(x.attributes||[])){if(a.namespaceURI===R&&rels.has(a.value))x.setAttributeNS(R,a.name,rels.get(a.value));}}
}
function prependProps(doc,element,tag){let p=child(element,tag);if(!p){p=w(doc,tag);element.insertBefore(p,element.firstChild);}return p;}
function mergeDefaultProps(doc,style,defs,kind){const props=child(style,kind);if(!props&&defs){style.appendChild(doc.importNode(defs,true));return;}if(!props||!defs)return;for(const d of Array.from(defs.children)){const p=child(props,d.localName);if(!p)props.appendChild(doc.importNode(d,true));else for(const a of d.attributes){if(!p.hasAttributeNS(a.namespaceURI,a.localName))p.setAttributeNS(a.namespaceURI,a.name,a.value);}}}
function createStyles(out,src,n){
 const sid='Case'+n+'_',styles=new Map(),defaultStyles={};const srcStyles=list(src.styles,W,'style');for(const s of srcStyles){const id=wa(s,'styleId');if(!id)continue;styles.set(id,sid+id);if(['1','true','on'].includes(wa(s,'default')))defaultStyles[wa(s,'type')]=sid+id;}
 const dd=child(src.styles.documentElement,'docDefaults'),pd=dd&&child(dd,'pPrDefault'),rd=dd&&child(dd,'rPrDefault'),pdef=pd&&child(pd,'pPr'),rdef=rd&&child(rd,'rPr');
 for(const kind of ['paragraph','character','table']){const id=sid+'Base_'+kind,s=w(out.styles,'style');setW(s,'type',kind);setW(s,'styleId',id);s.appendChild(w(out.styles,'name',id));if(kind==='paragraph'&&pdef)s.appendChild(out.styles.importNode(pdef,true));if(kind!=='table'&&rdef)s.appendChild(out.styles.importNode(rdef,true));out.styles.documentElement.appendChild(s);if(!defaultStyles[kind])defaultStyles[kind]=id;}
 for(const orig of srcStyles){const id=wa(orig,'styleId');if(!id)continue;const s=out.styles.importNode(orig,true),kind=wa(s,'type');mapRefs(s,styles,new Map(),new Map());setW(s,'styleId',styles.get(id));s.removeAttributeNS(W,'default');let name=child(s,'name');if(!name){name=w(out.styles,'name');s.insertBefore(name,s.firstChild);}setW(name,'val',sid+wa(name,'val'));const aliases=child(s,'aliases');if(aliases)aliases.remove();if(['paragraph','character','table'].includes(kind)&&!child(s,'basedOn')){const based=w(out.styles,'basedOn',sid+'Base_'+kind);let following=Array.from(s.children).find(x=>!['name','aliases'].includes(x.localName));s.insertBefore(based,following||null);}out.styles.documentElement.appendChild(s);}
 return {styles,defaultStyles};
}
function applyDefaults(root,def){const d=root.ownerDocument||root;for(const p of list(root,W,'p')){const pp=prependProps(d,p,'pPr');if(!child(pp,'pStyle'))pp.insertBefore(w(d,'pStyle',def.paragraph),pp.firstChild);}for(const t of list(root,W,'tbl')){const tp=prependProps(d,t,'tblPr');if(!child(tp,'tblStyle'))tp.insertBefore(w(d,'tblStyle',def.table),tp.firstChild);}}
/* V3: paragraph-only alignment, only in the combined export. Never alter page,
   font, indentation, table geometry or the original individual Word downloads. */
function fixNativeThaiBody(src,n){
 if(n!==4)return;
 // Scope approval uses a 2.5 cm first-line indent for prose (1417 twips).
 // Titles, headers, decisions and signatures use different paragraph properties.
 for(const p of Array.from(src.body.children)){
  if(p.namespaceURI!==W||p.localName!=='p')continue;
  const pp=child(p,'pPr'),ind=pp&&child(pp,'ind'),jc=pp&&child(pp,'jc');
  if(!ind||!jc||!['left','both','start'].includes(wa(jc,'val'))||Number(wa(ind,'firstLine'))!==1417||Number(wa(ind,'left')||'0')!==0)continue;
  if(!/[\u0E01-\u0E5B]/u.test(list(p,W,'t').map(x=>x.textContent).join('')))continue;
  setW(jc,'val','thaiDistribute');
 }
}
function fixHtmlThaiBody(html,n){
 if(![3,5,6].includes(n))return html;
 // Keep the original Word HTML and Office conditional comments intact. Change
 // only known prose elements, not tables, heading rows, signature or medicine cells.
 // Word's HTML importer may map this to ordinary Justify instead of ThaiDistribute.
 const align='text-align:justify;text-justify:inter-cluster;-ms-text-justify:inter-cluster;text-align-last:left;mso-ansi-language:TH;mso-bidi-language:TH;';
 const attr=(tag,name)=>new RegExp('\\s'+name+'\\s*=\\s*(["\'])([\\s\\S]*?)\\1','i').exec(tag);
 const bodyStart=html.search(/<body\b/i);if(bodyStart<0)return html;
 const before=html.slice(0,bodyStart),body=html.slice(bodyStart);
 let count=0;
 const changed=body.replace(/<(p|div)\b(?:[^>"']|"[^"]*"|'[^']*')*>/gi,(tag,kind)=>{
  const c=attr(tag,'class');if(!c)return tag;
  const classes=new Set(c[2].split(/\s+/));kind=kind.toLowerCase();
  const hit=n===3?((kind==='p'&&classes.has('MsoNormal'))||(kind==='div'&&classes.has('subindent'))):n===5?kind==='p'&&classes.has('memo-paragraph'):kind==='div'&&classes.has('content');
  if(!hit)return tag;
  let t=tag.replace(c[0],c[0].slice(0,-1)+' CaseThaiBody'+c[1]);
  // Preserve every unrelated inline style; remove only alignment/language keys.
  const st=attr(t,'style');
  if(st){const kept=st[2].replace(/(?:^|;)\s*(?:text-align(?:-last)?|text-justify|-ms-text-justify|mso-ansi-language|mso-bidi-language)\s*:[^;]*/gi,'').replace(/^;+|;+$/g,'');
   t=t.replace(st[0],` style=${st[1]}${kept?kept+';':''}${align}${st[1]}`);
  }else t=t.slice(0,-1)+` style="${align}">`;
  const a=attr(t,'align');if(a)t=t.replace(a[0],' align="justify"');else t=t.slice(0,-1)+' align="justify">';
  const l=attr(t,'lang');if(l)t=t.replace(l[0],' lang="th-TH"');else t=t.slice(0,-1)+' lang="th-TH">';
  count++;return t;
 });
 if(!count)return html;
 // Override old left-align rules for the marked paragraphs alone. No document-wide CSS.
 const css='<style type="text/css">.Section1 .CaseThaiBody, .Section1 .word-agenda-table .word-speech-content .content.CaseThaiBody, .Section1 p.MsoNormal.CaseThaiBody {text-align:justify !important;text-justify:inter-cluster !important;-ms-text-justify:inter-cluster !important;text-align-last:left !important;mso-ansi-language:TH;mso-bidi-language:TH;} </style>';
 return before.replace(/<\/head\s*>/i,css+'$&')+changed;
}
/* V4: zero paragraph spacing and horizontal indent inside tables only. */
const paraOrder=['pStyle','keepNext','keepLines','pageBreakBefore','framePr','widowControl','numPr','suppressLineNumbers','pBdr','shd','tabs','suppressAutoHyphens','kinsoku','wordWrap','overflowPunct','topLinePunct','autoSpaceDE','autoSpaceDN','bidi','adjustRightInd','snapToGrid','spacing','ind','contextualSpacing','mirrorIndents','suppressOverlap','jc','textDirection','textAlignment','textboxTightWrap','outlineLvl','divId','cnfStyle','rPr','sectPr','pPrChange'];
function orderedParaProp(pp,tag){let e=child(pp,tag);if(e)return e;e=w(pp.ownerDocument,tag);const idx=paraOrder.indexOf(tag),next=Array.from(pp.children).find(x=>x.namespaceURI===W&&paraOrder.indexOf(x.localName)>idx);pp.insertBefore(e,next||null);return e;}
function zeroNativeTableParagraphs(root){
 for(const p of list(root,W,'p')){
  let inside=false;for(let a=p.parentElement;a;a=a.parentElement){if(a.namespaceURI===W&&a.localName==='tc'){inside=true;break;}}if(!inside)continue;
  const pp=prependProps(p.ownerDocument,p,'pPr'),sp=orderedParaProp(pp,'spacing'),ind=orderedParaProp(pp,'ind');
  for(const k of ['before','after','beforeLines','afterLines','beforeAutospacing','afterAutospacing'])setW(sp,k,'0');
  for(const k of ['left','right','start','end','leftChars','rightChars','startChars','endChars'])ind.removeAttributeNS(W,k);
  setW(ind,'left','0');setW(ind,'right','0');
  // First-line/hanging indent and line spacing are separate properties; preserve them.
 }
}
function zeroHtmlTableParagraphs(html){
 const zero='margin-top:0pt;margin-bottom:0pt;margin-left:0pt;margin-right:0pt;mso-margin-top-alt:0pt;mso-margin-bottom-alt:0pt;mso-para-margin:0cm;mso-para-margin-top:0pt;mso-para-margin-bottom:0pt;mso-para-margin-left:0pt;mso-para-margin-right:0pt;';
 const prop=/^\s*(?:margin(?:-(?:top|bottom|left|right))?|mso-margin-(?:top|bottom)-alt|mso-para-margin(?:-(?:top|bottom|left|right))?)\s*:/i;
 const attr=(tag,name)=>new RegExp('\\s'+name+'\\s*=\\s*(["\'])([\\s\\S]*?)\\1','i').exec(tag);
 function mark(tag){const st=attr(tag,'style');if(st){const kept=st[2].split(';').filter(x=>x.trim()&&!prop.test(x)).join(';');tag=tag.replace(st[0],` style=${st[1]}${kept?kept+';':''}${zero}${st[1]}`);}else tag=tag.slice(0,-1)+` style="${zero}">`;return tag;}
 const pos=html.search(/<body\b/i);if(pos<0)return html;
 let depth=0;
 // Preserve Office conditional comments, inline markup, cell padding, width, and table layout.
 const body=html.slice(pos).replace(/<!--[\s\S]*?-->|<\/?(?:td|th|p|div)\b(?:[^>"']|"[^"]*"|'[^']*')*>/gi,tag=>{
  if(tag.startsWith('<!--'))return tag;const m=/^<(\/)?(td|th|p|div)\b/i.exec(tag);if(!m)return tag;const close=!!m[1],cell=/^(td|th)$/i.test(m[2]);
  if(close){if(cell)depth=Math.max(0,depth-1);return tag;}if(cell){depth++;return mark(tag);}return depth?mark(tag):tag;
 });
 const css='<style type="text/css">td,th,td p,th p,td div,th div{'+zero+'}</style>';
 return html.slice(0,pos).replace(/<\/head\s*>/i,css+'$&')+body;
}
function copyPart(out,src,p,n,ctx){
 if(ctx.parts.has(p))return ctx.parts.get(p);const data=src.map.get(p);if(!data)fail('Word ขาดส่วนประกอบ '+p);const np='word/case'+n+'/'+p;ctx.parts.set(p,np);const type=partType(src,p);addType(out,np,type);
 let bytes=data;
 if(type==='text/html'||p.endsWith('.html')){let html=zeroHtmlTableParagraphs(fixHtmlThaiBody(dec.decode(data),n)); // isolate CSS class/page names, preserve the original HTML and all inline formatting.
  html=html.replace(/\bSection1\b/g,'CaseSection'+n).replace(/\bMsoNormal\b/g,'Case'+n+'Normal').replace(/mso-style-name\s*:\s*Normal\b/gi,'mso-style-name:"Case'+n+'Normal"');
  bytes=enc.encode(html);
 }else if(/wordprocessingml\.(header|footer)\+xml$/.test(type||'')){const x=parse(data,p);mapRefs(x.documentElement,ctx.styles,ctx.nums,new Map());applyDefaults(x.documentElement,ctx.defaults);zeroNativeTableParagraphs(x.documentElement);bytes=serialize(x);}
 out.map.set(np,bytes);
 const rp=relPath(p);if(src.map.has(rp)){const rd=parse(src.map.get(rp),rp),newrd=newRels();for(const item of list(rd,P,'Relationship')){const id=item.getAttribute('Id'),ty=item.getAttribute('Type'),tg=item.getAttribute('Target'),mode=item.getAttribute('TargetMode');if(mode==='External'){if(!ty.endsWith('/hyperlink')||!/^https?:|^mailto:/i.test(tg))fail('Word มีลิงก์ภายนอกที่ไม่รองรับ');rel(newrd,id,ty,tg,mode);}else{const sub=copyPart(out,src,target(p,tg),n,ctx);rel(newrd,id,ty,'/'+sub);}}out.map.set(relPath(np),serialize(newrd));}
 return np;
}
function numbering(out,src,n,styles){const nums=new Map();if(!src.map.has('word/numbering.xml'))return nums;const x=parse(src.map.get('word/numbering.xml'),'numbering.xml');if(list(x,W,'numPicBullet').length)fail('รายการเลขลำดับรูปภาพไม่รองรับ');let dest;if(out.map.has('word/numbering.xml'))dest=parse(out.map.get('word/numbering.xml'),'numbering.xml');else{dest=parse(`<w:numbering xmlns:w="${W}"/>`,'numbering');rel(out.rels,'CaseNumbering',R+'/numbering','numbering.xml');addType(out,'word/numbering.xml','application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml');}const abs=new Map();let ai=Math.max(0,...list(dest,W,'abstractNum').map(y=>Number(wa(y,'abstractNumId'))))+1,ni=Math.max(0,...list(dest,W,'num').map(y=>Number(wa(y,'numId'))))+1;for(const e of list(x,W,'abstractNum')){const k=wa(e,'abstractNumId'),v=String(ai++),c=dest.importNode(e,true);abs.set(k,v);setW(c,'abstractNumId',v);mapRefs(c,styles,new Map(),new Map());const first=child(dest.documentElement,'num');dest.documentElement.insertBefore(c,first||null);}for(const e of list(x,W,'num')){const k=wa(e,'numId'),v=String(ni++),c=dest.importNode(e,true);nums.set(k,v);setW(c,'numId',v);const a=child(c,'abstractNumId');if(a&&abs.has(wa(a,'val')))setW(a,'val',abs.get(wa(a,'val')));dest.documentElement.appendChild(c);}out.map.set('word/numbering.xml',serialize(dest));return nums;}
function addBlank(out,kind){const name='word/case_empty_'+kind+'.xml',id='CaseEmpty'+kind;if(!out.map.has(name)){out.map.set(name,enc.encode(`<w:${kind==='header'?'hdr':'ftr'} xmlns:w="${W}"><w:p/></w:${kind==='header'?'hdr':'ftr'}>`));addType(out,name,'application/vnd.openxmlformats-officedocument.wordprocessingml.'+kind+'+xml');rel(out.rels,id,R+'/'+kind,'/'+name);}return id;}
const sectOrder=['headerReference','footerReference','footnotePr','endnotePr','type','pgSz','pgMar','paperSrc','pgBorders','lnNumType','pgNumType','cols','formProt','vAlign','noEndnote','titlePg','textDirection','bidi','rtlGutter','docGrid','printerSettings','sectPrChange'];
function sectInsert(sec,e){const idx=sectOrder.indexOf(e.localName);const next=Array.from(sec.children).find(x=>sectOrder.indexOf(x.localName)>idx);sec.insertBefore(e,next||null);}
function fixSection(out,sec,newDoc,even){const doc=sec.ownerDocument;for(const kind of ['header','footer']){const references=Array.from(sec.children).filter(x=>x.localName===kind+'Reference');const d=references.find(x=>wa(x,'type')==='default');for(const type of ['default','first','even']){if(references.some(x=>wa(x,'type')===type))continue;const ref=w(doc,kind+'Reference');setW(ref,'type',type);const fallback=type==='even'&&!even&&d?d.getAttributeNS(R,'id'):addBlank(out,kind);ref.setAttributeNS(R,'r:id',fallback);sectInsert(sec,ref);}}
 if(newDoc){let t=child(sec,'type');if(!t){t=w(doc,'type');sectInsert(sec,t);}setW(t,'val','nextPage');let p=child(sec,'pgNumType');if(!p){p=w(doc,'pgNumType');sectInsert(sec,p);}if(!wa(p,'start'))setW(p,'start','1');}
 return sec;
}
function splitEnd(body,sec){let p=body.lastElementChild;if(!p||p.namespaceURI!==W||p.localName!=='p'||child(child(p,'pPr')||p,'sectPr')){p=w(body.ownerDocument,'p');const pp=w(body.ownerDocument,'pPr'),spacing=w(body.ownerDocument,'spacing');for(const [k,v] of Object.entries({before:'0',after:'0',line:'20',lineRule:'exact'}))setW(spacing,k,v);pp.appendChild(spacing);p.appendChild(pp);body.appendChild(p);}prependProps(body.ownerDocument,p,'pPr').appendChild(sec);}
function sourceEven(src){if(!src.map.has('word/settings.xml'))return false;const a=list(parse(src.map.get('word/settings.xml'),'settings'),W,'evenAndOddHeaders')[0];return !!a&&!['0','false','off'].includes(wa(a,'val'));}
function embeddedFonts(pkg){const path='word/fontTable.xml';if(!pkg.map.has(path))return new Map();const doc=parse(pkg.map.get(path),'fontTable'),rr=parse(pkg.map.get(relPath(path)),'fontTable.rels'),rs=new Map(list(rr,P,'Relationship').map(e=>[e.getAttribute('Id'),target(path,e.getAttribute('Target'))])),result=new Map();for(const f of list(doc,W,'font'))for(const e of f.children){if(!e.localName.startsWith('embed'))continue;const raw=pkg.map.get(rs.get(e.getAttributeNS(R,'id'))),key=wa(e,'fontKey').replace(/[{}-]/g,'');if(!raw||!/^[0-9a-f]{32}$/i.test(key)||raw.length<32)fail('ข้อมูลฟอนต์ฝังไม่สมบูรณ์');const k=key.match(/../g).map(s=>parseInt(s,16)).reverse(),data=raw.slice();for(let i=0;i<32;i++)data[i]^=k[i%16];result.set(wa(f,'name')+'|'+e.localName,[data.length,crc(data)]);}return result;}
function assertFontCompatibility(out,src){const a=embeddedFonts(out),b=embeddedFonts(src);for(const [k,v] of b){if(!a.has(k)||String(a.get(k))!==String(v))fail('ต้นฉบับใช้ฟอนต์ฝังที่ต่างจากชุดหลัก: '+k);}}

function prepareBase(out){const styles=out.styles,dd=child(styles.documentElement,'docDefaults');if(dd){let rd=child(dd,'rPrDefault');if(rd){let rp=child(rd,'rPr');if(rp){let fs=child(rp,'rFonts');if(fs&&!wa(fs,'cs'))setW(fs,'cs',wa(fs,'ascii')||'TH SarabunIT๙');let sz=child(rp,'sz'),cs=child(rp,'szCs');if(sz&&!cs)rp.appendChild(w(styles,'szCs',wa(sz,'val')));}}} }
async function combine(blobs){if(!Array.isArray(blobs)||blobs.length!==6)fail('ต้องมี Word ต้นฉบับครบ 6 หัวข้อ');const srcs=[];for(const b of blobs)srcs.push(readPackage(await unzip(b)));const out=srcs[0];prepareBase(out);const anyEven=srcs.some(sourceEven);if(anyEven&&out.map.has('word/settings.xml')){const settings=parse(out.map.get('word/settings.xml'),'settings');let e=child(settings.documentElement,'evenAndOddHeaders');if(!e){e=w(settings,'evenAndOddHeaders');settings.documentElement.appendChild(e);}setW(e,'val','true');out.map.set('word/settings.xml',serialize(settings));}
 let prior=child(out.body,'sectPr');if(!prior)fail('เอกสารหลักไม่มี Section');prior.remove();fixSection(out,prior,false,sourceEven(out));for(const s of list(out.body,W,'sectPr'))fixSection(out,s,false,sourceEven(out));
 const imports=[];for(let index=1;index<6;index++){
  const src=srcs[index],n=index+1;assertFontCompatibility(out,src);copyNamespaces(out.document,src.document);copyNamespaces(out.styles,src.styles);
  const st=createStyles(out,src,n),nums=numbering(out,src,n,st.styles),ctx={parts:new Map(),styles:st.styles,nums,defaults:st.defaultStyles};const ids=new Map();let counter=0;
  // Existing source document properties stay independent; copy only relationships referenced by the body.
  for(const item of list(src.rels,P,'Relationship')){const id=item.getAttribute('Id'),ty=item.getAttribute('Type'),tg=item.getAttribute('Target'),mode=item.getAttribute('TargetMode');const tail=ty.split('/').pop();if(['styles','numbering','settings','webSettings','theme','fontTable'].includes(tail))continue;if(!['image','header','footer','hyperlink','aFChunk'].includes(tail))fail('ขั้นตอน '+n+' มีส่วนประกอบที่ยังไม่รองรับ: '+tail);const newid='Case'+n+'Rel'+(++counter);ids.set(id,newid);if(mode==='External'){if(tail!=='hyperlink'||!/^https?:|^mailto:/i.test(tg))fail('Word มีลิงก์ภายนอกที่ไม่รองรับ');rel(out.rels,newid,ty,tg,mode);}else{const path=target('word/document.xml',tg);if(tail==='aFChunk'&&partType(src,path)!=='text/html')fail('ไม่อนุญาตให้นำ DOCX ซ้อนเข้ามาอีกครั้ง');const np=copyPart(out,src,path,n,ctx);rel(out.rels,newid,ty,'/'+np);}}
  // Apply number maps to imported styles only.
  for(const s of list(out.styles,W,'style').filter(x=>wa(x,'styleId').startsWith('Case'+n+'_')))mapRefs(s,new Map(),nums,new Map());
  fixNativeThaiBody(src,n);
  const sb=src.body;mapRefs(sb,st.styles,nums,ids);applyDefaults(sb,st.defaultStyles);
  for(const chunk of list(sb,W,'altChunk')){let p=child(chunk,'altChunkPr');if(!p){p=w(src.document,'altChunkPr');chunk.appendChild(p);}let match=child(p,'matchSrc');if(!match){match=w(src.document,'matchSrc');p.appendChild(match);}setW(match,'val','true');imports.push(n);}
  // Each source starts a section. Section props describe the preceding section and are not shared.
  const sourceFinal=child(sb,'sectPr');if(!sourceFinal)fail('ขั้นตอน '+n+' ไม่มี Section');sourceFinal.remove();const internal=list(sb,W,'sectPr');internal.forEach((s,i)=>fixSection(out,s,i===0,sourceEven(src)));const sec=fixSection(out,sourceFinal,internal.length===0,sourceEven(src));splitEnd(out.body,prior);
  for(const x of Array.from(sb.children))out.body.appendChild(out.document.importNode(x,true));prior=out.document.importNode(sec,true);
 }
 out.body.appendChild(prior);
 zeroNativeTableParagraphs(out.body);
 // Drawing IDs must be unique after importing native body and all copied headers.
 let drawingId=0;const dp='http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';for(const e of list(out.document,dp,'docPr'))e.setAttribute('id',String(++drawingId));for(const [p,data] of out.map){if(!/header[^/]*\.xml$|footer[^/]*\.xml$/.test(p))continue;const x=parse(data,p);for(const e of list(x,dp,'docPr'))e.setAttribute('id',String(++drawingId));out.map.set(p,serialize(x));}
 out.map.set('word/document.xml',serialize(out.document));out.map.set('word/styles.xml',serialize(out.styles));out.map.set('word/_rels/document.xml.rels',serialize(out.rels));out.map.set('[Content_Types].xml',serialize(out.types));
 // Fail closed on dangling relationship IDs in the main story.
 const known=new Set(list(out.rels,P,'Relationship').map(x=>x.getAttribute('Id')));for(const e of list(out.document,'*','*'))for(const a of e.attributes)if(a.namespaceURI===R&&!known.has(a.value))fail('ความสัมพันธ์ของ Word ไม่ครบ: '+a.value);
 return {blob:zip(out.map),htmlSteps:imports};
}
window.TorskpDocxMerge=Object.freeze({combine});
})();
