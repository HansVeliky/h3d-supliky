<?php
declare(strict_types=1);

if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

/**
 * The page a visitor gets while the portal is switched off for work.
 *
 * It shows the thing the site is actually for: boxes shuffling around a
 * drawer grid, one cell at a time, the way the generator arranges them. That
 * says "somebody is rearranging things in there" better than a sentence
 * does, and it costs nothing - no images, no fonts, no scripts from
 * anywhere. Everything is inline because this file has to work while the
 * rest of the application is deliberately unavailable.
 *
 * Kept in lib/ (denied to the web server) and included from the maintenance
 * gate in bootstrap.php, so it can never be reached on its own and mistaken
 * for a real outage.
 */

$cs = !class_exists('Lang') || Lang::current() === 'cs';

$title = $cs ? 'Probíhá údržba' : 'Maintenance in progress';
$lead  = $cs
    ? 'Snažíme se všechno <strong>urovnat</strong>, aby to <strong>ladilo</strong>.'
    : 'We are <strong>tidying things up</strong> so everything <strong>lines up</strong>.';
$sub   = $cs
    ? 'Díky za trpělivost. Za chvíli bude vše zase na svém místě.'
    : 'Thanks for your patience. Everything will be back in place shortly.';
$badge = $cs ? 'ÚDRŽBA' : 'MAINTENANCE';
$site  = class_exists('Settings') ? (string) Settings::get('site_name') : 'H3D';
?><!doctype html>
<html lang="<?= $cs ? 'cs' : 'en' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
:root{--grid:#d9dfe7;--gap:6px}
*{box-sizing:border-box}
body{
  margin:0;min-height:100vh;display:grid;place-items:center;
  background:linear-gradient(145deg,#f7f9fb,#e8edf3);color:#3d4655;
  font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
}
.scene{
  width:min(820px,94vw);aspect-ratio:1.28;position:relative;overflow:hidden;
  border-radius:22px;
  background:linear-gradient(var(--grid) 1px,transparent 1px),
             linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:56px 56px;box-shadow:0 25px 70px #1e293b22;
}
.board{
  position:absolute;left:10%;top:9%;width:80%;height:78%;
  display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:repeat(3,1fr);
  gap:var(--gap);
}
.cell{border:1px solid #d2d9e2;border-radius:9px;background:#f4f6f9aa}
.box{
  position:absolute;
  width:calc((80% - 3 * var(--gap))/4);
  height:calc((78% - 2 * var(--gap))/3);
  border:7px solid color-mix(in srgb,var(--c) 63%,#263044);
  background:linear-gradient(145deg,color-mix(in srgb,var(--c) 100%,white 7%),var(--c));
  border-radius:3px;display:grid;place-items:center;color:#fff;text-align:center;
  font-weight:800;z-index:2;
  box-shadow:0 7px 14px #26304422,inset 0 0 0 1px #ffffff38;
  will-change:left,top;
  transition:left .72s cubic-bezier(.2,.85,.22,1),top .72s cubic-bezier(.2,.85,.22,1),
             box-shadow .72s ease;
}
.box.moving{z-index:5;box-shadow:0 12px 20px #2630442b,inset 0 0 0 1px #ffffff55}
.box span{font-size:clamp(18px,3vw,31px);line-height:1}
.box small{display:block;margin-top:4px;font-size:clamp(10px,1.25vw,14px);font-weight:650}
.copy{position:absolute;left:28px;bottom:18px;max-width:70%}
.copy h1{margin:0;font-size:clamp(19px,2.4vw,28px);font-weight:800;letter-spacing:-.02em}
.copy p{margin:4px 0 0;font-size:clamp(12px,1.45vw,16px);color:#657083}
.copy p strong{color:#6579c8}
.copy .sub{margin-top:3px;font-size:clamp(10px,1.15vw,13px);color:#8993a2}
.badge{
  position:absolute;right:28px;bottom:17px;padding:8px 12px;border:1px solid #cfd6df;
  border-radius:999px;background:#ffffffaa;color:#657083;font-size:11px;
  letter-spacing:.12em;font-weight:750;
}
.pulse{position:absolute;width:18px;height:18px;border-radius:50%;border:2px solid #91d5c7;opacity:0;pointer-events:none}
.pulse.active{animation:pulse .72s ease-out}
@keyframes pulse{0%{opacity:.8;transform:scale(.3)}100%{opacity:0;transform:scale(2.5)}}
/* Somebody who asked their system to stop animations gets a still drawer. */
@media(prefers-reduced-motion:reduce){.box{transition:none!important}}
@media(max-width:560px){.copy{left:18px;bottom:14px;max-width:100%}.badge{display:none}}
</style>
</head>
<body>
<div class="scene" id="scene">
  <div class="board" id="board"></div>
  <div class="copy">
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= $lead ?></p>
    <p class="sub"><?= htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <div class="badge"><?= htmlspecialchars($site . ' • ' . $badge, ENT_QUOTES, 'UTF-8') ?></div>
</div>
<script>
(function(){
  var scene=document.getElementById('scene'), board=document.getElementById('board');
  var cols=4, rows=3, gap=6;
  for(var i=0;i<cols*rows;i++){
    var cell=document.createElement('div');
    cell.className='cell';
    board.appendChild(cell);
  }
  var colors=['#a96fca','#7c72c7','#72c7b8','#c8759d','#a9ca72','#73c77d','#718bc7','#bd70bd'];
  var boxes=[];
  for(var j=0;j<8;j++){
    var el=document.createElement('div');
    el.className='box';
    el.style.setProperty('--c',colors[j]);
    el.innerHTML='<div><span>2 &times; 2</span><small>83.07 &times; 83.07 mm</small></div>';
    scene.appendChild(el);
    boxes.push(el);
  }
  var empty=[3,5,6,11];
  var at=new Map();
  [0,1,2,4,7,8,9,10].forEach(function(cell,i){ at.set(cell,i); });

  function pos(cell){
    var c=cell%cols, r=Math.floor(cell/cols);
    return {left:board.offsetLeft+c*(board.clientWidth+gap)/cols,
            top:board.offsetTop+r*(board.clientHeight+gap)/rows};
  }
  function place(el,cell,instant){
    var p=pos(cell);
    if(instant) el.style.transition='none';
    el.style.left=p.left+'px';
    el.style.top=p.top+'px';
    if(instant) requestAnimationFrame(function(){ el.style.transition=''; });
  }
  at.forEach(function(i,cell){ place(boxes[i],cell,true); });

  function neighbours(cell){
    var c=cell%cols, r=Math.floor(cell/cols), a=[];
    if(c>0)a.push(cell-1);
    if(c<cols-1)a.push(cell+1);
    if(r>0)a.push(cell-cols);
    if(r<rows-1)a.push(cell+cols);
    return a;
  }
  function pulseAt(cell){
    var p=pos(cell), q=document.createElement('div');
    q.className='pulse active';
    q.style.left=(p.left+((board.clientWidth+gap)/cols)/2-9)+'px';
    q.style.top=(p.top+((board.clientHeight+gap)/rows)/2-9)+'px';
    scene.appendChild(q);
    setTimeout(function(){ q.remove(); },750);
  }
  function shuffle(){
    var candidates=[];
    empty.forEach(function(e){
      neighbours(e).forEach(function(n){ if(at.has(n)) candidates.push([n,e]); });
    });
    if(!candidates.length) return;
    var pick=candidates[Math.floor(Math.random()*candidates.length)];
    var from=pick[0], to=pick[1], idx=at.get(from), el=boxes[idx];
    at.delete(from); at.set(to,idx);
    empty=empty.filter(function(x){ return x!==to; });
    empty.push(from);
    el.classList.add('moving');
    pulseAt(to);
    place(el,to,false);
    setTimeout(function(){ el.classList.remove('moving'); },760);
  }
  // A tab in the background should not keep painting; the timer is restarted
  // when the page comes back.
  var timer=null;
  function start(){ stop(); timer=setInterval(shuffle,1050); }
  function stop(){ if(timer){ clearInterval(timer); timer=null; } }
  document.addEventListener('visibilitychange',function(){ document.hidden?stop():start(); });
  window.addEventListener('resize',function(){ at.forEach(function(i,cell){ place(boxes[i],cell,true); }); });
  start();
})();
</script>
</body>
</html>
