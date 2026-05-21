document.getElementById("annee_en_cours1").textContent = new Date().getFullYear();


/* ══════════════════════════════════════════
   DIAPORAMA
══════════════════════════════════════════ */
const SLIDES = [
    { logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',  name:'UAHB',  desc:'Université Amadou Hampâté Bâ' },
    { logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png', name:'CMJLF', desc:'Collège Moderne Jean de la Fontaine' },
    { logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',   name:'CTD',   desc:'Collège Technique de Dakar' },
];

let cur = 0, timer = null;
const slides = document.querySelectorAll('.ps_slide');
const dotsEl = document.getElementById('slide-dots');

SLIDES.forEach((_, i) => {
    const d = document.createElement('div');
    d.className = 'ps_slide-dot' + (i === 0 ? ' ps_active' : '');
    d.style.width = i === 0 ? '28px' : '8px';
    d.addEventListener('click', () => goSlide(i));
    dotsEl.appendChild(d);
});

function getDots() { return dotsEl.querySelectorAll('.ps_slide-dot'); }

function goSlide(n, restart = true) {
    slides[cur].classList.remove('ps_active');
    getDots()[cur].classList.remove('ps_active');
    getDots()[cur].style.width = '8px';

    cur = (n + SLIDES.length) % SLIDES.length;

    slides[cur].classList.add('ps_active');
    getDots()[cur].classList.add('ps_active');
    getDots()[cur].style.width = '28px';

    const el = document.getElementById('ent-logo');
    const en = document.getElementById('ent-name');
    const ed = document.getElementById('ent-desc');
    [el, en, ed].forEach(x => { x.style.transition = 'opacity .3s'; x.style.opacity = '0'; });
    setTimeout(() => {
        el.src = SLIDES[cur].logo;
        en.textContent = SLIDES[cur].name;
        ed.textContent = SLIDES[cur].desc;
        [el, en, ed].forEach(x => x.style.opacity = '1');
    }, 300);

    if (restart) { clearInterval(timer); timer = setInterval(() => goSlide(cur + 1, false), 5000); }
}

timer = setInterval(() => goSlide(cur + 1, false), 5000);

/* ══════════════════════════════════════════
   NAVIGATION PHASES
══════════════════════════════════════════ */
function goPhase(n) {
    [1, 2, 3].forEach(i => {
        const el = document.getElementById('phase' + i);
        if (el) {
            el.style.display = i === n ? 'block' : 'none';
            if (i === n) el.className = 'ps_phase';
        }
    });
}

/* ══════════════════════════════════════════
   UTILITAIRES MOT DE PASSE
══════════════════════════════════════════ */
function togglePw(fid, iid) {
    const f = document.getElementById(fid);
    const i = document.getElementById(iid);
    f.type = f.type === 'password' ? 'text' : 'password';
    i.textContent = f.type === 'password' ? 'visibility' : 'visibility_off';
}

function checkStrength(v) {
    let s = 0;
    if (v.length >= 8)          s++;
    if (/[A-Z]/.test(v))        s++;
    if (/[0-9]/.test(v))        s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const f  = document.getElementById('sfill');
    const sl = document.getElementById('slabel');
    if (f)  { f.style.width = [0,25,50,75,100][s] + '%'; f.style.background = ['','#e53935','#ff9800','#fbc02d','#2e7d32'][s]; }
    if (sl) sl.textContent = ['—','Trop court','Faible','Moyen','Fort'][s];
    checkMatch();
    return s;
}



function checkMatch() {
    const p1  = document.getElementById('pw1') ? document.getElementById('pw1').value : '';
    const p2  = document.getElementById('pw2') ? document.getElementById('pw2').value : '';
    const err = document.getElementById('match-err');
    const f2  = document.getElementById('pw2');
    if (!p2 || !err) return;
    const bad = p2.length > 0 && p1 !== p2;
    err.classList.toggle('ps_show', bad);
    f2.style.borderColor = bad ? '#e53935' : '';
    f2.style.boxShadow   = bad ? '0 0 0 4px rgba(229,57,53,.1)' : '';
}

function handleReset() {
    const p1 = document.getElementById('pw1').value;
    const p2 = document.getElementById('pw2').value;
    if (!p1 || !p2) return;
    if (p1 !== p2) { checkMatch(); document.getElementById('pw2').focus(); return; }
    const btn = document.getElementById('btn-reset');
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> Enregistrement…';
    btn.disabled  = true;
    setTimeout(() => {
        [1, 2, 3].forEach(i => { const el = document.getElementById('phase' + i); if (el) el.style.display = 'none'; });
        document.getElementById('success-state').style.display = 'flex';
    }, 1300);
}



/* ══════════════════════════════════════════
   RECHARGEMENT PAGE (bouton "Renvoyer")
══════════════════════════════════════════ */
function refreshPage() {
    location.reload(true);
}

/* ══════════════════════════════════════════
   SWEETALERT2
══════════════════════════════════════════ */


function showAlert(title,message, type = "error", redirect = null, resetForm = false, btn = null,nomBtn = "OK") {
    const normalContent = `<span class="material-symbols-outlined" style="font-size:18px">send</span> Envoyer le lien`;

    Swal.fire({
        title : title,
        text: message,
        icon: type,
        timer: 4000,
        timerProgressBar: true,
        confirmButtonText: nomBtn,
        buttonsStyling: false,
        customClass: { confirmButton: "btn" },
        didOpen: () => {
            const confirmBtn = Swal.getConfirmButton();
            const colors = {
                success: "#113B26",
                warning: "#ffc107",
                error: "#dc3545",
                info: "#0d6efd",
                question: "#6c757d"
            };
            confirmBtn.style.backgroundColor =
                colors[type] || "#0d6efd";

            // confirmBtn.style.backgroundColor = type === "success" ? "#113B26" : "#dc3545";
            confirmBtn.style.color = "#fff";
        }
    }).then(() => {
        if (resetForm)
        {
            document.getElementById('formReset').reset();
        }
        if (btn) { btn.innerHTML = normalContent; btn.disabled = false; }
        if (redirect) window.location.href = redirect;
    });
}

/* ══════════════════════════════════════════
   VALIDATION FormValidation
══════════════════════════════════════════ */
const formReset = document.getElementById('formReset');

var validator = FormValidation.formValidation(
    formReset,
    {
        fields: {
            email: {
                validators: {
                    notEmpty: {
                        message: 'Le mail est obligatoire. Veuillez le renseigner.'
                    },
                    emailAddress: {
                        message: "Format invalide. Exemple : prenom.nom@uahb.sn."
                    },
                    callback: {
                        message: "L'adresse e-mail doit se terminer par @uahb.sn",
                        callback: function (input) {
                            const value = input.value.toLowerCase().trim();

                            if (value === "") {
                                return false;
                            }

                            return value.endsWith("@uahb.sn");
                        }
                    }
                }
            },
            matricule: {
                validators: {
                    notEmpty: {
                        message: 'Le matricule est obligatoire. Veuillez le renseigner.'
                    },
                    stringLength: {
                        min: 6,
                        max: 7,
                        message: 'Le matricule doit contenir 6 ou 7 chiffres.'
                    },
                    regexp: {
                        regexp: /^[0-9]+$/,
                        message: 'Le matricule doit contenir uniquement des chiffres.'
                    }
                }
            }
        },
        plugins: {
            trigger:   new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({ rowSelector: '.ps_field' })
        }
    }
);

/* ══════════════════════════════════════════
   SOUMISSION
══════════════════════════════════════════ */
const t = document.getElementById('formReset_submit');

t.addEventListener('click', function (e) {
    e.preventDefault();
    if (!validator) return;

    validator.validate().then(function (status) {
        if (status !== 'Valid') return;

        t.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> Envoi en cours…';
        t.disabled  = true;

        setTimeout(function () {
            $.ajax({
                type: 'post',
                url:  '/personnel/auth-controller',
                data: $("#formReset").serialize(),

                success: function (resp) {
                    if      (resp === "erreurConnexion")    showAlert("Erreur de connexion","Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t,"OK");
                    else if (resp === "champsObligatoire")  showAlert("Champs obligatoires","Tous les champs obligatoires doivent être remplis.", "error", null, false, t,"OK");
                    else if (resp === "pasCompte")         showAlert("Compte introuvable", "Aucun compte n'a été trouvé. Veuillez vous inscrire pour continuer.", "warning", "/personnel/signup", true, t, "S'inscrire");
                    else if (resp === "compteInactive")         showAlert("Compte non activé", "Votre compte n’a pas encore été activé. Veuillez vérifier le mail d’activation dans votre boîte Gmail pour procéder à l’activation. Sans cela, vous ne pourrez pas réinitialiser votre mot de passe en cas de problème. Contactez le service informatique à l’adresse criat@uahb.sn.", "warning", null, true, t, "OK");
                    else if (resp === "bloquer") showAlert("Ce compte est bloqué.","Veuillez contacter le service informatique.", "error", null, false, t,"OK");
                    else if (resp === "pasContrat")         showAlert("Aucun contrat","Aucun contrat trouvé. Veuillez contacter le DRH.", "error", null, true, t,"OK");
                    else if (resp === "finContrat")         showAlert("Contrat expiré","Votre contrat arrive à échéance. Veuillez contacter le DRH.", "error", null, true, t,"OK");
                    else if (resp === "erreurMail")         showAlert("Erreur d’envoi","Erreur lors de l'envoi de l'email de confirmation. Veuillez réessayer ultérieurement.", "error", null, true, t,"OK");
                     else if (resp === "succès") {
                        /* Afficher l'email dans la phase 2 */
                        const emailVal = document.getElementById('email1').value;
                        const emailDisplay = document.getElementById('email-display');
                        if (emailDisplay) emailDisplay.textContent = emailVal;

                        setTimeout(() => {
                            document.getElementById('phase1').style.display = 'none';
                            document.getElementById('phase2').style.display = 'block';
                        }, 500);
                    } else {
                        showAlert( "Erreur inattendue","Une erreur inattendue est survenue. Veuillez réessayer.", "error", null, true, t);
                    }
                },
                error: function () {
                    showAlert("Erreur réseau", "Vérifiez votre connexion et réessayez.", "error", null, false, t);
                }
            });
        }, 2000);
    });
});