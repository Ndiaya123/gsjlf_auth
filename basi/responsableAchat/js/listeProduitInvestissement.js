/**
 * listeProduitsInvestissementComptable.js
 * Page "Produits d'investissement — catalogue" (profil Comptable) : liste
 * en lecture seule de TOUS les produits Investissement (même ceux jamais
 * reçus en stock), avec un bouton "Détail" ouvrant la répartition par
 * direction. Aucune modification ni suppression possible ici.
 */

const REPARTITION_CONTROLLER_URL = '/personnel/resp_achat_basi_controller_3'; // ← ajuster selon le chemin réel

let dga_table = null;
let dga_produitsCourants = [];

document.addEventListener('DOMContentLoaded', function () {
    chargerRepartition();
});

function chargerRepartition() {
    dga_showLoader('Chargement du catalogue…');

    $.ajax({
        url: REPARTITION_CONTROLLER_URL, method: 'POST', data: { option: 40 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le catalogue.', 'error');
            return;
        }
        dga_produitsCourants = res.data || [];
        dga_renderTable(dga_produitsCourants);
        document.getElementById('dga-stat-nombre').textContent = res.nombre_total ?? 0;
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });

    chargerStatistiques();
}

function chargerStatistiques() {
    $.ajax({
        url: REPARTITION_CONTROLLER_URL, method: 'POST', data: { option: 42 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') return;
        const s = res.stats || {};
        document.getElementById('dga-stat-en-service').textContent = s.en_service ?? 0;
        document.getElementById('dga-stat-hors-service').textContent = s.hors_service ?? 0;
        document.getElementById('dga-stat-stock-global').textContent = dga_fmtNombre(s.stock_global_total ?? 0);
        document.getElementById('dga-stat-directions').textContent = s.nombre_directions ?? 0;
    });
}

function dga_renderTable(produits) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-repartition').DataTable({
        data: produits,
        columns: [
            { data: null },
            { data: null },
            { data: 'nomproduit' },
            { data: 'Stock_actuel' },
            { data: 'retrait' },
            { data: 'date_creation' },
            { data: 'id_statut' },
            { data: 'nom_createur' },
            { data: null, orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: (d, t, row) => row.nom_rubrique ? dga_escapeHtml(row.nom_rubrique) : '<span class="dga-cell-muted">—</span>' },
            { targets: 1, render: (d, t, row) => row.nom_sous_rubrique ? dga_escapeHtml(row.nom_sous_rubrique) : '<span class="dga-cell-muted">—</span>' },
            { targets: 2, render: d => `<span style="font-weight:700;color:#111827;">${dga_escapeHtml(d)}</span>` },
            { targets: 3, render: d => `<span class="dga-qte-dispo">${dga_fmtNombre(d)}</span>` },
            { targets: 4, render: d => dga_fmtNombre(d ?? 0) },
            { targets: 5, render: d => dga_fmtDate(d) },
            {
                targets: 6,
                render: d => parseInt(d) === 1
                    ? '<span class="dga-badge-statut" style="background:#ecfdf5;color:#059669;">En service</span>'
                    : '<span class="dga-badge-statut" style="background:#fef2f2;color:#991b1b;">Hors service</span>',
            },
            { targets: 7, render: d => dga_escapeHtml(d || '—') },
            {
                targets: 8,
                render: (d, t, row) => `<button type="button" class="dga-btn-detail-produit" onclick="dga_ouvrirDetailRepartition(${row.idP})">Détail</button>`,
            },
        ],
        order: [[2, 'asc']],
        language: {
            emptyTable: 'Aucun produit Investissement au catalogue pour le moment.',
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

    // Barre de recherche personnalisée (icône loupe), branchée sur la
    // recherche native de DataTables.
    $('[data-kt-docs-table-filter="search"]').off('keyup').on('keyup', function () {
        dga_table.search(this.value).draw();
    });
}

/* ────────────────────────── DÉTAIL PAR DIRECTION ─────────────────────── */
function dga_ouvrirDetailRepartition(idP) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: REPARTITION_CONTROLLER_URL, method: 'POST', data: { option: 41, idP: idP }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const produit = res.produit;
        const repartition = res.repartition || [];
        document.getElementById('detailRepartitionModalTitre').textContent = 'Répartition — ' + produit.nomproduit;

        const lignes = repartition.map(function (r) {
            return `
                <tr>
                    <td>${dga_escapeHtml(r.code_direction || r.nom_direction || '—')}</td>
                    <td>${dga_fmtNombre(r.quantite_recue)}</td>
                    <td>${dga_fmtNombre(r.quantite_sortie)}</td>
                    <td>${parseFloat(r.quantite_reservee) > 0 ? dga_fmtNombre(r.quantite_reservee) : '<span class="dga-cell-muted">0</span>'}</td>
                    <td><span class="dga-qte-dispo">${dga_fmtNombre(r.quantite_disponible)}</span></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">Aucune direction n\'a encore reçu ce produit en stock.</td></tr>';

        document.getElementById('dgaContenuDetailRepartition').innerHTML = `
            <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                <strong>Stock global actuel :</strong> <span class="dga-qte-dispo">${dga_fmtNombre(produit.stock_actuel)}</span>
            </p>
            <table class="dga-table-produits">
                <thead><tr><th>Direction</th><th>Reçu</th><th>Sorti</th><th>Réservé</th><th>Disponible</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        `;

        new bootstrap.Modal(document.getElementById('modalDetailRepartition')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────────── UTILITAIRES ────────────────────── */
function dga_showLoader(msg = 'Chargement…') {
    $('#dga-loader').remove();
    $('body').append(`
        <div id="dga-loader">
            <div class="dga-loader-bg"></div>
            <div class="dga-loader-box">
                <svg class="dga-loader-spin" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function dga_hideLoader() { $('#dga-loader').remove(); }

/**
 * Affiche un nombre sans décimales inutiles (les quantités sont des
 * entiers, mais la colonne SQL est un DECIMAL, donc PHP renvoie "4.00").
 */
function dga_fmtDate(d) { return d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—'; }

function dga_fmtNombre(v) {
    const n = parseFloat(v);
    return isNaN(n) ? dga_escapeHtml(v) : String(n);
}

function dga_ajaxErrorMessage(xhr) {
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

function dga_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}