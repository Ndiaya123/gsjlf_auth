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
   UTILITAIRES
══════════════════════════════════════════ */
function togglePw() {
    const f = document.getElementById('pw-field');
    const i = document.getElementById('eye-icon');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.textContent = f.type === 'password' ? 'visibility' : 'visibility_off';
}

/* ══════════════════════════════════════════
   SWEETALERT2
══════════════════════════════════════════ */
function showAlert(message, type = "error", redirect = null, resetForm = false, btn = null) {
    const normalContent = `<span class="material-symbols-outlined" style="font-size:18px">login</span> Se connecter`;

    Swal.fire({
        text: message,
        icon: type,
        timer: 4000,
        timerProgressBar: true,
        confirmButtonText: "OK",
        buttonsStyling: false,
        customClass: { confirmButton: "btn" },
        didOpen: () => {
            const confirmBtn = Swal.getConfirmButton();
            confirmBtn.style.backgroundColor = type === "success" ? "#113B26" : "#dc3545";
            confirmBtn.style.color = "#fff";
        }
    }).then(() => {
        if (resetForm) document.getElementById('formSignIn').reset();
        if (btn) { btn.innerHTML = normalContent; btn.disabled = false; }
        if (redirect) window.location.href = redirect;
    });
}

/* ══════════════════════════════════════════
   VALIDATION FormValidation
══════════════════════════════════════════ */
const formSignIn = document.getElementById('formSignIn');

var validator = FormValidation.formValidation(
    formSignIn,
    {
        fields: {
            email: {
                validators: {
                    notEmpty:     { message: 'Le mail est obligatoire. Veuillez le renseigner.' },
                    emailAddress: { message: "Format invalide. Exemple : prenom.nom@uahb.sn." },
                    callback: {
                        message: "L'adresse e-mail doit se terminer par @uahb.sn",
                        callback: function (input) {
                            const value = input.value.toLowerCase().trim();
                            if (value === "") return false;
                            return value.endsWith("@uahb.sn");
                        }
                    }
                }
            },
            password: {
                validators: {
                    notEmpty: { message: "Le mot de passe est obligatoire. Veuillez le renseigner." }
                }
            }
        },
        plugins: {
            trigger:   new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.ps_field'   /* CORRECTION : était '.field' */
            })
        }
    }
);

/* ══════════════════════════════════════════
   SOUMISSION
══════════════════════════════════════════ */
const t = document.getElementById('formSignIn_submit');

t.addEventListener('click', function (e) {
    e.preventDefault();
    if (!validator) return;

    validator.validate().then(function (status) {
        if (status !== 'Valid') return;

        t.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> Connexion…`;
        t.disabled  = true;

        setTimeout(function () {
            $.ajax({
                type: 'post',
                url:  '/personnel/auth-controller',
                data: $("#formSignIn").serialize(),
                success: function (resp) {
                    console.log(resp);

                    if (resp === "erreurConnexion") {
                        showAlert("Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t);

                    } else if (resp === "champsObligatoire") {
                        showAlert("Tous les champs obligatoires doivent être remplis.", "error", null, false, t);

                    } else if (resp === "pasCompte") {
                        showAlert("Adresse e-mail ou mot de passe incorrect.", "error", null, false, t);

                    } else if (resp === "compteInactive") {
                        showAlert("Compte non activé. Veuillez cliquer sur le lien envoyé par email pour l'activer.", "error", null, true, t);

                    } else if (resp === "bloquer") {
                        showAlert("Ce compte est bloqué. Veuillez contacter le service informatique.", "error", null, true, t);

                    } else if (resp.startsWith("mdp")) {

                        window.location.href = resp.substr(3);

                    } else if (resp.startsWith("succès")) {

                        alert(resp);
                        alert(resp.substr(0, 6));
                        setTimeout(() => {
                            document.getElementById('main-form').style.display   = 'none';
                            document.getElementById('success-state').style.display = 'flex';
                            setTimeout(() => { window.location.href = resp.substr(6); }, 2000);
                        }, 500);

                    } else {
                        showAlert("Une erreur inattendue est survenue. Veuillez réessayer.", "error", null, true, t);
                    }
                },
                error: function () {
                    showAlert("Erreur réseau. Vérifiez votre connexion et réessayez.", "error", null, false, t);
                }
            });
        }, 2000);
    });
});