/* ══════════════════════════════════════════
   ANNÉE EN COURS
══════════════════════════════════════════ */
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
const dotsEl  = document.getElementById('slide-dots');

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
   OTP — saisie cellule par cellule
══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', otpSetup);

function otpSetup() {
    document.querySelectorAll('.ps_otp-cell').forEach((inp, i, arr) => {
        inp.addEventListener('input', () => {
            inp.value = inp.value.replace(/\D/, '');
            inp.classList.toggle('ps_filled', !!inp.value);
            if (inp.value && arr[i + 1]) arr[i + 1].focus();
        });
        inp.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !inp.value && arr[i - 1]) {
                arr[i - 1].focus();
                arr[i - 1].value = '';
                arr[i - 1].classList.remove('ps_filled');
            }
        });
    });
}

/* ══════════════════════════════════════════
   SWEETALERT2
══════════════════════════════════════════ */
function showAlert(title, message, type = "error", redirect = null, resetForm = false, btn = null, nomBtn = "OK") {
    const normalContent = `<span class="material-symbols-outlined" style="font-size:13px;vertical-align:middle">refresh</span> Renvoyer le lien`;

    const colors = {
        success:  "#113B26",
        warning:  "#ffc107",
        error:    "#dc3545",
        info:     "#0d6efd",
        question: "#6c757d"
    };

    Swal.fire({
        title,
        text: message,
        icon: type,
        timer: 4000,
        timerProgressBar: true,
        confirmButtonText: nomBtn,
        buttonsStyling: false,
        customClass: { confirmButton: "btn" },
        didOpen: () => {
            const confirmBtn = Swal.getConfirmButton();
            confirmBtn.style.backgroundColor = colors[type] || "#0d6efd";
            confirmBtn.style.color = "#fff";
        }
    }).then(() => {
        if (btn) { btn.innerHTML = normalContent; btn.disabled = false; }
        if (redirect) window.location.href = redirect;
    });
}

/* ══════════════════════════════════════════
   RESET ACTIVATION — renvoi du lien
══════════════════════════════════════════ */
function resetActivation() {
    const matricule = document.getElementById("matricule").value;
    const t         = document.getElementById('btnResetActivation');

    t.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> En cours…';
    t.disabled  = true;

    $.ajax({
        type: 'post',
        url:  '/personnel/auth-controller',
        data: { option: 3, matricule: matricule },
        success: function (resp) {
            if      (resp === "erreurConnexion") showAlert("Erreur de connexion",  "Erreur de connexion. Veuillez réessayer ultérieurement.",                          "error",   null,                null, false, t, "OK");
            else if (resp === "dejaActive")      showAlert("Compte déjà actif",    "Votre compte est déjà activé. Connectez-vous pour accéder à votre espace.",         "warning", "/personnel/signin", null, true,  t, "Se connecter");
            else if (resp === "pasContrat")      showAlert("Aucun contrat",        "Aucun contrat trouvé. Veuillez contacter le DRH.",                                  "error",   null,                null, true,  t, "OK");
            else if (resp === "finContrat")      showAlert("Contrat expiré",       "Votre contrat arrive à échéance. Veuillez contacter le DRH.",                       "error",   null,                null, true,  t, "OK");
            else if (resp === "erreurMail")      showAlert("Erreur d'envoi",       "Erreur lors de l'envoi de l'email de confirmation. Veuillez réessayer.",            "error",   null,                null, true,  t, "OK");
            else if (resp === "succès") {
                setTimeout(() => {
                    document.getElementById('success-state').style.display      = 'none';
                    document.getElementById('success-state-mail').style.display = 'flex';
                }, 500);
            } else {
                showAlert("Erreur inattendue", "Une erreur inattendue est survenue. Veuillez réessayer.", "error", null, null, true, t, "OK");
            }
        },
        error: function () {
            showAlert("Erreur réseau", "Vérifiez votre connexion et réessayez.", "error", null, null, false, t, "OK");
        }
    });
}