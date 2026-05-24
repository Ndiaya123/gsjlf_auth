
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

            '#global-loader{' +
                'position:fixed;' +
                'inset:0;' +
                'z-index:9999;' +
                'display:flex;' +
                'align-items:center;' +
                'justify-content:center;' +
            '}' +

            '.loader-backdrop{' +
                'position:absolute;' +
                'inset:0;' +
                'background:rgba(4,20,11,.55);' +
                'backdrop-filter:blur(4px);' +
                'animation:loaderFadeIn .2s ease;' +
            '}' +

            '.loader-box{' +
                'position:relative;' +
                'z-index:1;' +
                'background:#fff;' +
                'border-radius:20px;' +
                'padding:36px 48px;' +
                'display:flex;' +
                'flex-direction:column;' +
                'align-items:center;' +
                'gap:18px;' +
                'box-shadow:0 0 0 1px rgba(17,59,38,.12),0 24px 60px rgba(0,0,0,.20);' +
                'animation:loaderSlideUp .25s cubic-bezier(.34,1.56,.64,1);' +
                'min-width:220px;' +
            '}' +

            '.loader-spinner svg{' +
                'width:48px;' +
                'height:48px;' +
                'animation:loaderRotate .9s linear infinite;' +
            '}' +

            '.loader-spinner circle{' +
                'stroke:#113B26;' +
                'stroke-linecap:round;' +
                'stroke-dasharray:80;' +
                'stroke-dashoffset:60;' +
                'animation:loaderDash 1.4s ease-in-out infinite;' +
            '}' +

            '.loader-msg{' +
                'margin:0;' +
                'font-size:.85rem;' +
                'font-weight:600;' +
                'color:#113B26;' +
                'letter-spacing:.02em;' +
                'text-align:center;' +
                'opacity:.85;' +
            '}' +

            '@keyframes loaderFadeIn{' +
                'from{opacity:0;}' +
                'to{opacity:1;}' +
            '}' +

            '@keyframes loaderSlideUp{' +
                'from{' +
                    'opacity:0;' +
                    'transform:translateY(16px) scale(.97);' +
                '}' +
                'to{' +
                    'opacity:1;' +
                    'transform:translateY(0) scale(1);' +
                '}' +
            '}' +

            '@keyframes loaderRotate{' +
                'to{transform:rotate(360deg);}' +
            '}' +

            '@keyframes loaderDash{' +
                '0%{stroke-dashoffset:80;}' +
                '50%{stroke-dashoffset:20;}' +
                '100%{stroke-dashoffset:80;}' +
            '}' +

            '</style>'
        );
    }
}

function hideLoader() {
    $('#global-loader').remove();
}

var _loaderSync = {
    dtReady: false,
    statsReady: false
};

function _checkCloseLoader() {

    if (_loaderSync.dtReady && _loaderSync.statsReady) {

        hideLoader();

        _loaderSync.dtReady = false;
        _loaderSync.statsReady = false;
    }
}

function _resetLoaderSync() {

    _loaderSync.dtReady = false;
    _loaderSync.statsReady = false;
}

function testJSON(text) {

    if (typeof text !== "string") {
        return false;
    }

    try {

        const json = JSON.parse(text);

        return (typeof json === 'object');

    } catch (e) {

        return false;
    }
}

// ==================================================
// DATATABLE
// ==================================================
var KTDatatablesServerSide = function () {

    var dt;

    function initDatatable() {

        dt = $("#kt_table_sous_menu").DataTable({

            responsive: true,

            processing: true,

            select: {
                style: 'os',
                selector: 'td:first-child',
                className: 'row-selected'
            },

            ajax: {

                url: "/personnel/admin-controller",

                method: "POST",

                data: {
                    option: 7
                },

                dataSrc: "",

                error: function () {

                    _loaderSync.dtReady = true;

                    _checkCloseLoader();

                    Swal.fire(
                        'Erreur',
                        "Impossible de charger la liste des sous menus.",
                        'error'
                    );
                }
            },

            columns: [
                { data: "icon",
                    className: 'text-center',
                    render: function (data, type, row) {

                        return data;
                    }
                },                { data: "nom_s",
                    className: 'text-center',
                    render: function (data, type, row) {

                        return data;
                    }
                    },

                {
                    data: "id",
                    className: 'text-center',
                    render: function (data, type, row) {

                        return `
<a href="javascript:void(0)"
class="btn btn-warning btn-sm max-w-150px"
onclick="get_detail_sous_menu(${data})">
    <i class="fa fa-edit"></i> Modifier
</a>
`;
                    }
                }
            ],

            ordering: false,

            createdRow: function (row, data) {

                $(row)
                    .find('td:eq(2)')
                    .attr('data-filter', data.id);
            }
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

        const filterSearch = document.querySelector('[data-kt-docs-table-filter="search"]');

        if (!filterSearch) {
            return;
        }

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

// ==================================================
// READY
// ==================================================
$(document).ready(function () {

    _resetLoaderSync();

    showLoader("Chargement des sous menus...");

    $.ajax({
        type: 'POST',
        url: '/personnel/admin-controller',
        data: { option: 8 },

        success: function (data) {

            if (typeof data === 'string' && data.substr(0, 4) === "icon") {

                $('#boxIcon1').html(data.substr(4));
                $('#boxIcon2').html(data.substr(4));

            } else {

                $('#boxIcon1').html('');
                $('#boxIcon2').html('');
            }

            _loaderSync.statsReady = true;

            _checkCloseLoader();
        },

        error: function () {

            _loaderSync.statsReady = true;

            _checkCloseLoader();

            Swal.fire(
                'Erreur',
                "Impossible de charger les icônes.",
                'error'
            );
        }
    });
});

// ==================================================
// FUNCTION ACTION
// ==================================================


function get_detail_sous_menu(id) {

    alert(id);

    // Supprimer les anciennes sélections
    const allIcons = document.querySelectorAll('.icon-item, .dropdown-item');

    allIcons.forEach(icon => {
        icon.classList.remove('active');
    });

    // Champs du formulaire
    let id_sous_menu = document.getElementById("id_sous_menu");
    let nom = document.getElementById("nomTacheToEdit");
    let id_icon = document.getElementById("selectedIconIdToEdit");
    let ancienIcon = document.getElementById("ancienIcon");

    // Affectation ID
    id_sous_menu.value = id;

    // Requête POST
    fetch('/personnel/admin-controller', {

        method: "POST",

        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },

        body: new URLSearchParams({
            option: 9,
            id: id
        })
    })

        .then((response) => response.text())

        .then((responseText) => {


            // Vérifier si la réponse est un JSON valide
            if (!testJSON(responseText)) {

                Swal.fire(
                    'Erreur',
                    'Le serveur a renvoyé une réponse invalide.',
                    'error'
                );

                return;
            }

            // Conversion JSON
            const data = JSON.parse(responseText);


            // Vérification données
            if (data === null || typeof data !== 'object') {

                Swal.fire(
                    'Erreur',
                    'Aucune donnée trouvée.',
                    'error'
                );

                return;
            }

            // Remplissage formulaire
            nom.value = data.nom || '';

            id_icon.value = data.idIcon || '';

            ancienIcon.innerHTML = data.icon || '';

            // Sélection active icône
            const selectedIcon = document.querySelector(
                `.icon-item[data-id="${data.idIcon}"]`
            );

            if (selectedIcon) {
                selectedIcon.classList.add('active');
            }

            // Ouvrir modal si nécessaire
            $('#edit_sous_menu').modal('show');
        })

        .catch((error) => {


            Swal.fire(
                'Erreur',
                'Impossible de récupérer les informations.',
                'error'
            );
        });
}



// Ajout de sous menu
function addSousMenu(event) {
    event.preventDefault(); // Empêcher le rechargement de la page

    const nom = document.getElementById('nom').value; // Récupérer la valeur du nom
    const idIcon = document.getElementById('selectedIconId').value; // Récupérer l'ID de l'icône sélectionnée
    const form = event.target;
    if (!idIcon) {
        const iconInput = document.getElementById('selectedIconId');
        iconInput.classList.add('is-invalid'); // Ajoute une classe d'erreur si le champ est vide
        return;
    }
    if (!nom) {
        const nomInput = document.getElementById('nom');
        nomInput.classList.add('is-invalid'); // Ajoute une classe d'erreur si le champ est vide
    }
    if (nom && idIcon) {
        // Sélectionner tous les boutons de soumission des formulaires
        const submitButton = document.querySelector('.submitAddSousMenuButton');
        submitButton.disabled = true;

        // Optionnel : Ajouter un indicateur de chargement
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> En cours...';

        const data = {
            action: "add_sous_menu",
            nom: nom,
            idIcon: idIcon,
        };

        fetch("/personnel/admin-controller", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams(data).toString()
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Network response was not ok");
                }
                return response.json(); // Utilisez `text()` pour inspecter la réponse brute
            })
            .then((result) => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText; // Réinitialiser le texte du bouton
                console.log('Résultat:', result);
                if(result.status == 'success'){

                    Swal.fire({
                        position: "center",
                        icon: "success",
                        title: "Sous-menu ajouté avec succès",
                        showConfirmButton: false,
                        timer: 1500
                    });
                    window.location.reload()

                    const modal = bootstrap.Modal.getInstance(document.getElementById("service-modal"));
                    if (modal) {
                        modal.hide(); // Ferme le modal si une instance existe
                    }
                    // déactiver le li qui est déjà sélectionné
                    const selectedLi = document.querySelector('.dropdown-item.active');
                    if (selectedLi) {
                        selectedLi.classList.remove('active'); // Supprime la classe 'active' de l'icône sélectionnée
                    }
                    KTDatatablesSousMenu.init(); // Rafraîchir la liste des sous-menus
                    form.reset();  // Réinitialise tous les champs du formulaire
                    document.getElementById('selectedIconId').value = '';
                }else{
                    Swal.fire({
                        position: "center",
                        icon: 'info',
                        title: result.message,
                        showConfirmButton: true,
                    });
                }
            })
            .catch((error) => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'ajout du sous-menu.',
                });
            });
    }
}

function ajouterSousMenu()
{

    $('#add_sous_menu').modal('show');


}

function selectIcon(iconId, element) {
    // Supprimer la classe "active" de toutes les icônes
    const allIcons = document.querySelectorAll('.icon-item, .dropdown-item'); // Correction : Séparateur correct entre les sélecteurs
    allIcons.forEach(icon => icon.classList.remove('active'));

    // Ajouter la classe "active" à l'icône sélectionnée
    element.classList.add('active');
    console.log("Id icon :", iconId);
    document.getElementById("selectedIconId").value = iconId;
    document.getElementById("selectedIconIdToEdit").value = iconId;
    // document.getElementById("id_icon").value = iconId;
   // document.getElementById("id_icon_tache").value = iconId;
  //  console.log(document.getElementById("id_icon_tache").value);
}