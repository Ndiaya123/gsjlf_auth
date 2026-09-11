/**
 * caisse-alimentation-caissier-scripts.bundle.js
 * Page "Mes alimentations de caisse" (profil Caissier) : liste filtrable
 * (intervalle d'années + statut) des alimentations concernant le caissier
 * connecté, avec possibilité d'Accepter / Rejeter une alimentation en
 * attente — uniquement si idStatut = 1 ET date_alimentation = date du jour
 * (condition déjà calculée côté serveur : row.peutDecider).
 */

const CAISSE_CAISSIER_CONTROLLER_URL = '/personnel/caisse_basi_controller'; // ← ajuster selon le chemin réel

const LIBELLES_TYPE_AC = { 1: 'Alimentation initiale', 2: 'Approvisionnement' };
const LIBELLES_STATUT_AC = { 1: 'En attente', 2: 'Confirmée', 3: 'Rejetée' };
const ANNEE_MIN_ALIMENTATION = 2026;

let dga_table = null;

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]); // évite le "flash" de tableau non stylé
    initSelectsAnnees();
    chargerMesAlimentations();

    document.getElementById('dga-btn-appliquer-filtres')?.addEventListener('click', chargerMesAlimentations);
    document.getElementById('dga-btn-reset-filtres')?.addEventListener('click', function () {
        const anneeCourante = new Date().getFullYear();
        document.getElementById('dga-annee-debut').value = '';
        document.getElementById('dga-annee-fin').value = anneeCourante;
        document.getElementById('dga-filtre-statut').value = '';
        chargerMesAlimentations();
    });
});

/* ────────────────────────── ANNÉES (filtres) ───────────────────────── */
function initSelectsAnnees() {
    const anneeCourante = new Date().getFullYear();
    const debutSel = document.getElementById('dga-annee-debut');
    const finSel   = document.getElementById('dga-annee-fin');
    if (!debutSel || !finSel) return;

    const anneeMax = Math.max(anneeCourante, ANNEE_MIN_ALIMENTATION);
    let options = '';
    for (let a = ANNEE_MIN_ALIMENTATION; a <= anneeMax; a++) {
        options += `<option value="${a}">${a}</option>`;
    }

    debutSel.innerHTML = '<option value="">Toutes</option>' + options;
    finSel.innerHTML   = options;

    debutSel.value = '';
    finSel.value   = anneeCourante;
}

/* ────────────────────────── CHARGEMENT LISTE ───────────────────────── */
function chargerMesAlimentations() {
    const anneeDebutVal = document.getElementById('dga-annee-debut').value;
    const anneeFinVal   = document.getElementById('dga-annee-fin').value;

    // "Année de" ne doit jamais dépasser "Année à" — uniquement si les deux
    // sont renseignées (Année de peut être vide par défaut).
    if (anneeDebutVal !== '' && anneeFinVal !== '' && parseInt(anneeDebutVal) > parseInt(anneeFinVal)) {
        Swal.fire('Intervalle invalide', '« Année de » ne peut pas être supérieure à « Année à ».', 'warning');
        return;
    }

    dga_showLoader('Chargement de vos alimentations…');

    $.ajax({
        url: CAISSE_CAISSIER_CONTROLLER_URL,
        method: 'POST',
        data: {
            option: 1,
            anneeDebut: anneeDebutVal,
            anneeFin: anneeFinVal,
            statut: document.getElementById('dga-filtre-statut').value,
        },
        dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') {
            dga_hideLoader();
            Swal.fire('Erreur', res.message || 'Impossible de charger vos alimentations.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
        dga_renderStatsJour(res.stats || {});
        dga_hideLoader();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
/* ────────────────────────── STATS DU JOUR ──────────────────────────── */
function dga_renderStatsJour(stats) {
    const total   = document.getElementById('dga-stat-total-jour');
    const liquide = document.getElementById('dga-stat-liquide-jour');
    const wave    = document.getElementById('dga-stat-wave-jour');
    const om      = document.getElementById('dga-stat-om-jour');
    if (total)   total.textContent   = dga_formatMontant(stats.montant_total_jour || 0);
    if (liquide) liquide.textContent = dga_formatMontant(stats.montant_liquide_jour || 0);
    if (wave)    wave.textContent    = dga_formatMontant(stats.montant_wave_jour || 0);
    if (om)      om.textContent      = dga_formatMontant(stats.montant_om_jour || 0);
}

function dga_renderTable(alimentations) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-alimentations').DataTable({
        data: alimentations,
        columns: [
            { data: 'numero' },
            { data: 'montant_total' },
            { data: 'commentaire' },
            { data: 'date_alimentation' },
            { data: 'idTypeAC' },
            { data: 'idStatut' },
            { data: 'utilisateur' },
            { data: 'dateEnregistrement' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            { targets: 1, render: d => '<span class="dga-cell-amount">' + dga_formatMontant(d) + '</span>' },
            { targets: 2, render: d => d ? dga_escapeHtml(d) : '<span style="color:#d1d5db;">—</span>' },
            { targets: 3, render: d => dga_fmtDate(d) },
            {
                targets: 4,
                render: d => `<span class="dga-badge-type dga-type-${d}">${LIBELLES_TYPE_AC[d] || d}</span>`,
            },
            {
                targets: 5,
                render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT_AC[d] || d}</span>`,
            },
            { targets: 7, render: d => dga_fmtDateHeure(d) },
            {
                targets: 8,
                render: (d, t, row) => `
                    <button type="button" class="dga-btn-detail" onclick="dga_ouvrirDetail('${d}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/></svg>
                        Détail
                    </button>
                    ${row.peutDecider ? `
                        <button type="button" class="dga-btn-accepter" onclick="dga_confirmerAccepter('${d}')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Accepter
                        </button>
                        <button type="button" class="dga-btn-rejeter" onclick="dga_confirmerRejeter('${d}')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Rejeter
                        </button>
                    ` : ''}
                `,
            },
        ],
        order: [[3, 'desc']],
        language: {
            emptyTable: 'Aucune alimentation à afficher.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
    });
}

/* ────────────────────────── ACTION : ACCEPTER ──────────────────────── */
/* ────────────────────────── ACTION : DÉTAIL ────────────────────────── */
function dga_ouvrirDetail(token) {
    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: CAISSE_CAISSIER_CONTROLLER_URL, method: 'POST', data: { option: 4, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const a = res.alimentation;
        const m = res.montants;

        Swal.fire({
            html: `
                <div class="dga-detail-wrap">
                    <div class="dga-detail-header">
                        <span class="dga-detail-numero">${dga_escapeHtml(a.numero)}</span>
                        <div class="dga-detail-badges">
                            <span class="dga-badge-type dga-type-${a.idTypeAC}">${LIBELLES_TYPE_AC[a.idTypeAC] || a.idTypeAC}</span>
                            <span class="dga-badge-statut dga-statut-${a.idStatut}">${LIBELLES_STATUT_AC[a.idStatut] || a.idStatut}</span>
                        </div>
                    </div>

                    <div class="dga-detail-grid">
                        <div class="dga-detail-item">
                            <span class="lbl">Date d'alimentation</span>
                            <span class="val">${dga_fmtDate(a.date_alimentation)}</span>
                        </div>
                        <div class="dga-detail-item">
                            <span class="lbl">Montant total</span>
                            <span class="val" style="color:#1a7a5e;">${dga_formatMontant(a.montant_total)}</span>
                        </div>
                        <div class="dga-detail-item dga-detail-item--full">
                            <span class="lbl">Commentaire</span>
                            <span class="val">${a.commentaire ? dga_escapeHtml(a.commentaire) : '—'}</span>
                        </div>
                    </div>

                    ${a.motifRej ? `
                        <div class="dga-detail-motif-rejet">
                            <span class="lbl">Motif du rejet</span>
                            <span class="val">${dga_escapeHtml(a.motifRej)}</span>
                        </div>
                    ` : ''}

                    <div class="dga-detail-souslabel">Répartition par mode de règlement</div>
                    <div class="dga-montant-cards">
                        <div class="dga-montant-card dga-mc-liquide">
                            <div class="dga-montant-card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                            </div>
                            <span class="lbl">Liquide</span>
                            <span class="val">${dga_formatMontant(m['1'])}</span>
                        </div>
                        <div class="dga-montant-card dga-mc-om">
                            <div class="dga-montant-card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            </div>
                            <span class="lbl">Orange Money</span>
                            <span class="val">${dga_formatMontant(m['5'])}</span>
                        </div>
                        <div class="dga-montant-card dga-mc-wave">
                            <div class="dga-montant-card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 12c2-4 4-4 6 0s4 4 6 0 4-4 6 0"/></svg>
                            </div>
                            <span class="lbl">Wave</span>
                            <span class="val">${dga_formatMontant(m['4'])}</span>
                        </div>
                    </div>

                    <div class="dga-detail-total">
                        <span class="lbl">Total général</span>
                        <span class="val">${dga_formatMontant(a.montant_total)}</span>
                    </div>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: 'Fermer',
            confirmButtonColor: '#1a7a5e',
            width: 640,
            padding: '2.2rem',
        });
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── ACTION : ACCEPTER ──────────────────────── */
function dga_confirmerAccepter(token) {
    Swal.fire({
        title: 'Accepter cette alimentation',
        text: 'Confirmez-vous cette alimentation de caisse ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        dga_showLoader('Acceptation en cours…');
        $.ajax({
            url: CAISSE_CAISSIER_CONTROLLER_URL, method: 'POST', data: { option: 2, token: token }, dataType: 'json'
        }).done(function (res) {
            dga_hideLoader();
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message || 'Alimentation acceptée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerMesAlimentations();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            dga_hideLoader();
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────── ACTION : REJETER ───────────────────────── */
function dga_confirmerRejeter(token) {
    Swal.fire({
        title: 'Rejeter cette alimentation',
        input: 'textarea',
        inputLabel: 'Motif du rejet *',
        inputPlaceholder: 'Précisez le motif du rejet…',
        showCancelButton: true,
        confirmButtonText: 'Rejeter',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        inputValidator: (value) => {
            if (!value || !value.trim()) return 'Le motif du rejet est obligatoire.';
        },
    }).then(function (result) {
        if (!result.isConfirmed) return;

        dga_showLoader('Rejet en cours…');
        $.ajax({
            url: CAISSE_CAISSIER_CONTROLLER_URL, method: 'POST', data: { option: 3, token: token, motif: result.value.trim() }, dataType: 'json'
        }).done(function (res) {
            dga_hideLoader();
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message || 'Alimentation rejetée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerMesAlimentations();
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

function dga_fmtDate(d) { return d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—'; }

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