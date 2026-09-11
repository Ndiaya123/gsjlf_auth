/**
 * caisse-alimentation-scripts.bundle.js
 * Page "Alimentation de caisse" (Comptabilité) : liste filtrable (intervalle
 * d'années + statut), création et modification d'une alimentation
 * (initiale ou approvisionnement).
 */

const CAISSE_CONTROLLER_URL = '/personnel/cpt_caisse_basi_controller'; // ← ajuster selon le chemin réel

const LIBELLES_TYPE_AC = { 1: 'Alimentation initiale', 2: 'Approvisionnement' };
const LIBELLES_STATUT_AC = { 1: 'En attente', 2: 'Confirmée', 3: 'Rejetée' };

let dga_table = null;
let dga_caissiers = [];
let dga_alimentationCourante = null; // token en cours de modification (null = création)

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]); // évite le "flash" de tableau non stylé
    initSelectsAnnees();
    chargerAlimentations();
    chargerCaissiers();

    document.getElementById('dga-btn-appliquer-filtres')?.addEventListener('click', chargerAlimentations);
    document.getElementById('dga-btn-reset-filtres')?.addEventListener('click', function () {
        const anneeCourante = new Date().getFullYear();
        document.getElementById('dga-annee-debut').value = '';
        document.getElementById('dga-annee-fin').value = anneeCourante;
        document.getElementById('dga-filtre-statut').value = '';
        chargerAlimentations();
    });

    document.getElementById('dga-btn-nouvelle')?.addEventListener('click', function () { dga_ouvrirModale(null); });
    document.getElementById('dgaSubmitAlimentation')?.addEventListener('click', soumettreAlimentation);

    ['dgaMontantLiquide', 'dgaMontantOM', 'dgaMontantWave'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', dga_recalculerMontantTotal);
    });

    document.getElementById('dgaTypeOption1')?.addEventListener('click', function () { dga_selectionnerType('1'); });
    document.getElementById('dgaTypeOption2')?.addEventListener('click', function () { dga_selectionnerType('2'); });
});

/* ────────────────────────── ANNÉES (filtres) ───────────────────────── */
const ANNEE_MIN_ALIMENTATION = 2026;

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
function chargerAlimentations() {
    const anneeDebutVal = document.getElementById('dga-annee-debut').value;
    const anneeFinVal   = document.getElementById('dga-annee-fin').value;

    // "Année de" ne doit jamais dépasser "Année à" — uniquement si les deux
    // sont renseignées (Année de peut être vide par défaut).
    if (anneeDebutVal !== '' && anneeFinVal !== '' && parseInt(anneeDebutVal) > parseInt(anneeFinVal)) {
        Swal.fire('Intervalle invalide', '« Année de » ne peut pas être supérieure à « Année à ».', 'warning');
        return;
    }

    dga_showLoader('Chargement des alimentations…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL,
        method: 'POST',
        data: {
            option: 10,
            anneeDebut: anneeDebutVal,
            anneeFin: anneeFinVal,
            statut: document.getElementById('dga-filtre-statut').value,
        },
        dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') {
            dga_hideLoader();
            Swal.fire('Erreur', res.message || 'Impossible de charger les alimentations.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
        dga_hideLoader();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function chargerCaissiers() {
    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 11 }, dataType: 'json'
    }).done(function (res) {
        dga_caissiers = (res && res.status === 'success') ? (res.data || []) : [];
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
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
            { data: 'caissier' },
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
            { targets: 8, render: d => dga_fmtDateHeure(d) },
            {
                targets: 9,
                render: (d, t, row) => `
                    <button type="button" class="dga-btn-detail" onclick="dga_ouvrirDetail('${d}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/></svg>
                        Détail
                    </button>
                    ${row.modifiable ? `
                        <button type="button" class="dga-btn-modifier" onclick="dga_ouvrirModale('${d}')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Modifier
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

/* ────────────────────────── ACTION : DÉTAIL ────────────────────────── */
function dga_ouvrirDetail(token) {
    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 13, token: token }, dataType: 'json'
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
                            <span class="lbl">Caissier</span>
                            <span class="val">${dga_escapeHtml(a.caissier || '—')}</span>
                        </div>
                        <div class="dga-detail-item">
                            <span class="lbl">Date d'alimentation</span>
                            <span class="val">${dga_fmtDate(a.date_alimentation)}</span>
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

/* ────────────────────────── OUVERTURE MODALE ───────────────────────── */
function dga_ouvrirModale(token) {
    dga_alimentationCourante = token;
    dga_clearErreur();

    remplirSelectCaissiers();
    document.getElementById('dgaCommentaire').value = '';

    if (!token) {
        // Création : le caissier reste modifiable
        document.getElementById('dgaModalTitre').textContent = 'Nouvelle alimentation';
        const selCaissierCreation = document.getElementById('dgaCaissier');
        selCaissierCreation.value = '';
        selCaissierCreation.disabled = false;
        selCaissierCreation.classList.remove('dga-inp-readonly');
        document.getElementById('dgaDateAlimentation').value = new Date().toISOString().slice(0, 10);
        dga_selectionnerType('1');
        dga_verrouillerType(false);
        document.getElementById('dgaMontantLiquide').value = 0;
        document.getElementById('dgaMontantOM').value = 0;
        document.getElementById('dgaMontantWave').value = 0;
        dga_recalculerMontantTotal();
        new bootstrap.Modal(document.getElementById('modalAlimentation')).show();
        return;
    }

    // Modification : charger le détail existant
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 13, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const a = res.alimentation;
        document.getElementById('dgaModalTitre').textContent = 'Modifier l\'alimentation — ' + a.numero;
        const selCaissierModif = document.getElementById('dgaCaissier');
        selCaissierModif.value = a.idCaissier;
        selCaissierModif.disabled = true;
        selCaissierModif.classList.add('dga-inp-readonly');
        document.getElementById('dgaDateAlimentation').value = a.date_alimentation;
        dga_selectionnerType(String(a.idTypeAC));
        dga_verrouillerType(true);
        document.getElementById('dgaCommentaire').value = a.commentaire || '';

        document.getElementById('dgaMontantLiquide').value = res.montants['1'] || 0;
        document.getElementById('dgaMontantOM').value      = res.montants['5'] || 0;
        document.getElementById('dgaMontantWave').value    = res.montants['4'] || 0;
        dga_recalculerMontantTotal();

        new bootstrap.Modal(document.getElementById('modalAlimentation')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function remplirSelectCaissiers() {
    const sel = document.getElementById('dgaCaissier');
    sel.innerHTML = '<option value="">Sélectionner…</option>' +
        dga_caissiers.map(c => `<option value="${c.id}">${dga_escapeHtml(c.nom)}</option>`).join('');
}

function dga_selectionnerType(valeur) {
    document.querySelector(`input[name="dgaTypeAC"][value="${valeur}"]`).checked = true;
    document.getElementById('dgaTypeOption1').classList.toggle('selected', valeur === '1');
    document.getElementById('dgaTypeOption2').classList.toggle('selected', valeur === '2');
}

function dga_verrouillerType(verrouille) {
    document.querySelectorAll('input[name="dgaTypeAC"]').forEach(function (input) {
        input.disabled = verrouille;
    });
    ['dgaTypeOption1', 'dgaTypeOption2'].forEach(function (id) {
        document.getElementById(id)?.classList.toggle('dga-type-locked', verrouille);
    });
}

function dga_recalculerMontantTotal() {
    const liquide = parseFloat(document.getElementById('dgaMontantLiquide').value) || 0;
    const om      = parseFloat(document.getElementById('dgaMontantOM').value) || 0;
    const wave    = parseFloat(document.getElementById('dgaMontantWave').value) || 0;
    document.getElementById('dgaMontantTotalAffiche').textContent = dga_formatMontant(liquide + om + wave);
}

/* ────────────────────────────── SOUMISSION ─────────────────────────── */
function soumettreAlimentation() {
    dga_clearErreur();

    const idCaissier       = document.getElementById('dgaCaissier').value;
    const dateAlimentation = document.getElementById('dgaDateAlimentation').value;
    const typeRadio         = document.querySelector('input[name="dgaTypeAC"]:checked');
    const commentaire       = document.getElementById('dgaCommentaire').value;
    const montantLiquide    = document.getElementById('dgaMontantLiquide').value;
    const montantOM         = document.getElementById('dgaMontantOM').value;
    const montantWave       = document.getElementById('dgaMontantWave').value;

    if (!idCaissier)       { dga_afficherErreur('Sélectionnez un caissier.'); return; }
    if (!dateAlimentation) { dga_afficherErreur("Renseignez la date d'alimentation."); return; }
    if (!typeRadio)        { dga_afficherErreur("Sélectionnez le type d'alimentation."); return; }

    const total = (parseFloat(montantLiquide) || 0) + (parseFloat(montantOM) || 0) + (parseFloat(montantWave) || 0);
    if (total <= 0) { dga_afficherErreur('Au moins un montant doit être supérieur à 0.'); return; }

    const data = {
        idCaissier: idCaissier,
        dateAlimentation: dateAlimentation,
        idTypeAC: typeRadio.value,
        commentaire: commentaire,
        montantLiquide: montantLiquide,
        montantOM: montantOM,
        montantWave: montantWave,
    };

    dga_toggleSpinner(true);

    if (dga_alimentationCourante) {
        data.option = 14;
        data.token = dga_alimentationCourante;
    } else {
        data.option = 12;
    }

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: data, dataType: 'json'
    }).done(function (res) {
        dga_toggleSpinner(false);
        if (res.status === 'success') {
            const modalEl = document.getElementById('modalAlimentation');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            Swal.fire({ title: 'Succès', text: res.message || 'Alimentation enregistrée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerAlimentations();
        } else {
            dga_afficherErreur(res.message || 'Une erreur est survenue.');
        }
    }).fail(function (xhr) {
        dga_toggleSpinner(false);
        dga_afficherErreur(dga_ajaxErrorMessage(xhr));
    });
}

function dga_afficherErreur(message) {
    const b = document.getElementById('dgaErreurGenerale');
    b.innerHTML = `<span>${dga_escapeHtml(message)}</span>`;
    b.style.display = 'flex';
    b.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function dga_clearErreur() {
    const b = document.getElementById('dgaErreurGenerale');
    b.style.display = 'none';
    b.innerHTML = '';
}

function dga_toggleSpinner(loading) {
    const btn = document.getElementById('dgaSubmitAlimentation');
    const spinner = btn.querySelector('.dga-spinner');
    if (spinner) spinner.classList.toggle('hidden', !loading);
    btn.disabled = loading;
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