<?php
if (!isset($wrappedStoryData) || empty($wrappedStoryData['visits'])) {
    header('Location: traveldiary.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>KULTOURA Wrapped</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,700;0,9..144,800;1,9..144,700&family=Inter:wght@400;600;700&family=Abril+Fatface&display=swap" rel="stylesheet">
<style>
@font-face{font-family:'Barabara';src:url('../assets/fonts/barabara/barabara.woff') format('woff');font-display:swap}
:root{--ink:#2a1a10;--soft:#76624f;--paper:#f5ede1;--card:#fbf6ee;--line:rgba(42,26,16,.13);--sage:#587a4c;--sage-d:#3f5c36;--terra:#b5472a;--caramel:#c07a2c;--gold:#e8b86b;--serif:'Fraunces',Georgia,serif;--sans:'Inter',system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{font-family:var(--sans);color:var(--ink);display:grid;place-items:center;overflow:hidden;background:#17100a}
.stage{position:relative;width:min(420px,100vw);height:min(860px,100dvh);border-radius:32px;overflow:hidden;background:var(--paper);user-select:none;transition:background .6s;--bt:rgba(42,26,16,.15);--bf:var(--ink)}
@media(max-width:480px){.stage{border-radius:0}}
.stage.dark{background:#130b06;--bt:#ffffff30;--bf:#fff}
.top{position:absolute;top:0;left:0;right:0;z-index:20;padding:calc(env(safe-area-inset-top,0px) + 14px) 18px 0}
.bars{display:flex;gap:4px}.bar{flex:1;height:3px;border-radius:9px;background:var(--bt);overflow:hidden}.bar i{display:block;height:100%;width:0;background:var(--bf)}
.meta{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
.brand{font:700 11px var(--sans);letter-spacing:.22em;text-transform:uppercase;color:var(--sage-d)}.stage.dark .brand{color:var(--gold)}
.count{font:600 11px var(--sans);padding:5px 10px;border-radius:99px;background:var(--ink);color:#fff}.stage.dark .count{background:#fffaf3;color:var(--ink)}
.slides{position:absolute;inset:0}
.slide{position:absolute;inset:0;padding:88px 24px 112px;display:flex;flex-direction:column;justify-content:center;opacity:0;visibility:hidden;transform:translateX(20px);transition:opacity .4s,transform .4s,visibility 0s .4s}
.slide.active{opacity:1;visibility:visible;transform:none;transition:opacity .4s,transform .4s}.slide.before{transform:translateX(-20px)}
.slide.bleed{padding:0;justify-content:flex-start}.slide.dark{color:#f6ebdd}
.rise{opacity:0;transform:translateY(14px)}.slide.active .rise{animation:rise .55s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(var(--i,0)*80ms + 100ms)}
@keyframes rise{to{opacity:1;transform:none}}
.eyebrow{display:flex;align-items:center;gap:10px;font:700 10.5px var(--sans);letter-spacing:.2em;text-transform:uppercase;color:var(--terra)}.eyebrow:before{content:"";width:22px;height:1.5px;background:currentColor}.dark .eyebrow{color:var(--gold)}
h1,h2{font-family:var(--serif);letter-spacing:-.025em;line-height:1.02}h2{font-size:36px;font-weight:700;margin-top:10px}
.lede{color:var(--soft);font-size:14px;line-height:1.55;margin-top:10px;max-width:320px}.dark .lede{color:#f6ebddb0}.head{margin-bottom:20px}
.ph{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--a,#d8b98f),var(--b,#a67c52))}
.ph img{width:100%;height:100%;object-fit:cover;display:block;position:absolute;inset:0}.ph.fill{position:absolute;inset:0}
.clip-ph{position:absolute;inset:-12%;background-size:cover;background-position:center;animation:ken 5s ease-in-out infinite alternate}
.clip-ph i{position:absolute;left:50%;top:50%;width:42px;height:42px;margin:-21px;border-radius:50%;display:grid;place-items:center;background:#ffffff3d;color:#fff;font-style:normal;font-size:14px}
@keyframes ken{to{transform:scale(1.12) translate(-3%,2%)}}
.badge{position:absolute;left:8px;bottom:9px;padding:3px 8px;border-radius:99px;background:#2a1a10b8;color:#fff;font:600 10px var(--sans);z-index:2}
.thumb{position:relative;width:46px;height:46px;border-radius:12px;overflow:hidden;flex:none}.thumb .ph{width:100%;height:100%}.thumb .badge{display:none}
.cover .bento{display:grid;grid-template-columns:1.25fr 1fr;grid-template-rows:1fr 1fr;gap:8px;height:260px;margin:20px 0 26px}.cover .bento .ph{border-radius:20px}.cover .bento .a{grid-row:span 2}
.cover h1{font-size:64px;font-weight:800}.cover h1 em{font-style:italic;color:var(--sage)}.cover h1 span{color:var(--caramel);font-style:italic;font-weight:600}
.cover p{margin-top:14px;color:var(--soft);font-size:14px;line-height:1.55}.cover .go{margin-top:20px;font:600 12px var(--sans);letter-spacing:.14em;text-transform:uppercase}
.lead{display:flex;align-items:flex-end;gap:16px;padding-bottom:18px;border-bottom:1.5px solid var(--ink)}.lead .big{font:800 88px/.85 var(--serif);letter-spacing:-.05em}.lead b{display:block;font:700 18px var(--serif)}.lead small{display:block;margin-top:4px;font-size:12.5px;color:var(--soft)}
.row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line)}.row span{flex:1;font-size:14px;color:var(--soft)}.row b{font:700 24px var(--serif)}.row .dot{width:10px;height:10px;border-radius:50%;background:var(--c)}
.foot{margin-top:14px;font:italic 500 13.5px var(--serif);color:var(--soft)}
.statement{padding-bottom:16px;border-bottom:1.5px solid var(--ink)}.statement small{font:600 11px var(--sans);letter-spacing:.16em;text-transform:uppercase;color:var(--soft)}.statement strong{display:block;font:800 44px/1 var(--serif);margin-top:8px}.statement span{display:block;margin-top:8px;font-size:13px;color:var(--soft)}
.pk{display:flex;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid var(--line)}.pk .tx{flex:1;min-width:0}.pk .k{font:600 10.5px var(--sans);letter-spacing:.14em;text-transform:uppercase;color:var(--soft)}.pk .v{font:700 16px var(--serif);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ico{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;background:#f3ddd5;font-size:20px;flex:none}
.timeline{border-top:1.5px solid var(--ink)}.tl{display:grid;grid-template-columns:48px 18px 1fr auto;column-gap:10px;align-items:center;border-bottom:1px solid var(--line)}
.tl .dt{text-align:right;padding:14px 0}.tl .dt b{display:block;font:800 26px/1 var(--serif)}.tl .dt small{font:700 10px var(--sans);letter-spacing:.14em;text-transform:uppercase;color:var(--soft)}
.mk{position:relative;align-self:stretch}.mk:before{content:"";position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--line)}.mk:after{content:"";position:absolute;left:3px;top:50%;margin-top:-6px;width:12px;height:12px;border-radius:50%;background:var(--c);box-shadow:0 0 0 4px var(--paper)}
.bd{padding:12px 0;min-width:0}.bd small{font:600 10.5px var(--sans);letter-spacing:.12em;text-transform:uppercase;color:var(--soft)}.bd div{font:700 15px/1.25 var(--serif);margin-top:3px}
.pc{position:relative;border-radius:22px;overflow:hidden;color:#fff;background:#4b3524;box-shadow:0 14px 30px -14px #2a181080}.pc .shade{position:absolute;inset:0;background:linear-gradient(transparent 35%,#1c100acc)}
.pc .tx{position:absolute;left:16px;right:16px;bottom:14px}.pc .tx b{display:block;font:700 17px/1.2 var(--serif)}.pc .tx small{display:block;margin-top:4px;font-size:11.5px;opacity:.85}
.pc .tag{position:absolute;top:12px;left:12px;font:700 9.5px var(--sans);letter-spacing:.14em;text-transform:uppercase;padding:5px 10px;border-radius:99px;background:#fffaf3;color:var(--ink)}
.pc.big{height:200px}.pc-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}.pc-row .pc{height:150px}.pc-row .tx b{font-size:14.5px}
.more{margin-top:12px;font:500 13px var(--sans);color:var(--soft)}
.lk{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--line)}.lk:first-child{border-top:1.5px solid var(--ink)}.lk .n{font:700 24px var(--serif);color:var(--caramel);width:34px}.lk .ph{width:68px;height:68px;border-radius:16px;flex:none}.lk .nm{flex:1;font:700 15px/1.3 var(--serif)}.lk .h{color:#c4455f}
.map{position:relative;margin:0 -24px;height:clamp(170px,26dvh,230px);background:linear-gradient(#0000 49%,#c9b89d55 50%,#0000 51%) 0 0/40px 40px,linear-gradient(90deg,#0000 49%,#c9b89d55 50%,#0000 51%) 0 0/40px 40px,#e8dcc9}
.pin{opacity:0;transition:opacity .3s;position:absolute;width:26px;height:26px;margin:-26px 0 0 -13px;border-radius:50% 50% 50% 4px;transform:rotate(-45deg);border:2.5px solid #fff;background:var(--c);box-shadow:0 6px 14px -4px #0006}
.pin.on{opacity:1}.route{position:absolute;inset:0;width:100%;height:100%}.route line{stroke:#b5472a;stroke-width:2.5;stroke-linecap:round;stroke-dasharray:2 7;vector-effect:non-scaling-stroke}.dot{position:absolute;width:16px;height:16px;margin:-8px 0 0 -8px;border-radius:50%;background:#b5472a;border:3px solid #fff;z-index:3;opacity:0;animation:pulse 1.2s infinite}@keyframes pulse{0%{box-shadow:0 0 0 0 #b5472a80}70%,100%{box-shadow:0 0 0 12px #b5472a00}}.mlabel{position:absolute;left:34px;top:10px;z-index:4;font:700 11px var(--sans);padding:5px 10px;border-radius:99px;background:var(--ink);color:#fff;letter-spacing:.04em}.mlabel:empty{display:none}.mrow{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--line)}.mrow .d{width:14px;height:14px;border-radius:50%;background:var(--c);flex:none}.mrow div{flex:1;font:700 14px/1.25 var(--serif)}.mrow small{display:block;font:500 11.5px var(--sans);color:var(--soft)}
.chap{display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #ffffff1f}.chap:first-child{border-top:1px solid #ffffff40}
.chap .n{font:italic 700 26px var(--serif);color:var(--gold)}.chap strong{display:block;font:700 15px/1.25 var(--serif)}.chap span{display:block;margin-top:3px;font-size:12px;color:#f6ebdd99}.chap .thumb{width:52px;height:52px}
.specs{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.specs span{font:600 10.5px var(--sans);letter-spacing:.1em;text-transform:uppercase;padding:6px 11px;border-radius:99px;border:1px solid #ffffff3d;color:#f6ebddcc}
.cut{position:absolute;inset:0;background:#0f0a06;overflow:hidden}.cut-item{position:absolute;inset:0;visibility:hidden;clip-path:inset(0 0 0 100%)}
.cut-item.prev{visibility:visible;z-index:1;clip-path:inset(0)}.cut-item.on{visibility:visible;z-index:2;clip-path:inset(0)}
.graded .clip-ph{filter:saturate(1.12) contrast(1.07) sepia(.14);animation-duration:var(--d,5s)}.chaps.many{display:grid;grid-template-columns:1fr 1fr;column-gap:18px;border-top:1px solid #ffffff40}.many .chap{grid-template-columns:30px 1fr;padding:9px 0;border-top:0!important}.many .chap span:not(.n),.many .thumb{display:none}.many .chap .n{font-size:20px}.many .chap strong{font-size:14px}.cut-fx{position:absolute;inset:0;z-index:5;pointer-events:none;background:radial-gradient(120% 90% at 50% 45%,#0c060322 30%,#0c0603cc),linear-gradient(#0c060333,#0c060333)}
.lt{position:absolute;inset:0;z-index:6;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 24px 60px;color:#fff;opacity:0}.lt small{margin-top:14px;font:600 13px var(--sans);letter-spacing:.24em;text-transform:uppercase;opacity:.9;text-shadow:0 2px 14px #000a}.cut-item.on .lt.st{animation:none;opacity:1}.lt span{font:400 clamp(46px,16vw,72px)/1 'Barabara',var(--serif);letter-spacing:.01em;text-transform:uppercase;overflow-wrap:anywhere;text-shadow:0 4px 30px #000a}.lt span:after{content:"";display:block;width:56px;height:2px;background:var(--gold);margin:16px auto 0}.cut-item.on .lt{animation:lt var(--d,5s) both}
@keyframes lt{0%,6%{opacity:0;transform:translateY(16px)}14%,78%{opacity:1;transform:none}92%,100%{opacity:0}}

.cut-title{display:grid;place-items:center;text-align:center;color:#f6ebdd;background:radial-gradient(520px 420px at 30% 20%,#3b2414,#130b06)}.cut-title .eyebrow{justify-content:center;color:var(--gold)}.cut-title .eyebrow:before{display:none}
.cut-title h2{font:400 clamp(22px,13vw,76px)/1 'Barabara',var(--serif);text-align:center;text-transform:uppercase;overflow-wrap:anywhere;max-width:100%}.cut-title h2:after{content:"";display:block;width:60px;height:1.5px;background:var(--gold);margin:20px auto 0}.cut-title p{margin-top:12px;font:600 11px var(--sans);letter-spacing:.2em;text-transform:uppercase;color:#f6ebddb0;padding:0 24px;line-height:1.7}
.cut-item .ph img,.cut-item .ph video{object-fit:contain!important;background:#0f0a06}.cut-item .clip-ph{inset:0;background-size:contain;background-repeat:no-repeat;animation:none!important}
.rail{position:absolute;left:24px;right:24px;bottom:100px;z-index:7;display:flex;gap:8px}.rail>div{flex:1;min-width:0}.rail small{display:block;margin-bottom:7px;font:600 9.5px var(--sans);letter-spacing:.1em;text-transform:uppercase;color:#ffffff70;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.rail .act small{color:#fff}.rail b{display:block;height:3px;border-radius:3px;background:#ffffff35;overflow:hidden}.rail i{display:block;height:100%;width:0;background:#fff}
.mosaic{display:grid;grid-template-columns:repeat(3,1fr);grid-auto-rows:88px;gap:8px}.mosaic .ph{border-radius:16px}.mosaic .t1{grid-column:span 2;grid-row:span 2}.mosaic .t2{grid-row:span 2}.mosaic .t6{grid-column:span 3}
.outro h2{font-size:48px;font-weight:800;margin-top:14px}.outro h2 em{color:var(--sage)}.quote{margin:24px 0 22px;border-top:1.5px solid var(--ink)}.quote div{padding:12px 0;border-bottom:1px solid var(--line);font:italic 500 19px var(--serif)}
.sig{font:800 14px var(--serif);letter-spacing:.16em;color:var(--sage-d)}.sig small{display:block;margin-top:4px;font:600 9.5px var(--sans);letter-spacing:.22em;color:var(--soft)}
.replay{margin-top:20px;cursor:pointer;padding:11px 18px;border-radius:99px;border:1.5px solid var(--ink);background:transparent;color:var(--ink);font:600 13px var(--sans)}
.pg{width:92px;height:92px;border-radius:50%;display:grid;place-items:center;font-size:42px;background:var(--card);border:1.5px solid var(--ink);margin-bottom:16px}.pt{font:800 40px/1.02 var(--serif);letter-spacing:-.025em}.pt em{color:var(--terra)}.pb{margin-top:18px}.pb div{display:grid;grid-template-columns:104px 1fr 22px;gap:10px;align-items:center;padding:6px 0;font-size:12.5px;color:var(--soft)}.pb i{display:block;height:8px;border-radius:9px;background:var(--line);overflow:hidden}.pb u{display:block;height:100%;background:var(--sage);border-radius:9px}.pb b{font:700 14px var(--serif);color:var(--ink);text-align:right}
.chips{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:20px}.chip{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:12px 10px}.chip small{font:600 9.5px var(--sans);letter-spacing:.12em;text-transform:uppercase;color:var(--soft)}.chip b{display:block;margin-top:5px;font:700 17px/1.1 var(--serif)}
.mbars{display:flex;gap:6px;align-items:flex-end;height:96px;border-bottom:1.5px solid var(--ink)}.mbars div{flex:1;text-align:center}.mbars i{display:block;border-radius:6px 6px 0 0;background:var(--line);min-height:3px}.mbars .hi i{background:var(--terra)}.mlab{display:flex;gap:6px;margin-top:6px}.mlab span{flex:1;text-align:center;font:600 8.5px var(--sans);color:var(--soft);text-transform:uppercase}
.bgs{display:grid;grid-template-columns:1fr 1fr;gap:10px}.bg{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:16px 14px;text-align:center}.bg .bi{width:52px;height:52px;margin:0 auto 10px;border-radius:50%;display:grid;place-items:center;font-size:24px;background:linear-gradient(135deg,var(--gold),var(--caramel))}.bg b{display:block;font:700 15px/1.15 var(--serif)}.bg small{display:block;margin-top:5px;font-size:11.5px;color:var(--soft);line-height:1.35}
.badge-download{position:relative;z-index:11;display:inline-block;margin-top:10px;color:var(--sage-d);font:700 10px var(--sans);letter-spacing:.08em;text-decoration:none;text-transform:uppercase}
.zone{position:absolute;top:0;bottom:0;z-index:10;pointer-events:none}.zone.l{left:0;width:30%}.zone.r{right:0;width:70%}
.actions{position:absolute;left:0;right:0;bottom:0;z-index:20;padding:28px 18px calc(env(safe-area-inset-bottom,0px) + 18px);display:flex;gap:8px;justify-content:center;flex-wrap:wrap;background:linear-gradient(transparent,var(--paper) 62%)}.stage.dark .actions{background:linear-gradient(transparent,#130b06 62%)}
.btn{border:1px solid var(--line);cursor:pointer;padding:11px 18px;border-radius:99px;font:600 13px var(--sans);background:var(--card);color:var(--ink)}.btn.fb{background:#1877f2;color:#fff;border-color:#1877f2}.btn{transition:transform .15s,background .3s}.btn:hover{transform:translateY(-2px)}.btn:active{transform:scale(.96)}.stage.dark .btn:not(.fb){background:#ffffff18;color:#fff;border-color:#ffffff2e}
.toast{position:absolute;left:50%;bottom:96px;z-index:40;transform:translateX(-50%);opacity:0;pointer-events:none;background:var(--ink);color:#fff;padding:10px 16px;border-radius:12px;font-size:13px;transition:.3s}.toast.show{opacity:1}
@media(prefers-reduced-motion:reduce){.clip-ph,.lt{animation:none!important}.lt{opacity:1}}
</style>
<style>.bg .bi{overflow:hidden}.bg .bi img{display:block;width:100%;height:100%;object-fit:contain}</style></head>
<body>
<main class="stage" id="stage">
<header class="top"><div class="bars" id="bars"></div><div class="meta"><div class="brand" id="brand"></div><div class="count" id="count"></div></div></header>
<div class="slides" id="slides"></div>
<div class="zone l" id="zl"></div><div class="zone r" id="zr"></div>
<footer class="actions"><button class="btn" id="share">Share</button><button class="btn" id="exportVideo">Export video</button><button class="btn fb" id="fb">Facebook</button></footer>
<div class="toast" id="toast"></div>
</main>
<script>
/* DATA — replace with JSON from your backend. media[].src / poster / places[].img / liked[].img = real uploads. */
const DATA = <?php echo json_encode($wrappedStoryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

/* dummy art (inline SVG) — remove once real uploads exist */
const PAL={campus:['#9cc8e8','#8aa36f','#4a4d52'],coffee:['#f6e7d3','#e2c4a0','#7a4b2a'],fiesta:['#3b2a63','#f08a4b','#1a1020'],hall:['#a9d4ee','#9db37f','#2f6b45'],gas:['#bfe1f3','#6f7378','#c8372d'],pool:['#8fe0d6','#2d8b9a','#4cc2d4'],tower:['#a8cfe8','#8aa36f','#aab6bf']};
const art=(k,v=0)=>{const[a,b,c]=PAL[k]||PAL.campus,x=(v%3)*16;
 const shape=k==='coffee'?`<path d="M${130+x} 150h140l-18 160h-104z" fill="${c}"/><rect x="${210+x}" y="60" width="9" height="200" rx="4" fill="#3d2b1f"/>`
  :k==='fiesta'?[0,1,2,3,4,5,6,7].map(i=>`<path d="M${i*54} ${70+(i%2)*14}l28 0l-14 34z" fill="${['#e63946','#f4b942','#2a9d8f','#7a5aa6'][i%4]}"/>`).join('')+`<rect y="320" width="400" height="80" fill="${c}"/>`
  :k==='pool'?`<rect x="40" y="190" width="320" height="170" rx="22" fill="#e9f7f4"/><rect x="54" y="204" width="292" height="142" rx="14" fill="${c}"/><circle cx="${320-x}" cy="70" r="36" fill="#fff6c9"/>`
  :`<rect x="${60+x/2}" y="${k==='tower'?60:190}" width="${k==='tower'?150:280}" height="${k==='tower'?280:130}" fill="${c}"/><rect x="${k==='tower'?230:100}" y="${k==='tower'?150:220}" width="${k==='tower'?90:200}" height="${k==='tower'?190:50}" fill="#ffffff55"/>`;
 return'data:image/svg+xml;utf8,'+encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" preserveAspectRatio="xMidYMid slice"><defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${a}"/><stop offset="1" stop-color="${b}"/></linearGradient></defs><rect width="400" height="400" fill="url(#g)"/>${shape}</svg>`)};
DATA.places.forEach((p,i)=>p.img=p.img||art(p.art,i));
DATA.liked.forEach((l,i)=>l.img=l.img||art(l.art,i));
DATA.media.forEach((m,i)=>{const k=DATA.places.find(p=>p.id===m.placeId).art;m.type==='video'?m.poster=m.poster||art(k,i+1):m.src=m.src||art(k,i)});

/* helpers */
const $=(s,r=document)=>r.querySelector(s),C=DATA.copy,CFG=DATA.config,EDIT=DATA.edit;
const byId=id=>DATA.places.find(p=>p.id===id),cat=p=>DATA.categories[p.category];
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const fmtDate=d=>new Date(d+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric'});
const fmtDur=s=>Math.floor(s/60)+':'+String(s%60).padStart(2,'0'),pad=n=>String(n).padStart(2,'0');
const plural=(n,w,pl)=>n+' '+(n===1?w:pl||w+'s');
const head=(l,t,le='')=>`<div class="head rise" style="--i:0"><div class="eyebrow">${l}</div><h2>${t}</h2>${le?`<p class="lede">${le}</p>`:''}</div>`;
const MEDIA=[...DATA.media].sort((a,b)=>a.date.localeCompare(b.date)),PHOTOS=MEDIA.filter(m=>m.type==='image'),CLIPS=MEDIA.filter(m=>m.type==='video');
const capSec=m=>Math.min(m.duration||5,CFG.maxClipSeconds);
const tile=(m,cls='')=>{const p=byId(m.placeId),[a,b]=p.tones,v=m.type==='video';
 const inner=v?(m.src?`<video muted playsinline loop autoplay src="${esc(m.src)}" poster="${esc(m.poster||'')}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover"></video>`:`<div class="clip-ph" style="background-image:url('${esc(m.poster||p.img)}')"><i>\u25B6</i></div>`):`<img src="${esc(m.src||p.img)}" alt="${esc(p.short)} photo" loading="lazy">`;
 return`<div class="ph ${cls}" style="--a:${a};--b:${b}">${inner}${v?`<span class="badge">\u25B6 ${fmtDur(capSec(m))}</span>`:''}</div>`};
const pTile=(o,cls='')=>`<div class="ph ${cls}" style="--a:${o.tones?o.tones[0]:'#d8b98f'};--b:${o.tones?o.tones[1]:'#a67c52'}"><img src="${esc(o.img)}" alt="${esc(o.name)}" loading="lazy"></div>`;
const thumb=m=>`<div class="thumb">${tile(m)}</div>`;
const mix=n=>{const media=[...CLIPS.slice(0,Math.min(2,n>3?2:1)),...PHOTOS];while(media.length<n&&DATA.places.length){const p=DATA.places[media.length%DATA.places.length];media.push({id:'cover-'+media.length,type:'image',placeId:p.id,date:DATA.visits[0]?.date||'2026-01-01',src:p.img})}return media.slice(0,n)};

/* derived */
const D=(()=>{const counts={},per={};DATA.visits.forEach(v=>{const c=byId(v.placeId).category;counts[c]=(counts[c]||0)+1;per[v.placeId]=(per[v.placeId]||0)+1});
 const sorted=[...DATA.visits].sort((a,b)=>a.date.localeCompare(b.date)),top=Object.entries(counts).sort((a,b)=>b[1]-a[1])[0];
 const days=[...new Set([...DATA.visits.map(v=>v.date),...MEDIA.map(m=>m.date)])].sort();let best=1,run=1;
 for(let i=1;i<days.length;i++){run=new Date(days[i])-new Date(days[i-1])===864e5?run+1:1;best=Math.max(best,run)}
 const mp={};MEDIA.forEach(m=>{const o=mp[m.placeId]||(mp[m.placeId]={p:0,c:0});m.type==='video'?o.c++:o.p++});
 const ranked=[...DATA.places].sort((a,b)=>(per[b.id]||0)-(per[a.id]||0));
 return{counts,per,sorted,days:days.length,streak:best,mp,ranked,topCat:top[0],topCatN:top[1],topPlace:ranked[0],first:byId(sorted[0].placeId),latest:byId(sorted.at(-1).placeId),cats:Object.keys(counts).length}})();
const mline=id=>{const o=D.mp[id]||{p:0,c:0};return[o.p?plural(o.p,'photo'):'',o.c?plural(o.c,'clip'):''].filter(Boolean).join(' \u00b7 ')||'No media yet'};

const MB=(()=>{const la=DATA.places.map(p=>p.lat),ln=DATA.places.map(p=>p.lng);return{a:Math.min(...la),b:Math.max(...la),c:Math.min(...ln),d:Math.max(...ln)}})();
const mpos=p=>({x:10+(p.lng-MB.c)/((MB.d-MB.c)||1)*80,y:18+(1-(p.lat-MB.a)/((MB.b-MB.a)||1))*66});
const ROUTE=D.sorted.filter((v,i,a)=>!i||v.placeId!==a[i-1].placeId).map(v=>({...mpos(byId(v.placeId)),id:v.placeId,date:v.date,name:byId(v.placeId).short}));
const MAP_START=400,MAP_MS=5200,RM=matchMedia('(prefers-reduced-motion: reduce)').matches;
function resetMap(){const box=$('#mapbox');if(!box)return;const pins=box.querySelectorAll('.pin'),lines=box.querySelectorAll('line');
 pins.forEach(p=>p.classList.toggle('on',RM));lines.forEach((l,k)=>{const Q=RM?ROUTE[k+1]:ROUTE[k];l.setAttribute('x2',Q.x);l.setAttribute('y2',Q.y)});$('#mdot').style.opacity=0;$('#mlabel').textContent=''}
function tickMap(e){const box=$('#mapbox');if(!box||RM||e<MAP_START||ROUTE.length<2)return;
 const n=ROUTE.length-1,t=Math.min((e-MAP_START)/MAP_MS,1)*n,leg=Math.min(Math.floor(t),n-1),f=t-leg,A=ROUTE[leg],B=ROUTE[leg+1];
 box.querySelectorAll('line').forEach((l,k)=>{const P=ROUTE[k],Q=ROUTE[k+1],g=k<leg?1:k===leg?f:0;l.setAttribute('x2',P.x+(Q.x-P.x)*g);l.setAttribute('y2',P.y+(Q.y-P.y)*g)});
 for(let k=0;k<=leg+(f>.85?1:0);k++)box.querySelector(`.pin[data-id="${ROUTE[k].id}"]`)?.classList.add('on');
 const dot=$('#mdot');dot.style.opacity=1;dot.style.left=A.x+(B.x-A.x)*f+'%';dot.style.top=A.y+(B.y-A.y)*f+'%';
 const r=f>.5?B:A;$('#mlabel').textContent=fmtDate(r.date)+' \u00b7 '+r.name}
/* the cut */
const ALL=EDIT.chapters.map(ch=>{const p=byId(ch.placeId),m=MEDIA.find(x=>x.id===ch.clipId)||CLIPS.find(x=>x.placeId===ch.placeId)||ch.media;return p&&m?{...ch,p,m}:null}).filter(Boolean);
const BUDGET=Math.max(EDIT.maxSeconds*1000-EDIT.titleMs-EDIT.endMs,0),MAXC=Math.max(Math.floor(BUDGET/EDIT.minClipMs),1);
const CH=ALL; /* never drop clips: they speed up to fit maxSeconds */
const PH_MS=1000,SEQ=[];CH.forEach((c,i)=>{SEQ.push({k:'clip',i,p:c.p,m:c.m,nat:capSec(c.m)*1000});PHOTOS.filter(x=>x.placeId===c.p.id&&x.id!==c.m.id).forEach(m=>SEQ.push({k:'photo',i,p:c.p,m,nat:PH_MS}))});
DATA.places.filter(p=>!CH.some(c=>c.p.id===p.id)).forEach(p=>PHOTOS.filter(x=>x.placeId===p.id).forEach(m=>SEQ.push({k:'photo',i:CH.length-1,p,m,nat:PH_MS})));
const SPEED=BUDGET?Math.max(1,SEQ.reduce((s,f)=>s+f.nat,0)/BUDGET):1;
const FILM=[{k:'title',ms:EDIT.titleMs},...SEQ.map(f=>({...f,ms:Math.round(f.nat/SPEED)})),{k:'end',ms:EDIT.endMs}];
const FILM_MS=FILM.reduce((s,f)=>s+f.ms,0),placesLine=CH.map(c=>c.p.short).join(' \u00b7 ');

const GRP={service:'local',coffee:'food',food:'food',event:'culture',culture:'culture',leisure:'nature'};
const PERS={local:{g:'\uD83E\uDDED',t:'The Local <em>Discoverer</em>',d:'You find Malvar through its everyday places \u2014 campuses, offices and civic spaces \u2014 and make them part of your story.'},food:{g:'\u2615',t:'The Food <em>Explorer</em>',d:'Coffee runs, bakeries and market stalls shape your days in Malvar.'},culture:{g:'\uD83C\uDFDB',t:'The Culture <em>Enthusiast</em>',d:'Festivals and heritage sites draw you in. You travel for the stories of the town.'},nature:{g:'\uD83C\uDF3F',t:'The Nature <em>Seeker</em>',d:'You chase open air, plazas and poolside afternoons.'},memory:{g:'\uD83D\uDCF8',t:'The Memory <em>Collector</em>',d:'You capture almost every stop. Your gallery is the real itinerary.'}};
const SC={local:0,food:0,culture:0,nature:0};DATA.visits.forEach(v=>SC[GRP[byId(v.placeId).category]]++);
const PK=(PHOTOS.length+CLIPS.length)/DATA.visits.length>=3?'memory':Object.entries(SC).sort((a,b)=>b[1]-a[1])[0][0];
const SCN={local:'Local services',food:'Food & coffee',culture:'Culture & events',nature:'Leisure & nature'};
const MONTHS=Array(12).fill(0),WK=Array(7).fill(0),MN=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],WN=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
DATA.visits.forEach(v=>{MONTHS[+v.date.slice(5,7)-1]++;WK[new Date(v.date+'T00:00:00').getDay()]++});
const topM=MONTHS.indexOf(Math.max(...MONTHS)),topW=WK.indexOf(Math.max(...WK));
const fp=new Set(DATA.visits.filter(v=>['food','coffee'].includes(byId(v.placeId).category)).map(v=>v.placeId)).size,cv=DATA.visits.filter(v=>['event','culture'].includes(byId(v.placeId).category)).length,uq=new Set(DATA.visits.map(v=>v.placeId)).size;
const BADGES_ALL=[{file:'first_destination.svg',t:'First Destination',d:'Your first recorded stop: '+D.first.short,ok:DATA.visits.length>0},{file:'food_explorer.svg',t:'Food Explorer',d:fp+' food & coffee spots tried',ok:fp>=2},{file:'culture_enthusiast.svg',t:'Culture Enthusiast',d:cv+' fiesta & heritage visits',ok:cv>=2},{file:'memory_keeper.svg',t:'Memory Keeper',d:PHOTOS.length+' photos uploaded',ok:PHOTOS.length>=10},{file:'malvar_explorer.svg',t:'Malvar Explorer',d:uq+' different places',ok:uq>=5},{file:'on_a_roll.svg',t:'On a Roll',d:D.streak+'-day streak',ok:D.streak>=7}];
const BADGES=BADGES_ALL.filter(b=>b.ok);
/* slides */
const SLIDES=[
{id:'cover',build:()=>{const[a,b,c]=mix(3);return`<div class="cover"><div class="eyebrow rise">${esc(CFG.brand)} \u00b7 ${esc(CFG.region)}</div><div class="bento rise" style="--i:1">${tile(a,'a')}${tile(b)}${tile(c)}</div><h1 class="rise" style="--i:2"><em>Wrapped</em><br><span>${CFG.year}</span></h1><p class="rise" style="--i:3">${C.coverTag.map(esc).join('<br>')}</p><div class="go rise" style="--i:4">${esc(C.startHint)} \u2192</div></div>`}},
{id:'numbers',build:()=>{const rows=[['#587a4c',DATA.places.length,'Places visited'],['#c07a2c',D.days,'Days traveled'],['#c4455f',PHOTOS.length,'Photos uploaded'],['#d0652d',CLIPS.length,'Clips captured'],['#7a5aa6',DATA.stats.favoritesMarked,'Favorites marked'],['#3f7f7a',D.cats,'Categories explored']];
 const foot=Object.entries(D.counts).map(([k,n])=>`${n} ${DATA.categories[k].label.toLowerCase()}`).join(' \u00b7 ');
 return`${head('By the numbers','Your trip,<br>in figures')}<div class="lead rise" style="--i:1"><span class="big" data-count="${MEDIA.length}">0</span><div><b>moments captured</b><small>across ${plural(D.days,'day')} and ${plural(DATA.places.length,'place')}</small></div></div>${rows.map(([c,n,l],k)=>`<div class="row rise" style="--i:${k+2};--c:${c}"><i class="dot"></i><span>${l}</span><b data-count="${n}">0</b></div>`).join('')}<p class="foot rise" style="--i:9">${esc(foot)}</p>`}},
{id:'personality',build:()=>{const P=PERS[PK],tot=DATA.visits.length;return`${head('Your travel personality','You are')}<div class="pg rise" style="--i:1">${P.g}</div><div class="pt rise" style="--i:2">${P.t}</div><p class="lede rise" style="--i:3">${P.d}</p><div class="pb rise" style="--i:4">${Object.entries(SC).sort((a,b)=>b[1]-a[1]).map(([k,n])=>`<div><span>${SCN[k]}</span><i><u style="width:${n/tot*100}%"></u></i><b>${n}</b></div>`).join('')}</div>`}},
{id:'habits',build:()=>{const mx=Math.max(...MONTHS);return`${head('Your travel habits','How you<br>explore')}<div class="chips rise" style="--i:1"><div class="chip"><small>Top month</small><b>${MN[topM]}</b></div><div class="chip"><small>Top day</small><b>${WN[topW]}</b></div><div class="chip"><small>Top category</small><b>${esc(DATA.categories[D.topCat].label)}</b></div></div><div class="mbars rise" style="--i:2">${MONTHS.map((n,i)=>`<div class="${i===topM?'hi':''}"><i style="height:${n?Math.max(n/mx*90,6):3}px"></i></div>`).join('')}</div><div class="mlab rise" style="--i:2">${MN.map(m=>`<span>${m[0]}</span>`).join('')}</div><p class="foot rise" style="--i:3">${plural(MONTHS[topM],'visit')} in ${MN[topM]}; ${MONTHS.filter(n=>!n).length} quiet months still waiting for a story.</p>`}},
{id:'picks',build:()=>{const rows=[['Favorite place',D.topPlace.name,D.topPlace],['First place visited',D.first.name,D.first],['Latest adventure',D.latest.name,D.latest],['Longest streak',plural(D.streak,'day'),null]];
 return`${head('Top picks','What defined<br>your year')}<div class="statement rise" style="--i:1"><small>Most visited category</small><strong>${esc(DATA.categories[D.topCat].label)}</strong><span>${plural(D.topCatN,'visit')} this year</span></div>${rows.map(([k,v,p],n)=>`<div class="pk rise" style="--i:${n+2}"><div class="tx"><div class="k">${k}</div><div class="v" title="${esc(v)}">${esc(v)}</div></div>${p?`<div class="thumb">${pTile(p)}</div>`:'<div class="ico">\uD83D\uDD25</div>'}</div>`).join('')}`}},
{id:'timeline',build:()=>{const rec=[...D.sorted].reverse(),first=rec.at(-1);
 return`${head('Journey timeline','Where you<br>went, when',`From a coffee start to a coffee finale, on a ${plural(D.streak,'day')}-streak.`)}<div class="timeline">${rec.slice(0,4).map((v,n)=>{const p=byId(v.placeId),m=MEDIA.find(x=>x.placeId===v.placeId&&x.date===v.date),d=new Date(v.date+'T00:00:00');
  return`<div class="tl rise" style="--i:${n+1};--c:${cat(p).color}"><div class="dt"><b>${d.getDate()}</b><small>${d.toLocaleDateString('en-US',{month:'short'})}</small></div><div class="mk"></div><div class="bd"><small>${v.type}</small><div>${esc(p.name)}</div></div>${m?thumb(m):'<span></span>'}</div>`}).join('')}</div><p class="foot rise" style="--i:6">It started ${fmtDate(first.date)} \u2014 with coffee.</p>`}},
{id:'places',build:()=>{const[h,...rest]=D.ranked,more=D.ranked.length-3;
 const card=(p,c='')=>`<div class="pc ${c}">${pTile(p,'fill')}<div class="shade"></div>${c?'<span class="tag">Most visited</span>':''}<div class="tx"><b>${esc(p.name)}</b><small>${plural(D.per[p.id]||0,'visit')} \u00b7 ${mline(p.id)}</small></div></div>`;
 return`${head('Places you explored','Your favorite<br>spots',`${esc(D.topPlace.short)} was home base, while ${esc(D.latest.short)} brewed your afternoons.`)}<div class="rise" style="--i:1">${card(h,'big')}</div><div class="pc-row rise" style="--i:2">${rest.slice(0,2).map(p=>card(p)).join('')}</div>${more>0?`<p class="more rise" style="--i:3">+ ${plural(more,'more place')} on your map</p>`:''}`}},
{id:'liked',build:()=>`${head('What you liked','Your<br>favorites',`${esc(DATA.categories[D.topCat].label)} topped your list, with ${plural(D.topCatN,'helpful visit')}.`)}${DATA.liked.slice(0,4).map((l,n)=>`<div class="lk rise" style="--i:${n+1}"><span class="n">${pad(n+1)}</span>${pTile(l)}<span class="nm">${esc(l.name)}</span><span class="h">\u2665</span></div>`).join('')}`},
{id:'map',onEnter:()=>resetMap(),onTick:e=>tickMap(e),build:()=>{const la=DATA.places.map(p=>p.lat),ln=DATA.places.map(p=>p.lng),[a,b,c,d]=[Math.min(...la),Math.max(...la),Math.min(...ln),Math.max(...ln)];
 return`${head('Travel map','All over<br>Malvar')}<div class="map rise" style="--i:1" id="mapbox"><svg class="route" viewBox="0 0 100 100" preserveAspectRatio="none">${ROUTE.slice(1).map((q,k)=>`<line x1="${ROUTE[k].x}" y1="${ROUTE[k].y}" x2="${ROUTE[k].x}" y2="${ROUTE[k].y}"/>`).join('')}</svg>${DATA.places.map(p=>`<i class="pin" data-id="${p.id}" style="--c:${cat(p).color};left:${10+(p.lng-c)/((d-c)||1)*80}%;top:${18+(1-(p.lat-a)/((b-a)||1))*66}%" title="${esc(p.short)}"></i>`).join('')}<i class="dot" id="mdot"></i><span class="mlabel" id="mlabel"></span></div><div style="margin-top:6px">${DATA.places.slice(0,5).map((p,n)=>`<div class="mrow rise" style="--i:${n+2};--c:${cat(p).color}"><span class="d"></span><div>${esc(p.short)}<small>${esc(cat(p).label)} \u00b7 ${mline(p.id)}</small></div></div>`).join('')}</div><p class="more rise" style="--i:8">+ ${DATA.places.length-5} more on the map</p>`}},
{id:'badges',build:()=>`${head('Your achievements','Badges<br>you earned',`${BADGES.length} of ${BADGES_ALL.length} unlocked from your activity.`)}<div class="bgs">${BADGES.map((b,n)=>`<div class="bg rise" style="--i:${n+1}"><div class="bi"><img src="../assets/images/wrapped-badges/${b.file}" alt=""></div><b>${b.t}</b><small>${esc(b.d)}</small><a class="badge-download" href="../assets/images/wrapped-badges/${encodeURIComponent(b.file)}" download="${encodeURIComponent(b.file)}">Download SVG</a></div>`).join('')}</div>${BADGES.length<BADGES_ALL.length?`<p class="more rise" style="--i:8">+ ${BADGES_ALL.length-BADGES.length} more to unlock</p>`:''}`},
{id:'cutIntro',dark:1,build:()=>`${head(esc(EDIT.title),`${plural(CH.length,'place')}.<br>One edit.`,`Your clips from ${esc(placesLine)}, cut together with titles and transitions.`)}<div class="chaps ${CH.length>6?'many':''}">${CH.map((c,n)=>`<div class="chap rise" style="--i:${n+1}"><span class="n">${pad(n+1)}</span><div><strong>${esc(CH.length>6?c.p.short:c.p.name)}</strong><span>${esc(c.label)} \u00b7 ${fmtDate(c.m.date)}</span></div>${thumb(c.m)}</div>`).join('')}</div><div class="specs rise" style="--i:7"><span>Runtime ${fmtDur(Math.round(FILM_MS/1000))}</span>${SPEED>1.05?`<span>${SPEED.toFixed(1)}\u00d7 speed</span>`:''}<span>${PHOTOS.length} photos included</span><span>${EDIT.maxSeconds}s max</span><span>Warm grade</span><span>Titles</span></div>`},
{id:'film',dark:1,bleed:1,dur:()=>FILM_MS,onEnter:()=>{filmIdx=-1;document.querySelectorAll('.cut-item').forEach(i=>i.classList.remove('on','prev'))},onTick:e=>tickFilm(e),
 build:()=>`<div class="cut" id="cut">${FILM.map(f=>{
  if(f.k==='title')return`<div class="cut-item cut-title"><div><span class="eyebrow">${esc(CFG.brand)} presents</span><h2>${esc(EDIT.title)}</h2><p>${esc(CFG.region)} \u00b7 ${CFG.year}</p></div></div>`;
  if(f.k==='end')return`<div class="cut-item cut-title"><div><span class="eyebrow">Fin</span><h2>${esc(CFG.brand)}</h2><p>${esc(placesLine)}</p></div></div>`;
  const{c,i}=f;return`<div class="cut-item" style="--d:${f.ms}ms">${tile(f.m,'fill graded')}<div class="lt ${f.k==='photo'?'st':''}"><span>${esc(f.p.short)}</span><small>${fmtDate(f.m.date)}</small></div></div>`}).join('')}<div class="cut-fx"></div></div>`},
{id:'gallery',build:()=>{const it=mix(6);return`${head('Photos &amp; clips','The rest of<br>the story',`${plural(PHOTOS.length,'photo')} and ${plural(CLIPS.length,'clip')} from every stop.`)}<div class="mosaic rise" style="--i:1">${it.map((m,i)=>tile(m,'t'+(i+1))).join('')}</div>${MEDIA.length>it.length?`<p class="more rise" style="--i:2">+ ${MEDIA.length-it.length} more in your gallery</p>`:''}`}},
{id:'outro',build:()=>`<div class="outro"><div class="eyebrow rise">${esc(CFG.brand)} \u00b7 ${CFG.year}</div><h2 class="rise" style="--i:1">${esc(C.outroTitle[0])}<br><em>${esc(C.outroTitle[1])}</em></h2><p class="lede rise" style="--i:2">${C.outroSub.map(esc).join(' ')}</p><div class="quote rise" style="--i:3">${C.quote.map(q=>`<div>${esc(q)}</div>`).join('')}</div><div class="sig rise" style="--i:4">${esc(CFG.brand.toUpperCase())}<small>${esc(CFG.region.toUpperCase())}</small></div><button class="replay rise" style="--i:5" id="replay">\u21BB Watch again</button></div>`}
];

/* engine */
const stage=$('#stage'),total=SLIDES.length;let idx=0,elapsed=0,lastTs=0,hold=false,manual=false,filmIdx=-1;
$('#brand').textContent=CFG.brand;
$('#slides').innerHTML=SLIDES.map((s,i)=>`<section class="slide ${s.bleed?'bleed':''} ${s.dark?'dark':''}" aria-label="Slide ${i+1} of ${total}">${s.build()}</section>`).join('');
$('#bars').innerHTML=SLIDES.map(()=>'<div class="bar"><i></i></div>').join('');
const els=[...$('#slides').children],fills=[...$('#bars').children].map(b=>b.firstChild),durOf=i=>SLIDES[i].dur?SLIDES[i].dur():CFG.slideMs;
function fitTitles(){els.forEach(el=>{const maxW=Math.max(stage.clientWidth-48,180),maxH=Math.max(stage.clientHeight*.52,120);el.querySelectorAll('.cut-title h2').forEach(h=>{h.style.fontSize='76px';h.style.width='100%';for(let size=76;size>18;size-=1){h.style.fontSize=size+'px';if(h.scrollWidth<=maxW+1&&h.scrollHeight<=maxH)break}});el.querySelectorAll('.lt span').forEach(h=>{for(let size=72;size>18;size-=1){h.style.fontSize=size+'px';if(h.scrollWidth<=maxW+1&&h.scrollHeight<=maxH)break}})})}
fitTitles();if('ResizeObserver'in window)new ResizeObserver(fitTitles).observe(stage);else addEventListener('resize',fitTitles);document.fonts?.ready.then(fitTitles);
function tickFilm(e){let acc=0,k=0;for(;k<FILM.length;k++){if(e<acc+FILM[k].ms)break;acc+=FILM[k].ms}k=Math.min(k,FILM.length-1);
 const root=$('#cut');if(!root)return;const f=FILM[k],ch=f.k==='clip'?f.i:f.k==='end'?CH.length:-1;
 if(k!==filmIdx){filmIdx=k;root.querySelectorAll('.cut-item').forEach((it,i)=>{it.classList.toggle('on',i===k);it.classList.toggle('prev',i===k-1)});root.querySelectorAll('.rail>div').forEach((r,i)=>r.classList.toggle('act',i===ch));root.querySelectorAll('video').forEach(v=>v.playbackRate=SPEED)}
 const l=Math.min((e-acc)/f.ms,1);root.querySelectorAll('.rail i').forEach((b,i)=>b.style.width=i<ch?'100%':i===ch?l*100+'%':'0%')}
function counters(el){el.querySelectorAll('[data-count]').forEach(n=>{const end=+n.dataset.count,t0=performance.now();(function t(now){const p=Math.min((now-t0)/900,1);n.textContent=Math.round(end*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(t)})(t0)})}
function go(n){idx=Math.max(0,Math.min(total-1,n));elapsed=0;const s=SLIDES[idx];
 els.forEach((el,i)=>{el.classList.toggle('active',i===idx);el.classList.toggle('before',i<idx)});fills.forEach((b,i)=>b.style.width=i<idx?'100%':'0%');
 $('#count').textContent=(idx+1)+'/'+total;stage.classList.toggle('dark',!!s.dark);counters(els[idx]);if(s.onEnter)s.onEnter()}
function loop(ts){const dt=Math.min(ts-(lastTs||ts),100);lastTs=ts;
 if(!hold&&!manual&&!document.hidden){elapsed+=dt;const d=durOf(idx);fills[idx].style.width=Math.min(elapsed/d,1)*100+'%';if(SLIDES[idx].onTick)SLIDES[idx].onTick(elapsed);if(elapsed>=d&&idx<total-1)go(idx+1)}
 requestAnimationFrame(loop)}
const toast=m=>{const t=$('#toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),2400)};
function exportFilm(){
 const button=$('#exportVideo');if(!window.MediaRecorder||!HTMLCanvasElement.prototype.captureStream){toast('Video export is not supported in this browser');return}
 button.disabled=true;button.textContent='Preparing video…';manual=true;
 const canvas=document.createElement('canvas');canvas.width=1080;canvas.height=1920;const ctx=canvas.getContext('2d');
 const assets=FILM.map(f=>{if(!f.m)return null;if(f.m.type==='video'){const v=document.createElement('video');v.muted=true;v.playsInline=true;v.preload='auto';v.crossOrigin='anonymous';v.src=f.m.src||'';return v}const im=new Image();im.crossOrigin='anonymous';im.src=f.m.src||f.m.poster||'';return im}),posters=FILM.map(f=>{if(!f.m||f.m.type!=='video'||!f.m.poster)return null;const im=new Image();im.crossOrigin='anonymous';im.src=f.m.poster;return im});
 const mime=['video/mp4','video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm'].find(t=>MediaRecorder.isTypeSupported(t));if(!mime){button.disabled=false;button.textContent='Export video';manual=false;toast('This browser cannot create a downloadable video');return}
 const fit=(source)=>{if(!source||!source.videoWidth&&!source.naturalWidth)return;const w=source.videoWidth||source.naturalWidth,h=source.videoHeight||source.naturalHeight,s=Math.min(canvas.width/w,canvas.height/h),dw=w*s,dh=h*s;ctx.drawImage(source,(canvas.width-dw)/2,(canvas.height-dh)/2,dw,dh)};
 const card=(title,sub)=>{const g=ctx.createLinearGradient(0,0,canvas.width,canvas.height);g.addColorStop(0,'#3b2414');g.addColorStop(1,'#130b06');ctx.fillStyle=g;ctx.fillRect(0,0,canvas.width,canvas.height);ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillStyle='#f6ebdd';const max=canvas.width-120;let size=150;ctx.font=size+'px Barabara';while(ctx.measureText(title.toUpperCase()).width>max&&size>32)ctx.font=(--size)+'px Barabara';ctx.fillText(title.toUpperCase(),canvas.width/2,canvas.height/2,max);ctx.fillStyle='#f6ebddb0';ctx.font='600 30px Inter';ctx.fillText(sub.toUpperCase(),canvas.width/2,canvas.height/2+120,max)};
 const draw=(k,offset)=>{const f=FILM[k],a=assets[k];ctx.fillStyle='#0f0a06';ctx.fillRect(0,0,canvas.width,canvas.height);
  if(f.k==='title'){card(EDIT.title,CFG.region+' · '+CFG.year);return}if(f.k==='end'){card(CFG.brand,placesLine);return}
  if(f.k==='clip'&&a instanceof HTMLVideoElement){if(a.readyState>=2)fit(a);else fit(posters[k])}else fit(a);
  const grad=ctx.createLinearGradient(0,canvas.height*.65,0,canvas.height);grad.addColorStop(0,'#0000');grad.addColorStop(1,'#000c');ctx.fillStyle=grad;ctx.fillRect(0,canvas.height*.62,canvas.width,canvas.height*.38);ctx.textAlign='center';ctx.fillStyle='#fff';ctx.font='100px Barabara';ctx.textBaseline='bottom';let title=f.p.short.toUpperCase(),size=100;while(ctx.measureText(title).width>canvas.width-100&&size>32)ctx.font=(--size)+'px Barabara';ctx.fillText(title,canvas.width/2,canvas.height-150,canvas.width-100);ctx.font='600 30px Inter';ctx.fillText(fmtDate(f.m.date).toUpperCase(),canvas.width/2,canvas.height-85);
  if(f.k==='clip'&&a instanceof HTMLVideoElement&&a.readyState>=2){a.playbackRate=SPEED;if(a.paused)a.play().catch(()=>{})}else if(a instanceof HTMLVideoElement)a.pause();
 };
 ctx.fillStyle='#130b06';ctx.fillRect(0,0,canvas.width,canvas.height);const waitAsset=a=>new Promise(resolve=>{if(!a||a instanceof HTMLImageElement&&a.complete||a instanceof HTMLVideoElement&&a.readyState>=2){resolve();return}const done=()=>resolve();a.addEventListener('load',done,{once:true});a.addEventListener('loadeddata',done,{once:true});a.addEventListener('error',done,{once:true});setTimeout(done,5000)});
 Promise.all([...assets,...posters].map(waitAsset)).then(()=>document.fonts?.ready).then(()=>{
  const stream=canvas.captureStream(30),rec=new MediaRecorder(stream,{mimeType:mime,videoBitsPerSecond:5000000}),chunks=[];rec.ondataavailable=e=>{if(e.data.size)chunks.push(e.data)};rec.onstop=()=>{stream.getTracks().forEach(t=>t.stop());const blob=new Blob(chunks,{type:mime}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='kultoura-wrapped-'+CFG.year+(mime.includes('mp4')?'.mp4':'.webm');a.click();setTimeout(()=>URL.revokeObjectURL(url),60000);button.disabled=false;button.textContent='Export video';manual=false;toast('Video downloaded')};
  rec.start(250);const start=performance.now();let last=-1;function frame(now){const t=now-start;if(t>=FILM_MS){draw(FILM.length-1,FILM[FILM.length-1].ms);rec.stop();return}let acc=0,k=0;for(;k<FILM.length-1&&t>=acc+FILM[k].ms;k++)acc+=FILM[k].ms;draw(k,t-acc);if(k!==last){last=k;if(assets[k] instanceof HTMLVideoElement){assets[k].currentTime=0;assets[k].play().catch(()=>{})}}requestAnimationFrame(frame)}requestAnimationFrame(frame);button.textContent='Recording…';toast('Recording Wrapped video…');
 });
}
$('#exportVideo').onclick=e=>{e.stopPropagation();exportFilm()};
$('#share').onclick=async e=>{e.stopPropagation();const d={title:CFG.brand+' Wrapped '+CFG.year,text:C.shareText,url:location.href};try{if(navigator.share)await navigator.share(d);else{await navigator.clipboard.writeText(d.url);toast('Link copied to clipboard')}}catch(_){}};
$('#fb').onclick=e=>{e.stopPropagation();window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(location.href),'_blank','noopener')};
document.addEventListener('click',e=>{if(e.target.closest('#replay'))go(0)});
let downAt=0,sx=0,sy=0;
stage.addEventListener('pointerdown',e=>{if(e.target.closest('button'))return;downAt=Date.now();sx=e.clientX;sy=e.clientY;hold=true});
['pointerup','pointercancel','pointerleave'].forEach(ev=>stage.addEventListener(ev,()=>hold=false));
stage.addEventListener('click',e=>{if(e.target.closest('button,a')||Date.now()-downAt>=250)return;const r=stage.getBoundingClientRect();go(e.clientX<r.left+r.width*.3?idx-1:idx+1)});
stage.addEventListener('pointerup',e=>{const dx=e.clientX-sx,dy=e.clientY-sy;if(Math.abs(dx)>55&&Math.abs(dx)>Math.abs(dy)*1.4)go(idx+(dx<0?1:-1))});
document.addEventListener('keydown',e=>{if(e.key==='ArrowRight')go(idx+1);else if(e.key==='ArrowLeft')go(idx-1);else if(e.key===' '){e.preventDefault();manual=!manual}});
go(0);requestAnimationFrame(loop);
</script></body></html>
