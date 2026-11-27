/* ---------- Theme / menu / tabs / sidebar ---------- */
function tg(){var r=document.documentElement,d=r.dataset.theme||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');d=d=='dark'?'light':'dark';r.dataset.theme=d;localStorage.setItem('th',d)}
function mn(){var m=document.getElementById('mn');if(m)m.classList.toggle('open')}
function tab(n){var a=document.getElementById('f1'),b=document.getElementById('f2');if(!a||!b)return;a.style.display=n==1?'':'none';b.style.display=n==2?'':'none';document.getElementById('b1').className=n==1?'on':'';document.getElementById('b2').className=n==2?'on':''}
function sd(){document.body.classList.toggle('sdopen')}

/* ---------- Global double-submit lock for normal forms ----------
   After a form is submitted, its submit buttons are hidden/disabled so the
   same form cannot be sent twice (add question, create test, send doubt ...).
   Add data-nolock="1" on a form to opt out. */
(function(){
document.addEventListener('submit',function(ev){
  var f=ev.target;
  if(!f||ev.defaultPrevented||(f.dataset&&f.dataset.nolock))return;      // confirm() said "cancel"
  if(f.dataset.sent==='1'){ev.preventDefault();return}                    // second click -> ignore
  f.dataset.sent='1';
  setTimeout(function(){
    f.querySelectorAll('button:not([type=button]):not([type=reset]),input[type=submit]').forEach(function(b){
      b.disabled=true;b.style.opacity='.5';b.style.cursor='not-allowed'
    })
  },0)
},false);
/* coming back with the Back button: unlock forms again */
window.addEventListener('pageshow',function(ev){
  if(!ev.persisted)return;
  document.querySelectorAll('form[data-sent]').forEach(function(f){
    delete f.dataset.sent;
    f.querySelectorAll('button,input[type=submit]').forEach(function(b){b.disabled=false;b.style.opacity='';b.style.cursor=''})
  })
})
})();

/* ---------- Syllabus cascade (exam → subject → topic) ---------- */
(function(){var S=window.SYL;if(!S)return;
function fill(el,arr,any){el.innerHTML='';if(any){var o=document.createElement('option');o.value='';o.textContent='Any';el.appendChild(o)}arr.forEach(function(k){var o=document.createElement('option');o.textContent=k;el.appendChild(o)})}
document.querySelectorAll('.syE').forEach(function(E){
  var f=E.form,B=f.querySelector('.syS'),T=f.querySelector('.syT'),any=!!E.dataset.opt;
  function subs(){return Object.keys((S[E.value]||{}).subjects||{})}
  function tops(){return (((S[E.value]||{}).subjects||{})[B.value])||[]}
  function fT(){if(T)fill(T,tops(),any)}
  function fB(){fill(B,subs(),any);fT()}
  fill(E,Object.keys(S),false);E.onchange=fB;B.onchange=fT;fB()
})})();

/* ===== CBT exam runner ===== */
(function(){
var T=window.TEST;if(!T)return;
var A=document.getElementById('t'),Q=T.qs,N=Q.length,S=[],SI={};
Q.forEach(function(q,i){if(!(q.s in SI)){SI[q.s]=S.length;S.push({n:q.s,ix:[]})}S[SI[q.s]].ix.push(i)});
var st=Q.map(function(){return{a:null,v:0,m:0}}),cur=0,end=0,t0=0,done=false,sending=false,iv;
var isTT=!!T.test_id;   /* teacher test: server saves only ONE attempt, so a retry is safe */

function e(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function cl(i){var s=st[i];return !s.v?'nv':(s.m?(s.a!==null?'am':'mk'):(s.a!==null?'an':'na'))}
function cnt(ix){var c={an:0,na:0,nv:0,mk:0,am:0};ix.forEach(function(i){c[cl(i)]++});return c}
function all(){var a=[];for(var i=0;i<N;i++)a.push(i);return a}
function hms(s){s=Math.max(0,s);return ('0'+Math.floor(s/3600)).slice(-2)+':'+('0'+Math.floor(s/60)%60).slice(-2)+':'+('0'+s%60).slice(-2)}
function legend(){return '<span><i class="nv sq"></i>Not visited</span><span><i class="na sq"></i>Not answered</span><span><i class="an sq"></i>Answered</span><span><i class="mk sq"></i>Marked for review</span><span><i class="am sq"></i>Answered &amp; marked</span>'}

/* hide every button on the exam screen so nothing can be clicked again */
function hideButtons(){
  A.querySelectorAll('button').forEach(function(b){b.disabled=true;b.style.display='none'});
  document.querySelectorAll('.modal').forEach(function(m){m.remove()})
}

function intro(){
  A.innerHTML='<div class="narrow" style="padding:20px"><div class="card"><h2>'+e(T.title)+'</h2><div class="inl"><span class="pill">'+N+' questions</span><span class="pill">'+T.min+' minutes</span><span class="pill">+'+T.marks+' / -'+T.neg+' marking</span></div><h3 style="margin-top:18px">Instructions</h3><ul class="mu"><li>The countdown timer at the top right shows the time left. The test submits automatically when it reaches zero.</li><li>Use <b>Save &amp; Next</b> to save an answer, <b>Mark for Review &amp; Next</b> to flag a question and <b>Clear Response</b> to remove an answer.</li><li>Each correct answer gives +'+T.marks+' mark(s); each wrong answer deducts '+T.neg+'. Unanswered questions score 0.</li><li>Do not refresh or close this page during the test.</li>'+(isTT?'<li><b>You can submit this test only once.</b></li>':'')+'</ul><div class="leg" style="max-width:440px">'+legend()+'</div><h3>Sections</h3>'+S.map(function(s){return '<div>'+e(s.n)+' &ndash; '+s.ix.length+' questions</div>'}).join('')+'<p><label><input type="checkbox" id="ok" style="width:auto"> I have read and understood the instructions.</label></p><button class="p" id="go" disabled>Start test</button> <a class="btn" href="'+T.tests+'">Cancel</a></div></div>';
  var ok=document.getElementById('ok'),g=document.getElementById('go');
  ok.onchange=function(){g.disabled=!ok.checked};
  g.onclick=function(){g.disabled=true;g.style.display='none';start()}
}
function start(){end=Date.now()+T.min*6e4;t0=Date.now();try{document.documentElement.requestFullscreen()}catch(x){}window.onbeforeunload=function(){return 'Your test is in progress.'};show(0);iv=setInterval(tick,1000)}
function tick(){if(done)return;var l=Math.round((end-Date.now())/1000),m=document.getElementById('tm');if(m){m.textContent=hms(l);m.className='cb-t'+(l<300?' warn':'')}if(l<=0)finish()}
function sec(i){return SI[Q[i].s]}
function show(i){if(done)return;cur=i;st[i].v=1;draw()}
function next(){if(cur<N-1)show(cur+1);else draw()}

function draw(){
  if(done)return;
  var q=Q[cur],s=S[sec(cur)],c=st[cur],k=cnt(all());
  A.innerHTML='<div class="cbt"><div class="cb-h"><b>'+e(T.title)+'</b><span>&#128100; '+e(T.name)+'</span><span class="cb-t" id="tm"></span></div><div class="cb-sec">'+S.map(function(x,j){return '<button class="'+(j==sec(cur)?'on':'')+'" data-s="'+j+'">'+e(x.n)+'</button>'}).join('')+'</div><div class="cb-m"><div class="cb-q"><div class="cb-qh"><b>Question '+(cur+1)+' of '+N+'</b><span>+'+T.marks+' / -'+T.neg+'</span></div><h3>'+e(q.q)+'</h3>'+q.o.map(function(o,j){return '<button class="opt'+(c.a===j?' sel':'')+'" data-j="'+j+'"><b>'+'ABCD'[j]+'.</b> '+e(o)+'</button>'}).join('')+'</div><aside class="cb-p"><b>'+e(s.n)+'</b><div class="mu" style="margin:6px 0">Answered '+(k.an+k.am)+' &middot; Not answered '+k.na+' &middot; Not visited '+k.nv+' &middot; Marked '+(k.mk+k.am)+'</div><div class="pb">'+s.ix.map(function(i){return '<button class="'+cl(i)+(i==cur?' cur':'')+'" data-i="'+i+'">'+(i+1)+'</button>'}).join('')+'</div><div class="leg">'+legend()+'</div></aside></div><div class="cb-f"><button data-a="mk">Mark for Review &amp; Next</button><button data-a="clr">Clear Response</button><button data-a="prev">Previous</button><button class="p" data-a="save">Save &amp; Next</button><span class="grow"></span><button class="p" style="background:#dc2626" data-a="sub">Submit</button></div></div>';
  tick();
  A.querySelectorAll('.cb-sec button').forEach(function(b){b.onclick=function(){show(S[+b.dataset.s].ix[0])}});
  A.querySelectorAll('.pb button').forEach(function(b){b.onclick=function(){show(+b.dataset.i)}});
  A.querySelectorAll('.opt').forEach(function(b){b.onclick=function(){st[cur].a=+b.dataset.j;draw()}});
  A.querySelectorAll('[data-a]').forEach(function(b){b.onclick=function(){
    var a=b.dataset.a;
    if(a=='save'){st[cur].m=0;next()}
    else if(a=='mk'){st[cur].m=1;next()}
    else if(a=='clr'){st[cur].a=null;draw()}
    else if(a=='prev'){if(cur>0)show(cur-1)}
    else confirmSub()
  }})
}

function confirmSub(){
  if(done||document.querySelector('.modal'))return;      /* never open two confirm boxes */
  var m=document.createElement('div');m.className='modal';
  var rows=S.map(function(s){var c=cnt(s.ix);return '<tr><td>'+e(s.n)+'</td><td>'+s.ix.length+'</td><td>'+(c.an+c.am)+'</td><td>'+c.na+'</td><td>'+c.nv+'</td><td>'+(c.mk+c.am)+'</td></tr>'}).join('');
  m.innerHTML='<div class="card"><h3>Submit test?</h3><div class="sc"><table><tr><th>Section</th><th>Total</th><th>Answered</th><th>Not answered</th><th>Not visited</th><th>Marked</th></tr>'+rows+'</table></div><p class="mu">You cannot change your answers after submitting.</p><button id="cn">Go back</button> <button class="p" id="fs">Submit now</button></div>';
  document.body.appendChild(m);
  document.getElementById('cn').onclick=function(){m.remove()};
  document.getElementById('fs').onclick=function(){
    this.disabled=true;this.style.display='none';          /* hide the button at once */
    var c=document.getElementById('cn');if(c)c.style.display='none';
    finish()
  }
}

/* ---- finish: lock the screen, build the payload, send once ---- */
function finish(){
  if(done)return;
  done=true;clearInterval(iv);window.onbeforeunload=null;
  hideButtons();                                            /* all buttons gone immediately */
  try{document.exitFullscreen()}catch(x){}
  var ans={};st.forEach(function(s,i){if(s.a!==null)ans[Q[i].id]=s.a});
  var payload={csrf:T.csrf, ans:ans, secs:Math.round((Date.now()-t0)/1000), def:T.def||null};
  if(T.test_id)payload.test_id=T.test_id;                   /* teacher test */
  send(payload)
}

function send(payload){
  if(sending)return;                                        /* one request at a time */
  sending=true;
  A.innerHTML='<div class="card" style="margin:20px"><b>Submitting your test…</b><p class="mu">Please wait, do not close this page.</p></div>';

  fetch(T.url,{
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json','X-CSRF-Token':T.csrf},
    body:JSON.stringify(payload)
  })
  .then(function(r){
    var ct=(r.headers.get('content-type')||'').toLowerCase();
    if(ct.indexOf('application/json')!==-1) return r.json();
    /* Server returned HTML (PHP fatal / 500 / 404) — read it so we can show the real cause */
    return r.text().then(function(txt){
      var m=(txt.match(/<b>(?:Warning|Fatal error|Parse error|Notice|Uncaught)[^<]*<\/b>[^<]*/)||[])
             .join(' ').replace(/<[^>]+>/g,' ');
      return {error:'server_html', status:r.status, message:(m||txt).slice(0,400)};
    });
  })
  .then(function(r){
    sending=false;
    if(r && r.error){
      var msgs={
        not_logged_in:'Your session expired. Please log in again to view the result.',
        csrf_mismatch:'Security token mismatch. Please log in again and retry.',
        csrf:'Security token mismatch. Please log in again and retry.',
        no_test:'The test session was not found. Please start the test again.',
        bad_sig:'The test data could not be verified. Please start again.',
        bad_test:'This test is not available for your account.',
        already_submitted:'You have already submitted this paper. It can be attempted only once.',
        closed:(r.message||'This test is closed.')+' Your answers could not be saved.',
        server:'Server error: '+(r.message||'unknown'),
        server_error:'Server error: '+(r.message||'unknown')
      };
      var shown = msgs[r.error] || ('The server returned HTTP '+r.status+'. '+(r.message||''));
      A.innerHTML='<div class="card" style="margin:20px"><b>Submit failed</b><p class="mu">'+e(shown)+'</p><p><a class="btn" href="'+T.tests+'">Back to tests</a> <a class="btn p" href="'+T.dash+'">Dashboard</a></p></div>';
      return;
    }
    result(r);
  })
  .catch(function(err){
    sending=false;
    if(isTT){
      /* safe: the server keeps only the first attempt, so trying again cannot create a duplicate */
      A.innerHTML='<div class="card" style="margin:20px"><b>Network error</b><p class="mu">Could not reach the server. Your answers are still saved on this screen. Tap <b>Try again</b> &mdash; it is safe, your test will be saved only once.</p><p><button class="p" id="rt">Try again</button></p></div>';
      var rt=document.getElementById('rt');
      rt.onclick=function(){rt.disabled=true;rt.style.display='none';send(payload)}
    }else{
      A.innerHTML='<div class="card" style="margin:20px"><b>Network error</b><p class="mu">Could not reach the server. This usually means the server is slow or unreachable. Check your internet, then open <a href="'+T.tests+'">your tests</a> or your certificates to see whether the result was saved.</p><p><a class="btn p" href="'+T.dash+'">Dashboard</a> <a class="btn" href="'+T.tests+'">Back to tests</a></p></div>';
    }
  });
}

function result(r){
  /* teacher tests return only correct / wrong / total / score — so every extra field is optional */
  var marks=(r.marks!==undefined)?r.marks:r.correct;
  var max=(r.max!==undefined)?r.max:r.total;
  var pts=(r.points!==undefined)?' &middot; +'+r.points+' points':'';
  var mail=(r.mailok===undefined)?'':(r.mailok===false?' <span style="color:#b91c1c">(result email could not be sent — check spam or your profile email)</span>':' (a copy has been emailed to you)');
  var certLine = r.certificate
    ? '<a class="btn" href="'+r.certificate+'" target="_blank" rel="noopener">🏆 View / print certificate</a> '
    : '';
  var secTable = (r.sections&&r.sections.length)
    ? '<div class="sc"><table><tr><th>Section</th><th>Questions</th><th>Correct</th><th>Wrong</th><th>Skipped</th><th>Marks</th></tr>'
      +r.sections.map(function(s){return '<tr><td>'+e(s.n)+'</td><td>'+s.t+'</td><td>'+s.c+'</td><td>'+s.w+'</td><td>'+s.k+'</td><td>'+s.m+'</td></tr>'}).join('')
      +'</table></div>'
    : '';
  var already=r.already?'<p><span class="pill" style="background:#fef3c7;color:#b45309">You had already submitted this test &mdash; showing your saved result.</span></p>':'';
  A.innerHTML='<div class="narrow" style="padding:20px">'
    +'<div class="card">'
    +already
    +'<div class="big">'+marks+' / '+max+' marks &middot; '+r.score+'%</div>'
    +'<p class="mu">Correct '+r.correct+' &middot; Wrong '+r.wrong+' &middot; Skipped '+(r.total-r.correct-r.wrong)+' &middot; Time '+Math.floor(r.secs/60)+' min'+pts+mail+'</p>'
    +secTable
    +'<p>'+certLine+'<a class="btn p" href="'+T.dash+'">Dashboard</a> <a class="btn" href="'+T.tests+'">More tests</a></p>'
    +'</div>'
    +'<h2>Answers &amp; explanations</h2>'
    +(r.review||[]).map(function(x,n){
      return '<div class="card"><b>'+(n+1)+'. '+e(x.q)+'</b>'
        +x.o.map(function(o,j){return '<div class="opt '+(j==x.ans?'right':(j===x.pick?'wrong':''))+'" style="padding:6px 10px;border-radius:8px">'+'ABCD'[j]+'. '+e(o)+'</div>'}).join('')
        +'<div class="mu">'+(x.pick===null?'Skipped &middot; ':'')+'Explanation: '+e(x.exp||'')+'</div></div>'
    }).join('')
    +'</div>';
}

intro();
})();

/* ===== premium behaviours ===== */
(function(){
function count(el){var n=+el.dataset.count||0,i=0,st=Math.max(1,Math.ceil(n/50)),t=setInterval(function(){i+=st;if(i>=n){i=n;clearInterval(t)}el.textContent=i.toLocaleString('en-IN')},24);if(!n)el.textContent='0'}
var els=document.querySelectorAll('.rv'),cs=document.querySelectorAll('[data-count]');
if('IntersectionObserver' in window){
  var io=new IntersectionObserver(function(es){
    es.forEach(function(e){
      if(e.isIntersecting){
        e.target.classList.add('in');
        if(e.target.dataset.count!==undefined)count(e.target);
        io.unobserve(e.target)
      }
    })
  },{threshold:.1});
  els.forEach(function(x){io.observe(x)});
  cs.forEach(function(x){io.observe(x)})
} else {
  els.forEach(function(x){x.classList.add('in')});
  cs.forEach(count)
}

/* hero slider */
var h=document.querySelector('.hs');
if(h){
  var sl=h.querySelectorAll('.hslide'),dt=h.querySelectorAll('.hdot'),i=0,tm;
  function go(n){i=(n+sl.length)%sl.length;sl.forEach(function(x,k){x.classList.toggle('on',k==i)});dt.forEach(function(x,k){x.classList.toggle('on',k==i)})}
  function stop(){clearInterval(tm)}
  function play(){stop();tm=setInterval(function(){go(i+1)},6500)}
  h.querySelector('.hprev').onclick=function(){go(i-1);play()};
  h.querySelector('.hnext').onclick=function(){go(i+1);play()};
  dt.forEach(function(x,k){x.onclick=function(){go(k);play()}});
  h.onmouseenter=stop;h.onmouseleave=play;
  var x0=null;
  h.addEventListener('touchstart',function(e){x0=e.touches[0].clientX},{passive:true});
  h.addEventListener('touchend',function(e){if(x0===null)return;var d=e.changedTouches[0].clientX-x0;if(Math.abs(d)>40){go(i+(d<0?1:-1));play()}x0=null});
  go(0);play()
}

/* password eye + strength meter */
document.querySelectorAll('input[type=password]').forEach(function(p){
  var w=document.createElement('div');w.className='pw';
  p.parentNode.insertBefore(w,p);w.appendChild(p);
  var b=document.createElement('button');
  b.type='button';b.className='eye';b.setAttribute('aria-label','Show password');b.textContent='👁';
  b.onclick=function(){p.type=p.type=='password'?'text':'password'};
  w.appendChild(b);
  if(p.dataset.meter){
    var m=document.createElement('div');m.className='meter';m.innerHTML='<i></i>';
    w.parentNode.insertBefore(m,w.nextSibling);
    p.addEventListener('input',function(){
      var v=p.value,sc=(v.length>=6)+(v.length>=10)+(/[A-Z]/.test(v)&&/[a-z]/.test(v))+/\d/.test(v)+/[^A-Za-z0-9]/.test(v),bar=m.firstChild;
      bar.style.width=(v?Math.max(12,sc*20):0)+'%';
      bar.style.background=sc<=1?'#ef4444':sc<=3?'#f59e0b':'#16a34a'
    })
  }
})
})();