/* ══════════════════════════════════════════
   DIAPORAMA
══════════════════════════════════════════ */
document.getElementById("annee_en_cours1").textContent = new Date().getFullYear();


const SLIDES = [
    { img:'/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg',  logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',  name:'UAHB',  desc:'Université Amadou Hampâté Bâ' },
    { img:'/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg', logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png', name:'CMJLF', desc:'Collège Moderne Jean de la Fontaine' },
    { img:'/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg',   logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',   name:'CTD',   desc:'Collège Technique de Dakar' },
];

let current = 0;
let timer   = null;
const DELAY = 5000;

const dotsEl = document.getElementById('slide-dots');
SLIDES.forEach((_, i) => {
    const d = document.createElement('div');
    d.className = 'ps_slide-dot' + (i === 0 ? ' ps_active' : '');
    d.style.width = i === 0 ? '28px' : '8px';
    d.addEventListener('click', () => goSlide(i));
    dotsEl.appendChild(d);
});

function getDots() { return dotsEl.querySelectorAll('.ps_slide-dot'); }

function goSlide(n, restart=true) {
    document.getElementById('slide-' + current).classList.remove('ps_active');
    getDots()[current].classList.remove('ps_active');
    getDots()[current].style.width = '8px';

    current = (n + SLIDES.length) % SLIDES.length;

    document.getElementById('slide-' + current).classList.add('ps_active');
    const dots = getDots();
    dots[current].classList.add('ps_active');
    dots[current].style.width = '28px';

    const entLogo = document.getElementById('ent-logo');
    const entName = document.getElementById('ent-name');
    const entDesc = document.getElementById('ent-desc');
    [entLogo, entName, entDesc].forEach(el => {
        el.style.transition = 'opacity .3s';
        el.style.opacity = '0';
    });
    setTimeout(() => {
        entLogo.src = SLIDES[current].logo;
        entName.textContent = SLIDES[current].name;
        entDesc.textContent = SLIDES[current].desc;
        [entLogo, entName, entDesc].forEach(el => { el.style.opacity = '1'; });
    }, 300);

    if (restart) { clearInterval(timer); timer = setInterval(nextSlide, DELAY); }
}

function nextSlide() { goSlide(current + 1, false); }
timer = setInterval(nextSlide, DELAY);

/* ══════════════════════════════════════════
   FORMULAIRE — utilitaires
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
    const pct = [0, 25, 50, 75, 100][s];
    const col = ['', '#e53935', '#ff9800', '#fbc02d', '#2e7d32'][s];
    const lbl = ['—', 'Trop court', 'Faible', 'Moyen', 'Fort'][s];
    document.getElementById('sfill').style.cssText = `width:${pct}%;background:${col}`;
    document.getElementById('slabel').textContent  = lbl;
    checkMatch();
    return s;
}

function resetStrength() {
    document.getElementById('sfill').style.width = '0%';
    document.getElementById('sfill').style.background = '';
    document.getElementById('slabel').textContent = '—';
}
function checkMatch() {
    const p1  = document.getElementById('f-pw').value;
    const p2  = document.getElementById('f-pw2').value;
    const err = document.getElementById('match-err');
    const f2  = document.getElementById('f-pw2');
    const bad = p2.length > 0 && p1 !== p2;
    err.classList.toggle('ps_show', bad);
    f2.style.borderColor = bad ? '#e53935' : '';
    f2.style.boxShadow   = bad ? '0 0 0 4px rgba(229,57,53,.1)' : '';
}

/* ══════════════════════════════════════════
   SWEETALERT2
══════════════════════════════════════════ */
function showAlert(title,message, type = "error", redirect = null, resetForm = false, btn = null,nomBtn = "OK") {
    const normalContent = `<span class="material-symbols-outlined" style="font-size:18px">person_add</span> Créer mon compte`;

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
            document.getElementById('formSignUp').reset();
            resetStrength();
        }
        if (btn) { btn.innerHTML = normalContent; btn.disabled = false; }
        if (redirect) window.location.href = redirect;
    });
}

/* ══════════════════════════════════════════
   VALIDATION FormValidation
══════════════════════════════════════════ */
const formSignUp = document.getElementById('formSignUp');

var validator = FormValidation.formValidation(
    formSignUp,
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
            },
            password: {
                validators: {
                    notEmpty: { message: "Le mot de passe est obligatoire. Veuillez le renseigner." },
                    callback: {
                        message: "Mot de passe trop faible. Minimum : 8 caractères, une majuscule, un chiffre et un symbole.",
                        callback: function (input) {
                            return checkStrength(input.value) >= 3; // Moyen ou Fort
                        }
                    }
                }
            },
            "confirm-password": {
                validators: {
                    notEmpty: { message: "La confirmation du mot de passe est obligatoire." },
                    identical: {
                        /* CORRECTION BUG : utiliser formSignUp.querySelector (pas 'e' inexistant) */
                        compare: function () {
                            return formSignUp.querySelector('[name="password"]').value;
                        },
                        message: "Les mots de passe ne correspondent pas."
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
const t = document.getElementById('formSignUp_submit');

t.addEventListener('click', function (e) {
    e.preventDefault();
    if (!validator) return;

    validator.validate().then(function (status) {
        if (status !== 'Valid') return;

        t.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> Création…';
        t.disabled  = true;

        setTimeout(function () {
            $.ajax({
                type: 'post',
                url:  '/personnel/auth-controller',
                data: $("#formSignUp").serialize(),
                success: function (resp) {
                    if      (resp === "erreurConnexion")    showAlert("Erreur de connexion","Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t,"OK");
                    else if (resp === "champsObligatoire")  showAlert("Champs obligatoires","Tous les champs obligatoires doivent être remplis.", "error", null, false, t,"OK");
                    else if (resp === "dejaCompte")         showAlert("Compte existant","Vous avez déjà un compte. Veuillez vous connecter.", "warning", "/personnel/signin", true, t,"Se connecter");
                    else if (resp === "matriculeExistsPas") showAlert("Matricule introuvable","Ce matricule n'existe pas dans notre système.", "error", null, false, t,"OK");
                    else if (resp === "emailInvalide") showAlert("E-mail incorrect","Veuillez renseigner une adresse e-mail valide se terminant par @uahb.sn.", "error", null, false, t,"OK");
                    else if (resp === "passwordCourt") showAlert("Mot de passe trop court","Le mot de passe doit contenir au moins 8 caractères.", "error", null, false, t,"OK");
                    else if (resp === "pasContrat")         showAlert("Aucun contrat","Aucun contrat trouvé. Veuillez contacter le DRH.", "error", null, true, t,"OK");
                    else if (resp === "finContrat")         showAlert("Contrat expiré","Votre contrat arrive à échéance. Veuillez contacter le DRH.", "error", null, true, t,"OK");
                    else if (resp === "erreurMail")         showAlert("Erreur d’envoi","Erreur lors de l'envoi de l'email de confirmation. Veuillez réessayer ultérieurement.", "error", null, true, t,"OK");
                    else if (resp === "pasCorrespondantPWD")   showAlert("Mots de passe différents","Les mots de passe ne correspondent pas.", "error", null, false, t,"OK");
                    else if (resp === "pasCorrespondantEmail") showAlert("E-mail incorrect","L'adresse e-mail saisie est incorrecte.", "error", null, false, t,"OK");
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