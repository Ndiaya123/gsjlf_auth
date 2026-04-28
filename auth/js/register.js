function togglePwd(btn, id) {
    const i = document.getElementById(id); const show = i.type === 'password'; i.type = show ? 'text' : 'password';
    btn.innerHTML = show
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}
function strength(v) {
    const sb = document.getElementById('sb'),
        st = document.getElementById('st');

    if (!sb) return 0;

    let s = 0;

    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;

    sb.className = 'sbar' + (s ? ' s' + s : '');
    st.textContent = v.length ? ['', 'Faible', 'Moyen', 'Bon', 'Excellent'][s] : '';

    return s; // ✅ IMPORTANT
}
function otpSetup() {
    document.querySelectorAll('.otp-cell').forEach((inp, i, arr) => {
        inp.addEventListener('input', () => {
            inp.value = inp.value.replace(/\D/, '');
            inp.classList.toggle('filled', !!inp.value);
            if (inp.value && arr[i + 1]) arr[i + 1].focus();
        });
        inp.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !inp.value && arr[i - 1]) { arr[i - 1].focus(); arr[i - 1].value = ''; arr[i - 1].classList.remove('filled') }
        });
    });
}
function goStep(n) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    const p = document.getElementById('p' + n); if (p) p.classList.add('active');
    document.querySelectorAll('.sd').forEach((d, i) => {
        d.className = 'sd';
        if (i + 1 < n) d.classList.add('done');
        else if (i + 1 === n) d.classList.add('active');
    });
    if (n === 2) { const e = document.getElementById('ei'), s = document.getElementById('esh'); if (e && s) s.textContent = e.value || 'votre email' }
}
function activate() {
    document.getElementById('sp').style.display = 'none';
    const s = document.getElementById('ss'); s.style.display = 'block';
    s.style.animation = 'fadeUp .5s var(--ease) both';
}
document.addEventListener('DOMContentLoaded', otpSetup);



function showAlert(message, type = "error", redirect = null, resetForm = false, btn = null) {

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
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
        }

        if (redirect) {
            window.location.href = redirect;
        }
    });
}



// partie form button


var KTPasswordResetNewPassword = function () {
    var e, t, r, o, s = function () {
        return 80 <= o.getScore()
    };
    return {
        init: function () {
            e = document.querySelector("#formSignUp"),
                t = document.querySelector("#formSignUp_submit"),
                o = KTPasswordMeter.getInstance(e.querySelector('[data-kt-password-meter="true"]')),
                r = FormValidation.formValidation(e, {
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

                                        const score = strength(value);

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
                        trigger: new FormValidation.plugins.Trigger({
                            event: {
                                password: !1
                            }
                        }),
                        bootstrap: new FormValidation.plugins.Bootstrap5({
                            rowSelector: ".ff",
                            eleInvalidClass: "",
                            eleValidClass: ""
                        })
                    }
                }),
                t.addEventListener("click", (function (s) {
                    s.preventDefault(),
                        r.revalidateField("password"),
                        r.validate().then((function (r) {
                            if (r == "Valid") {
                                t.setAttribute('data-kt-indicator', 'on');
                                t.disabled = true;
                                setTimeout(function () {

                                    var form_data = $("#formSignUp").serialize();

                                    alert(form_data);
                                    alert("papa");

                                    $.ajax({
                                        type: 'post',
                                        url: '/personnel/auth-controller',
                                        data: form_data,
                                        success: function (resp) {
                                            alert(resp);

// if (resp == "erreurConnexion") {
//                                                 Swal.fire({
//                                                     text: "Erreur de connexion. Veuillez réessayer ultérieurement.",
//                                                     icon: "info",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-danger"
//                                                     }
//                                                 }).then((value) => {
//                                                     $("#formSignUp")[0].reset();
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             } else if (resp === "champsObligatoire") {
//                                                 Swal.fire({
//                                                     text: "Les champs marqués d'un astérisque (*) sont obligatoires",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-danger"
//                                                     }
//                                                 }).then((value) => {
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             }else if (resp == "dejaCompte") {
//                                                 Swal.fire({
//                                                     text: "Vous avez déjà un compte.Veuillez-vous connecter",
//                                                     icon: "info",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-danger"
//                                                     }
//                                                 }).then((value) => {
//                                                     $("#formSignUp")[0].reset();
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                     window.location.href = "/personnel/page-de-connexion";
//                                                 });

//                                             } else if (resp == "matriculeExistsPas") {
//                                                 Swal.fire({
//                                                     text: "Ce matricule n'existe pas.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             } else if (resp == "pasContrat") {
//                                                 Swal.fire({
//                                                     text: "Aucun contrat n’a été trouvé. Veuillez vous rapprocher du DRH.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     $("#formSignUp")[0].reset();
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             }else if (resp == "finContrat") {
//                                                 Swal.fire({
//                                                     text: "Votre contrat arrive à échéance. Veuillez contacter le DRH.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     $("#formSignUp")[0].reset();
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             } else if (resp == "erreurMail") {
//                                                 Swal.fire({
//                                                     text: "L’adresse e-mail n’est pas valide ou une erreur est survenue lors de l’envoi du mail.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     $("#formSignUp")[0].reset();
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             } else if (resp == "pasCorrespondantPWD") {
//                                                 Swal.fire({
//                                                     text: "Le nouveau mot de passe et le mot de passe de confirmation ne correspondent pas.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             } else if (resp == "pasCorrespondantEmail") {
//                                                 Swal.fire({
//                                                     text: "L’adresse e-mail saisie est incorrecte. Merci de la vérifier.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             }
//                                             else if (resp == "succès") {

//                                                 window.location = "/personnel/activate-account";
//                                             }
//                                             else if (resp == "erreur") {
//                                                 Swal.fire({
//                                                     text: "Une erreure est survenue lors du traitement.Veuillez réessayer ultérieurement.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     $("#formSignUp")[0].reset();
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             } else {
//                                                 Swal.fire({
//                                                     text: "Une erreure est survenue. Veuillez réessayer ultérieurement.",
//                                                     icon: "error",
//                                                     buttonsStyling: false,
//                                                     confirmButtonText: "quitter",
//                                                     customClass: {
//                                                         confirmButton: "btn btn-primary"
//                                                     }
//                                                 }).then((value) => {
//                                                     t.removeAttribute('data-kt-indicator');
//                                                     t.disabled = false;
//                                                 });

//                                             }


                                            if (resp == "erreurConnexion") {
                                                showAlert("Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t);

                                            } else if (resp === "champsObligatoire") {
                                                showAlert("Les champs marqués d'un astérisque (*) sont obligatoires.", "error", null, false, t);

                                            } else if (resp == "dejaCompte") {
                                                showAlert("Vous avez déjà un compte. Veuillez vous connecter.", "error", "/personnel/page-de-connexion", true, t);

                                            } else if (resp == "matriculeExistsPas") {
                                                showAlert("Ce matricule n'existe pas.", "error", null, false, t);

                                            } else if (resp == "pasContrat") {
                                                showAlert("Aucun contrat n’a été trouvé. Veuillez vous rapprocher du DRH.", "error", null, true, t);

                                            } else if (resp == "finContrat") {
                                                showAlert("Votre contrat arrive à échéance. Veuillez contacter le DRH.", "error", null, true, t);

                                            } else if (resp == "erreurMail") {
                                                showAlert("L’adresse e-mail n’est pas valide ou une erreur est survenue lors de l’envoi du mail.", "error", null, true, t);

                                            } else if (resp == "pasCorrespondantPWD") {
                                                showAlert("Les mots de passe ne correspondent pas.", "error", null, false, t);

                                            } else if (resp == "pasCorrespondantEmail") {
                                                showAlert("L’adresse e-mail saisie est incorrecte.", "error", null, false, t);

                                            } else if (resp == "succès") {
                                                showAlert("Compte créé avec succès !", "success", "/personnel/activate-account", false, t);

                                            } else {
                                                showAlert("Une erreur est survenue. Veuillez réessayer ultérieurement.", "error", null, true, t);
                                            }


                                        }
                                    })


                                }, 2000);
                            }
                        }))
                })),
                e.querySelector('input[name="password"]').addEventListener("input", (function () {
                    this.value.length > 0 && r.updateFieldStatus("password", "NotValidated")
                }))
        }
    }
}();
KTUtil.onDOMContentLoaded((function () {
    KTPasswordResetNewPassword.init()
}));