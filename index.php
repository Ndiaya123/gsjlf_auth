<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>GSJLF — Environnement Numérique de Travail</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/index.css">

</head>
<body>

<!-- ════════════════════════════════════
     NAV
════════════════════════════════════ -->
<nav id="navbar">
    <a href="/personnel/accueil" class="nav-logo">
        <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF">
        <div><div class="nav-name">GSJLF</div><div class="nav-sub">Environnement Numérique de Travail</div></div>
    </a>
    <div class="nav-links">
        <a href="#entites"       class="nav-link">Entités</a>
        <a href="#fonctionnalites" class="nav-link">Fonctionnalités</a>
        <a href="#chiffres"      class="nav-link">En chiffres</a>
    </div>
    <div class="nav-actions">
        <a href="/personnel/signin"  class="nav-btn-ghost">Se connecter</a>
        <a href="/personnel/signup"  class="nav-btn-solid">
            <span class="material-symbols-outlined" style="font-size:15px">person_add</span>
            Créer un compte
        </a>
    </div>
</nav>

<!-- ════════════════════════════════════
     HERO
════════════════════════════════════ -->
<section class="hero" id="hero">
    <!-- Slides -->
    <div class="hero-slide active" id="hs0" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
    <div class="hero-slide"        id="hs1" style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
    <div class="hero-slide"        id="hs2" style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>

    <div class="hero-overlay"></div>
    <div class="hero-overlay-color" id="hero-color"></div>

    <div class="hero-content">
        <div class="hero-kicker">
            <span class="material-symbols-outlined" style="font-size:13px">verified</span>
            Plateforme ENT Officielle · <span id="annee_en_cours1"> </span>
        </div>

        <h1 class="hero-title">
            L'espace numérique<br>
            <span class="line2">de votre communauté</span>
            <span class="line3">Groupe Scolaire Jean de la Fontaine — Dakar, Sénégal</span>
        </h1>

        <p class="hero-desc">
            Accédez à vos cours, notes, emplois du temps et outils collaboratifs.
            Un seul espace sécurisé pour tous les membres du groupe scolaire.
        </p>

        <div class="hero-actions">
            <a href="/personnel/signin" class="btn-hero-primary">
                <span class="material-symbols-outlined" style="font-size:20px">login</span>
                Accéder à mon espace
            </a>
            <a href="/personnel/signup" class="btn-hero-ghost">
                <span class="material-symbols-outlined" style="font-size:20px">arrow_forward</span>
                Créer un compte
            </a>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hstat-num">3</div>
                <div class="hstat-lbl">Établissements</div>
            </div>
            <div class="hero-stat">
                <div class="hstat-num">500+</div>
                <div class="hstat-lbl">Membres actifs</div>
            </div>
            <div class="hero-stat">
                <div class="hstat-num">100%</div>
                <div class="hstat-lbl">Sécurisé SSL</div>
            </div>
            <div class="hero-stat">
                <div class="hstat-num">24/7</div>
                <div class="hstat-lbl">Disponible</div>
            </div>
        </div>
    </div>

    <!-- Badge entité courante -->
    <div class="hero-ent-badge" id="hero-ent-badge">
        <img id="heb-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
        <div>
            <div class="heb-name" id="heb-name">UAHB</div>
            <div class="heb-desc" id="heb-desc">Université Amadou Hampâté Bâ</div>
        </div>
    </div>

    <!-- Dots navigation -->
    <div class="hero-nav" id="hero-nav"></div>
</section>

<!-- ════════════════════════════════════
     ENTITÉS
════════════════════════════════════ -->
<section class="section-entities" id="entites">
    <div class="sec-inner">
        <div class="sec-header reveal">
            <div>
                <div class="sec-kicker">
                    <span class="material-symbols-outlined" style="font-size:13px">account_tree</span>
                    Nos établissements
                </div>
                <h2 class="sec-title">Un groupe,<br>trois excellences.</h2>
            </div>
            <p class="sec-sub">Le GSJLF réunit trois institutions complémentaires, chacune dédiée à un niveau d'enseignement et à un public spécifique.</p>
        </div>

        <div class="entities-grid">
            <!-- UAHB — grande carte -->
            <div class="ent-card uahb reveal">
                <div class="ent-card-bg" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
                <div class="ent-card-overlay"></div>
                <div class="ent-card-content">
                    <div class="ent-logo-wrap"><img src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="UAHB"></div>
                    <div class="ent-tag">Enseignement Supérieur</div>
                    <div class="ent-name">Université Amadou<br>Hampâté Bâ</div>
                    <p class="ent-desc">Licence, Master et formations continues. Une université moderne ancrée dans les valeurs africaines d'excellence.</p>
                    <a href="/personnel/signin" class="ent-pill">
                        <span class="material-symbols-outlined" style="font-size:15px">login</span>
                        Accéder à l'espace UAHB
                    </a>
                </div>
            </div>

            <!-- CMJLF -->
            <div class="ent-card cmjlf reveal delay-2">
                <div class="ent-card-bg" style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
                <div class="ent-card-overlay"></div>
                <div class="ent-card-content">
                    <div class="ent-logo-wrap"><img src="/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png" alt="CMJLF"></div>
                    <div class="ent-tag">Enseignement Secondaire</div>
                    <div class="ent-name">Collège Moderne Jean de la Fontaine</div>
                    <p class="ent-desc">Un cadre rigoureux et bienveillant pour la réussite de chaque élève.</p>
                    <a href="/personnel/signin" class="ent-pill">
                        <span class="material-symbols-outlined" style="font-size:15px">login</span>
                        Espace CMJLF
                    </a>
                </div>
            </div>

            <!-- CTD -->
            <div class="ent-card ctd reveal delay-3">
                <div class="ent-card-bg" style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
                <div class="ent-card-overlay"></div>
                <div class="ent-card-content">
                    <div class="ent-logo-wrap"><img src="/personnel/ressources/dist_assets/media/logos/logo_ctd.png" alt="CTD"></div>
                    <div class="ent-tag">Formation Technique</div>
                    <div class="ent-name">Collège Technique de Dakar</div>
                    <p class="ent-desc">Des filières techniques et professionnelles certifiantes pour l'emploi.</p>
                    <a href="/personnel/signin" class="ent-pill">
                        <span class="material-symbols-outlined" style="font-size:15px">login</span>
                        Espace CTD
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     FONCTIONNALITÉS
════════════════════════════════════ -->
<section class="section-features" id="fonctionnalites">
    <div class="sec-inner">
        <div class="sec-header reveal" style="color:white">
            <div>
                <div class="sec-kicker" style="color:rgba(240,204,106,.8)">
                    <span class="material-symbols-outlined" style="font-size:13px">apps</span>
                    Fonctionnalités
                </div>
                <h2 class="sec-title" style="color:white">Tout ce dont vous<br>avez besoin.</h2>
            </div>
            <p class="sec-sub" style="color:rgba(255,255,255,.55)">Des outils pensés pour les étudiants, enseignants, parents et l'administration du groupe scolaire.</p>
        </div>

        <div class="features-grid">
            <div class="feat-card reveal delay-1">
                <div class="feat-icon"><span class="material-symbols-outlined">folder_open</span></div>
                <div class="feat-title">Ressources Pédagogiques</div>
                <p class="feat-desc">Cours, devoirs, bibliothèques numériques accessibles depuis n'importe quel appareil, à toute heure.</p>
            </div>
            <div class="feat-card reveal delay-2">
                <div class="feat-icon"><span class="material-symbols-outlined">assessment</span></div>
                <div class="feat-title">Notes & Bulletins</div>
                <p class="feat-desc">Consultez vos résultats, bulletins et relevés de notes en temps réel, par matière et par trimestre.</p>
            </div>
            <div class="feat-card reveal delay-3">
                <div class="feat-icon"><span class="material-symbols-outlined">calendar_month</span></div>
                <div class="feat-title">Emplois du Temps</div>
                <p class="feat-desc">Planning de cours, examens et activités synchronisé et disponible en permanence.</p>
            </div>
            <div class="feat-card reveal delay-4">
                <div class="feat-icon"><span class="material-symbols-outlined">forum</span></div>
                <div class="feat-title">Messagerie Interne</div>
                <p class="feat-desc">Communiquez en toute sécurité avec les enseignants, camarades et l'administration.</p>
            </div>
            <div class="feat-card reveal delay-5">
                <div class="feat-icon"><span class="material-symbols-outlined">payments</span></div>
                <div class="feat-title">Gestion Financière</div>
                <p class="feat-desc">Suivi des paiements de scolarité, reçus et attestations générés en ligne instantanément.</p>
            </div>
            <div class="feat-card reveal delay-6">
                <div class="feat-icon"><span class="material-symbols-outlined">shield</span></div>
                <div class="feat-title">Sécurité Renforcée</div>
                <p class="feat-desc">Authentification sécurisée, données chiffrées et contrôle des accès par profil et entité.</p>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     CHIFFRES
════════════════════════════════════ -->
<section class="section-numbers" id="chiffres">
    <div class="sec-inner">
        <div class="sec-header reveal" style="margin-bottom:56px;color:white">
            <div>
                <div class="sec-kicker" style="color:rgba(240,204,106,.7)">
                    <span class="material-symbols-outlined" style="font-size:13px">bar_chart</span>
                    Le GSJLF en chiffres
                </div>
                <h2 class="sec-title" style="color:white">Une communauté<br><span style="color:var(--beige);font-style:italic">qui grandit.</span></h2>
            </div>
        </div>
        <div class="numbers-grid">
            <div class="num-card reveal delay-1">
                <div class="num-big">3</div>
                <div class="num-lbl">Établissements actifs</div>
            </div>
            <div class="num-card reveal delay-2">
                <div class="num-big">100+</div>
                <div class="num-lbl">Membres inscrits</div>
            </div>
            <div class="num-card reveal delay-3">
                <div class="num-big">60+</div>
                <div class="num-lbl">Années d'expérience</div>
            </div>
            <div class="num-card reveal delay-4">
                <div class="num-big">100%</div>
                <div class="num-lbl">Sécurisé & disponible</div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     CTA
════════════════════════════════════ -->
<section class="section-cta">
    <div class="sec-inner" style="max-width:1300px;margin:0 auto">
        <div class="cta-box reveal-scale">
            <div class="cta-orb"></div>

            <div class="cta-left">
                <div class="cta-kicker">
                    <span class="material-symbols-outlined" style="font-size:13px">rocket_launch</span>
                    Rejoindre l'ENT
                </div>
                <h2 class="cta-title">Prêt à accéder à<br>votre <span>espace</span> ?</h2>
                <p class="cta-desc">Créez votre compte ou connectez-vous pour rejoindre la communauté numérique du Groupe Scolaire Jean de la Fontaine.</p>
                <div class="cta-btns">
                    <a href="/personnel/signup" class="cta-btn-primary">
                        <span class="material-symbols-outlined" style="font-size:17px">person_add</span>
                        Créer un compte
                    </a>
                    <a href="/personnel/signin" class="cta-btn-ghost">
                        <span class="material-symbols-outlined" style="font-size:17px">login</span>
                        Se connecter
                    </a>
                </div>
            </div>

            <div class="cta-right">
                <div class="cta-right-bg" style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
                <div class="cta-right-bg" style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
                <div class="cta-right-overlay"></div>
                <div class="cta-logos">
                    <div class="cta-logo-item">
                        <img src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="UAHB">
                        <div><span>UAHB</span><small>Université Amadou Hampâté Bâ</small></div>
                    </div>
                    <div class="cta-logo-item">
                        <img src="/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png" alt="CMJLF">
                        <div><span>CMJLF</span><small>Collège Moderne Jean de la Fontaine</small></div>
                    </div>
                    <div class="cta-logo-item">
                        <img src="/personnel/ressources/dist_assets/media/logos/logo_ctd.png" alt="CTD">
                        <div><span>CTD</span><small>Collège Technique de Dakar</small></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     FOOTER
════════════════════════════════════ -->
<footer>
    <div class="sec-inner" style="max-width:1300px;margin:0 auto">
        <div class="footer-grid">
            <div>
                <a href="/personnel/accueil" class="ft-brand-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF">
                    <span>Groupe Scolaire<br>Jean de la Fontaine</span>
                </a>
                <p class="ft-brand-desc">Une vision éducative moderne et inclusive, ancrée dans les valeurs africaines et l'excellence académique depuis plus de 60 ans.</p>
                <div class="ft-socials">
                    <a href="#" class="ft-social"><span class="material-symbols-outlined" style="font-size:16px">language</span></a>
                    <a href="#" class="ft-social"><span class="material-symbols-outlined" style="font-size:16px">mail</span></a>
                    <a href="#" class="ft-social"><span class="material-symbols-outlined" style="font-size:16px">phone</span></a>
                </div>
            </div>
            <div class="ft-col">
                <h4>Entités</h4>
                <a href="https://www.uahb.sn/"><span class="material-symbols-outlined" style="font-size:14px">account_balance</span>UAHB</a>
                <a href="https://www.cmjlf.uahb.sn/"><span class="material-symbols-outlined" style="font-size:14px">menu_book</span>CMJLF</a>
                <a href="https://www.ctd.uahb.sn/"><span class="material-symbols-outlined" style="font-size:14px">workspace_premium</span>CTD</a>
            </div>
            <div class="ft-col">
                <h4>Accès ENT</h4>
                <a href="/personnel/signin"><span class="material-symbols-outlined" style="font-size:14px">login</span>Connexion</a>
                <a href="/personnel/signup"><span class="material-symbols-outlined" style="font-size:14px">person_add</span>Inscription</a>
<!--                <a href="activate.html"><span class="material-symbols-outlined" style="font-size:14px">verified</span>Activation</a>-->
                <a href="/personnel/change-password"><span class="material-symbols-outlined" style="font-size:14px">lock_reset</span>Mot de passe</a>
            </div>
            <div class="ft-col">
                <h4>Contact</h4>
                <a href="#"><span class="material-symbols-outlined" style="font-size:14px">mail</span>criat@uahb.sn</a>
                <a href="#"><span class="material-symbols-outlined" style="font-size:14px">support_agent</span>Support technique</a>
                <a href="#"><span class="material-symbols-outlined" style="font-size:14px">help</span>Aide & FAQ</a>
            </div>
        </div>
        <div class="ft-bottom">
            <p>© <span id="annee_en_cours2"> </span> Groupe Scolaire Jean de la Fontaine — Tous droits réservés. Dakar, Sénégal.</p>
            <div class="ft-badge">
                <span class="material-symbols-outlined" style="font-size:13px">verified_user</span>
                Plateforme ENT · Version 2.0
            </div>
        </div>
    </div>
</footer>

<script>

    document.getElementById("annee_en_cours1").textContent = new Date().getFullYear();
    document.getElementById("annee_en_cours2").textContent = new Date().getFullYear();

    /* ══════════════════════
       NAVBAR SCROLL
    ══════════════════════ */
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 40);
    });

    /* ══════════════════════
       HERO DIAPORAMA
    ══════════════════════ */
    const HERO_SLIDES = [
        { img:'/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg',  logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',  name:'UAHB',  desc:'Université Amadou Hampâté Bâ',       color:'rgba(17,59,38,.3)' },
        { img:'/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg', logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png', name:'CMJLF', desc:'Collège Moderne Jean de la Fontaine', color:'rgba(134,76,32,.28)' },
        { img:'/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg',   logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',   name:'CTD',   desc:'Collège Technique de Dakar',          color:'rgba(45,138,87,.25)' },
    ];
    let heroCur = 0, heroTimer = null;
    const heroSlides = document.querySelectorAll('.hero-slide');
    const heroNavEl  = document.getElementById('hero-nav');

    /* Build dots */
    HERO_SLIDES.forEach((_, i) => {
        const d = document.createElement('div');
        d.className = 'hero-dot' + (i === 0 ? ' active' : '');
        d.style.width = i === 0 ? '32px' : '8px';
        d.addEventListener('click', () => goHeroSlide(i));
        heroNavEl.appendChild(d);
    });

    function getHeroDots() { return heroNavEl.querySelectorAll('.hero-dot'); }

    function goHeroSlide(n, restart = true) {
        const prev = heroCur;
        heroSlides[prev].classList.remove('active');
        getHeroDots()[prev].classList.remove('active');
        getHeroDots()[prev].style.width = '8px';

        heroCur = (n + HERO_SLIDES.length) % HERO_SLIDES.length;
        heroSlides[heroCur].classList.add('active');
        getHeroDots()[heroCur].classList.add('active');
        getHeroDots()[heroCur].style.width = '32px';

        /* couleur overlay */
        document.getElementById('hero-color').style.background = HERO_SLIDES[heroCur].color;

        /* badge entité */
        const logo = document.getElementById('heb-logo');
        const name = document.getElementById('heb-name');
        const desc = document.getElementById('heb-desc');
        const badge = document.getElementById('hero-ent-badge');
        badge.style.opacity = '0'; badge.style.transform = 'translateY(6px)';
        setTimeout(() => {
            logo.src = HERO_SLIDES[heroCur].logo;
            name.textContent = HERO_SLIDES[heroCur].name;
            desc.textContent = HERO_SLIDES[heroCur].desc;
            badge.style.transition = 'opacity .4s,transform .4s';
            badge.style.opacity = '1'; badge.style.transform = 'translateY(0)';
        }, 300);

        if (restart) { clearInterval(heroTimer); heroTimer = setInterval(() => goHeroSlide(heroCur + 1, false), 6000); }
    }

    heroTimer = setInterval(() => goHeroSlide(heroCur + 1, false), 6000);

    /* ══════════════════════
       SCROLL REVEAL
    ══════════════════════ */
    const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-scale');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    revealEls.forEach(el => observer.observe(el));

    /* ══════════════════════
       COUNTER ANIMATION
    ══════════════════════ */
    function animateCount(el, target, suffix = '') {
        let start = 0;
        const duration = 1800;
        const step = (timestamp) => {
            if (!start) start = timestamp;
            const progress = Math.min((timestamp - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const val = Math.floor(eased * target);
            el.textContent = val + suffix;
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    const numCards = document.querySelectorAll('.num-card');
    const numObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const big = entry.target.querySelector('.num-big');
                const text = big.textContent;
                if (text.includes('+')) animateCount(big, parseInt(text), '+');
                else if (text.includes('%')) animateCount(big, parseInt(text), '%');
                else if (!isNaN(parseInt(text))) animateCount(big, parseInt(text));
                numObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });
    numCards.forEach(c => numObserver.observe(c));
</script>
</body>
</html>
