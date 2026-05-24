/* ══════════════════════════════════════════════════
   LOADER GLOBAL
══════════════════════════════════════════════════ */
function showLoader(message) {
    message = message || 'Chargement en cours…';
    $('#global-loader').remove();
    $('body').append(
        '<div id="global-loader">' +
        '<div class="loader-backdrop"></div>' +
        '<div class="loader-box">' +
        '<div class="loader-spinner">' +
        '<svg viewBox="0 0 50 50">' +
        '<circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>' +
        '</svg>' +
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

/* ══════════════════════════════════════════════════
   SYNCHRONISATION LOADER (DataTable + Stats)
══════════════════════════════════════════════════ */
var _loaderSync = { dtReady: false, statsReady: false };

function _checkCloseLoader() {
    if (_loaderSync.dtReady && _loaderSync.statsReady) {
        hideLoader();
        _loaderSync.dtReady   = false;
        _loaderSync.statsReady = false;
    }
}

function _resetLoaderSync() {
    _loaderSync.dtReady   = false;
    _loaderSync.statsReady = false;
}

/* ══════════════════════════════════════════════════
   UTILITAIRES
══════════════════════════════════════════════════ */

/** Vérifie si une chaîne est un JSON valide */
function testJSON(text) {
    if (typeof text !== 'string') return false;
    try {
        return typeof JSON.parse(text) === 'object';
    } catch (e) {
        return false;
    }
}

/** Retourne l'année courante en fuseau Africa/Dakar */
function getYearInDakar() {
    return new Intl.DateTimeFormat('fr-FR', {
        timeZone: 'Africa/Dakar',
        year: 'numeric'
    }).format(new Date());
}

/**
 * Affiche une alerte SweetAlert2 centralisée
 * @param {string} icon    - 'success' | 'error' | 'warning' | 'info'
 * @param {string} title
 * @param {string} html
 * @param {Function} [onClose] - callback appelé après fermeture
 */
function showSwal(icon, title, html, onClose) {
    var isError   = (icon === 'error'   || icon === 'warning');
    var btnColor  = icon === 'success' ? '#113B26'
        : icon === 'warning' ? '#d97706'
            : '#d33';

    return Swal.fire({
        icon:               icon,
        title:              title,
        html:               html,
        confirmButtonText:  'OK',
        confirmButtonColor: btnColor,
        background:         '#fff',
        color:              '#333',
        allowOutsideClick:  false,
        allowEscapeKey:     false,
        timer:              3500,
        timerProgressBar:   true,
        showClass: { popup: isError ? 'animate__animated animate__shakeX' : 'animate__animated animate__fadeInDown' },
        hideClass: { popup: 'animate__animated animate__fadeOut' }
    }).then(function () {
        if (typeof onClose === 'function') onClose();
    });
}

/* ══════════════════════════════════════════════════
   DATATABLE
══════════════════════════════════════════════════ */
var KTDatatablesServerSide = function () {

    var dt;

    function initDatatable() {

        dt = $('#kt_datatable_utilisateurs').DataTable({

            responsive: true,

            select: {
                style:     'os',
                selector:  'td:first-child',
                className: 'row-selected'
            },

            ajax: {
                url:    '/personnel/admin-controller',
                method: 'POST',
                data:   { option: 1 },
                dataSrc: '',
                error: function () {
                    _loaderSync.dtReady = true;
                    _checkCloseLoader();
                    showSwal('error', 'Erreur', 'Impossible de charger la liste des utilisateurs.');
                }
            },

            columns: [
                { data: 'photo'       },
                { data: 'matricule'   },
                { data: 'prenom'      },
                { data: 'nom'         },
                { data: 'qualification'},
                { data: 'affectation' },
                { data: 'poste'       },
                { data: 'email'       },
                { data: 'dateCreation'},
                { data: 'etat'        },
                { data: 'tmp'         }
            ],

            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    className: 'text-end',
                    render: function (data) {
                        return '<div class="d-flex align-items-center">' +
                            '<div class="symbol symbol-45px me-5">' +
                            '<img src="' + data + '" alt="Photo" onerror="this.src=\'/assets/media/default.png\'">' +
                            '</div></div>';
                    }
                },
                {
                    targets: -1,
                    data: 'tmp',
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        var etat1 = Number(row.etat1);
                        var etat2 = Number(row.etat2);
                        if (etat2 !== 1) return '';
                        if (etat1 === 0) return '';
                        if (etat1 === 1) {
                            return '<a href="javascript:void(0)" class="btn btn-danger btn-sm"' +
                                ' onclick="actionBloquerCompte(\'' + row.tmp + '\')">' +
                                '<i class="fa fa-lock"></i> Bloquer le compte</a>';
                        }
                        if (etat1 === 2) {
                            return '<a href="javascript:void(0)" class="btn btn-success btn-sm"' +
                                ' onclick="actionDeBloquerCompte(\'' + row.tmp + '\')">' +
                                '<i class="fa fa-unlock"></i> Débloquer le compte</a>';
                        }
                        return '';
                    }
                }
            ],

            ordering: false
        });

        dt.one('draw', function () {
            _loaderSync.dtReady = true;
            _checkCloseLoader();
        });

        dt.on('draw', function () {
            KTMenu.createInstances();
        });
    }

    function handleSearchDatatable() {
        var filterSearch = document.querySelector('[data-kt-docs-table-filter="search"]');
        if (!filterSearch) return;
        filterSearch.addEventListener('keyup', function (e) {
            dt.search(e.target.value).draw();
        });
    }

    return {
        init: function () {
            initDatatable();
            handleSearchDatatable();
        }
    };

}();

KTUtil.onDOMContentLoaded(function () {
    KTDatatablesServerSide.init();
});

/* ══════════════════════════════════════════════════
   READY
══════════════════════════════════════════════════ */
$(document).ready(function () {
    _resetLoaderSync();
    showLoader('Chargement des utilisateurs…');
    loadStats();
});

/* ══════════════════════════════════════════════════
   STATS
══════════════════════════════════════════════════ */
function setStatEl(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
}

function setStatBar(pct) {
    var bar = document.getElementById('ps1-bar-fill');
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.transform  = 'scaleY(0)';
    bar.getBoundingClientRect(); // force reflow
    bar.style.transition = 'transform 0.6s ease';
    bar.style.transform  = 'scaleY(' + (pct / 100) + ')';
}

var STAT_IDS = [
    'ps1-total', 'ps1-actifs', 'ps1-inactifs', 'ps1-bloquer',
    'ps1-pct-actifs', 'ps1-pct-inactifs', 'ps1-pct-bloquer'
];

function setStatsLoading() {
    STAT_IDS.forEach(function (id) { setStatEl(id, '…'); });
    setStatBar(0);
}

function setStatsFallback(symbol) {
    STAT_IDS.forEach(function (id) { setStatEl(id, symbol || '—'); });
    setStatBar(0);
}

function loadStats() {
    setStatsLoading();
    $.ajax({
        type: 'post',
        url:  '/personnel/admin-controller',
        data: { option: 2 },
        success: function (data) {
            if (!data || !testJSON(data)) {
                setStatsFallback('—');
                _loaderSync.statsReady = true;
                _checkCloseLoader();
                return;
            }
            var json     = JSON.parse(data);
            var total    = json.total   || 0;
            var actifs   = json.actifs  || 0;
            var inactifs = json.inactifs|| 0;
            var bloques  = json.bloques || 0;

            var pctActifs   = total > 0 ? Math.round((actifs   / total) * 100) : 0;
            var pctInactifs = total > 0 ? Math.round((inactifs / total) * 100) : 0;
            var pctBloques  = total > 0 ? Math.round((bloques  / total) * 100) : 0;

            setStatEl('ps1-total',       total);
            setStatEl('ps1-actifs',      actifs);
            setStatEl('ps1-inactifs',    inactifs);
            setStatEl('ps1-bloquer',     bloques);
            setStatEl('ps1-pct-actifs',  pctActifs   + '%');
            setStatEl('ps1-pct-inactifs',pctInactifs + '%');
            setStatEl('ps1-pct-bloquer', pctBloques  + '%');
            setStatBar(pctActifs);

            _loaderSync.statsReady = true;
            _checkCloseLoader();
        },
        error: function () {
            setStatsFallback('—');
            _loaderSync.statsReady = true;
            _checkCloseLoader();
        }
    });
}

/* ══════════════════════════════════════════════════
   HELPER — recharger DataTable + Stats
══════════════════════════════════════════════════ */
function reloadAll() {
    _resetLoaderSync();
    showLoader('Mise à jour en cours…');
    setTimeout(function () {
        $('#kt_datatable_utilisateurs').DataTable().destroy();
        KTDatatablesServerSide.init();
        loadStats();
    }, 100);
}

/* ══════════════════════════════════════════════════
   ACTION — BLOQUER COMPTE
══════════════════════════════════════════════════ */
function actionBloquerCompte(tmp) {

    Swal.fire({
        title:             'Confirmation',
        text:              'Êtes-vous sûr de vouloir bloquer cet utilisateur ?',
        icon:              'warning',
        showCancelButton:  true,
        confirmButtonColor:'#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, bloquer',
        cancelButtonText:  'Annuler'
    }).then(function (result) {

        if (!result.isConfirmed) {
            showSwal('info', 'Annulée', 'Action annulée.');
            return;
        }

        showLoader("Blocage de l'utilisateur en cours…");

        $.ajax({
            type: 'post',
            url:  '/personnel/admin-controller',
            data: { option: 3, tmp: tmp },
            success: function (data) {
                hideLoader();
                if (data === 'sessionExpired') {
                    window.location.href = '/personnel/signin';
                } else if (data === 'succès') {
                    showSwal('success', 'Succès', "Le compte a été bloqué avec succès.", reloadAll);
                } else {
                    /* erreur, erreurConnexion ou tout autre cas */
                    showSwal('error', 'Erreur', "Une erreur inattendue est survenue. Veuillez réessayer.");
                }
            },
            error: function () {
                hideLoader(); /* CORRECTION : était manquant dans le code original */
                showSwal('error', 'Erreur réseau', 'Impossible de contacter le serveur.');
            }
        });
    });
}

/* ══════════════════════════════════════════════════
   ACTION — DÉBLOQUER COMPTE
══════════════════════════════════════════════════ */
function actionDeBloquerCompte(tmp) {

    Swal.fire({
        title:             'Confirmation',
        text:              'Êtes-vous sûr de vouloir débloquer cet utilisateur ?',
        icon:              'question',
        showCancelButton:  true,
        confirmButtonColor:'#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, débloquer',
        cancelButtonText:  'Annuler'
    }).then(function (result) {

        if (!result.isConfirmed) {
            showSwal('info', 'Annulée', 'Action annulée.');
            return;
        }

        showLoader("Déblocage de l'utilisateur en cours…");

        $.ajax({
            type: 'post',
            url:  '/personnel/admin-controller',
            data: { option: 4, tmp: tmp },
            success: function (data) {
                hideLoader();
                if (data === 'sessionExpired') {
                    window.location.href = '/personnel/signin';
                } else if (data === 'succès') {
                    showSwal('success', 'Succès', "Le compte a été débloqué avec succès.", reloadAll);
                } else {
                    showSwal('error', 'Erreur', "Une erreur inattendue est survenue. Veuillez réessayer.");
                }
            },
            error: function () {
                hideLoader(); /* CORRECTION : était appelé après Swal dans le code original */
                showSwal('error', 'Erreur réseau', 'Impossible de contacter le serveur.');
            }
        });
    });
}

/* ══════════════════════════════════════════════════
   MODAL — AJOUTER UTILISATEUR
══════════════════════════════════════════════════ */
function ajouterUtilisateur() {
    $('#kt_modal_add_user').modal('show');
}

function videAddUser() {
    ['matricule', 'matricule2', 'prenom', 'nom', 'email', 'password'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.value = ''; el.disabled = false; }
    });
    document.getElementById('box1').className = 'd-block';
    document.getElementById('box2').className = 'd-none';
    $('#formAddUser')[0].reset();
    $('#formAddUser2')[0].reset();
}

function closeAddUser() {
    videAddUser();
    $('#kt_modal_add_user').modal('hide');
}

/* ─── Étape 1 : saisie matricule ─── */
var form1     = document.getElementById('formAddUser');
var validator1 = FormValidation.formValidation(form1, {
    fields: {
        matricule: {
            validators: {
                notEmpty:     { message: 'Le matricule est obligatoire. Veuillez le renseigner.' },
                stringLength: { min: 6, max: 7, message: 'Le matricule doit contenir 6 ou 7 chiffres.' },
                regexp:       { regexp: /^[0-9]+$/, message: 'Le matricule doit contenir uniquement des chiffres.' }
            }
        }
    },
    plugins: {
        trigger:   new FormValidation.plugins.Trigger(),
        bootstrap: new FormValidation.plugins.Bootstrap5({
            rowSelector:     '.fv-row',
            eleInvalidClass: '',
            eleValidClass:   ''
        })
    }
});

var submitButton1 = document.getElementById('formAddUser_submit');

submitButton1.addEventListener('click', function (e) {
    e.preventDefault();
    if (!validator1) return;

    validator1.validate().then(function (status) {
        if (status !== 'Valid') return;

        submitButton1.setAttribute('data-kt-indicator', 'on');
        submitButton1.disabled = true;

        $.ajax({
            type: 'POST',
            url:  '/personnel/admin-controller',
            data: $('#formAddUser').serialize(),
            success: function (resp) {

                submitButton1.removeAttribute('data-kt-indicator');
                submitButton1.disabled = false;

                if (resp === 'sessionExpired') {
                    window.location.href = '/personnel/signin';

                } else if (resp === 'champsObligatoire') {
                    showSwal('warning', 'Champs obligatoires', "Tous les champs marqués d'un astérisque (*) sont obligatoires.");

                } else if (resp === 'erreurConnexion') {
                    showSwal('error', 'Erreur de connexion', 'Impossible de contacter le serveur. Veuillez réessayer.');

                } else if (resp === 'dejaCompte') {
                    closeAddUser();
                    showSwal('warning', 'Compte existant', "Cet utilisateur possède déjà un compte.");

                } else if (resp === 'matriculeExistsPas') {
                    showSwal('error', 'Matricule introuvable', "Ce matricule n'existe pas dans notre système.");

                } else if (testJSON(resp)) {
                    var json = JSON.parse(resp);
                    document.getElementById('matricule2').value = json.matricule;
                    document.getElementById('prenom').value     = json.prenom;
                    document.getElementById('nom').value        = json.nom;
                    document.getElementById('email').value      = json.email;
                    document.getElementById('password').value   = json.pwd;
                    document.getElementById('photoPersonnel').src = json.photo;

                    /* Verrouillage des champs en lecture seule */
                    ['matricule2', 'prenom', 'nom', 'email', 'password'].forEach(function (id) {
                        document.getElementById(id).disabled = true;
                    });

                    document.getElementById('box1').className = 'd-none';
                    document.getElementById('box2').className = 'd-block';

                } else {
                    closeAddUser();
                    showSwal('error', 'Erreur', 'Une erreur inattendue est survenue. Veuillez réessayer.');
                }
            },
            error: function () {
                submitButton1.removeAttribute('data-kt-indicator');
                submitButton1.disabled = false;
                closeAddUser();
                showSwal('error', 'Erreur serveur', 'Impossible de contacter le serveur. Veuillez réessayer.');
            }
        });
    });
});

/* ─── Étape 2 : confirmation et création ─── */
var form2      = document.getElementById('formAddUser2');
var validator2 = FormValidation.formValidation(form2, {
    fields: {
        matricule2: {
            validators: {
                notEmpty:     { message: 'Le matricule est obligatoire. Veuillez le renseigner.' },
                stringLength: { min: 6, max: 7, message: 'Le matricule doit contenir 6 ou 7 chiffres.' },
                regexp:       { regexp: /^[0-9]+$/, message: 'Le matricule doit contenir uniquement des chiffres.' }
            }
        },
        prenom:   { validators: { notEmpty: { message: 'Le prénom est obligatoire.'              } } },
        nom:      { validators: { notEmpty: { message: 'Le nom est obligatoire.'                 } } },
        email:    { validators: { notEmpty: { message: "L'adresse e-mail est obligatoire."       } } },
        password: { validators: { notEmpty: { message: 'Le mot de passe est obligatoire.'        } } }
    },
    plugins: {
        trigger:   new FormValidation.plugins.Trigger(),
        bootstrap: new FormValidation.plugins.Bootstrap5({
            rowSelector:     '.fv-row',
            eleInvalidClass: '',
            eleValidClass:   ''
        })
    }
});

var submitButton2 = document.getElementById('formAddUser2_submit');

submitButton2.addEventListener('click', function (e) {
    e.preventDefault();
    if (!validator2) return;

    validator2.validate().then(function (status) {
        if (status !== 'Valid') return;

        submitButton2.setAttribute('data-kt-indicator', 'on');
        submitButton2.disabled = true;

        /* Réactiver les champs pour la sérialisation */
        ['matricule2', 'prenom', 'nom', 'email', 'password'].forEach(function (id) {
            document.getElementById(id).disabled = false;
        });

        $.ajax({
            type: 'POST',
            url:  '/personnel/admin-controller',
            data: $('#formAddUser2').serialize(),
            success: function (resp) {

                /* Reverrouiller les champs */
                ['matricule2', 'prenom', 'nom', 'email', 'password'].forEach(function (id) {
                    document.getElementById(id).disabled = true;
                });

                submitButton2.removeAttribute('data-kt-indicator');
                submitButton2.disabled = false;

                if (resp === 'sessionExpired') {
                    window.location.href = '/personnel/signin';

                } else if (resp === 'champsObligatoire') {
                    showSwal('warning', 'Champs obligatoires', "Tous les champs obligatoires doivent être remplis.");

                } else if (resp === 'erreurConnexion') {
                    closeAddUser();
                    showSwal('error', 'Erreur de connexion', 'Impossible de contacter le serveur. Veuillez réessayer.');

                } else if (resp === 'dejaCompte') {
                    closeAddUser();
                    showSwal('warning', 'Compte existant', "Cet utilisateur possède déjà un compte.");

                } else if (resp === 'matriculeExistsPas') {
                    closeAddUser();
                    showSwal('error', 'Matricule introuvable', "Ce matricule n'existe pas dans notre système.");

                } else if (resp === 'pasContrat') {
                    closeAddUser();
                    showSwal('error', 'Aucun contrat', 'Aucun contrat trouvé. Veuillez contacter le DRH.');

                } else if (resp === 'finContrat') {
                    closeAddUser();
                    showSwal('error', 'Contrat expiré', 'Le contrat de cet utilisateur est arrivé à échéance. Veuillez contacter le DRH.');

                } else if (resp === 'erreurMail') {
                    closeAddUser();
                    showSwal('error', "Erreur d'envoi", "Erreur lors de l'envoi de l'email de confirmation. Veuillez réessayer.");

                } else if (resp === 'succès') {
                    closeAddUser();
                    showSwal('success', 'Compte créé', "Le compte de l'utilisateur a été créé avec succès.", reloadAll);

                } else {
                    closeAddUser();
                    showSwal('error', 'Erreur inattendue', 'Une erreur inattendue est survenue. Veuillez réessayer.');
                }
            },
            error: function () {
                /* Reverrouiller même en cas d'erreur réseau */
                ['matricule2', 'prenom', 'nom', 'email', 'password'].forEach(function (id) {
                    document.getElementById(id).disabled = true;
                });
                submitButton2.removeAttribute('data-kt-indicator');
                submitButton2.disabled = false;
                closeAddUser();
                showSwal('error', 'Erreur serveur', 'Impossible de contacter le serveur. Veuillez réessayer.');
            }
        });
    });
});