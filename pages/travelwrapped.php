<?php
$isPublicShared = false;
if (!isset($wrappedStoryData) && isset($_GET['share'])) {
    require_once __DIR__ . '/../config/session_boot.php';
    include __DIR__ . '/../config/dbmain.php';
    require_once __DIR__ . '/../config/travel_wrapped_share.php';
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store');
    $token = (string) $_GET['share'];
    $sharedStory = null;
    if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
        try {
            kt_wrapped_share_ensure_table($conn);
            $shareHash = hash('sha256', $token);
            $shareStmt = $conn->prepare('SELECT payload,user_id FROM travel_wrapped_shares WHERE share_hash=? LIMIT 1');
            $shareStmt->bind_param('s', $shareHash);
            $shareStmt->execute();
            $shareRow = $shareStmt->get_result()->fetch_assoc();
            $shareStmt->close();
            if (!empty($shareRow['payload'])) {
                $sharedStory = json_decode((string) $shareRow['payload'], true);
                if (is_array($sharedStory)) {
                    if (empty($sharedStory['owner'])) {
                        $ownerStmt = $conn->prepare('SELECT username FROM users WHERE id=? LIMIT 1');
                        $ownerId = (int) ($shareRow['user_id'] ?? 0);
                        $ownerStmt->bind_param('i', $ownerId);
                        $ownerStmt->execute();
                        $sharedStory['owner'] = (string) ($ownerStmt->get_result()->fetch_assoc()['username'] ?? 'KULTOURA traveler');
                        $ownerStmt->close();
                    }
                    if (!empty($sharedStory['copy']['coverTag'][0]) && preg_match('/^Your (.+) journey,$/i', (string) $sharedStory['copy']['coverTag'][0], $coverMatch)) {
                        $sharedStory['copy']['coverTag'][0] = $sharedStory['owner'] . "'s " . $coverMatch[1] . ' journey,';
                    }
                    if (isset($sharedStory['copy']['shareText'])) {
                        $sharedStory['copy']['shareText'] = $sharedStory['owner'] . "'s KULTOURA Wrapped";
                    }
                }
            }
        } catch (Throwable $error) {
            error_log('Travel Wrapped public share lookup failed: ' . $error->getMessage());
        }
    }
    if (is_array($sharedStory) && !empty($sharedStory['visits'])) {
        $wrappedStoryData = $sharedStory;
        $isPublicShared = true;
    } else {
        http_response_code(404);
        ?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wrapped link unavailable</title><body style="font:16px system-ui;max-width:520px;margin:12vh auto;padding:24px;color:#243128"><h1>This Wrapped link is unavailable</h1><p>It may be invalid or no longer available.</p><a href="../index.php">Visit KULTOURA</a></body></html><?php
        exit;
    }
}
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
body{font-family:var(--sans);color:var(--ink);display:grid;place-items:center;overflow:hidden;background:transparent;position:relative;isolation:isolate}
.diary-backdrop{position:fixed;inset:0;width:100%;height:100%;border:0;z-index:-2;pointer-events:none;background:#f4efe5}
body:before{content:"";position:fixed;inset:0;z-index:-1;background:rgba(20,12,6,.72);pointer-events:none}
.stage{position:relative;z-index:1;width:min(420px,46vh,calc(100vw - 48px));height:min(760px,88vh,88dvh);border-radius:26px;overflow:hidden;background:var(--paper);user-select:none;transition:background .6s;--bt:rgba(42,26,16,.15);--bf:var(--ink)}
@media(max-width:520px){.stage{width:min(420px,calc(100vw - 32px));height:min(760px,88vh,88dvh);border-radius:24px}}
.popup-close{position:absolute;z-index:50;top:calc(env(safe-area-inset-top,0px) + 31px);right:12px;width:34px;height:34px;display:grid;place-items:center;border:1px solid var(--line);border-radius:50%;background:var(--card);color:var(--ink);text-decoration:none;font:600 20px/1 var(--sans);box-shadow:0 3px 12px #2a1a1020}
.popup-close:hover{background:#fffaf3;transform:scale(1.04)}.popup-close:focus-visible{outline:3px solid var(--terra);outline-offset:3px}
.stage.dark{background:#130b06;--bt:#ffffff30;--bf:#fff}
.stage.exporting .rise{opacity:1!important;transform:none!important;animation:none!important}
.top{position:absolute;top:0;left:0;right:0;z-index:20;padding:calc(env(safe-area-inset-top,0px) + 14px) 56px 0 18px}
.bars{display:flex;gap:4px}.bar{position:relative;flex:1;height:3px;border-radius:9px;background:var(--bt);overflow:hidden}.bar i{display:block;height:100%;width:0;background:var(--bf)}.bar.film-progress{background:transparent}.bar.film-progress>i{display:none}
.meta{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
.brand{font:700 11px var(--sans);letter-spacing:.22em;text-transform:uppercase;color:var(--sage-d)}.stage.dark .brand{color:var(--gold)}
.slides{position:absolute;inset:0}
.slide{position:absolute;inset:0;padding:78px 24px 142px;display:flex;flex-direction:column;justify-content:safe center;overflow-x:hidden;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#76624f55 transparent;overscroll-behavior:contain;opacity:0;visibility:hidden;transform:translateX(20px);transition:opacity .4s,transform .4s,visibility 0s .4s}
.slide::-webkit-scrollbar{width:5px}.slide::-webkit-scrollbar-thumb{background:#76624f55;border-radius:9px}.slide>*{flex:0 0 auto}.slide:not(.bleed)::after{content:"";position:sticky;bottom:-142px;z-index:8;display:block;flex:0 0 36px;width:calc(100% + 48px);margin:0 -24px -142px;pointer-events:none;background:linear-gradient(transparent,var(--paper) 92%)}.stage.dark .slide:not(.bleed)::after{background:linear-gradient(transparent,#130b06 92%)}
.slide.active{opacity:1;visibility:visible;transform:none;transition:opacity .4s,transform .4s}.slide.before{transform:translateX(-20px)}
.slide.bleed{padding:0;justify-content:flex-start}.slide.dark{color:#f6ebdd}
.rise{opacity:0;transform:translateY(14px)}.slide.active .rise{animation:rise .55s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(var(--i,0)*80ms + 100ms)}
@keyframes rise{to{opacity:1;transform:none}}
.eyebrow{display:flex;align-items:center;gap:10px;font:700 12px var(--sans);letter-spacing:.16em;text-transform:uppercase;color:#9e351f}.eyebrow:before{content:"";width:22px;height:1.5px;background:currentColor}.dark .eyebrow{color:#f3c778}
h1,h2{font-family:var(--serif);letter-spacing:-.025em;line-height:1.02}h2{font-size:clamp(30px,8.5vw,36px);font-weight:700;margin-top:10px}
.lede{color:#665343;font-size:14px;line-height:1.55;margin-top:10px;max-width:320px}.dark .lede{color:#f6ebdd}.head{margin-bottom:20px}
.ph{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--a,#d8b98f),var(--b,#a67c52))}
.ph img{width:100%;height:100%;object-fit:cover;display:block;position:absolute;inset:0}.ph.fill{position:absolute;inset:0}
.clip-ph{position:absolute;inset:-12%;background-size:cover;background-position:center;animation:ken 5s ease-in-out infinite alternate}
.clip-ph i{position:absolute;left:50%;top:50%;width:42px;height:42px;margin:-21px;border-radius:50%;display:grid;place-items:center;background:#ffffff3d;color:#fff;font-style:normal;font-size:14px}
@keyframes ken{to{transform:scale(1.12) translate(-3%,2%)}}
.badge{position:absolute;left:8px;bottom:9px;padding:3px 8px;border-radius:99px;background:#2a1a10e8;color:#fff;font:600 12px var(--sans);z-index:2}
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
.pc .tag{position:absolute;top:12px;left:12px;font:700 12px var(--sans);letter-spacing:.1em;text-transform:uppercase;padding:5px 10px;border-radius:99px;background:#fffaf3;color:var(--ink)}
.pc.big{height:clamp(145px,20dvh,200px)}.pc-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}.pc-row .pc{height:clamp(105px,15dvh,150px)}.pc-row .tx b{font-size:14.5px}
.more{margin-top:12px;font:500 13px var(--sans);color:var(--soft)}
.lk{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--line)}.lk:first-child{border-top:1.5px solid var(--ink)}.lk .n{font:700 24px var(--serif);color:var(--caramel);width:34px}.lk .ph{width:68px;height:68px;border-radius:16px;flex:none}.lk .nm{flex:1;font:700 15px/1.3 var(--serif)}.lk .h{color:#c4455f}
.map{position:relative;margin:0 -24px;height:clamp(170px,26dvh,230px);background:radial-gradient(ellipse at 50% 50%,#eff0df 0,#e7e8d7 70%,#d9dfd0 100%);border-block:1px solid #aebba9;overflow:hidden}.route{position:absolute;inset:0;width:100%;height:100%;overflow:visible}.route .outline{fill:#d3e0cb;stroke:#587a4c;stroke-width:2;vector-effect:non-scaling-stroke}.route .ordered-path{fill:none;stroke:#b5472a;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}.map-place-label{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:1;color:#587a4c55;font:800 12px var(--sans);letter-spacing:.2em;text-transform:uppercase;pointer-events:none}.map-north{position:absolute;right:12px;top:10px;z-index:2;color:#496343;font:700 10px/1.2 var(--sans);text-align:center}.map-north b{display:block;font-size:17px}.pin{opacity:0;transition:opacity .18s;position:absolute;width:28px;height:28px;margin:-14px 0 0 -14px;display:grid;place-items:center;border:2px solid #fff;border-radius:50%;background:var(--c);color:#fff;font:700 12px var(--sans);box-shadow:0 4px 12px #17261f55}.pin.on{opacity:1}.dot{position:absolute;width:16px;height:16px;margin:-8px 0 0 -8px;border-radius:50%;background:#b5472a;border:3px solid #fff;z-index:3;opacity:0;animation:pulse 1.2s infinite}@keyframes pulse{0%{box-shadow:0 0 0 0 #b5472a80}70%,100%{box-shadow:0 0 0 12px #b5472a00}}.mlabel{position:absolute;left:34px;top:10px;z-index:4;font:700 12px var(--sans);padding:5px 10px;border-radius:99px;background:var(--ink);color:#fff;letter-spacing:.04em}.mlabel:empty{display:none}.mrow{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--line)}.mrow .d{width:22px;height:22px;border-radius:50%;background:var(--c);color:#fff;display:grid;place-items:center;flex:none;font:700 11px var(--sans)}.mrow div{flex:1;font:700 14px/1.25 var(--serif)}.mrow small{display:block;font:500 12px var(--sans);color:#665343}.category-cta{display:block;margin-top:5px;color:#754326;font:600 12px/1.4 var(--sans);text-decoration:underline;text-underline-offset:2px}
.chap{display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #ffffff1f}.chap:first-child{border-top:1px solid #ffffff40}
.chap .n{font:italic 700 26px var(--serif);color:var(--gold)}.chap strong{display:block;font:700 15px/1.25 var(--serif)}.chap span{display:block;margin-top:3px;font-size:12px;color:#f6ebdd99}.chap .thumb{width:52px;height:52px}
.specs{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.specs span{font:600 12px var(--sans);letter-spacing:.08em;text-transform:uppercase;padding:6px 11px;border-radius:99px;border:1px solid #ffffff3d;color:#f6ebddcc}
.cut{position:absolute;inset:0;background:#0f0a06;overflow:hidden}.cut-item{position:absolute;inset:0;visibility:hidden;clip-path:inset(0 0 0 100%)}
.cut-item.prev{visibility:visible;z-index:1;clip-path:inset(0)}.cut-item.on{visibility:visible;z-index:2;clip-path:inset(0)}
.graded .clip-ph{filter:saturate(1.12) contrast(1.07) sepia(.14);animation-duration:var(--d,5s)}.chaps.many{display:grid;grid-template-columns:1fr 1fr;column-gap:18px;border-top:1px solid #ffffff40}.many .chap{grid-template-columns:30px 1fr;padding:9px 0;border-top:0!important}.many .chap span:not(.n),.many .thumb{display:none}.many .chap .n{font-size:20px}.many .chap strong{font-size:14px}.cut-fx{position:absolute;inset:0;z-index:5;pointer-events:none;background:radial-gradient(120% 90% at 50% 45%,#0c060322 30%,#0c0603cc),linear-gradient(#0c060333,#0c060333)}
.lt{position:absolute;inset:auto 0 13%;z-index:6;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 24px;color:#fff;opacity:0}.lt:before{content:"";position:absolute;inset:-42px 0 -18px;z-index:-1;background:linear-gradient(transparent,#000b)}.lt small{margin-top:6px;font:600 12px var(--sans);letter-spacing:.16em;text-transform:uppercase;opacity:1;text-shadow:0 2px 14px #000a}.cut-item.on .lt.st{animation:none;opacity:1}.lt span{display:block;width:100%;max-width:100%;min-width:0;font:400 clamp(14px,4vw,22px)/1.05 'Barabara',var(--serif);letter-spacing:.01em;text-transform:uppercase;overflow-wrap:anywhere;word-break:normal;color:#fff;text-shadow:0 2px 14px #000b}.lt span:after{content:"";display:block;width:42px;height:2px;background:rgba(232,184,107,.9);margin:7px auto 0}.cut-item.on .lt{animation:lt var(--d,5s) both}
@keyframes lt{0%,6%{opacity:0;transform:translateY(16px)}14%,78%{opacity:1;transform:none}92%,100%{opacity:0}}

.cut-title{display:grid;place-items:center;overflow:hidden;text-align:center;color:#f6ebdd;background:radial-gradient(520px 420px at 30% 20%,#3b2414,#130b06)}.cut-title>div{width:100%;min-width:0;max-width:100%;padding:0 28px}.cut-title .eyebrow{justify-content:center;color:var(--gold);font-size:12px}.cut-title .eyebrow:before{display:none}
.cut-title h2{display:block;width:100%;max-width:100%;font:400 clamp(25px,8.5vw,44px)/1.06 'Barabara',var(--serif);text-align:center;text-transform:uppercase;white-space:normal;overflow-wrap:break-word;word-break:normal;letter-spacing:-.035em}.cut-title.fin h2{font-size:clamp(24px,8vw,48px);white-space:nowrap;letter-spacing:-.055em}.cut-title h2:after{content:"";display:block;width:60px;height:1.5px;background:var(--gold);margin:20px auto 0}.cut-title p{margin-top:12px;font:600 12px var(--sans);letter-spacing:.16em;text-transform:uppercase;color:#f6ebdd;padding:0 24px;line-height:1.7}
.cut-item .ph img,.cut-item .ph video{object-fit:contain!important;background:#130b06}.cut-item .clip-ph{inset:0;background-size:contain;background-position:center;background-repeat:no-repeat;animation:none!important}
.rail{position:absolute;left:24px;right:24px;bottom:100px;z-index:7;display:flex;gap:8px}.rail>div{flex:1;min-width:0}.rail small{display:block;margin-bottom:7px;font:600 9.5px var(--sans);letter-spacing:.1em;text-transform:uppercase;color:#ffffff70;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.rail .act small{color:#fff}.rail b{display:block;height:3px;border-radius:3px;background:#ffffff35;overflow:hidden}.rail i{display:block;height:100%;width:0;background:#fff}
.mosaic{display:grid;grid-template-columns:repeat(3,1fr);grid-auto-rows:88px;gap:8px}.mosaic .ph{border-radius:16px}.mosaic .t1{grid-column:span 2;grid-row:span 2}.mosaic .t2{grid-row:span 2}.mosaic .t6{grid-column:span 3}
.outro h2{font-size:48px;font-weight:800;margin-top:14px}.outro h2 em{color:var(--sage)}.quote{margin:20px 0 16px;border-top:1.5px solid var(--ink)}.quote div{padding:10px 0;border-bottom:1px solid var(--line);font:italic 500 18px var(--serif)}.visit-cta{position:relative;z-index:11;display:flex;align-items:center;justify-content:space-between;gap:12px;margin:16px 0 2px;padding:14px 16px;border-radius:17px;background:var(--ink);color:#fffaf3;text-decoration:none;box-shadow:0 10px 24px #2a1a1020;transition:transform .18s,background .18s}.visit-cta:hover{transform:translateY(-2px);background:#3b2618}.visit-cta:focus-visible{outline:3px solid var(--terra);outline-offset:3px}.visit-cta span{font:500 11px/1.35 var(--sans);color:#fffaf3b8}.visit-cta strong{display:block;margin-top:3px;color:#fffaf3;font:700 15px var(--serif)}.visit-cta i{font:normal 22px/1 var(--sans);color:var(--gold)}
.sig{font:800 14px var(--serif);letter-spacing:.16em;color:var(--sage-d)}.sig small{display:block;margin-top:4px;font:600 12px var(--sans);letter-spacing:.16em;color:var(--soft)}
.replay{margin-top:20px;cursor:pointer;padding:11px 18px;border-radius:99px;border:1.5px solid var(--ink);background:transparent;color:var(--ink);font:600 13px var(--sans)}
.pg{width:92px;height:92px;border-radius:50%;display:grid;place-items:center;font-size:42px;background:var(--card);border:1.5px solid var(--ink);margin-bottom:16px}.pt{font:800 40px/1.02 var(--serif);letter-spacing:-.025em}.pt em{color:var(--terra)}.pb{margin-top:18px}.pb div{display:grid;grid-template-columns:104px 1fr 22px;gap:10px;align-items:center;padding:6px 0;font-size:12.5px;color:var(--soft)}.pb i{display:block;height:8px;border-radius:9px;background:var(--line);overflow:hidden}.pb u{display:block;height:100%;background:var(--sage);border-radius:9px}.pb b{font:700 14px var(--serif);color:var(--ink);text-align:right}
.chips{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:20px}.chip{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:12px 10px}.chip small{font:600 9.5px var(--sans);letter-spacing:.12em;text-transform:uppercase;color:var(--soft)}.chip b{display:block;margin-top:5px;font:700 17px/1.1 var(--serif)}
.mbars{display:flex;gap:6px;align-items:flex-end;height:96px;border-bottom:1.5px solid var(--ink)}.mbars div{flex:1;text-align:center}.mbars i{display:block;border-radius:6px 6px 0 0;background:var(--line);min-height:3px}.mbars .hi i{background:var(--terra)}.mlab{display:flex;gap:6px;margin-top:6px}.mlab span{flex:1;text-align:center;font:600 8.5px var(--sans);color:var(--soft);text-transform:uppercase}
.bgs{display:grid;grid-template-columns:1fr 1fr;gap:10px}.bg{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:16px 14px;text-align:center}.bg .bi{width:52px;height:52px;margin:0 auto 10px;border-radius:50%;display:grid;place-items:center;font-size:24px;background:linear-gradient(135deg,var(--gold),var(--caramel))}.bg b{display:block;font:700 15px/1.15 var(--serif)}.bg small{display:block;margin-top:5px;font-size:11.5px;color:var(--soft);line-height:1.35}
.badge-download{position:relative;z-index:11;display:inline-block;margin-top:10px;color:var(--sage-d);font:700 12px var(--sans);letter-spacing:.06em;text-decoration:none;text-transform:uppercase}
.zone{position:absolute;top:0;bottom:0;z-index:10;pointer-events:none}.zone.l{left:0;width:30%}.zone.r{right:0;width:70%}
.actions{position:absolute;left:0;right:0;bottom:0;z-index:20;padding:24px 18px calc(env(safe-area-inset-bottom,0px) + 14px);display:flex;gap:10px;justify-content:center;flex-wrap:wrap;background:linear-gradient(180deg,transparent 0,var(--paper) 72%)}.stage.dark .actions{background:linear-gradient(180deg,transparent 0,#130b06 72%)}
.btn{width:46px;height:46px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);cursor:pointer;padding:0;border-radius:50%;font:600 13px var(--sans);background:var(--card);color:var(--ink)}.btn svg{width:20px;height:20px;display:block}.btn{transition:transform .15s,background .3s}.btn:hover{transform:translateY(-2px)}.btn:active{transform:scale(.96)}.btn:disabled{opacity:.55;cursor:wait;transform:none}.stage.dark .btn{background:#ffffff18;color:#fff;border-color:#ffffff45}
.btn-share{width:auto;min-width:96px;gap:8px;padding:0 15px;border-radius:99px;background:var(--card);color:var(--ink);font:600 13px var(--sans)}.btn-share svg{width:17px;height:17px}.stage.dark .btn-share{background:var(--paper);color:var(--ink);border-color:#ffffff65}
.toast{position:absolute;left:50%;top:calc(env(safe-area-inset-top,0px) + 78px);bottom:auto;z-index:40;transform:translateX(-50%);opacity:0;pointer-events:none;background:var(--ink);color:#fff;padding:10px 16px;border-radius:12px;font-size:13px;transition:.3s}.toast.show{opacity:1}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important}.rise{opacity:1!important;transform:none!important}.clip-ph,.lt,.dot{animation:none!important}.lt{opacity:1!important}}
.media-dialog{width:min(420px,calc(100vw - 28px));max-height:min(78dvh,680px);margin:auto;border:1px solid var(--line);border-radius:22px;padding:22px;background:var(--paper);color:var(--ink);box-shadow:0 22px 80px #0008}.media-dialog::backdrop{background:#100b08b8;backdrop-filter:blur(5px)}.dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.dialog-head h2{font:700 24px/1.1 var(--serif)}.dialog-head p{margin-top:6px;color:#665343;font:400 13px/1.45 var(--sans)}.dialog-close{border:0;background:transparent;color:var(--ink);font-size:24px;cursor:pointer}.media-choice-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;max-height:46dvh;overflow:auto;margin:18px 0}.media-choice{display:flex;align-items:center;gap:8px;min-width:0;padding:7px;border:1px solid var(--line);border-radius:12px;background:var(--card);font:500 12px/1.3 var(--sans)}.media-choice img,.media-choice video{width:46px;height:46px;object-fit:cover;border-radius:8px;background:#ddd;flex:none}.media-choice input{accent-color:var(--sage-d)}.dialog-actions{display:flex;justify-content:flex-end;gap:8px}.dialog-actions button,.share-option{min-height:42px;border:1px solid var(--line);border-radius:99px;padding:0 15px;background:var(--card);color:var(--ink);font:600 12px var(--sans);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.dialog-actions .primary{background:var(--ink);color:var(--paper)}.share-options{display:grid;gap:9px;margin-top:18px}.share-link-box{display:flex;gap:8px;margin-top:16px}.share-link-box input{width:100%;min-width:0;height:42px;padding:0 11px;border:1px solid var(--line);border-radius:11px;background:var(--card);color:var(--ink);font:500 12px var(--sans)}.share-link-box button{flex:none}.share-note{margin-top:8px;color:#665343;font:500 12px/1.4 var(--sans)}.share-option:hover{background:#eee2d0}.bar.film-progress .film-subprogress{position:absolute;inset:0;display:flex;gap:1px}.bar.film-progress .film-subprogress span{position:relative;display:block;flex:1;min-width:0;height:100%;overflow:hidden;background:var(--bt)}.bar.film-progress .film-subprogress i{display:block;width:0;height:100%;background:var(--bf)}.skip-reel{position:absolute;z-index:9;right:18px;bottom:88px;padding:8px 13px;border:1px solid #ffffff8c;border-radius:99px;background:#130b06aa;color:#fff;font:600 12px var(--sans)}
.pb .category-cta{grid-column:1/-1}.bg.locked{filter:grayscale(1);opacity:.68}.bg.locked .bi{background:#c7c0b5}.bg.locked .bi img{opacity:.35}.actions{background:linear-gradient(180deg,transparent 0,var(--paper) 72%)}.stage.dark .actions{background:linear-gradient(180deg,transparent 0,#130b06 72%)}.share-options .share-option{width:100%;font:600 13px var(--sans)}.media-dialog form{display:flex;flex-direction:column}
</style>
<style>.bg .bi{overflow:hidden}.bg .bi img{display:block;width:100%;height:100%;object-fit:contain}</style></head>
<body>
<iframe class="diary-backdrop" src="<?php echo $isPublicShared ? '../index.php' : 'traveldiary.php'; ?>" title="<?php echo $isPublicShared ? 'KULTOURA homepage' : 'Travel Diary'; ?>" aria-hidden="true" tabindex="-1"></iframe>
<main class="stage" id="stage">
<a class="popup-close" href="<?php echo $isPublicShared ? '../index.php' : 'traveldiary.php'; ?>" aria-label="Close Travel Wrapped" title="Close Travel Wrapped">&times;</a>
<header class="top"><div class="bars" id="bars"></div><div class="meta"><div class="brand" id="brand"></div></div></header>
<div class="slides" id="slides"></div>
<div class="zone l" id="zl"></div><div class="zone r" id="zr"></div>
<footer class="actions">
<?php if (!$isPublicShared): ?>
<button class="btn btn-share" id="share" type="button" aria-label="Share Travel Wrapped" title="Share"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.6M8.2 13.2l7.6 4.6"/></svg><span>Share</span></button>
<?php endif; ?>
<button class="btn" id="exportVideo" type="button" aria-label="Save this slide or reel" title="Save this slide or reel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg></button>
</footer>
<div class="toast" id="toast"></div>
</main>
<dialog class="media-dialog" id="mediaChooser" aria-labelledby="mediaChooserTitle"><form method="dialog"><div class="dialog-head"><div><h2 id="mediaChooserTitle">Choose photos to include</h2><p>Uncheck any photo or clip you don’t want in the shared recap or reel.</p></div><button class="dialog-close" value="cancel" aria-label="Close photo chooser">&times;</button></div><div class="media-choice-list" id="mediaChoices"></div><div class="dialog-actions"><button value="cancel">Cancel</button><button class="primary" id="mediaContinue" value="default">Continue</button></div></form></dialog>
<dialog class="media-dialog" id="shareSheet" aria-labelledby="shareTitle"><div class="dialog-head"><div><h2 id="shareTitle">Share this Wrapped</h2><p>Choose how to invite someone into your story.</p></div><button class="dialog-close" id="shareClose" aria-label="Close share options">&times;</button></div><div class="share-link-box"><input id="shareUrl" type="url" readonly aria-label="Public Travel Wrapped link"><button class="share-option" id="copyShare" type="button">Copy link</button></div><p class="share-note">Anyone with this link can view the traveler&#39;s Wrapped without signing in.</p><div class="share-options"><button class="share-option" id="nativeShare" type="button">Share via apps</button><a class="share-option" id="messengerShare" target="_blank" rel="noopener">Messenger</a><a class="share-option" id="facebookShare" target="_blank" rel="noopener">Facebook</a></div></dialog>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
/* DATA — replace with JSON from your backend. media[].src / poster / places[].img / liked[].img = real uploads. */
const DATA = <?php echo json_encode($wrappedStoryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const IS_PUBLIC_SHARED = <?php echo $isPublicShared ? 'true' : 'false'; ?>;
const OWNER_RAW=String(DATA.owner||'KULTOURA traveler').trim();
const OWNER=OWNER_RAW===OWNER_RAW.toLowerCase()?OWNER_RAW.replace(/\b\p{L}/gu,c=>c.toUpperCase()):OWNER_RAW;
const OWNER_POSSESSIVE = /s$/i.test(OWNER) ? OWNER + "'" : OWNER + "'s";

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
const ORIGINAL_EDIT_CHAPTERS=EDIT.chapters.map(ch=>({...ch,media:ch.media?{...ch.media}:ch.media}));
if(Array.isArray(C.coverTag)&&C.coverTag[0])C.coverTag[0]=C.coverTag[0].replace(/^.*?(?=20\d{2}\b)/,OWNER_POSSESSIVE+' ');
C.shareText=OWNER_POSSESSIVE+' KULTOURA Wrapped '+CFG.year;
if(IS_PUBLIC_SHARED){
 C.outroSub=[OWNER_POSSESSIVE+' journey through Malvar, in moments.','A story shared with KULTOURA.'];
 EDIT.chapters.forEach(ch=>{if(/your diary/i.test(ch.label||''))ch.label='A diary moment'});
}
const byId=id=>DATA.places.find(p=>p.id===id),cat=p=>DATA.categories[p.category];
const placeLabel=p=>p.shortName||p.short||String(p.name||'').slice(0,20);
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const fmtDate=d=>new Date(d+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric'});
const fmtDur=s=>Math.floor(s/60)+':'+String(s%60).padStart(2,'0'),pad=n=>String(n).padStart(2,'0');
const plural=(n,w,pl)=>n+' '+(n===1?w:pl||w+'s');
const head=(l,t,le='')=>`<div class="head rise" style="--i:0"><div class="eyebrow">${l}</div><h2>${t}</h2>${le?`<p class="lede">${le}</p>`:''}</div>`;
const ALL_MEDIA=[...DATA.media].sort((a,b)=>a.date.localeCompare(b.date));let MEDIA=[...ALL_MEDIA],PHOTOS=MEDIA.filter(m=>m.type==='image'),CLIPS=MEDIA.filter(m=>m.type==='video');
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

const MAP_PLACES=DATA.places.filter(p=>p.hasCoordinates!==false&&Number.isFinite(+p.lat)&&Number.isFinite(+p.lng));
const MAP_POINTS=[...(Array.isArray(DATA.mapOutline)?DATA.mapOutline:[]),...MAP_PLACES.map(p=>[p.lat,p.lng])];
const MB=(()=>{const la=MAP_POINTS.map(p=>Array.isArray(p)?+p[0]:+p.lat),ln=MAP_POINTS.map(p=>Array.isArray(p)?+p[1]:+p.lng);return{a:Math.min(...la),b:Math.max(...la),c:Math.min(...ln),d:Math.max(...ln)}})();
/* Malvar is close to square geographically; keep the outline near-square on the wide map panel. */
const mpos=p=>({x:25+(p.lng-MB.c)/((MB.d-MB.c)||1)*50,y:7+(1-(p.lat-MB.a)/((MB.b-MB.a)||1))*86});
const ROUTE=D.sorted.filter((v,i,a)=>MAP_PLACES.some(p=>p.id===v.placeId)&&a.findIndex(x=>x.placeId===v.placeId)===i).map((v,i)=>({...mpos(byId(v.placeId)),id:v.placeId,date:v.date,name:placeLabel(byId(v.placeId)),number:i+1}));
const MAP_PATH=DATA.mapOutline?.length?DATA.mapOutline.map((point,i)=>{const p=Array.isArray(point)?{lat:+point[0],lng:+point[1]}:point,pos=mpos(p);return`${i?'L':'M'}${pos.x},${pos.y}`}).join(' ')+' Z':'';
const MAP_START=120,MAP_MS=1500,RM=matchMedia('(prefers-reduced-motion: reduce)').matches;
function resetMap(){const box=$('#mapbox');if(!box)return;const pins=box.querySelectorAll('.pin');pins.forEach(p=>p.classList.toggle('on',RM));$('#mdot').style.opacity=0;$('#mlabel').textContent=''}
function tickMap(e){const box=$('#mapbox');if(!box||RM||e<MAP_START||ROUTE.length<2)return;
 const n=ROUTE.length-1,t=Math.min((e-MAP_START)/MAP_MS,1)*n,leg=Math.min(Math.floor(t),n-1),f=t-leg,A=ROUTE[leg],B=ROUTE[leg+1];
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
const FILM_MS=FILM.reduce((s,f)=>s+f.ms,0),placesLine=CH.map(c=>placeLabel(c.p)).join(' \u00b7 ');

const GRP={service:'local',coffee:'food',food:'food',event:'culture',culture:'culture',leisure:'nature'};
const PERS={local:{g:'\uD83E\uDDED',t:'The Local <em>Discoverer</em>',d:`${OWNER} finds Malvar through its everyday places \u2014 campuses, offices and civic spaces \u2014 and makes them part of the story.`},food:{g:'\u2615',t:'The Food <em>Explorer</em>',d:`Coffee runs, bakeries and market stalls shape ${OWNER_POSSESSIVE} days in Malvar.`},culture:{g:'\uD83C\uDFDB',t:'The Culture <em>Enthusiast</em>',d:`Festivals and heritage sites draw ${OWNER} in. ${OWNER} travels for the stories of the town.`},nature:{g:'\uD83C\uDF3F',t:'The Nature <em>Seeker</em>',d:`${OWNER} chases open air, plazas and poolside afternoons.`},memory:{g:'\uD83D\uDCF8',t:'The Memory <em>Collector</em>',d:`${OWNER} captures almost every stop. The gallery tells the rest of the story.`}};
const SC={local:0,food:0,culture:0,nature:0};DATA.visits.forEach(v=>SC[GRP[byId(v.placeId).category]]++);
const PK=(PHOTOS.length+CLIPS.length)/DATA.visits.length>=3?'memory':Object.entries(SC).sort((a,b)=>b[1]-a[1])[0][0];
const SCN={local:'Local services',food:'Food & coffee',culture:'Culture & events',nature:'Leisure & nature'};
const MONTHS=Array(12).fill(0),WK=Array(7).fill(0),MN=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],WN=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
DATA.visits.forEach(v=>{MONTHS[+v.date.slice(5,7)-1]++;WK[new Date(v.date+'T00:00:00').getDay()]++});
const topM=MONTHS.indexOf(Math.max(...MONTHS)),topW=WK.indexOf(Math.max(...WK));
const fp=new Set(DATA.visits.filter(v=>['food','coffee'].includes(byId(v.placeId).category)).map(v=>v.placeId)).size,cv=DATA.visits.filter(v=>['event','culture'].includes(byId(v.placeId).category)).length,uq=new Set(DATA.visits.map(v=>v.placeId)).size;
const BADGES_ALL=[{file:'first_destination.svg',t:'First Destination',d:`${OWNER_POSSESSIVE} first recorded stop: ${placeLabel(D.first)}`,requirement:'Visit a place in Malvar',ok:DATA.visits.length>0},{file:'food_explorer.svg',t:'Food Explorer',d:fp+' food & coffee spots visited',requirement:'Visit 2 food or coffee spots',ok:fp>=2},{file:'culture_enthusiast.svg',t:'Culture Enthusiast',d:cv+' fiesta & heritage visits',requirement:'Visit 2 culture or heritage places',ok:cv>=2},{file:'memory_keeper.svg',t:'Memory Keeper',d:PHOTOS.length+' photos uploaded',requirement:'Add 10 photos to your diary',ok:PHOTOS.length>=10},{file:'malvar_explorer.svg',t:'Malvar Explorer',d:uq+' different places',requirement:'Visit 5 different places',ok:uq>=5},{file:'on_a_roll.svg',t:'On a Roll',d:D.streak+'-day streak',requirement:'Travel on 7 consecutive days',ok:D.streak>=7}];
const BADGES_UNLOCKED=BADGES_ALL.filter(b=>b.ok).length;
/* slides */
const ALL_SLIDES=[
{id:'cover',build:()=>{const[a,b,c]=mix(3);return`<div class="cover"><div class="eyebrow rise">${esc(CFG.brand)} \u00b7 ${esc(CFG.region)}</div><div class="bento rise" style="--i:1">${tile(a,'a')}${tile(b)}${tile(c)}</div><h1 class="rise" style="--i:2"><em>Wrapped</em><br><span>${CFG.year}</span></h1><p class="rise" style="--i:3">${C.coverTag.map(esc).join('<br>')}</p><div class="go rise" style="--i:4">${esc(C.startHint)} \u2192</div></div>`}},
{id:'numbers',build:()=>{const rows=[['#587a4c',DATA.places.length,'Places visited'],['#c07a2c',D.days,'Days traveled'],['#c4455f',PHOTOS.length,'Photos uploaded'],['#d0652d',CLIPS.length,'Clips captured'],['#7a5aa6',DATA.stats.favoritesMarked,'Favorites marked'],['#3f7f7a',D.cats,'Categories explored']];
 const foot=Object.entries(D.counts).map(([k,n])=>`${n} ${DATA.categories[k].label.toLowerCase()}`).join(' \u00b7 ');
 return`${head('By the numbers',`${esc(OWNER_POSSESSIVE)} trip,<br>in figures`)}<div class="lead rise" style="--i:1"><span class="big" data-count="${MEDIA.length}">0</span><div><b>moments captured</b><small>across ${plural(D.days,'day')} and ${plural(DATA.places.length,'place')}</small></div></div>${rows.map(([c,n,l],k)=>`<div class="row rise" style="--i:${k+2};--c:${c}"><i class="dot"></i><span>${l}</span><b data-count="${n}">0</b></div>`).join('')}<p class="foot rise" style="--i:9">${esc(foot)}</p>`}},
{id:'personality',build:()=>{const P=PERS[PK],tot=DATA.visits.length;return`${head(`${esc(OWNER_POSSESSIVE)} travel personality`,'Travel style')}<div class="pg rise" style="--i:1">${P.g}</div><div class="pt rise" style="--i:2">${P.t}</div><p class="lede rise" style="--i:3">${esc(P.d)}</p><div class="pb rise" style="--i:4">${Object.entries(SC).sort((a,b)=>b[1]-a[1]).map(([k,n])=>`<div><span>${SCN[k]}</span><i><u style="width:${n/tot*100}%"></u></i><b>${n}</b>${n===0&&DATA.suggestions?.[k]?`<a class="category-cta" href="${esc(DATA.suggestions[k].url)}">Not yet: try ${esc(DATA.suggestions[k].name)} \u2192</a>`:''}</div>`).join('')}</div>`}},
{id:'habits',build:()=>{const mx=Math.max(...MONTHS);return`${head(`${esc(OWNER_POSSESSIVE)} travel habits`,'Travel rhythm')}<div class="chips rise" style="--i:1"><div class="chip"><small>Top month</small><b>${MN[topM]}</b></div><div class="chip"><small>Top day</small><b>${WN[topW]}</b></div><div class="chip"><small>Top category</small><b>${esc(DATA.categories[D.topCat].label)}</b></div></div><div class="mbars rise" style="--i:2">${MONTHS.map((n,i)=>`<div class="${i===topM?'hi':''}"><i style="height:${n?Math.max(n/mx*90,6):3}px"></i></div>`).join('')}</div><div class="mlab rise" style="--i:2">${MN.map(m=>`<span>${m[0]}</span>`).join('')}</div><p class="foot rise" style="--i:3">${plural(MONTHS[topM],'visit')} in ${MN[topM]}; ${MONTHS.filter(n=>!n).length} quiet months still waiting for a story.</p>`}},
{id:'picks',build:()=>{const rows=[['Favorite place',placeLabel(D.topPlace),D.topPlace],['First place visited',placeLabel(D.first),D.first],['Latest adventure',placeLabel(D.latest),D.latest],['Longest streak',plural(D.streak,'day'),null]];
 return`${head('Top picks',`What defined<br>${esc(OWNER_POSSESSIVE)} year`)}<div class="statement rise" style="--i:1"><small>Most visited category</small><strong>${esc(DATA.categories[D.topCat].label)}</strong><span>${plural(D.topCatN,'visit')} this year</span></div>${rows.map(([k,v,p],n)=>`<div class="pk rise" style="--i:${n+2}"><div class="tx"><div class="k">${k}</div><div class="v" title="${esc(v)}">${esc(v)}</div></div>${p?`<div class="thumb">${pTile(p)}</div>`:'<div class="ico">\uD83D\uDD25</div>'}</div>`).join('')}`}},
{id:'timeline',build:()=>{const rec=[...D.sorted].reverse(),first=rec.at(-1);
 return`${head('Journey timeline','First stop<br>to latest',`A ${D.streak}-day streak from ${fmtDate(first.date)} to ${fmtDate(rec[0].date)}.`)}<div class="timeline">${rec.slice(0,4).map((v,n)=>{const p=byId(v.placeId),m=MEDIA.find(x=>x.placeId===v.placeId&&x.date===v.date),d=new Date(v.date+'T00:00:00');
  return`<div class="tl rise" style="--i:${n+1};--c:${cat(p).color}"><div class="dt"><b>${d.getDate()}</b><small>${d.toLocaleDateString('en-US',{month:'short'})}</small></div><div class="mk"></div><div class="bd"><small>${v.type}</small><div>${esc(p.name)}</div></div>${m?thumb(m):'<span></span>'}</div>`}).join('')}</div><p class="foot rise" style="--i:6">It started ${fmtDate(first.date)} \u2014 with coffee.</p>`}},
{id:'places',build:()=>{const[h,...rest]=D.ranked,more=D.ranked.length-3;
 const card=(p,c='')=>`<div class="pc ${c}">${pTile(p,'fill')}<div class="shade"></div>${c?'<span class="tag">Most visited</span>':''}<div class="tx"><b>${esc(p.name)}</b><small>${plural(D.per[p.id]||0,'visit')} \u00b7 ${mline(p.id)}</small></div></div>`;
 return`${head('Most visited places',`${esc(OWNER_POSSESSIVE)} favorite<br>spots`,`${esc(placeLabel(D.topPlace))} was home base, while ${esc(placeLabel(D.latest))} brewed ${esc(OWNER_POSSESSIVE)} afternoons.`)}<div class="rise" style="--i:1">${card(h,'big')}</div><div class="pc-row rise" style="--i:2">${rest.slice(0,2).map(p=>card(p)).join('')}</div>${more>0?`<p class="more rise" style="--i:3">+ ${plural(more,'more place')} on the map</p>`:''}`}},
{id:'liked',build:()=>`${head('Saved places','Favorites',`${esc(DATA.categories[D.topCat].label)} topped the list, with ${plural(D.topCatN,'helpful visit')}.`)}${DATA.liked.slice(0,4).map((l,n)=>`<div class="lk rise" style="--i:${n+1}"><span class="n">${pad(n+1)}</span>${pTile(l)}<span class="nm">${esc(l.name)}</span><span class="h">\u2665</span></div>`).join('')}`},
{id:'map',onEnter:()=>resetMap(),onTick:e=>tickMap(e),build:()=>{const more=Math.max(0,MAP_PLACES.length-5),path=ROUTE.map((p,i)=>`${i?'L':'M'}${p.x},${p.y}`).join(' ');
 return`${head('Travel map','Malvar<br>in places')}<div class="map rise" style="--i:1" id="mapbox"><svg class="route" viewBox="0 0 100 100" preserveAspectRatio="none">${MAP_PATH?`<path class="outline" d="${MAP_PATH}"/>`:''}${path?`<path class="ordered-path" d="${path}"/>`:''}</svg><span class="map-place-label">Malvar</span><span class="map-north"><b>↑</b>N</span>${MAP_PLACES.map((p,n)=>{const q=mpos(p);return`<span class="pin on" data-id="${p.id}" style="--c:${cat(p).color};left:${q.x}%;top:${q.y}%">${n+1}</span>`}).join('')}<i class="dot" id="mdot"></i><span class="mlabel" id="mlabel"></span></div><div style="margin-top:6px">${MAP_PLACES.slice(0,5).map((p,n)=>`<div class="mrow rise" style="--i:${n+2};--c:${cat(p).color}"><span class="d">${n+1}</span><div>${esc(placeLabel(p))}<small>${esc(cat(p).label)} \u00b7 ${mline(p.id)}</small></div></div>`).join('')}</div>${more?`<p class="more rise" style="--i:8">+ ${more} more on the map</p>`:''}`}},
{id:'badges',build:()=>`${head(`${esc(OWNER_POSSESSIVE)} achievements`,'Earned badges',`${BADGES_UNLOCKED} of ${BADGES_ALL.length} unlocked from ${esc(OWNER_POSSESSIVE)} Malvar travels.`)}<div class="bgs">${BADGES_ALL.map((b,n)=>`<div class="bg rise ${b.ok?'':'locked'}" style="--i:${n+1}"><div class="bi"><img src="../assets/images/wrapped-badges/${b.file}" alt=""></div><b>${b.t}</b><small>${esc(b.ok?b.d:b.requirement||b.d)}</small>${b.ok?`<a class="badge-download" href="../assets/images/wrapped-badges/${encodeURIComponent(b.file)}" download="${encodeURIComponent(b.file)}">Save badge</a>`:''}</div>`).join('')}</div>`},
{id:'cutIntro',dark:1,build:()=>`${head(esc(EDIT.title),`${plural(CH.length,'place')}.<br>One edit.`,`${esc(OWNER_POSSESSIVE)} clips from ${esc(placesLine)}, cut together with titles and transitions.`)}<div class="chaps ${CH.length>6?'many':''}">${CH.map((c,n)=>`<div class="chap rise" style="--i:${n+1}"><span class="n">${pad(n+1)}</span><div><strong>${esc(CH.length>6?placeLabel(c.p):c.p.name)}</strong><span>${esc(c.label)} \u00b7 ${fmtDate(c.m.date)}</span></div>${thumb(c.m)}</div>`).join('')}</div><div class="specs rise" style="--i:7"><span>Runtime ${fmtDur(Math.round(FILM_MS/1000))}</span>${SPEED>1.05?`<span>${SPEED.toFixed(1)}\u00d7 speed</span>`:''}<span>${PHOTOS.length} photos included</span><span>${EDIT.maxSeconds}s max</span><span>Warm grade</span><span>Titles</span></div>`},
{id:'film',dark:1,bleed:1,dur:()=>FILM_MS,onEnter:()=>{filmIdx=-1;document.querySelectorAll('.cut-item').forEach(i=>i.classList.remove('on','prev'));if(RM)document.querySelector('.cut-item')?.classList.add('on')},onTick:e=>tickFilm(e),
 build:()=>`<div class="cut" id="cut"><button class="skip-reel" id="skipReel" type="button">Skip reel \u2192</button>${FILM.map(f=>{
  if(f.k==='title')return`<div class="cut-item cut-title"><div><span class="eyebrow">${esc(CFG.brand)} presents</span><h2>${esc(EDIT.title)}</h2><p>${esc(CFG.region)} \u00b7 ${CFG.year}</p></div></div>`;
  if(f.k==='end')return`<div class="cut-item cut-title fin"><div><span class="eyebrow">Fin</span><h2>${esc(CFG.brand)}</h2><p>${esc(placesLine)}</p></div></div>`;
  const{c,i}=f;return`<div class="cut-item" style="--d:${f.ms}ms">${tile(f.m,'fill graded')}<div class="lt ${f.k==='photo'?'st':''}"><span>${esc(placeLabel(f.p))}</span><small>${fmtDate(f.m.date)}</small></div></div>`}).join('')}<div class="cut-fx"></div></div>`},
{id:'gallery',build:()=>{const it=mix(6);return`${head('Photos &amp; clips','The rest of<br>the story',`${plural(PHOTOS.length,'photo')} and ${plural(CLIPS.length,'clip')} from every stop.`)}<div class="mosaic rise" style="--i:1">${it.map((m,i)=>tile(m,'t'+(i+1))).join('')}</div>${MEDIA.length>it.length?`<p class="more rise" style="--i:2">+ ${MEDIA.length-it.length} more in the gallery</p>`:''}`}},
{id:'outro',build:()=>`<div class="outro"><div class="eyebrow rise">${esc(CFG.brand)} \u00b7 ${CFG.year}</div><h2 class="rise" style="--i:1">${esc(C.outroTitle[0])}<br><em>${esc(C.outroTitle[1])}</em></h2><p class="lede rise" style="--i:2">${C.outroSub.map(esc).join(' ')}</p><div class="quote rise" style="--i:3">${C.quote.map(q=>`<div>${esc(q)}</div>`).join('')}</div><div class="sig rise" style="--i:5">${esc(CFG.brand.toUpperCase())}<small>${esc(CFG.region.toUpperCase())}</small></div><a class="visit-cta rise" style="--i:6" href="../pages/tourism.php"><span>Keep discovering Malvar<strong>Explore places you haven\u2019t visited</strong></span><i aria-hidden="true">\u2197</i></a><button class="replay rise" style="--i:7" id="saveRecapCard">Save my recap card</button><button class="replay rise" style="--i:8" id="replay">\u21BB Watch again</button></div>`}
];
const SLIDES=ALL_SLIDES.filter(s=>{if(s.id==='habits')return DATA.visits.length>=5;if(s.id==='picks')return DATA.places.length>=3;if(s.id==='places')return DATA.places.length>=4;if(s.id==='liked')return DATA.liked.length>=3;if(s.id==='map')return MAP_PLACES.length>=3;if(s.id==='gallery')return ALL_MEDIA.length>=4;return true});

/* engine */
const stage=$('#stage'),total=SLIDES.length,REDUCED=RM;let idx=0,elapsed=0,lastTs=0,hold=false,manual=false,filmIdx=-1,entryUntil=0;
$('#brand').textContent=CFG.brand;
$('#slides').innerHTML=SLIDES.map((s,i)=>`<section class="slide ${s.bleed?'bleed':''} ${s.dark?'dark':''}" aria-label="Slide ${i+1} of ${total}">${s.build()}</section>`).join('');
$('#bars').innerHTML=SLIDES.map(s=>`<div class="bar ${s.id==='film'?'film-progress':''}"><i></i>${s.id==='film'?`<span class="film-subprogress" aria-label="Reel progress">${FILM.map(()=>'<span><i></i></span>').join('')}</span>`:''}</div>`).join('');
const els=[...$('#slides').children],fills=[...$('#bars').children].map(b=>b.firstChild),durOf=i=>SLIDES[i].dur?SLIDES[i].dur():CFG.slideMs;
function tickFilm(e){let acc=0,k=0;for(;k<FILM.length;k++){if(e<acc+FILM[k].ms)break;acc+=FILM[k].ms}k=Math.min(k,FILM.length-1);
 const root=$('#cut');if(!root)return;const f=FILM[k],ch=f.k==='clip'?f.i:f.k==='end'?CH.length:-1;
 if(k!==filmIdx){filmIdx=k;root.querySelectorAll('.cut-item').forEach((it,i)=>{it.classList.toggle('on',i===k);it.classList.toggle('prev',i===k-1)});root.querySelectorAll('.rail>div').forEach((r,i)=>r.classList.toggle('act',i===ch));root.querySelectorAll('video').forEach(v=>v.playbackRate=SPEED)}
 const l=Math.min((e-acc)/f.ms,1);$('#bars .film-subprogress')?.querySelectorAll('i').forEach((b,i)=>b.style.width=i<k?'100%':i===k?l*100+'%':'0%')}
function counters(el){el.querySelectorAll('[data-count]').forEach(n=>{const end=+n.dataset.count;if(REDUCED){n.textContent=end;return}const t0=performance.now();(function t(now){const p=Math.min((now-t0)/900,1);n.textContent=Math.round(end*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(t)})(t0)})}
function go(n){idx=Math.max(0,Math.min(total-1,n));elapsed=0;const s=SLIDES[idx];
 els.forEach((el,i)=>{el.classList.toggle('active',i===idx);el.classList.toggle('before',i<idx)});fills.forEach((b,i)=>b.style.width=i<idx?'100%':'0%');
 stage.classList.toggle('dark',!!s.dark);updateExportButton();counters(els[idx]);entryUntil=performance.now()+Math.max(0,...[...els[idx].querySelectorAll('.rise')].map(el=>{const c=getComputedStyle(el);return(parseFloat(c.animationDelay)||0)*1000+(parseFloat(c.animationDuration)||0)*1000}));if(s.onEnter)s.onEnter()}
function loop(ts){const dt=Math.min(ts-(lastTs||ts),100);lastTs=ts;
 if(!hold&&!manual&&!document.hidden&&!mediaChooser?.open&&!shareSheet?.open&&!REDUCED){elapsed+=dt;const d=durOf(idx);fills[idx].style.width=Math.min(elapsed/d,1)*100+'%';if(SLIDES[idx].onTick)SLIDES[idx].onTick(elapsed);if(elapsed>=d&&performance.now()>=entryUntil&&idx<total-1)go(idx+1)}
 requestAnimationFrame(loop)}
const toast=m=>{const t=$('#toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),2400)};
async function exportImage(){
 const button=$('#exportVideo'),slide=els[idx];if(typeof window.html2canvas!=='function'){toast('Image export could not load. Check your connection and try again.');return}
 button.disabled=true;manual=true;stage.classList.add('exporting');
 try{await document.fonts?.ready;const canvas=await html2canvas(slide,{scale:2,backgroundColor:getComputedStyle(stage).backgroundColor,useCORS:true,logging:false});const blob=await new Promise((resolve,reject)=>canvas.toBlob(file=>file?resolve(file):reject(new Error('Image export failed')),'image/png'));const url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download=`kultoura-wrapped-${CFG.year}-${SLIDES[idx].id}.png`;link.click();setTimeout(()=>URL.revokeObjectURL(url),60000);toast('Slide saved as an image')}
 catch(err){toast('Could not export this slide as an image. Please try again.')}
 finally{button.disabled=false;manual=false;stage.classList.remove('exporting')}
}
function exportFilm(){
 const button=$('#exportVideo');if(!window.MediaRecorder||!HTMLCanvasElement.prototype.captureStream){toast('Video export is not supported in this browser');return}
 button.disabled=true;manual=true;
 const canvas=document.createElement('canvas');canvas.width=1080;canvas.height=1920;const ctx=canvas.getContext('2d');
 const frames=FILM.filter(f=>!f.m||selectedMediaIds.has(String(f.m.id))),filmDuration=frames.reduce((sum,f)=>sum+f.ms,0);const assets=frames.map(f=>{if(!f.m)return null;if(f.m.type==='video'){const v=document.createElement('video');v.muted=true;v.playsInline=true;v.preload='auto';v.crossOrigin='anonymous';v.src=f.m.src||'';return v}const im=new Image();im.crossOrigin='anonymous';im.src=f.m.src||f.m.poster||'';return im}),posters=frames.map(f=>{if(!f.m||f.m.type!=='video'||!f.m.poster)return null;const im=new Image();im.crossOrigin='anonymous';im.src=f.m.poster;return im});
 const mime=['video/mp4','video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm'].find(t=>MediaRecorder.isTypeSupported(t));if(!mime){button.disabled=false;manual=false;toast('This browser cannot create a downloadable video');return}
 const fit=(source)=>{if(!source||!source.videoWidth&&!source.naturalWidth)return;const w=source.videoWidth||source.naturalWidth,h=source.videoHeight||source.naturalHeight,s=Math.min(canvas.width/w,canvas.height/h),dw=w*s,dh=h*s;ctx.drawImage(source,(canvas.width-dw)/2,(canvas.height-dh)/2,dw,dh)};
 const card=(title,sub)=>{const g=ctx.createLinearGradient(0,0,canvas.width,canvas.height);g.addColorStop(0,'#3b2414');g.addColorStop(1,'#130b06');ctx.fillStyle=g;ctx.fillRect(0,0,canvas.width,canvas.height);ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillStyle='#f6ebdd';const max=canvas.width-120;let size=150;ctx.font=size+'px Barabara';while(ctx.measureText(title.toUpperCase()).width>max&&size>32)ctx.font=(--size)+'px Barabara';ctx.fillText(title.toUpperCase(),canvas.width/2,canvas.height/2,max);ctx.fillStyle='#f6ebddb0';ctx.font='600 30px Inter';ctx.fillText(sub.toUpperCase(),canvas.width/2,canvas.height/2+120,max)};
 const draw=(k,offset)=>{const f=frames[k],a=assets[k];ctx.fillStyle='#0f0a06';ctx.fillRect(0,0,canvas.width,canvas.height);
  if(f.k==='title'){card(EDIT.title,CFG.region+' · '+CFG.year);return}if(f.k==='end'){card(CFG.brand,placesLine);return}
  if(f.k==='clip'&&a instanceof HTMLVideoElement){if(a.readyState>=2)fit(a);else fit(posters[k])}else fit(a);
  const grad=ctx.createLinearGradient(0,canvas.height*.65,0,canvas.height);grad.addColorStop(0,'#0000');grad.addColorStop(1,'#000c');ctx.fillStyle=grad;ctx.fillRect(0,canvas.height*.62,canvas.width,canvas.height*.38);ctx.textAlign='center';ctx.fillStyle='#fff';ctx.textBaseline='bottom';const words=placeLabel(f.p).toUpperCase().split(/\s+/);let size=64,lines=[];while(size>=20){ctx.font=size+'px Barabara';lines=[];let line='';words.forEach(word=>{const next=line?line+' '+word:word;if(ctx.measureText(next).width>canvas.width-100&&line){lines.push(line);line=word}else line=next});if(line)lines.push(line);if(lines.length*size*.95<=canvas.height*.22)break;size-=2}ctx.font=size+'px Barabara';lines.forEach((line,i)=>ctx.fillText(line,canvas.width/2,canvas.height-155-(lines.length-1-i)*size*.95,canvas.width-100));ctx.font='600 30px Inter';ctx.fillStyle='rgba(255,255,255,.9)';ctx.fillText(fmtDate(f.m.date).toUpperCase(),canvas.width/2,canvas.height-85);
  if(f.k==='clip'&&a instanceof HTMLVideoElement&&a.readyState>=2){a.playbackRate=SPEED;if(a.paused)a.play().catch(()=>{})}else if(a instanceof HTMLVideoElement)a.pause();
 };
 ctx.fillStyle='#130b06';ctx.fillRect(0,0,canvas.width,canvas.height);const waitAsset=a=>new Promise(resolve=>{if(!a||a instanceof HTMLImageElement&&a.complete||a instanceof HTMLVideoElement&&a.readyState>=2){resolve();return}const done=()=>resolve();a.addEventListener('load',done,{once:true});a.addEventListener('loadeddata',done,{once:true});a.addEventListener('error',done,{once:true});setTimeout(done,5000)});
 Promise.all([...assets,...posters].map(waitAsset)).then(()=>document.fonts?.ready).then(()=>{
  const stream=canvas.captureStream(30),rec=new MediaRecorder(stream,{mimeType:mime,videoBitsPerSecond:5000000}),chunks=[];rec.ondataavailable=e=>{if(e.data.size)chunks.push(e.data)};rec.onstop=()=>{stream.getTracks().forEach(t=>t.stop());const blob=new Blob(chunks,{type:mime}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='kultoura-wrapped-'+CFG.year+(mime.includes('mp4')?'.mp4':'.webm');a.click();setTimeout(()=>URL.revokeObjectURL(url),60000);button.disabled=false;manual=false;toast('Video downloaded')};
  rec.start(250);const start=performance.now();let last=-1;function frame(now){const t=now-start;if(t>=filmDuration){draw(frames.length-1,frames[frames.length-1].ms);rec.stop();return}let acc=0,k=0;for(;k<frames.length-1&&t>=acc+frames[k].ms;k++)acc+=frames[k].ms;draw(k,t-acc);if(k!==last){last=k;if(assets[k] instanceof HTMLVideoElement){assets[k].currentTime=0;assets[k].play().catch(()=>{})}}requestAnimationFrame(frame)}requestAnimationFrame(frame);toast('Recording Wrapped video…');
 }).catch(()=>{button.disabled=false;manual=false;toast('Could not export this video. Please try again.')});
}
function updateExportButton(){const button=$('#exportVideo');button.title=SLIDES[idx]?.id==='film'?'Save Malvar in Moments video':'Save this slide as an image';button.setAttribute('aria-label',button.title)}
const mediaChooser=$('#mediaChooser'),shareSheet=$('#shareSheet');let afterMediaChoice=null,selectedMediaIds=new Set(ALL_MEDIA.map(m=>String(m.id)));
function requestMediaChoice(action){afterMediaChoice=action;$('#mediaChoices').innerHTML=ALL_MEDIA.length?ALL_MEDIA.map((m,n)=>{const p=byId(m.placeId),src=m.type==='video'?(m.poster||p.img):(m.src||p.img);return`<label class="media-choice"><input type="checkbox" value="${esc(m.id)}" ${selectedMediaIds.has(String(m.id))?'checked':''}><img src="${esc(src)}" alt=""><span>${esc(placeLabel(p))}<br><small>${m.type==='video'?'Clip':'Photo'} ${n+1}</small></span></label>`}).join(''):'<p>No photos or clips are attached to this recap.</p>';mediaChooser.showModal()}
function applyMediaSelection(){selectedMediaIds=new Set([...$('#mediaChoices').querySelectorAll('input:checked')].map(x=>x.value));MEDIA=ALL_MEDIA.filter(m=>selectedMediaIds.has(String(m.id)));PHOTOS=MEDIA.filter(m=>m.type==='image');CLIPS=MEDIA.filter(m=>m.type==='video');DATA.media=MEDIA;EDIT.chapters=ORIGINAL_EDIT_CHAPTERS.map(ch=>{const source=ch.media||ALL_MEDIA.find(m=>String(m.id)===String(ch.clipId));if(!source||selectedMediaIds.has(String(source.id)))return ch;return{...ch,clipId:'scene-'+ch.placeId,media:{id:'scene-'+ch.placeId,type:'video',placeId:ch.placeId,date:source.date,src:null,poster:byId(ch.placeId).img,duration:5}}})}
mediaChooser.addEventListener('close',()=>{if(mediaChooser.returnValue!=='default'){afterMediaChoice=null;return}applyMediaSelection();const action=afterMediaChoice;afterMediaChoice=null;if(action)action()});
async function getPublicShareUrl(){if(IS_PUBLIC_SHARED)return location.href;const response=await fetch('travelwrapped_share.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({story:DATA})});const result=await response.json();if(!response.ok||!result.url)throw new Error(result.error||'Could not create a share link.');return result.url}
async function prepareShare(){try{const url=await getPublicShareUrl();$('#shareUrl').value=url;$('#messengerShare').href='https://www.facebook.com/dialog/send?link='+encodeURIComponent(url);$('#facebookShare').href='https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(url);$('#nativeShare').dataset.url=url;$('#copyShare').dataset.url=url;shareSheet.showModal()}catch(error){toast(error.message||'Could not create a share link.')}}
if($('#share'))$('#share').onclick=e=>{e.stopPropagation();prepareShare()};
$('#nativeShare').onclick=async()=>{const url=$('#nativeShare').dataset.url;if(!url)return;try{if(navigator.share)await navigator.share({title:CFG.brand+' Wrapped '+CFG.year,text:C.shareText,url});else await navigator.clipboard.writeText(url);if(!navigator.share)toast('Public link copied')}catch(error){if(error.name!=='AbortError')toast('Sharing was unavailable. Choose another option below.')}};
$('#copyShare').onclick=async()=>{const url=$('#copyShare').dataset.url;try{await navigator.clipboard.writeText(url);toast('Public link copied');shareSheet.close()}catch{prompt('Copy your public KULTOURA Wrapped link',url)}};
$('#shareClose').onclick=()=>shareSheet.close();
$('#exportVideo').onclick=e=>{e.stopPropagation();requestMediaChoice(()=>{if(SLIDES[idx]?.id==='film')exportFilm();else{els[idx].innerHTML=SLIDES[idx].build();exportImage()}})};
document.addEventListener('click',e=>{if(e.target.closest('#replay'))go(0);if(e.target.closest('#saveRecapCard')){e.preventDefault();requestMediaChoice(()=>{go(0);setTimeout(()=>{els[0].innerHTML=SLIDES[0].build();exportImage()},650)})}if(e.target.closest('#skipReel')){e.preventDefault();go(idx+1)}});
let downAt=0,sx=0,sy=0;
stage.addEventListener('pointerdown',e=>{if(e.target.closest('button'))return;downAt=Date.now();sx=e.clientX;sy=e.clientY;hold=true});
['pointerup','pointercancel','pointerleave'].forEach(ev=>stage.addEventListener(ev,()=>hold=false));
stage.addEventListener('click',e=>{if(e.target.closest('button,a')||Date.now()-downAt>=250)return;const r=stage.getBoundingClientRect();go(e.clientX<r.left+r.width*.3?idx-1:idx+1)});
stage.addEventListener('pointerup',e=>{const dx=e.clientX-sx,dy=e.clientY-sy;if(Math.abs(dx)>55&&Math.abs(dx)>Math.abs(dy)*1.4)go(idx+(dx<0?1:-1))});
document.addEventListener('keydown',e=>{if(e.key==='ArrowRight')go(idx+1);else if(e.key==='ArrowLeft')go(idx-1);else if(e.key===' '){e.preventDefault();manual=!manual}});
go(0);try{if(!localStorage.getItem('kultouraWrappedNavHint')){localStorage.setItem('kultouraWrappedNavHint','1');setTimeout(()=>toast('Tap left or right to move \u00b7 hold to pause'),500)}}catch{}requestAnimationFrame(loop);
</script></body></html>
