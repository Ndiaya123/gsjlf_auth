/**
 * liste-paiements-scripts.bundle.js
 * Page "Liste des paiements" (profil Comptable) : liste filtrable par
 * intervalle de dates (défaut : paiements du jour), statistiques
 * (nombre + montant total), et annulation logique d'un paiement.
 */

const CAISSE_CONTROLLER_URL = '/personnel/caisse_basi_controller'; // ← ajuster selon le chemin réel

let dga_table = null;

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    initDates();
    chargerPaiements();

    document.getElementById('dga-btn-appliquer-filtres')?.addEventListener('click', chargerPaiements);
    document.getElementById('dga-btn-reset-filtres')?.addEventListener('click', function () {
        document.getElementById('dga-date-debut').value = '';
        document.getElementById('dga-date-fin').value = new Date().toISOString().slice(0, 10);
        chargerPaiements();
    });
});

/* ────────────────────────── DATES (filtres) ────────────────────────── */
function initDates() {
    // Date de début vide par défaut ; Date de fin = aujourd'hui.
    document.getElementById('dga-date-debut').value = '';
    document.getElementById('dga-date-fin').value = new Date().toISOString().slice(0, 10);
}

/* ────────────────────────── CHARGEMENT LISTE ───────────────────────── */
function chargerPaiements() {
    const dateDebut = document.getElementById('dga-date-debut').value;
    const dateFin   = document.getElementById('dga-date-fin').value;

    // La date de début ne peut jamais dépasser la date de fin — uniquement
    // si les deux sont renseignées (date de début peut être vide).
    if (dateDebut !== '' && dateFin !== '' && dateDebut > dateFin) {
        Swal.fire('Intervalle invalide', 'La date de début ne peut pas être supérieure à la date de fin.', 'warning');
        return;
    }

    dga_showLoader('Chargement des paiements…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL,
        method: 'POST',
        data: { option: 9, dateDebut: dateDebut, dateFin: dateFin },
        dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les paiements.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
        document.getElementById('dga-stat-nombre').textContent = res.nombre_total ?? 0;
        document.getElementById('dga-stat-montant').textContent = dga_formatMontant(res.montant_total || 0);
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function dga_renderTable(paiements) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-paiements').DataTable({
        data: paiements,
        columns: [
            { data: 'numeroPAP' },
            { data: 'nom_commande' },
            { data: 'idTypePAP' },
            { data: 'caissier' },
            { data: 'mode_reglement_nom' },
            { data: 'montant' },
            { data: 'date_paiement' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            {
                targets: 2,
                render: d => parseInt(d) === 1
                    ? '<span class="dga-badge-type dga-type-1">Achat</span>'
                    : '<span class="dga-badge-type dga-type-2">Paiement</span>',
            },
            {
                targets: 4,
                render: (d, t, row) => {
                    const idMode = parseInt(row.mode_reglement);
                    const cls = [1, 4, 5].includes(idMode) ? `dga-mode-${idMode}` : 'dga-mode-autre';
                    return `<span class="dga-badge-mode ${cls}">${dga_escapeHtml(d || '—')}</span>`;
                },
            },
            {
                targets: 5,
                render: (d, t, row) => (parseFloat(d) <= 0)
                    ? '<span style="color:#d1d5db;text-decoration:line-through;">' + dga_formatMontant(row.montant) + '</span>'
                    : '<span class="dga-cell-amount">' + dga_formatMontant(d) + '</span>',
            },
            { targets: 6, render: d => dga_fmtDateHeure(d) },
            {
                targets: 7,
                render: (d, t, row) => row.modifiable
                    ? `<button type="button" class="dga-btn-annuler" onclick="dga_confirmerAnnuler('${d}')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Annuler
                       </button>`
                    : '<span style="color:#d1d5db;">—</span>',
            },
        ],
        order: [[6, 'desc']],
        language: {
            emptyTable: 'Aucun paiement à afficher.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });
}

/* ────────────────────────── ANNULATION ─────────────────────────────── */
function dga_confirmerAnnuler(token) {
    Swal.fire({
        title: 'Annuler ce paiement',
        text: "Cette action remet le montant du paiement à 0, met à jour la commande liée (et la tranche concernée le cas échéant). Confirmez-vous ?",
        input: 'textarea',
        inputLabel: "Motif de l'annulation (optionnel)",
        inputPlaceholder: 'Précisez le motif si nécessaire…',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Annuler le paiement',
        cancelButtonText: 'Retour',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        dga_showLoader('Annulation en cours…');
        $.ajax({
            url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 16, token: token, motif: result.value || '' }, dataType: 'json'
        }).done(function (res) {
            dga_hideLoader();
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message || 'Paiement annulé avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerPaiements();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            dga_hideLoader();
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────────── UTILITAIRES ────────────────────── */
function dga_showLoader(msg = 'Chargement…') {
    $('#dga-loader').remove();
    $('body').append(`
        <div id="dga-loader">
            <div class="dga-loader-bg"></div>
            <div class="dga-loader-box">
                <svg class="dga-loader-spin" viewBox="0 0 50 50">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
                </svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function dga_hideLoader() { $('#dga-loader').remove(); }

function dga_fmtDateHeure(d) {
    if (!d) return '—';
    const dt = new Date(d.replace(' ', 'T'));
    return dt.toLocaleDateString('fr-FR') + ' à ' + dt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
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

function dga_formatMontant(value) {
    const n = Number(value);
    if (isNaN(n)) return dga_escapeHtml(String(value ?? ''));
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' FCFA';
}

function dga_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}