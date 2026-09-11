// ─── LOADER ───────────────────────────────────────────────────────────────────

function showLoader(message) {
    message = message || 'Chargement en cours…';
    $('#global-loader').remove();
    $('body').append(
        '<div id="global-loader">' +
        '<div class="loader-backdrop"></div>' +
        '<div class="loader-box">' +
        '<div class="loader-spinner">' +
        '<svg viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>' +
        '</div>' +
        '<p class="loader-msg">' + message + '</p>' +
        '</div>' +
        '</div>'
    );
    if (!$('#loader-style').length) {
        $('head').append(
            '<style id="loader-style">' +
            '#global-loader{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center}' +
            '.loader-backdrop{position:absolute;inset:0;background:rgba(4,20,11,.55);backdrop-filter:blur(4px);animation:loaderFadeIn .2s ease}' +
            '.loader-box{position:relative;z-index:1;background:#fff;border-radius:20px;padding:36px 48px;display:flex;flex-direction:column;align-items:center;gap:18px;box-shadow:0 0 0 1px rgba(17,59,38,.12),0 24px 60px rgba(0,0,0,.20);animation:loaderSlideUp .25s cubic-bezier(.34,1.56,.64,1);min-width:220px}' +
            '.loader-spinner svg{width:48px;height:48px;animation:loaderRotate .9s linear infinite}' +
            '.loader-spinner circle{stroke:#113B26;stroke-linecap:round;stroke-dasharray:80;stroke-dashoffset:60;animation:loaderDash 1.4s ease-in-out infinite}' +
            '.loader-msg{margin:0;font-size:.85rem;font-weight:600;color:#113B26;letter-spacing:.02em;text-align:center;opacity:.85}' +
            '@keyframes loaderFadeIn{from{opacity:0}to{opacity:1}}' +
            '@keyframes loaderSlideUp{from{opacity:0;transform:translateY(16px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}' +
            '@keyframes loaderRotate{to{transform:rotate(360deg)}}' +
            '@keyframes loaderDash{0%{stroke-dashoffset:80}50%{stroke-dashoffset:20}100%{stroke-dashoffset:80}}' +
            '</style>'
        );
    }
}

function hideLoader() {
    $('#global-loader').remove();
}


// ─── DATATABLE ────────────────────────────────────────────────────────────────

var KTDatatablesServerSide = function () {

    var dt;

    function initDatatable() {

        showLoader('Chargement du tableau…');

        dt = $("#table_rubrique").DataTable({
            select: {
                className: 'row-selected',
            },
            ajax: {
                url: "/personnel/chef_service_basi_controller",
                method: 'POST',
                data: { option: 1 },
                dataSrc: "",
                error: function () {
                    hideLoader();
                }
            },
            columns: [
                { data: "numero" },
                { data: "nom_rubrique" },
                { data: "date_creation" },
                { data: "createur" },
                { data: "tmp" }
            ],
            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    className: 'fw-bolder text-muted text-center',
                    render: function (data) { return data; }
                },
                {
                    targets: 1,
                    orderable: false,
                    className: 'fw-bolder text-muted text-center',
                    render: function (data) { return data; }
                },
                {
                    targets: 2,
                    orderable: false,
                    className: 'fw-bolder text-muted text-center',
                    render: function (data) { return data; }
                },
                {
                    targets: 3,
                    orderable: false,
                    className: 'fw-bolder text-muted text-center',
                    render: function (data) { return data; }
                },
                {
                    targets: -1,
                    orderable: false,
                    className: 'fw-bolder text-muted text-cente w-150pxr',
                    render: function (data, type, row) {
                        if (row.subrub_count > 0) {
                            return `<span class="badge bg-secondary text-white" title="Modification/Suppression désactivé car liée à une ou plusieurs sous-rubriques">
                                        <i class="bi bi-lock-fill"></i> Verrouillée
                                    </span>`;
                        } else {
                            let section_btn = `<button class="btn btn-sm btn-warning btn-edit" onclick="modifierRubrique('${row.tmp}', '${row.nom_rubrique.replace(/'/g, "\\'")}')">Modifier</button>`;
                            section_btn += ` <button class="btn btn-sm btn-danger btn-delete" onclick="deleteRubrique('${row.tmp}', '${row.nom_rubrique.replace(/'/g, "\\'")}')">Supprimer</button>`;
                            return section_btn;
                        }
                    }
                }
            ],
            ordering: false,
            initComplete: function () {
                hideLoader();
            }
        });

        dt.on('draw', function () {
            KTMenu.createInstances();
        });
    }

    var handleSearchDatatable = function () {
        const filterSearch = document.querySelector('[data-kt-docs-table-filter="search"]');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function (e) {
                dt.search(e.target.value).draw();
            });
        }
    };

    return {
        init: function () {
            initDatatable();
            handleSearchDatatable();
        },
        reload: function () {
            if (dt) {
                dt.ajax.reload();
            }
        }
    };

}();

KTUtil.onDOMContentLoaded(function () {
    KTDatatablesServerSide.init();
});


// ─── AJOUTER RUBRIQUE ─────────────────────────────────────────────────────────

const form1 = document.getElementById('formRubrique');

var validator1 = FormValidation.formValidation(
    form1,
    {
        fields: {
            nom_rubrique: {
                validators: {
                    notEmpty: {
                        message: "Veuillez compléter ce champ."
                    },
                    regexp: {
                        regexp: /^[\p{L}\p{N}\s'&-]+$/u,
                        message: "Les caractères spéciaux ne sont pas autorisés."
                    }
                }
            }
        },
        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.fv-row',
                eleInvalidClass: '',
                eleValidClass: ''
            })
        }
    }
);

const submitButton1 = document.getElementById('formRubrique_submit');

submitButton1.addEventListener('click', function (e) {
    e.preventDefault();

    if (validator1) {
        validator1.validate().then(function (status) {
            if (status === 'Valid') {

                submitButton1.setAttribute('data-kt-indicator', 'on');
                submitButton1.disabled = true;

                const form_data = $("#formRubrique").serialize();

                $.ajax({
                    type: "POST",
                    url: "/personnel/chef_service_basi_controller",
                    data: form_data,
                    success: function (resp) {


                        if (resp === "sessionExpired") {

                            window.location.href = '/personnel/signin';


                        } else if (resp === "caratereSpeciaux") {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Les caractères spéciaux ne sont pas autorisés.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545'
                            }).then(function () {
                                submitButton1.removeAttribute('data-kt-indicator');
                                submitButton1.disabled = false;
                            });

                        } else if (resp === "rubriqueExiste") {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Attention',
                                text: 'Le nom de la rubrique existe déjà.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#f39c12'
                            }).then(function () {
                                submitButton1.removeAttribute('data-kt-indicator');
                                submitButton1.disabled = false;
                            });

                        } else if (resp === "obligatoire") {
                            Swal.fire({
                                icon: 'error',
                                title: 'Champs obligatoires',
                                text: "Tous les champs marqués d'un astérisque (*) sont obligatoires.",
                                confirmButtonText: 'Compris',
                                confirmButtonColor: '#6c757d'
                            }).then(function () {
                                submitButton1.removeAttribute('data-kt-indicator');
                                submitButton1.disabled = false;
                            });

                        } else if (resp === "succès") {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                text: 'La rubrique a été enregistrée avec succès.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#113B26'
                            }).then(function () {
                                submitButton1.removeAttribute('data-kt-indicator');
                                submitButton1.disabled = false;
                                closeRubrique();
                                KTDatatablesServerSide.reload();
                            });

                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Une erreur inattendue est survenue. Veuillez réessayer.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545'
                            }).then(function () {
                                submitButton1.removeAttribute('data-kt-indicator');
                                submitButton1.disabled = false;
                                closeRubrique();
                                window.location.reload();
                            });
                        }
                    },
                    error: function () {
                        submitButton1.removeAttribute('data-kt-indicator');
                        submitButton1.disabled = false;
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur serveur',
                            text: 'Impossible de contacter le serveur. Veuillez réessayer.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    }
});


function videRubrique() {
    document.getElementById("nom_rubrique").value = "";
    $("#formRubrique")[0].reset();
}

function closeRubrique() {
    videRubrique();
    $("#kt_modal_new_rubrique").modal('hide');
}


// ─── MODIFIER RUBRIQUE ────────────────────────────────────────────────────────

function modifierRubrique(e1, e2) {
    // ✅ CORRIGÉ : suppression des alert(e1) et alert(e2) de debug
    document.getElementById("tmp").value = e1;
    document.getElementById("nom_rubrique_up").value = e2;
    // ✅ AJOUTÉ : on stocke le nom original pour la comparaison côté serveur
    document.getElementById("original_nom").value = e2;

    $("#kt_modal_update_rubrique").modal('show');
}


const form2 = document.getElementById('formRubriqueUpdate');

var validator2 = FormValidation.formValidation(
    form2,
    {
        fields: {
            nom_rubrique_up: {
                validators: {
                    notEmpty: {
                        message: "Veuillez compléter ce champ."
                    },
                    regexp: {
                        regexp: /^[\p{L}\p{N}\s'&-]+$/u,
                        message: "Les caractères spéciaux ne sont pas autorisés."
                    }
                }
            }
        },
        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.fv-row',
                eleInvalidClass: '',
                eleValidClass: ''
            })
        }
    }
);

const submitButton2 = document.getElementById('formRubriqueUpdate_submit');

submitButton2.addEventListener('click', function (e) {
    e.preventDefault();

    if (validator2) {
        validator2.validate().then(function (status) {
            if (status === 'Valid') {

                submitButton2.setAttribute('data-kt-indicator', 'on');
                submitButton2.disabled = true;

                const form_data = $("#formRubriqueUpdate").serialize();

                $.ajax({
                    type: "POST",
                    url: "/personnel/chef_service_basi_controller",
                    data: form_data,
                    success: function (resp) {

                        if (resp === "sessionExpired") {
                            Swal.fire({
                                icon: 'error',
                                title: 'Session expirée',
                                text: 'Votre session a expiré. Veuillez vous reconnecter.',
                                confirmButtonText: 'Se reconnecter',
                                confirmButtonColor: '#6c757d'
                            }).then(function () {
                                closeRubriqueUpdate();
                                window.location.reload();
                            });

                        } else if (resp === "obligatoire") {
                            Swal.fire({
                                icon: 'error',
                                title: 'Champs obligatoires',
                                text: "Tous les champs marqués d'un astérisque (*) sont obligatoires.",
                                confirmButtonText: 'Compris',
                                confirmButtonColor: '#6c757d'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                            });

                        } else if (resp === "caratereSpeciaux") {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Les caractères spéciaux ne sont pas autorisés.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                            });

                        } else if (resp === "rubriqueExiste") {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Attention',
                                text: 'Le nom de la rubrique existe déjà.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#f39c12'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                            });

                        } else if (resp === "sousRubriqueExiste") {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Attention',
                                text: 'Impossible de modifier le nom : cette rubrique est liée à des sous-rubriques actives.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#f39c12'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                            });

                        } else if (resp === "succès") {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                text: 'La rubrique a été modifiée avec succès.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#113B26'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                                closeRubriqueUpdate();
                                KTDatatablesServerSide.reload();
                            });

                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Une erreur inattendue est survenue. Veuillez réessayer.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                                closeRubriqueUpdate();
                                window.location.reload();
                            });
                        }
                    },
                    error: function () {
                        submitButton2.removeAttribute('data-kt-indicator');
                        submitButton2.disabled = false;
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur serveur',
                            text: 'Impossible de contacter le serveur. Veuillez réessayer.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    }
});


function videRubriqueUpdate() {
    // ✅ CORRIGÉ : vide le bon champ "nom_rubrique_up" et non "nom_rubrique"
    document.getElementById("nom_rubrique_up").value = "";
    document.getElementById("original_nom").value = "";
    $("#formRubriqueUpdate")[0].reset();
}

function closeRubriqueUpdate() {
    videRubriqueUpdate();
    $("#kt_modal_update_rubrique").modal('hide');
}


// ─── SUPPRIMER RUBRIQUE ───────────────────────────────────────────────────────

function deleteRubrique(e1, e2) {
    // ✅ AJOUTÉ : confirmation avant suppression
    Swal.fire({
        title: 'Confirmer la suppression',
        text: `Voulez-vous vraiment supprimer la rubrique "${e2}" ? Cette action est irréversible.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Annuler',
        cancelButtonColor: '#6c757d'
    }).then(function (result) {
        if (result.isConfirmed) {
            showLoader('Suppression en cours…');

            $.ajax({
                type: "POST",
                url: "/personnel/chef_service_basi_controller",
                data: { option: 4, e1: e1, e2: e2 },
                success: function (resp) {
                    // ✅ CORRIGÉ : suppression du alert(resp) de debug
                    hideLoader();

                    if (resp === "sessionExpired") {
                        Swal.fire({
                            icon: 'error',
                            title: 'Session expirée',
                            text: 'Votre session a expiré. Veuillez vous reconnecter.',
                            confirmButtonText: 'Se reconnecter',
                            confirmButtonColor: '#6c757d'
                        }).then(function () {
                            window.location.reload();
                        });

                    } else if (resp === "sousRubriqueExiste") {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Suppression impossible',
                            text: 'Cette rubrique est liée à des sous-rubriques actives et ne peut pas être supprimée.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#f39c12'
                        });

                    } else if (resp === "succès") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'La rubrique a été supprimée avec succès.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#113B26'
                        }).then(function () {
                            // ✅ CORRIGÉ : pas de référence à submitButton2 ici, simple reload
                            KTDatatablesServerSide.reload();
                        });

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur inattendue est survenue. Veuillez réessayer.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        }).then(function () {
                            window.location.reload();
                        });
                    }
                },
                error: function () {
                    hideLoader();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur serveur',
                        text: 'Impossible de contacter le serveur. Veuillez réessayer.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });
}