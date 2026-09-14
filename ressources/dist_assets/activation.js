

const resend_form = document.getElementById('resend_form');
var validator = FormValidation.formValidation(
    resend_form,
    {
        fields: {
            'email': {
                validators: {
                    emailAddress: {
                        message: 'Veuillez respecter le format de l\'email : test@gmail.com.'
                    },
                    notEmpty: {
                        message: 'Le mail est un champ obligatoire. Veuillez le résigner.'
                    }
                }
            }
        },

        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.fv-row'
            })
        }
    }
);

const submitButton = document.getElementById('btnResendActivation');
submitButton.addEventListener('click', function (e) {
   
    e.preventDefault();
    if (validator) {
        validator.validate().then(function (status) {

            if (status == 'Valid') {
          
                submitButton.setAttribute('data-kt-indicator', 'on');
                submitButton.disabled = true;
                setTimeout(function () {

                    var form_data = $("#resend_form").serialize();
                   
                    $.ajax({
                        type: 'post',
                        url: '/authentification-controller',
                        data: form_data,
                        success: function (reponse) {
                           
                            if (reponse == "succès") {
                                window.location.href = '/reactivation-reussit';

                            } else if (reponse == "champObligatoire") {
                                Swal.fire({
                                    title: "DEMANDE D'ADMISSION",
                                    text: "Les champs marqués d'un astérisque (*) sont obligatoires",
                                    icon: "warning",
                                    confirmButtonText: "OK",
                                    confirmButtonColor: "#fff309ff",
                                    timer: 7000,
                                    timerProgressBar: true,
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                    } else {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                        
                                    }
                                });
                            } else if (reponse == "erreurMail") {
                                Swal.fire({
                                    title: "DEMANDE D'ADMISSION",
                                    text: "L'e-mail n'a pas pu être envoyé, l'adresse e-mail est invalide. Veuillez vérifier et réessayer.",
                                    icon: "info",
                                    confirmButtonText: "OK",
                                    confirmButtonColor: "#ff0000",
                                    timer: 7000,
                                    timerProgressBar: true,
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                    } else {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                    }
                                });

                            } else if (reponse == "erreur") {

                                Swal.fire({
                                    title: "DEMANDE D'ADMISSION",
                                    text: "Une erreur est survenue. Veuillez réessayer ultérieurement.",
                                    icon: "info",
                                    confirmButtonText: "OK",
                                    confirmButtonColor: "#ff0000",
                                    timer: 7000,
                                    timerProgressBar: true,
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                    } else {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                    }
                                });
                            } else {
                                Swal.fire({
                                    title: "DEMANDE D'ADMISSION",
                                    text: "Une erreur est survenue. Veuillez réessayer ultérieurement.",
                                    icon: "info",
                                    confirmButtonText: "OK",
                                    confirmButtonColor: "#ff0000",
                                    timer: 7000,
                                    timerProgressBar: true,
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                    } else {
                                         submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                                        
                                    }
                                });

                            }


                        }

                    })
                }, 2000);
            }
        });
    }
});
