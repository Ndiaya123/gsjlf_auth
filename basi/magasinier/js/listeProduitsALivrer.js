/**
 * listeProduitsLivres.js
 * Page "Liste des produits livrés" (profil Magasinier) : historique des
 * produits remis aux demandeurs (une ligne = un produit d'un bon de sortie)
 * avec l'état de réception côté demandeur (Reçu / présumé / en attente /
 * partiel / écart).
 *
 * Filtres : "Année de début" (vide par défaut = pas de borne basse explicite)
 * et "Année de fin" (par défaut le 31/12 de l'année en cours). Sans début,
 * le serveur prend le 1er janvier de l'année de fin : par défaut, les
 * produits livrés dans l'année en cours. "Tout l'historique" lève la borne.
 */

const MAGASINIER_CONTROLLER_URL = '/personnel/magasinier_basi_controller'; // ← ajuster selon le chemin réel

let plv_table = null;
let plv_type = 'fonct';   // 'fonct' (option 7) | 'invest' (option 8)
let plv_tout = false;

const PLV_STATUTS = {
    recu:       { cls: 'plv-b-recu',     lbl: 'Reçu' },
    presumee:   { cls: 'plv-b-presumee', lbl: 'Reçu (présumé)' },
    attente:    { cls: 'plv-b-attente',  lbl: 'En attente' },
    partiel:    { cls: 'plv-b-partiel',  lbl: 'Partiellement reçu' },
    ecart:      { cls: 'plv-b-ecart',    lbl: 'Écart signalé' },
    clos_ecart: { cls: 'plv-b-clos',     lbl: 'Clos avec écart' },
};

document.addEventListener('DOMContentLoaded', function () {
    plv_reinitialiserFiltres();
    plv_renderTable([]);

    document.querySelectorAll('#plv-switch-type .dga-switch-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            plv_type = btn.dataset.cible;
            document.querySelectorAll('#plv-switch-type .dga-switch-btn').forEach(function (b) {
                b.classList.toggle('dga-switch-active', b === btn);
            });
            plv_majLibelles();
            plv_charger();
        });
    });
    document.getElementById('plv-btn-filtrer').addEventListener('click', function () { plv_tout = false; plv_charger(); });
    document.getElementById('plv-btn-annee').addEventListener('click', function () { plv_reinitialiserFiltres(); plv_charger(); });
    document.getElementById('plv-btn-tout').addEventListener('click', function () {
        plv_tout = true;
        document.getElementById('plv-date-debut').value = '';
        plv_charger();
    });
    ['plv-date-debut', 'plv-date-fin'].forEach(function (id) {
        document.getElementById(id).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { plv_tout = false; plv_charger(); }
        });
    });

    plv_majLibelles();
    plv_charger();
});

/** Début : vide (null). Fin : 31/12 de l'année en cours. */
function plv_reinitialiserFiltres() {
    plv_tout = false;
    document.getElementById('plv-date-debut').value = '';
    document.getElementById('plv-date-fin').value = new Date().getFullYear() + '-12-31';
}

function plv_majLibelles() {
    const f = plv_type === 'fonct';
    document.getElementById('plv-th-dest').textContent = f ? 'Demandeur' : 'Direction';
    document.getElementById('plv-titre-liste').textContent = 'Produits livrés — ' + (f ? 'Fonctionnement' : 'Investissement');
}

/* ────────────────────────── CHARGEMENT ────────────────────────── */
function plv_charger() {
    const debut = document.getElementById('plv-date-debut').value;
    const fin   = document.getElementById('plv-date-fin').value;
    if (debut && fin && debut > fin) {
        Swal.fire('Dates incohérentes', 'La date de début doit être antérieure ou égale à la date de fin.', 'warning');
        return;
    }
    plv_showLoader('Chargement…');
    $.ajax({
        url: MAGASINIER_CONTROLLER_URL, method: 'POST', dataType: 'json',
        data: { option: plv_type === 'fonct' ? 7 : 8, date_debut: debut, date_fin: fin, tout: plv_tout ? 1 : 0 }
    }).done(function (res) {
        plv_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || "Impossible de charger l'historique.", 'error');
            return;
        }
        plv_renderTable(res.data || []);
        plv_renderStats(res.stats || {});
        plv_renderPeriode(res);
    }).fail(function (xhr) {
        plv_hideLoader();
        Swal.fire('Erreur', plv_ajaxErrorMessage(xhr), 'error');
    });
}

function plv_renderStats(s) {
    document.getElementById('plv-stat-lignes').textContent  = s.lignes  ?? 0;
    document.getElementById('plv-stat-recu').textContent    = s.recu    ?? 0;
    document.getElementById('plv-stat-attente').textContent = s.attente ?? 0;
    document.getElementById('plv-stat-ecart').textContent   = s.ecart   ?? 0;
}

function plv_renderPeriode(res) {
    const el = document.getElementById('plv-periode');
    if (res.tout) el.textContent = "Tout l'historique jusqu'au " + plv_fmtDate(res.date_fin) + '.';
    else el.textContent = 'Période affichée : du ' + plv_fmtDate(res.date_debut) + ' au ' + plv_fmtDate(res.date_fin) + '.';
}

function plv_renderTable(lignes) {
    if (plv_table) { try { plv_table.destroy(); } catch (e) {} plv_table = null; }

    plv_table = $('#plv-table').DataTable({
        data: lignes,
        columns: [
            { data: 'numero_bon' },
            { data: 'nom_expression' },
            { data: null },
            { data: 'produit' },
            { data: 'quantite_livree' },
            { data: 'quantite_recue' },
            { data: 'date_livraison' },
            { data: 'statut_reception' },
            { data: 'date_reception' },
        ],
        columnDefs: [
            { targets: 0, render: d => '<strong>' + plv_escapeHtml(d) + '</strong>' },
            { targets: 1, render: d => plv_escapeHtml(d) },
            { targets: 2, render: (d, t, row) => plv_escapeHtml(plv_type === 'fonct' ? (row.demandeur || '—') : (row.code_direction || row.nom_direction || '—')) },
            { targets: 3, render: d => plv_escapeHtml(d) },
            { targets: [4, 5], render: d => plv_fmtNombre(d) },
            { targets: 6, render: (d, t) => t === 'sort' || t === 'type' ? (d || '') : plv_fmtDate(d) },
            { targets: 7, render: (d, t, row) => plv_renderStatut(d, t, row) },
            { targets: 8, render: (d, t, row) => plv_renderRecuLe(d, t, row) },
        ],
        order: [[6, 'desc']],
        language: {
            emptyTable: 'Aucun produit livré sur cette période.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
        initComplete: function () {
            document.documentElement.classList.remove('ld-booting');
        },
    });
}

function plv_renderStatut(code, type, row) {
    const st = PLV_STATUTS[code] || { cls: 'plv-b-clos', lbl: code };
    if (type !== 'display') return st.lbl;          // tri / recherche sur le libellé
    let sub = '';
    if (code === 'attente' || code === 'partiel') {
        const j = parseInt(row.jours_depuis_livraison, 10);
        if (!isNaN(j)) sub = '<span class="plv-sub">livré il y a ' + j + ' j</span>';
    } else if (code === 'ecart') {
        sub = '<span class="plv-sub">' + plv_fmtNombre(row.quantite_ecart) + ' signalé(s)</span>';
    } else if (code === 'clos_ecart') {
        sub = '<span class="plv-sub">' + plv_fmtNombre(row.quantite_perdue) + ' perdu(s)</span>';
    }
    return '<span class="plv-badge ' + st.cls + '">' + plv_escapeHtml(st.lbl) + '</span>' + sub;
}

function plv_renderRecuLe(d, type, row) {
    if (type !== 'display') return d || '';
    if (!d) return '<span style="color:#9ca3af">—</span>';
    const par = row.statut_reception === 'presumee' ? 'présomption automatique' : (row.recu_par || '');
    return plv_fmtDate(d) + (par ? '<span class="plv-sub">' + plv_escapeHtml(par) + '</span>' : '');
}

/* ────────────────────────────── UTILITAIRES ────────────────────── */
function plv_showLoader(msg) {
    $('#dga-loader').remove();
    $('body').append(
        '<div id="dga-loader"><div class="dga-loader-bg"></div><div class="dga-loader-box">' +
        '<svg class="dga-loader-spin" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>' +
        '<p>' + (msg || 'Chargement…') + '</p></div></div>');
}
function plv_hideLoader() { $('#dga-loader').remove(); }

function plv_fmtDate(d) { return d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('fr-FR') : '—'; }

function plv_fmtNombre(v) {
    const n = parseFloat(v);
    return isNaN(n) ? plv_escapeHtml(v) : String(n);
}

function plv_ajaxErrorMessage(xhr) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
    if (!xhr || xhr.status === 0) return 'Impossible de contacter le serveur. Vérifiez votre connexion.';
    switch (xhr.status) {
        case 400: return 'Requête invalide. Merci de réessayer.';
        case 401: return 'Votre session a expiré. Veuillez vous reconnecter.';
        case 403: return "Vous n'avez pas les droits nécessaires pour effectuer cette action.";
        case 404: return 'Ressource introuvable.';
        case 500: return 'Erreur interne du serveur. Merci de réessayer plus tard.';
        default:  return 'Impossible de contacter le serveur (code ' + xhr.status + ').';
    }
}

function plv_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}