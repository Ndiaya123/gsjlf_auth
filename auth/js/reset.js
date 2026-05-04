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
            $("#formReset")[0].reset();
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


const formReset = document.getElementById('formReset');
var validator = FormValidation.formValidation(
    formReset,
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
            password: {
                validators: {
                    notEmpty: {
                        message: "Le nouveau mot de passe est un champ obligatoire. Veuillez le résigner."
                    }
                }
            }
        },

        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.ff'
            })
        }
    }
);

const t = document.getElementById('formReset_submit');
t.addEventListener('click', function (e) {
    e.preventDefault();
    if (validator) {
        validator.validate().then(function (status) {
            if (status == 'Valid') {
                t.setAttribute('data-kt-indicator', 'on');
                t.disabled = true;
                setTimeout(function () {
                    var form_data = $("#formReset").serialize();

                    alert(form_data);
                    $.ajax({
                        type: 'post',
                        url: '/personnel/auth-controller',
                        data: form_data,
                        success: function (resp) {

                            alert("connection");
                            alert(resp);


                            if (resp === "erreurConnexion") {

                                showAlert("Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t);

                            } else if (resp === "champsObligatoire") {

                                showAlert("Les champs marqués d'un astérisque (*) sont obligatoires.", "error", null, false, t);

                            } else if (resp === "pasCompte") {

                                showAlert("Adresse e-mail ou mot de passe incorrect", "error", null, false, t);

                            } else if (resp === "compteInactive") {

                                showAlert("Le compte n’est pas encore activé. Veuillez cliquer sur le lien envoyé par mail pour l’activer.", "error", null, true, t);

                            } else if (resp.substr(0, 6) === "succès") {

                                window.location.href = "/personnel/acceuil";

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


