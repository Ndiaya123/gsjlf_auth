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


$(document).ready(function () {


    $.ajax({
        type: 'post',
        url: '/personnel/chef_service_basi_controller',
        data: { option: 6 },
        success: function (resp) {
            if (resp === "erreur") {
                $('#rubrique').html("");
                $('#rubrique_up').html("");


            } else if (resp.substr(0, 7) === "<option") {
                $('#rubrique').html(resp);
                $('#rubrique_up').html(resp);

            } else {
                $('#rubrique').html("");
                $('#rubrique_up').html("");

            }

        }
    });


});


// ─── DATATABLE ────────────────────────────────────────────────────────────────

var KTDatatablesServerSide = function () {

    var dt;

    function initDatatable() {

        showLoader('Chargement du tableau…');

        dt = $("#table_sous_rubrique").DataTable({
            select: {
                className: 'row-selected',
            },
            ajax: {
                url: "/personnel/chef_service_basi_controller",
                method: 'POST',
                data: { option: 5 },
                dataSrc: "",
                error: function () {
                    hideLoader();
                }
            },
            columns: [
                { data: "numero" },
                { data: "nom_sous_rubrique" },
                { data: "nom_rubrique" },
                { data: "date_creation" },
                { data: "createur" },
                { data: "tmp" }
            ],
            columnDefs: [
                {
                    // N° — discret, aligné au centre
                    targets: 0,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        return '<span style="color:#9ca3af;font-weight:700;">' + data + '</span>';
                    }
                },
                {
                    // Nom — mis en avant, c'est l'information principale
                    targets: 1,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        return '<span style="color:#111827;font-weight:700;">' + data + '</span>';
                    }
                },
                {
                    // Rubrique parente
                    targets: 2,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        return '<span style="">' + data + '</span>';
                    }
                },
                {
                    targets: 3,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        return '<span style="">' + data + '</span>';
                    }
                },
                {
                    targets: 4,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        return '<span style="">' + data + '</span>';
                    }
                },
                {
                    targets: -1,
                    orderable: false,
                    className: 'text-center w-150px',
                    render: function (data, type, row) {
                        if (row.subrub_count > 0) {
                            return '<span class="dga-badge-locked" title="Modification/Suppression désactivé car liée à une ou plusieurs sous-rubriques">' +
                                '<i class="bi bi-lock-fill"></i> Verrouillée' +
                                '</span>';
                        } else {
                            let section_btn = `<button class="dga-btn-modifier btn-edit" onclick="modifierSousRubrique('${row.tmp}', '${row.nom_sous_rubrique.replace(/'/g, "\\'")}', '${row.rubrique}')">Modifier</button>`;
                            section_btn += ` <button class="dga-btn-supprimer btn-delete" onclick="deleteRubrique('${row.tmp}', '${row.nom_sous_rubrique.replace(/'/g, "\\'")}')">Supprimer</button>`;
                            return section_btn;
                        }
                    }
                }
            ],
            ordering: false,
            initComplete: function () {

                    document.documentElement.classList.remove('ld-booting');
                    document.getElementById('lb-table')?.classList.add('lb-ready');


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

const form1 = document.getElementById('formSousRubrique');

var validator1 = FormValidation.formValidation(
    form1,
    {
        fields: {
            nom_sous_rubrique: {
                validators: {
                    notEmpty: {
                        message: "Veuillez compléter ce champ."
                    },
                    regexp: {
                        regexp: /^[^§!$£*#@~"[\]{}:;<>\\|\/?^=()%\-+]*$/,
                        message: "Les caractères spéciaux ne sont pas autorisés."
                    }
                }
            },
            rubrique: {
                validators: {
                    notEmpty: {
                        message: "Veuillez compléter ce champ."
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

const submitButton1 = document.getElementById('formSousRubrique_submit');

submitButton1.addEventListener('click', function (e) {
    e.preventDefault();

    if (validator1) {
        validator1.validate().then(function (status) {
            if (status === 'Valid') {

                submitButton1.setAttribute('data-kt-indicator', 'on');
                submitButton1.disabled = true;

                const form_data = $("#formSousRubrique").serialize();

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
                                text: 'Une sous-rubrique portant ce nom existe déjà dans cette rubrique.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#f39c12'
                            }).then(function () {
                                submitButton1.removeAttribute('data-kt-indicator');
                                submitButton1.disabled = false;
                            });

                        } else if (resp === "rubriqueParentNo") {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Attention',
                                text: 'Rubrique parente non trouvée ou inactive.',
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
                                closeSousRubrique();
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
                                closeSousRubrique();
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


function videSousRubrique() {
    document.getElementById("nom_sous_rubrique").value = "";
    $('#rubrique').val('').trigger('change');
    $("#formSousRubrique")[0].reset();
}

function closeSousRubrique() {
    videSousRubrique();
    $("#kt_modal_new_sous_rubrique").modal('hide');
}


// ─── MODIFIER RUBRIQUE ────────────────────────────────────────────────────────

function modifierSousRubrique(e1, e2, e3) {
    document.getElementById("tmp").value = e1;
    document.getElementById("nom_sous_rubrique_up").value = e2;
    document.getElementById("original_nom").value = e2;
    $('#rubrique_up').val(e3).trigger('change');

    $("#kt_modal_update_rubrique").modal('show');
}


const form2 = document.getElementById('formSousRubriqueUpdate');

var validator2 = FormValidation.formValidation(
    form2,
    {
        fields: {
            nom_sous_rubrique_up: {
                validators: {
                    notEmpty: {
                        message: "Veuillez compléter ce champ."
                    },
                    regexp: {
                        regexp: /^[^§!$£*#@~"[\]{}:;<>\\|\/?^=()%\-+]*$/,
                        message: "Les caractères spéciaux ne sont pas autorisés."
                    }
                }
            },
            rubrique_up: {
                validators: {
                    notEmpty: {
                        message: "Veuillez compléter ce champ."
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

const submitButton2 = document.getElementById('formSousRubriqueUpdate_submit');

submitButton2.addEventListener('click', function (e) {
    e.preventDefault();

    if (validator2) {
        validator2.validate().then(function (status) {
            if (status === 'Valid') {

                submitButton2.setAttribute('data-kt-indicator', 'on');
                submitButton2.disabled = true;

                const form_data = $("#formSousRubriqueUpdate").serialize();

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
                                closeSousRubriqueUpdate();
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
                                text: 'Une sous-rubrique portant ce nom existe déjà dans cette rubrique.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#f39c12'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                            });

                        }else if (resp === "rubriqueParentNo") {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Attention',
                                text: 'Rubrique parente non trouvée ou inactive.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#f39c12'
                            }).then(function () {
                                submitButton2.removeAttribute('data-kt-indicator');
                                submitButton2.disabled = false;
                            });

                        } else if (resp === "ligneBudgetExiste") {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Attention',
                                text: 'Impossible de renommer cette sous-rubrique, car elle est liée à une ou plusieurs lignes budgétaires actives.',
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
                                closeSousRubriqueUpdate();
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
                                closeSousRubriqueUpdate();
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


function videSousRubriqueUpdate() {
    document.getElementById("nom_sous_rubrique_up").value = "";
    document.getElementById("original_nom").value = "";
    $('#rubrique_up').val('').trigger('change');

    $("#formSousRubriqueUpdate")[0].reset();
}

function closeSousRubriqueUpdate() {
    videSousRubriqueUpdate();
    $("#kt_modal_update_rubrique").modal('hide');
}


// ─── SUPPRIMER RUBRIQUE ───────────────────────────────────────────────────────

function deleteRubrique(e1, e2) {
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
                data: { option: 9, e1: e1, e2: e2 },
                success: function (resp) {
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

                    } else if (resp === "ligneBudgetExiste") {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Suppression impossible',
                            text: 'Impossible de supprimer cette sous-rubrique : elle est liée à une ou plusieurs lignes budgétaires actives.',
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