<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>GSJLF — Réinitialisation mot de passe</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <style>
        :root{--green:#113B26;--green-mid:#1a5c38;--ctd:#2d8a57;--cmjlf:#864c20;--beige:#f0cc6a;--cream:#fdf8ef;--dark:#0c1a10;--muted:#5e6b61;--border:rgba(17,59,38,.12);--shadow:0 32px 90px rgba(15,51,32,.18)}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;color:var(--dark);overflow-x:hidden;background:radial-gradient(ellipse 70% 60% at 0% 0%,rgba(17,59,38,.1),transparent),radial-gradient(ellipse 50% 50% at 100% 10%,rgba(134,76,32,.08),transparent),#fdf8ef}
        .page-shell{min-height:100vh;display:grid;grid-template-columns:1.1fr .9fr}
        .sidebar{position:sticky;top:0;height:100vh;overflow:hidden;display:flex;flex-direction:column}
        .slide{position:absolute;inset:0;z-index:0;background-size:cover;background-position:center;opacity:0;transition:opacity 1.2s ease}
        .slide.active{opacity:1}
        .sidebar-overlay{position:absolute;inset:0;z-index:2;background:linear-gradient(160deg,rgba(6,16,10,.85) 0%,rgba(6,16,10,.68) 45%,rgba(6,16,10,.82) 100%)}
        .sidebar-overlay::before{content:"";position:absolute;inset:16px;border-radius:22px;border:1px solid rgba(255,255,255,.09);pointer-events:none}
        .sidebar-orb{position:absolute;z-index:3;width:380px;height:380px;border-radius:50%;background:radial-gradient(circle,rgba(240,204,106,.2),transparent 65%);right:-160px;top:-130px;pointer-events:none}
        .sb-inner{position:relative;z-index:10;height:100%;padding:32px;display:flex;flex-direction:column;justify-content:space-between}
        .sb-logo{display:flex;align-items:center;gap:13px;text-decoration:none}
        .sb-logo-img{width:52px;height:52px;border-radius:50%;background:white;padding:3px;object-fit:contain;box-shadow:0 12px 28px rgba(0,0,0,.3);flex-shrink:0}
        .sb-name{color:white;font-weight:800;font-size:14px;line-height:1.3}
        .sb-sub{color:rgba(255,255,255,.48);font-size:11px;font-weight:600;margin-top:2px}
        .sb-kicker{display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:999px;background:rgba(240,204,106,.18);border:1px solid rgba(240,204,106,.3);color:var(--beige);font-size:11px;font-weight:800;letter-spacing:.08em;margin-bottom:20px;animation:sbUp .6s .1s ease both}
        .sb-headline{font-family:'Playfair Display',serif;font-size:clamp(32px,3.6vw,52px);font-weight:900;color:white;line-height:1.0;letter-spacing:-.025em;margin-bottom:18px;animation:sbUp .6s .18s ease both}
        .sb-headline span{color:var(--beige);font-style:italic}
        .sb-desc{font-size:14px;line-height:1.9;color:rgba(255,255,255,.65);font-weight:500;max-width:360px;animation:sbUp .6s .26s ease both}
        .sb-stats{display:flex;gap:12px;margin-top:28px;flex-wrap:wrap;animation:sbUp .6s .34s ease both}
        .sb-stat{padding:14px 18px;border-radius:18px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.11);backdrop-filter:blur(12px);min-width:76px;transition:.25s}
        .sb-stat:hover{background:rgba(255,255,255,.14);transform:translateY(-2px)}
        .sb-stat-num{font-family:'Playfair Display',serif;font-size:24px;font-weight:900;color:white;line-height:1}
        .sb-stat-lbl{font-size:10px;font-weight:700;color:rgba(255,255,255,.5);margin-top:4px}
        .slide-ent{display:flex;align-items:center;gap:10px;margin-top:18px;animation:sbUp .6s .42s ease both}
        .slide-ent img{width:32px;height:32px;border-radius:50%;object-fit:contain;background:white;padding:2px;box-shadow:0 6px 14px rgba(0,0,0,.2)}
        .slide-ent-info span{display:block;font-size:11px;font-weight:800;color:var(--beige);letter-spacing:.06em}
        .slide-ent-info small{display:block;font-size:10px;font-weight:600;color:rgba(255,255,255,.5);margin-top:1px}
        .slide-dots{display:flex;align-items:center;gap:8px;margin-top:16px;animation:sbUp .6s .48s ease both}
        .slide-dot{height:4px;border-radius:999px;background:rgba(255,255,255,.3);cursor:pointer;transition:all .4s ease}
        .slide-dot.active{background:var(--beige)}
        .sb-bottom{display:flex;align-items:center;justify-content:space-between;font-size:11px;font-weight:700;color:rgba(255,255,255,.35)}
        .sb-secure{display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.07);color:rgba(255,255,255,.55)}
        @keyframes sbUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
        .main-wrap{display:flex;align-items:center;justify-content:center;padding:44px 36px}
        .auth-card{width:100%;max-width:480px;background:rgba(255,255,255,.93);backdrop-filter:blur(32px);border:1px solid rgba(255,255,255,.95);border-radius:32px;padding:38px;box-shadow:var(--shadow);animation:cardIn .5s cubic-bezier(.22,1,.36,1) both}
        @keyframes cardIn{from{opacity:0;transform:translateY(28px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
        .back-link{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:800;color:var(--muted);text-decoration:none;margin-bottom:26px;padding:7px 14px;border-radius:999px;border:1px solid var(--border);background:white;transition:.2s}
        .back-link:hover{color:var(--green);box-shadow:0 6px 16px rgba(15,51,32,.08)}
        .form-icon{width:56px;height:56px;border-radius:20px;display:grid;place-items:center;background:linear-gradient(135deg,var(--green),var(--green-mid));color:white;margin-bottom:18px;box-shadow:0 14px 30px rgba(17,59,38,.22)}
        .form-title{font-family:'Playfair Display',serif;font-size:30px;font-weight:900;letter-spacing:-.03em;margin-bottom:8px}
        .form-subtitle{font-size:13px;line-height:1.75;color:var(--muted);font-weight:600;margin-bottom:22px}
        .field{margin-bottom:14px}
        .field label{display:block;font-size:10px;font-weight:800;color:#2a3a2f;margin-bottom:7px;letter-spacing:.06em;text-transform:uppercase}
        .input-wrap{position:relative}
        .input-wrap .ico{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#96aca0;font-size:18px;pointer-events:none;transition:color .2s}
        .input-wrap:focus-within .ico{color:var(--green)}
        .input-wrap input{width:100%;height:50px;border-radius:14px;border:1.5px solid var(--border);background:white;outline:none;padding:0 14px 0 42px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:13px;color:var(--dark);transition:border-color .22s,box-shadow .22s,transform .15s}
        .input-wrap input:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(17,59,38,.08);transform:translateY(-1px)}
        .input-wrap input::placeholder{color:#b8c4bc;font-weight:600}
        .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#96aca0;display:grid;place-items:center;padding:4px;transition:color .2s}
        .eye-btn:hover{color:var(--green)}
        .info-box{display:flex;align-items:flex-start;gap:11px;padding:13px 15px;border-radius:14px;background:rgba(240,204,106,.14);border:1px solid rgba(240,204,106,.32);color:#5c3e08;font-size:12px;line-height:1.65;font-weight:700;margin-bottom:18px}
        .info-box .ico2{font-size:18px;color:#a06a10;margin-top:1px;flex-shrink:0}
        .info-box-success{display:flex;align-items:flex-start;gap:11px;padding:13px 15px;border-radius:14px;background:rgba(46,125,50,.08);border:1px solid rgba(46,125,50,.24);color:#1b5e20;font-size:12px;line-height:1.65;font-weight:700;margin-bottom:18px}
        .link-card{display:flex;align-items:center;gap:12px;padding:14px;border-radius:16px;background:linear-gradient(135deg,rgba(17,59,38,.04),rgba(240,204,106,.1));border:1px solid var(--border);margin-bottom:16px}
        .link-card-icon{width:42px;height:42px;min-width:42px;border-radius:13px;display:grid;place-items:center;background:var(--green);color:white;box-shadow:0 10px 20px rgba(17,59,38,.18)}
        .link-card strong{display:block;font-size:13px;margin-bottom:3px}
        .link-card p{font-size:11px;line-height:1.6;color:var(--muted);font-weight:700}
        .rules{display:grid;gap:8px;margin:0 0 16px}
        .rules span{display:flex;align-items:center;gap:8px;font-size:11px;font-weight:700;color:var(--muted)}
        .rules .material-symbols-outlined{font-size:14px;color:var(--ctd)}
        .strength-bar{margin:-6px 0 14px}
        .strength-track{height:4px;background:var(--border);border-radius:999px;overflow:hidden}
        .strength-fill{height:100%;border-radius:999px;transition:.4s ease;width:0%}
        .strength-label{font-size:11px;font-weight:700;color:var(--muted);margin-top:5px}
        .match-error{display:none;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#e53935;margin-top:-8px;margin-bottom:12px}
        .match-error.show{display:flex}
        .submit-btn{width:100%;height:52px;border:0;border-radius:16px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:14px;color:white;background:linear-gradient(135deg,var(--green),var(--green-mid));box-shadow:0 14px 32px rgba(17,59,38,.22);transition:transform .25s,box-shadow .25s;display:flex;align-items:center;justify-content:center;gap:9px;position:relative;overflow:hidden;margin-bottom:10px}
        .submit-btn::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.12),transparent);opacity:0;transition:.25s}
        .submit-btn:hover{transform:translateY(-2px);box-shadow:0 20px 42px rgba(17,59,38,.28)}
        .submit-btn:hover::before{opacity:1}
        .submit-btn:disabled{opacity:.7;cursor:not-allowed;transform:none}
        .btn-ghost{width:100%;height:52px;border:1.5px solid var(--border);border-radius:16px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:14px;color:var(--green);background:white;transition:transform .25s,box-shadow .25s;display:flex;align-items:center;justify-content:center;gap:9px}
        .btn-ghost:hover{background:var(--cream);box-shadow:0 6px 16px rgba(17,59,38,.08)}
        .alt-line{display:flex;align-items:center;gap:12px;margin:18px 0;color:#96a89a;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.12em}
        .alt-line::before,.alt-line::after{content:"";flex:1;height:1px;background:var(--border)}
        .switch-note{text-align:center;font-size:13px;font-weight:700;color:var(--muted)}
        .link-btn{border:0;background:transparent;color:var(--cmjlf);font-family:inherit;font-weight:800;cursor:pointer;font-size:13px;text-decoration:none}
        .link-btn:hover{text-decoration:underline}
        .phase{animation:phaseIn .35s cubic-bezier(.22,1,.36,1) both}
        @keyframes phaseIn{from{opacity:0;transform:translateX(14px) scale(.98)}to{opacity:1;transform:translateX(0) scale(1)}}
        .success-banner{display:flex;flex-direction:column;align-items:center;padding:36px 16px;text-align:center}
        .success-icon{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--green-mid));display:grid;place-items:center;color:white;margin-bottom:20px;box-shadow:0 16px 34px rgba(17,59,38,.22);animation:popIn .5s cubic-bezier(.34,1.56,.64,1) both}
        @keyframes popIn{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
        .success-banner h3{font-family:'Playfair Display',serif;font-size:24px;font-weight:900;margin-bottom:9px}
        .success-banner p{font-size:13px;line-height:1.75;color:var(--muted);font-weight:600;max-width:300px}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:980px){.page-shell{grid-template-columns:1fr}.sidebar{display:none}.main-wrap{padding:22px 16px}.auth-card{padding:26px 22px;border-radius:24px}}
    </style>
</head>
<body>
<div class="page-shell">
    <aside class="sidebar">
        <div class="slide active" style="background-image:url('uahb-mobile.jpg')"></div>
        <div class="slide"        style="background-image:url('cmjlf-mobile.jpg')"></div>
        <div class="slide"        style="background-image:url('ctd-mobile.jpg')"></div>
        <div class="sidebar-overlay"></div>
        <div class="sidebar-orb"></div>
        <div class="sb-inner">
            <div><a href="index.html" class="sb-logo">
                    <img src="logo_gsjlf.png" alt="GSJLF" class="sb-logo-img">
                    <div><div class="sb-name">Groupe Scolaire Jean de la Fontaine</div><div class="sb-sub">Environnement Numérique de Travail</div></div>
                </a></div>
            <div class="sb-mid">
                <div class="sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">lock_reset</span>Récupération d'accès</div>
                <h2 class="sb-headline">Réinitialisez<br>votre <span>mot de passe.</span></h2>
                <p class="sb-desc">Recevez un lien sécurisé pour créer un nouveau mot de passe et retrouver l'accès à votre espace ENT.</p>
                <div class="sb-stats">
                    <div class="sb-stat"><div class="sb-stat-num">10min</div><div class="sb-stat-lbl">Validité</div></div>
                    <div class="sb-stat"><div class="sb-stat-num">1x</div><div class="sb-stat-lbl">Usage</div></div>
                    <div class="sb-stat"><div class="sb-stat-num">SSL</div><div class="sb-stat-lbl">Chiffré</div></div>
                </div>
                <div class="slide-ent">
                    <img id="ent-logo" src="logo_uahb.png" alt="">
                    <div class="slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou Hampâté Bâ</small></div>
                </div>
                <div class="slide-dots" id="slide-dots"></div>
            </div>
            <div class="sb-bottom">
                <div class="sb-secure"><span class="material-symbols-outlined" style="font-size:13px">verified_user</span>Réinitialisation sécurisée</div>
                <span>© 2026 GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="main-wrap">
        <div class="auth-card">
            <a href="signin.html" class="back-link"><span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>Retour à la connexion</a>

            <!-- PHASE 1 : demande -->
            <div id="phase1" class="phase">
                <div class="form-icon"><span class="material-symbols-outlined">lock_reset</span></div>
                <h1 class="form-title">Mot de passe oublié</h1>
                <p class="form-subtitle">Renseignez votre matricule et email pour recevoir un lien de réinitialisation sécurisé.</p>
                <div class="info-box">
                    <span class="material-symbols-outlined ico2">link</span>
                    <p>Le lien est personnel, usage unique et expire automatiquement après 10 minutes.</p>
                </div>
                <div class="field">
                    <label>Matricule</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">pin</span>
                        <input type="text" id="mat1" placeholder="Votre numéro matricule" required>
                    </div>
                </div>
                <div class="field">
                    <label>Email institutionnel</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">alternate_email</span>
                        <input type="email" id="email1" placeholder="prenom.nom@gsjlf.sn" required>
                    </div>
                </div>
                <div class="link-card">
                    <div class="link-card-icon"><span class="material-symbols-outlined">mark_email_read</span></div>
                    <div><strong>Lien sécurisé par email</strong><p>Le lien permettra de définir un nouveau mot de passe sur une page dédiée.</p></div>
                </div>
                <div class="rules">
                    <span><span class="material-symbols-outlined">check_circle</span>Lien personnel et non partageable.</span>
                    <span><span class="material-symbols-outlined">check_circle</span>Expire automatiquement après 10 minutes.</span>
                    <span><span class="material-symbols-outlined">check_circle</span>Vérifiez vos spams si vous ne recevez rien.</span>
                </div>
                <button class="submit-btn" type="button" id="btn-p1" onclick="sendLink()">
                    <span class="material-symbols-outlined" style="font-size:18px">send</span>Envoyer le lien
                </button>
                <div class="alt-line">retour</div>
                <p class="switch-note">Vous vous souvenez du mot de passe ? <a href="signin.html" class="link-btn">Se connecter</a></p>
            </div>

            <!-- PHASE 2 : email envoyé -->
            <div id="phase2" style="display:none">
                <div class="form-icon" style="background:linear-gradient(135deg,#0f7c40,#1aad60)">
                    <span class="material-symbols-outlined">mark_email_unread</span>
                </div>
                <h1 class="form-title">Email envoyé !</h1>
                <p class="form-subtitle">Un lien de réinitialisation a été envoyé à <strong id="email-display"></strong>.</p>
                <div class="info-box-success">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#2e7d32;margin-top:1px;flex-shrink:0">check_circle</span>
                    <p>Email envoyé avec succès. Lien valable 10 minutes. Pensez à vérifier votre dossier spam.</p>
                </div>
                <button class="submit-btn" type="button" onclick="goPhase(3)">
                    <span class="material-symbols-outlined" style="font-size:18px">lock_reset</span>Définir mon nouveau mot de passe
                </button>
                <button class="btn-ghost" type="button" onclick="goPhase(1)">
                    <span class="material-symbols-outlined" style="font-size:18px">refresh</span>Renvoyer le lien
                </button>
            </div>

            <!-- PHASE 3 : nouveau mot de passe -->
            <div id="phase3" style="display:none">
                <div class="form-icon"><span class="material-symbols-outlined">key</span></div>
                <h1 class="form-title">Nouveau mot de passe</h1>
                <p class="form-subtitle">Choisissez un nouveau mot de passe sécurisé pour votre compte ENT.</p>
                <div class="field">
                    <label>Nouveau mot de passe</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">lock</span>
                        <input type="password" id="pw1" placeholder="Créer un mot de passe" oninput="checkStrength(this.value)" required>
                        <button type="button" class="eye-btn" onclick="togglePw('pw1','eye1')">
                            <span class="material-symbols-outlined" style="font-size:18px" id="eye1">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="strength-bar">
                    <div class="strength-track"><div class="strength-fill" id="sfill"></div></div>
                    <div class="strength-label" id="slabel">—</div>
                </div>
                <div class="field">
                    <label>Confirmation du mot de passe</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">lock_clock</span>
                        <input type="password" id="pw2" placeholder="Répéter le mot de passe" oninput="checkMatch()" required>
                        <button type="button" class="eye-btn" onclick="togglePw('pw2','eye2')">
                            <span class="material-symbols-outlined" style="font-size:18px" id="eye2">visibility</span>
                        </button>
                    </div>
                </div>
                <p class="match-error" id="match-err">
                    <span class="material-symbols-outlined" style="font-size:13px">error</span>Les mots de passe ne correspondent pas.
                </p>
                <div class="rules">
                    <span><span class="material-symbols-outlined">check_circle</span>Minimum 8 caractères</span>
                    <span><span class="material-symbols-outlined">check_circle</span>Au moins une majuscule et un chiffre</span>
                    <span><span class="material-symbols-outlined">check_circle</span>Différent de votre ancien mot de passe</span>
                </div>
                <button class="submit-btn" type="button" id="btn-reset" onclick="handleReset()">
                    <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>Enregistrer le nouveau mot de passe
                </button>
            </div>

            <!-- SUCCESS -->
            <div id="success-state" class="success-banner" style="display:none">
                <div class="success-icon"><span class="material-symbols-outlined" style="font-size:34px">lock_open</span></div>
                <h3>Mot de passe mis à jour !</h3>
                <p>Votre mot de passe a été réinitialisé avec succès. Connectez-vous avec vos nouveaux identifiants.</p>
                <a href="signin.html" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter</a>
            </div>
        </div>
    </main>
</div>
<script>
    const SLIDES=[{logo:'logo_uahb.png',name:'UAHB',desc:'Université Amadou Hampâté Bâ'},{logo:'logo_cmjlf.png',name:'CMJLF',desc:'Collège Moderne Jean de la Fontaine'},{logo:'logo_ctd.png',name:'CTD',desc:'Collège Technique de Dakar'}];
    let cur=0,timer=null;
    const slides=document.querySelectorAll('.slide');
    const dotsEl=document.getElementById('slide-dots');
    SLIDES.forEach((_,i)=>{const d=document.createElement('div');d.className='slide-dot'+(i===0?' active':'');d.style.width=i===0?'28px':'8px';d.addEventListener('click',()=>goSlide(i));dotsEl.appendChild(d);});
    function getDots(){return dotsEl.querySelectorAll('.slide-dot')}
    function goSlide(n,restart=true){slides[cur].classList.remove('active');getDots()[cur].classList.remove('active');getDots()[cur].style.width='8px';cur=(n+SLIDES.length)%SLIDES.length;slides[cur].classList.add('active');getDots()[cur].classList.add('active');getDots()[cur].style.width='28px';const el=document.getElementById('ent-logo'),en=document.getElementById('ent-name'),ed=document.getElementById('ent-desc');[el,en,ed].forEach(x=>{x.style.transition='opacity .3s';x.style.opacity='0';});setTimeout(()=>{el.src=SLIDES[cur].logo;en.textContent=SLIDES[cur].name;ed.textContent=SLIDES[cur].desc;[el,en,ed].forEach(x=>x.style.opacity='1');},300);if(restart){clearInterval(timer);timer=setInterval(()=>goSlide(cur+1,false),5000);}}
    timer=setInterval(()=>goSlide(cur+1,false),5000);

    function goPhase(n){[1,2,3].forEach(i=>{const el=document.getElementById('phase'+i);if(el){el.style.display=i===n?'block':'none';if(i===n)el.className='phase';}});}
    function sendLink(){const email=document.getElementById('email1').value;if(!email){document.getElementById('email1').focus();return;}document.getElementById('email-display').textContent=email;const btn=document.getElementById('btn-p1');btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Envoi…';btn.disabled=true;setTimeout(()=>goPhase(2),1300);}
    function togglePw(fid,iid){const f=document.getElementById(fid),i=document.getElementById(iid);f.type=f.type==='password'?'text':'password';i.textContent=f.type==='password'?'visibility':'visibility_off';}
    function checkStrength(v){let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const f=document.getElementById('sfill');if(f){f.style.width=[0,25,50,75,100][s]+'%';f.style.background=['','#e53935','#ff9800','#fbc02d','#2e7d32'][s];}const sl=document.getElementById('slabel');if(sl)sl.textContent=['—','Trop court','Faible','Moyen','Fort'][s];checkMatch();}
    function checkMatch(){const p1=document.getElementById('pw1').value,p2=document.getElementById('pw2').value,err=document.getElementById('match-err'),f2=document.getElementById('pw2');if(!p2)return;const bad=p2.length>0&&p1!==p2;err.classList.toggle('show',bad);f2.style.borderColor=bad?'#e53935':'';f2.style.boxShadow=bad?'0 0 0 4px rgba(229,57,53,.1)':'';}
    function handleReset(){const p1=document.getElementById('pw1').value,p2=document.getElementById('pw2').value;if(!p1||!p2)return;if(p1!==p2){checkMatch();document.getElementById('pw2').focus();return;}const btn=document.getElementById('btn-reset');btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Enregistrement…';btn.disabled=true;setTimeout(()=>{[1,2,3].forEach(i=>{const el=document.getElementById('phase'+i);if(el)el.style.display='none';});document.getElementById('success-state').style.display='flex';},1300);}
</script>
</body>
</html>
