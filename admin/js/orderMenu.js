/**
 * ============================================================================
 *  Gestion de l'ordre d'affichage des menus et des tâches (par application)
 * ============================================================================
 *  Dépendances :
 *    - jQuery (déjà chargé par plugins.bundle.js / scripts.bundle.js)
 *    - SortableJS (https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js)
 *
 *  À inclure sur la page admin-ordre-menu.php, après jQuery et SortableJS.
 * ============================================================================
 */

// TODO: remplacer par l'URL réelle de votre controller.php
// (celle utilisée par les autres pages admin, ex : ajouterSousMenu(), etc.)
const CONTROLLER_URL = "/personnel/admin/controller.php";

let sortableTopLevel = null;
let sortablesSousMenu = [];

/* ------------------------------------------------------------------------ *
 *  Loader (fourni)
 * ------------------------------------------------------------------------ */
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
            '#global-loader{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;}' +
            '.loader-backdrop{position:absolute;inset:0;background:rgba(4,20,11,.55);backdrop-filter:blur(4px);animation:loaderFadeIn .2s ease;}' +
            '.loader-box{position:relative;z-index:1;background:#fff;border-radius:20px;padding:36px 48px;display:flex;flex-direction:column;align-items:center;gap:18px;box-shadow:0 0 0 1px rgba(17,59,38,.12),0 24px 60px rgba(0,0,0,.20);animation:loaderSlideUp .25s cubic-bezier(.34,1.56,.64,1);min-width:220px;}' +
            '.loader-spinner svg{width:48px;height:48px;animation:loaderRotate .9s linear infinite;}' +
            '.loader-spinner circle{stroke:#113B26;stroke-linecap:round;stroke-dasharray:80;stroke-dashoffset:60;animation:loaderDash 1.4s ease-in-out infinite;}' +
            '.loader-msg{margin:0;font-size:.85rem;font-weight:600;color:#113B26;letter-spacing:.02em;text-align:center;opacity:.85;}' +
            '@keyframes loaderFadeIn{from{opacity:0;}to{opacity:1;}}' +
            '@keyframes loaderSlideUp{from{opacity:0;transform:translateY(16px) scale(.97);}to{opacity:1;transform:translateY(0) scale(1);}}' +
            '@keyframes loaderRotate{to{transform:rotate(360deg);}}' +
            '@keyframes loaderDash{0%{stroke-dashoffset:80;}50%{stroke-dashoffset:20;}100%{stroke-dashoffset:80;}}' +
            '</style>'
        );
    }
}

function hideLoader() {
    $('#global-loader').remove();
}

/* ------------------------------------------------------------------------ *
 *  Petit toast de notification (succès / erreur)
 * ------------------------------------------------------------------------ */
function showToast(type, message) {
    if (!$('#toast-style').length) {
        $('head').append(
            '<style id="toast-style">' +
            '#ordre-toast{position:fixed;top:20px;right:20px;z-index:10000;padding:14px 22px;border-radius:10px;' +
            'font-size:.9rem;font-weight:600;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,.18);animation:toastIn .2s ease;}' +
            '#ordre-toast.success{background:#113B26;}' +
            '#ordre-toast.error{background:#b71c1c;}' +
            '@keyframes toastIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}' +
            '</style>'
        );
    }
    $('#ordre-toast').remove();
    $('body').append('<div id="ordre-toast" class="' + (type === 'success' ? 'success' : 'error') + '">' + message + '</div>');
    setTimeout(function () {
        $('#ordre-toast').fadeOut(300, function () {
            $(this).remove();
        });
    }, 2500);
}

/* ------------------------------------------------------------------------ *
 *  Chargement de la liste des applications (option = 23)
 * ------------------------------------------------------------------------ */
function chargerApplications() {
    $.post(CONTROLLER_URL, { option: 23 }, function (html) {
        $('#selectApplication').html(html);
    }).fail(function () {
        showToast('error', "Impossible de charger la liste des applications.");
    });
}

/* ------------------------------------------------------------------------ *
 *  Chargement de l'arbre sous-menus / tâches pour l'application choisie
 *  (option = 32, sousAction = 'get')
 * ------------------------------------------------------------------------ */
function chargerOrdreMenu(idAppli) {
    if (!idAppli) {
        $('#zoneOrdreMenu').html(
            '<div class="text-muted text-center py-10">Sélectionnez une application pour afficher son menu.</div>'
        );
        $('#btnEnregistrerOrdre').prop('disabled', true);
        return;
    }

    showLoader('Chargement du menu…');

    $.post(CONTROLLER_URL, { option: 32, sousAction: 'get', idAppli: idAppli }, function (reponse) {
        hideLoader();

        let data;
        try {
            data = (typeof reponse === 'string') ? JSON.parse(reponse) : reponse;
        } catch (e) {
            showToast('error', "Réponse invalide du serveur.");
            return;
        }

        if (!data || !data.success) {
            $('#zoneOrdreMenu').html('<div class="text-muted text-center py-10">Aucun menu trouvé pour cette application.</div>');
            $('#btnEnregistrerOrdre').prop('disabled', true);
            return;
        }

        renderOrdreMenu(data.items || []);
        $('#btnEnregistrerOrdre').prop('disabled', false);

    }, 'json').fail(function () {
        hideLoader();
        showToast('error', "Erreur lors du chargement du menu.");
    });
}

/* ------------------------------------------------------------------------ *
 *  Construction du HTML de la liste triable à partir des items reçus
 * ------------------------------------------------------------------------ */
function renderOrdreMenu(items) {
    if (!items.length) {
        $('#zoneOrdreMenu').html('<div class="text-muted text-center py-10">Aucun sous-menu ni tâche pour cette application.</div>');
        return;
    }

    let html = '<ul id="listeOrdreMenu" class="ordre-menu-list">';

    items.forEach(function (item) {
        if (item.type === 'sousMenu') {
            html += '<li class="ordre-item ordre-item-sousmenu" data-type="sousMenu" data-id="' + item.id + '">';
            html += '  <div class="ordre-item-header">';
            html += '    <span class="drag-handle-top" title="Déplacer">&#9776;</span>';
            html += '    <span class="ordre-item-icon">' + (item.icon || '') + '</span>';
            html += '    <span class="ordre-item-label">' + escapeHtml(item.nom) + '</span>';
            html += '    <span class="badge bg-light-primary ms-2">Sous-menu</span>';
            html += '  </div>';
            html += '  <ul class="ordre-sub-list" data-parent-id="' + item.id + '">';

            (item.taches || []).forEach(function (t) {
                html += '<li class="ordre-item ordre-item-tache" data-type="tache" data-id="' + t.id + '">';
                html += '  <span class="drag-handle-sub" title="Déplacer">&#9776;</span>';
                html += '  <span class="ordre-item-label">' + escapeHtml(t.nom) + '</span>';
                html += '</li>';
            });

            html += '  </ul>';
            html += '</li>';

        } else if (item.type === 'tache') {
            html += '<li class="ordre-item ordre-item-tache-libre" data-type="tache" data-id="' + item.id + '">';
            html += '  <span class="drag-handle-top" title="Déplacer">&#9776;</span>';
            html += '  <span class="ordre-item-label">' + escapeHtml(item.nom) + '</span>';
            html += '  <span class="badge bg-light-secondary ms-2">Tâche</span>';
            html += '</li>';
        }
    });

    html += '</ul>';

    $('#zoneOrdreMenu').html(html);
    initSortables();
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/* ------------------------------------------------------------------------ *
 *  Initialisation de SortableJS : une liste de premier niveau (sous-menus +
 *  tâches libres) et une liste indépendante par sous-menu (ses tâches).
 *  Les tâches ne changent jamais de sous-menu : on ne réordonne qu'à
 *  l'intérieur de chaque liste.
 * ------------------------------------------------------------------------ */
function initSortables() {
    if (sortableTopLevel) {
        sortableTopLevel.destroy();
        sortableTopLevel = null;
    }
    sortablesSousMenu.forEach(function (s) { s.destroy(); });
    sortablesSousMenu = [];

    const topEl = document.getElementById('listeOrdreMenu');
    if (!topEl) return;

    sortableTopLevel = new Sortable(topEl, {
        handle: '.drag-handle-top',
        animation: 150,
        ghostClass: 'ordre-item-ghost'
    });

    document.querySelectorAll('.ordre-sub-list').forEach(function (subEl) {
        sortablesSousMenu.push(new Sortable(subEl, {
            handle: '.drag-handle-sub',
            animation: 150,
            ghostClass: 'ordre-item-ghost'
        }));
    });
}

/* ------------------------------------------------------------------------ *
 *  Lecture de l'ordre courant du DOM → tableau à envoyer au serveur
 * ------------------------------------------------------------------------ */
function collectionOrdreDOM() {
    const items = [];

    $('#listeOrdreMenu > li').each(function () {
        const $li = $(this);
        const type = $li.data('type');
        const id = $li.data('id');

        if (type === 'sousMenu') {
            const taches = [];
            $li.find('> .ordre-sub-list > li').each(function () {
                taches.push($(this).data('id'));
            });
            items.push({ type: 'sousMenu', id: id, taches: taches });
        } else if (type === 'tache') {
            items.push({ type: 'tache', id: id });
        }
    });

    return items;
}

/* ------------------------------------------------------------------------ *
 *  Enregistrement du nouvel ordre (option = 32, sousAction = 'save')
 * ------------------------------------------------------------------------ */
function enregistrerOrdre() {
    const idAppli = $('#selectApplication').val();
    if (!idAppli) {
        showToast('error', "Veuillez sélectionner une application.");
        return;
    }

    const items = collectionOrdreDOM();
    if (!items.length) {
        showToast('error', "Rien à enregistrer.");
        return;
    }

    showLoader('Enregistrement de l\'ordre…');

    $.post(CONTROLLER_URL, {
        option: 32,
        sousAction: 'save',
        idAppli: idAppli,
        items: JSON.stringify(items)
    }, function (reponse) {
        hideLoader();

        if (String(reponse).indexOf('succès') !== -1 || String(reponse).indexOf('succes') !== -1) {
            showToast('success', "Ordre enregistré avec succès.");
        } else {
            showToast('error', "Erreur lors de l'enregistrement de l'ordre.");
        }
    }).fail(function () {
        hideLoader();
        showToast('error', "Erreur réseau lors de l'enregistrement.");
    });
}

/* ------------------------------------------------------------------------ *
 *  Initialisation de la page
 * ------------------------------------------------------------------------ */
$(document).ready(function () {
    chargerApplications();

    $('#btnEnregistrerOrdre').prop('disabled', true);

    $('#selectApplication').on('change', function () {
        chargerOrdreMenu($(this).val());
    });

    $('#btnEnregistrerOrdre').on('click', function () {
        enregistrerOrdre();
    });
});