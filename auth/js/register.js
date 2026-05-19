/* ══════════════════════════════════════════
   DIAPORAMA
══════════════════════════════════════════ */
const SLIDES = [
    { img:'/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg',  logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',  name:'UAHB',  desc:'Université Amadou Hampâté Bâ' },
    { img:'/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg', logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png', name:'CMJLF', desc:'Collège Moderne Jean de la Fontaine' },
    { img:'/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg',   logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',   name:'CTD',   desc:'Collège Technique de Dakar' },
];

let current = 0;
let timer   = null;
const DELAY = 5000; // 5 secondes par slide

/* Créer les dots */
const dotsEl = document.getElementById('slide-dots');
SLIDES.forEach((_, i) => {
    const d = document.createElement('div');
    d.className = 'slide-dot' + (i === 0 ? ' active' : '');
    d.style.width = i === 0 ? '28px' : '8px';
    d.addEventListener('click', () => goSlide(i));
    dotsEl.appendChild(d);
});

function getDots() { return dotsEl.querySelectorAll('.slide-dot'); }

function goSlide(n, restart=true) {
    /* fade out current */
    document.getElementById('slide-' + current).classList.remove('active');
    getDots()[current].classList.remove('active');
    getDots()[current].style.width = '8px';

    current = (n + SLIDES.length) % SLIDES.length;

    /* fade in next */
    document.getElementById('slide-' + current).classList.add('active');
    const dots = getDots();
    dots[current].classList.add('active');
    dots[current].style.width = '28px';

    /* update entity info with fade */
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

    if(restart) { clearInterval(timer); timer = setInterval(nextSlide, DELAY); }
}

function nextSlide() { goSlide(current + 1, false); }

/* Démarrer le timer */
timer = setInterval(nextSlide, DELAY);

/* ══════════════════════════════════════════
   FORMULAIRE
══════════════════════════════════════════ */
function togglePw(fid, iid) {
    const f = document.getElementById(fid);
    const i = document.getElementById(iid);
    f.type = f.type === 'password' ? 'text' : 'password';
    i.textContent = f.type === 'password' ? 'visibility' : 'visibility_off';
}

function checkStrength(v) {
    let s = 0;
    if(v.length >= 8) s++;
    if(/[A-Z]/.test(v)) s++;
    if(/[0-9]/.test(v)) s++;
    if(/[^A-Za-z0-9]/.test(v)) s++;
    const pct  = [0, 25, 50, 75, 100][s];
    const col  = ['', '#e53935', '#ff9800', '#fbc02d', '#2e7d32'][s];
    const lbl  = ['—', 'Trop court', 'Faible', 'Moyen', 'Fort'][s];
    document.getElementById('sfill').style.cssText = `width:${pct}%;background:${col}`;
    document.getElementById('slabel').textContent  = lbl;
    checkMatch();

    return s;
}

function checkMatch() {
    const p1  = document.getElementById('f-pw').value;
    const p2  = document.getElementById('f-pw2').value;
    const err = document.getElementById('match-err');
    const f2  = document.getElementById('f-pw2');
    const bad = p2.length > 0 && p1 !== p2;
    err.classList.toggle('show', bad);
    f2.style.borderColor = bad ? '#e53935' : '';
    f2.style.boxShadow   = bad ? '0 0 0 4px rgba(229,57,53,.1)' : '';
}

function handleSignup() {
    const mat   = document.getElementById('f-mat').value.trim();
    const email = document.getElementById('f-email').value.trim();
    const pw    = document.getElementById('f-pw').value;
    const pw2   = document.getElementById('f-pw2').value;

    if(!mat)       { document.getElementById('f-mat').focus();   return; }
    if(!email)     { document.getElementById('f-email').focus(); return; }
    if(!pw)        { document.getElementById('f-pw').focus();    return; }
    if(pw !== pw2) { checkMatch(); document.getElementById('f-pw2').focus(); return; }

    const btn = document.getElementById('submit-btn');
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Création…';
    btn.disabled  = true;

    setTimeout(() => {
        document.getElementById('signup-form').style.display  = 'none';
        document.getElementById('success-state').style.display = 'flex';
    }, 1500);
}






function showAlert(message, type = "error", redirect = null, resetForm = false, btn = null) {


    const normalContent = `
        <span class="material-symbols-outlined" style="font-size:18px">person_add</span>
                    Créer mon compte
    `;

    Swal.fire({
        text: message,
        icon: type,
        timer: 4000,
        timerProgressBar: true,
        confirmButtonText: "OK",
        buttonsStyling: false,
        customClass: {
            confirmButton: "btn"
        },
        didOpen: () => {
            const confirmBtn = Swal.getConfirmButton();

            if (type === "success") {
                confirmBtn.style.backgroundColor = "#113B26";
                confirmBtn.style.color = "#fff";
            } else {
                confirmBtn.style.backgroundColor = "#dc3545";
                confirmBtn.style.color = "#fff";
            }
        }
    }).then(() => {

        if (resetForm) {
            $("#formSignUp")[0].reset();
        }

        // ✅ corriger ici
        if (btn) {


           // btn.removeAttribute('data-kt-indicator');
           // btn.disabled = false;

            btn.innerHTML = normalContent;
            btn.disabled = false;
        }

        if (redirect) {
            window.location.href = redirect;
        }
    });
}




const formSignUp = document.getElementById('formSignUp');
var validator = FormValidation.formValidation(
    formSignUp,
    {
        fields: {
            email: {
                validators: {
                    emailAddress: {
                        message: 'Veuillez respecter le format de l\'email : test@uahb.sn.'
                    },
                    notEmpty: {
                        message: 'Le mail est un champ obligatoire. Veuillez le résigner.'
                    },
                    callback: {
                        message: "L’adresse e-mail doit se terminer par @uahb.sn",
                        callback: function (input) {
                            const value = input.value.toLowerCase().trim();

                            if (value === "") return false;

                            return value.endsWith("@uahb.sn");
                        }
                    }
                }
            },
            matricule: {
                validators: {
                    notEmpty: {
                        message: 'Le matricule est un champ obligatoire. Veuillez le résigner.'
                    },
                    stringLength: {
                        min: 6,
                        max: 7,
                        message: 'Veuillez saisir le bon matricule composé de 7 chiffres..'
                    },
                }
            },
            password: {
                validators: {
                    notEmpty: {
                        message: "Le nouveau mot de passe est un champ obligatoire. Veuillez le résigner."
                    },
                    // callback: {
                    //   message: "Veuillez entrer un mot de passe valide",
                    //  callback: function (e) {
                    //    if (e.value.length > 0) return s()
                    // }
                    //}

                    callback: {
                        message: "Mot de passe trop faible. Utilisez au moins 8 caractères, une majuscule, un chiffre et un symbole.",
                        callback: function (input) {
                            const value = input.value;

                            const score = checkStrength(value);

                            // ❌ Refuser si < Bon
                            return score >= 3;
                        }
                    }
                }
            },
            "confirm-password": {
                validators: {
                    notEmpty: {
                        message: "Le mot de passe de confirmation est obligatoire. Veuilez le renseigner."
                    },
                    identical: {
                        compare: function () {
                            return e.querySelector('[name="password"]').value
                        },
                        message: "Le nouveau mot de passe et le mot de passe de confirmation ne correspondent pas."
                    }
                }
            }

            ,
            cgu: {
                validators: {

                    notEmpty: {
                        message: 'Les CGU sont obligatoires. Veuillez cocher la case.'
                    }
                }
            },
        },

        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.field'
            })
        }
    }
);

const t = document.getElementById('formSignUp_submit');
t.addEventListener('click', function (e) {
    e.preventDefault();
    if (validator) {
        validator.validate().then(function (status) {
            if (status == 'Valid') {
              //  t.setAttribute('data-kt-indicator', 'on');
               // t.disabled = true;

                t.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Création…';
                t.disabled  = true;

                setTimeout(function () {
                    var form_data = $("#formSignUp").serialize();
                    $.ajax({
                        type: 'post',
                        url: '/personnel/auth-controller',
                        data: form_data,
                        success: function (resp) {

                            alert(resp);

                            if (resp === "erreurConnexion") {
                                showAlert("Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t);

                            } else if (resp === "champsObligatoire") {
                                showAlert("Les champs marqués d'un astérisque (*) sont obligatoires.", "error", null, false, t);

                            } else if (resp === "dejaCompte") {
                                showAlert("Vous avez déjà un compte. Veuillez vous connecter.", "error", "/personnel/signIn", true, t);

                            } else if (resp === "matriculeExistsPas") {
                                showAlert("Ce matricule n'existe pas.", "error", null, false, t);

                            } else if (resp === "pasContrat") {
                                showAlert("Aucun contrat n’a été trouvé. Veuillez vous rapprocher du DRH.", "error", null, true, t);

                            } else if (resp === "finContrat") {
                                showAlert("Votre contrat arrive à échéance. Veuillez contacter le DRH.", "error", null, true, t);

                            } else if (resp === "erreurMail") {
                                showAlert("L’adresse e-mail n’est pas valide ou une erreur est survenue lors de l’envoi du mail.", "error", null, true, t);

                            } else if (resp === "pasCorrespondantPWD") {
                                showAlert("Les mots de passe ne correspondent pas.", "error", null, false, t);

                            } else if (resp === "pasCorrespondantEmail") {
                                showAlert("L’adresse e-mail saisie est incorrecte.", "error", null, false, t);

                            } else if (resp.substr(0, 6) === "succès") {
                               // showAlert("Compte créé avec succès !", "success", "/personnel/activate-account/"+resp.substr(6), false, t);

                                setTimeout(() => {

                                    // cacher le formulaire
                                    document.getElementById('signup-form').style.display = 'none';

                                    // afficher succès
                                    document.getElementById('success-state').style.display = 'flex';

                                    // attendre 2 secondes avant redirection
                                   // setTimeout(() => {

                                      //  window.location.href = "/personnel/accueil";

                                  //  }, 5000);

                                }, 500);

                            } else {
                                showAlert("Une erreur est survenue. Veuillez réessayer ultérieurement.", "error", null, true, t);
                            }


                        }
                    })
                }, 2000);
            }
        });
    }
});



