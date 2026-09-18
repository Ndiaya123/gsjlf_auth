/**
 * toutesLignesDemandesPaiement-scripts.bundle.js
 * Page "Toutes les lignes de demandes de paiement" : affiche TOUTES les
 * lignes non épuisées de TOUTES les demandes de paiement confondues (pas
 * une seule demande, contrairement à la page "Voir la demande").
 *
 * Réutilise le même mécanisme d'action groupée que "Voir la demande"
 * (Passer au paiement). Un paiement peut regrouper des lignes provenant de
 * PLUSIEURS demandes différentes — aucune contrainte de ce côté : le
 * serveur détermine lui-même, pour chaque ligne, sa demande d'origine
 * (demandes_ligne.idD), sans exiger un idD global unique.
 */

const VD_INFO_URL       = '/personnel/drh_basi_controller?option=18'; // ← ajuster selon le chemin réel
const PC_CONTROLLER_URL = '/personnel/drh_basi_controller';            // ← ajuster selon le chemin réel

let vd_table      = null;
let vd_ligneParId = {};   // lignes indexées par idDemande (= idDL)
let vd_selection  = new Set();

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('vd-btn-retour')?.addEventListener('click', () => window.history.back());
    initModalesSubmit();
    chargerLignes();
});

/* ────────────────────────── CHARGEMENT INITIAL ─────────────────── */
function chargerLignes() {
    vd_showLoader('Chargement des lignes…');

    $.ajax({
        url: VD_INFO_URL,
        method: 'POST',
        dataType: 'json'
    }).done(function (res) {
        vd_hideLoader();
        try {
            if (res.status !== 'success') {
                Swal.fire('Erreur', res.message || 'Impossible de charger les lignes.', 'error');
                return;
            }

            const lignes = res.data || [];
            vd_indexerLignes(lignes);
            vd_renderTable(lignes);

            const compteur = document.getElementById('vd-compteur-lignes');
            if (compteur) compteur.textContent = lignes.length + ' ligne' + (lignes.length > 1 ? 's' : '');
        } catch (err) {
            console.error('Erreur traitement des lignes :', err);
            Swal.fire('Erreur', 'Une erreur inattendue est survenue lors de l\'affichage.', 'error');
        }
    }).fail(function (xhr) {
        vd_hideLoader();
        Swal.fire('Erreur', vd_ajaxErrorMessage(xhr), 'error');
    });
}

function vd_indexerLignes(lignes) {
    vd_ligneParId = {};
    (lignes || []).forEach(row => { vd_ligneParId[row.idDemande] = row; });
    vd_selection.clear();
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function vd_renderTable(lignes) {
    if (vd_table) { try { vd_table.destroy(); } catch (e) {} vd_table = null; }

    vd_table = $('#vd-table-paiement').DataTable({
        data: lignes,
        columns: [
            { data: 'idDemande', orderable: false, searchable: false },
            { data: 'idD' },
            { data: 'designation' },
            { data: 'montant_a_payer' },
            { data: 'montant_paye' },
            { data: 'montant_restant' }
        ],
        columnDefs: [
            { targets: 0, render: d => vd_renderCheckbox(d) },
            { targets: 1, render: (d, t, row) => `<a href="/personnel/voir-demande.php?token=${encodeURIComponent(row.tmp)}" class="vd-lien-demande" title="Voir cette demande">#${d}</a>` },
            { targets: 2, render: d => '<span class="vd-cell-name">' + vd_escapeHtml(d || '') + '</span>' },
            { targets: 3, render: d => '<span class="vd-cell-amount">' + vd_formatMontant(d) + '</span>' },
            { targets: 4, render: d => vd_formatMontant(d) },
            { targets: 5, render: d => '<span class="vd-cell-amount">' + vd_formatMontant(d) + '</span>' },
        ],
        order: [[1, 'desc']],
        language: vd_dataTableLangFr(),
        drawCallback: () => vd_reappliquerSelection('vd-table-paiement'),
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });

    $('#vd-table-paiement tbody').off('change', '.vd-row-check').on('change', '.vd-row-check', vd_onRowCheckChange);
    document.getElementById('checkAllPaiement').onchange = function () { vd_toggleAll('#vd-table-paiement', this.checked); };

    vd_updateSelectionLabel();
}

function vd_renderCheckbox(idDemande) {
    const checked = vd_selection.has(String(idDemande)) ? 'checked' : '';
    return '<input type="checkbox" class="vd-checkbox vd-row-check" data-id="' + idDemande + '" ' + checked + '/>';
}

function vd_reappliquerSelection(tableId) {
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(function (tr) {
        const cb = tr.querySelector('.vd-row-check');
        if (cb && vd_selection.has(cb.dataset.id)) tr.classList.add('vd-row-selected');
    });
}

function vd_onRowCheckChange() {
    const id = $(this).data('id').toString();
    const tr = $(this).closest('tr');
    if (this.checked) {
        vd_selection.add(id);
        tr.addClass('vd-row-selected');
    } else {
        vd_selection.delete(id);
        tr.removeClass('vd-row-selected');
    }
    vd_updateSelectionLabel();
}

function vd_toggleAll(tableSelector, checked) {
    document.querySelectorAll(tableSelector + ' tbody .vd-row-check').forEach(function (cb) {
        cb.checked = checked;
        $(cb).trigger('change');
    });
}

/* ──────────────────────── LABEL DE SÉLECTION ───────────────────── */
function vd_updateSelectionLabel() {
    const label = document.getElementById('vd-selection-label');
    const count = vd_selection.size;

    label.innerHTML = count > 0
        ? '<strong>' + count + '</strong> ligne' + (count > 1 ? 's' : '') + ' sélectionnée' + (count > 1 ? 's' : '')
        : 'Aucune ligne sélectionnée';

    const btnPaiement = document.getElementById('btnPasserPaiement');
    if (btnPaiement) btnPaiement.disabled = count === 0;
}

/* ──────────────────────── BOUTON DE LA BARRE ───────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnPasserPaiement')?.addEventListener('click', vd_ouvrirModalePasserPaiement);
});

/* ═══════════════════════════ PASSER AU PAIEMENT ══════════════════════ */
let pp_modesReglement = [];
let pp_modesPaiement  = [];
let pp_listesChargees = false;

function vd_ouvrirModalePasserPaiement() {
    pp_chargerListesSiNecessaire().then(function () {
        pp_resetBloc1();
        pp_remplirLignes();
        pp_reinitialiserJustificatifs();
        new bootstrap.Modal(document.getElementById('modalPasserPaiement')).show();
    });
}

/* ── Bloc 1 : mode de règlement / modalité de paiement ─────────────────── */
function pp_chargerListesSiNecessaire() {
    if (pp_listesChargees) return Promise.resolve();

    return Promise.all([
        $.ajax({ url: PC_CONTROLLER_URL, method: 'POST', data: { option: 10 }, dataType: 'json' }), // mode_reglement
        $.ajax({ url: PC_CONTROLLER_URL, method: 'POST', data: { option: 11 }, dataType: 'json' }), // mode_paiement
    ]).then(function (res) {
        const [rMR, rMP] = res;
        pp_modesReglement = (rMR && rMR.status === 'success') ? (rMR.data || []) : [];
        pp_modesPaiement  = (rMP && rMP.status === 'success') ? (rMP.data || []) : [];

        pp_remplirSelectEl(document.getElementById('ppModeReglement'), pp_modesReglement, m => m.id, m => m.nom);
        pp_remplirSelectEl(document.getElementById('ppModePaiement'),  pp_modesPaiement,  m => m.id, m => m.nom);

        pp_listesChargees = true;
    }).catch(function () {
        Swal.fire('Erreur', 'Impossible de charger les modes de règlement / modalités de paiement.', 'error');
    });
}

function pp_remplirSelectEl(sel, items, getValue, getLabel) {
    if (!sel) return;
    sel.innerHTML = '<option value="">Sélectionner…</option>' +
        items.map(it => `<option value="${getValue(it)}">${vd_escapeHtml(getLabel(it))}</option>`).join('');
}

function pp_resetBloc1() {
    const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v; };
    set('ppModeReglement', '');
    set('ppModePaiement', '');
    set('ppNbTranches', '');
    document.getElementById('ppNbTranchesWrap').style.display = 'none';
    document.getElementById('ppTranchesWrap').style.display = 'none';
    document.getElementById('ppTranchesFields').innerHTML = '';
    document.getElementById('ppTranchesErreur').style.display = 'none';
    pp_clearErreur();
}

function pp_afficherErreur(message, champsInvalides) {
    const banniere = document.getElementById('ppErreurGenerale');
    banniere.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>${vd_escapeHtml(message)}</span>
    `;
    banniere.style.display = 'flex';
    banniere.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    document.querySelectorAll('#modalPasserPaiement .pc-invalid').forEach(el => el.classList.remove('pc-invalid'));
    (champsInvalides || []).forEach(el => { if (el) el.classList.add('pc-invalid'); });
}

function pp_clearErreur() {
    const banniere = document.getElementById('ppErreurGenerale');
    banniere.style.display = 'none';
    banniere.innerHTML = '';
    document.querySelectorAll('#modalPasserPaiement .pc-invalid').forEach(el => el.classList.remove('pc-invalid'));
}

/* ── Tranches (modalité de paiement = 3) ────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('ppModePaiement')?.addEventListener('change', function () {
        const estTranche = parseInt(this.value) === 3;
        document.getElementById('ppNbTranchesWrap').style.display = estTranche ? '' : 'none';
        document.getElementById('ppTranchesWrap').style.display   = estTranche ? '' : 'none';
        if (!estTranche) {
            document.getElementById('ppNbTranches').value = '';
            document.getElementById('ppTranchesFields').innerHTML = '';
            document.getElementById('ppTranchesErreur').style.display = 'none';
        }
    });

    document.getElementById('ppNbTranches')?.addEventListener('input', pp_genererChampsTranches);
});

function pp_genererChampsTranches() {
    const n = parseInt(document.getElementById('ppNbTranches').value);
    const wrap = document.getElementById('ppTranchesFields');
    wrap.innerHTML = '';
    document.getElementById('ppTranchesErreur').style.display = 'none';

    if (!n || n < 2) return;

    for (let i = 1; i <= n; i++) {
        const estDerniere = (i === n);
        wrap.insertAdjacentHTML('beforeend', `
            <div class="vd-pc-tranche-field">
                <label>Tranche ${i} (%)</label>
                <input type="number" class="vd-modal-inp pp-tranche-inp" data-ordre="${i}"
                       min="0" max="100" step="0.01"
                       ${estDerniere ? 'readonly placeholder="Calculé automatiquement"' : 'placeholder="0"'}/>
            </div>
        `);
    }

    wrap.querySelectorAll('.pp-tranche-inp:not([readonly])').forEach(inp => {
        inp.addEventListener('input', pp_recalculerDerniereTranche);
    });
    pp_recalculerDerniereTranche();
}

function pp_recalculerDerniereTranche() {
    const inputs = Array.from(document.querySelectorAll('.pp-tranche-inp'));
    if (!inputs.length) return;

    const derniere = inputs[inputs.length - 1];
    const autres   = inputs.slice(0, -1);
    const somme    = autres.reduce((s, inp) => s + (parseFloat(inp.value) || 0), 0);
    const calc     = Math.round((100 - somme) * 100) / 100;
    derniere.value = calc;

    const err = document.getElementById('ppTranchesErreur');
    const uneAutreEstNulle = autres.some(inp => (parseFloat(inp.value) || 0) <= 0);

    if (uneAutreEstNulle) {
        err.textContent = 'Chaque tranche doit être strictement supérieure à 0 %.';
        err.style.display = '';
    } else if (calc <= 0) {
        err.textContent = 'La dernière tranche calculée doit être strictement supérieure à 0 % — réduisez les pourcentages saisis.';
        err.style.display = '';
    } else {
        err.style.display = 'none';
    }
}

function pp_validerTranches() {
    const estTranche = parseInt(document.getElementById('ppModePaiement').value) === 3;
    if (!estTranche) return null;

    const n = parseInt(document.getElementById('ppNbTranches').value);
    if (!n || n < 2) return 'Renseignez un nombre de tranches valide (2 ou plus).';

    const inputs = Array.from(document.querySelectorAll('.pp-tranche-inp'));
    if (inputs.length !== n) return 'Les champs de tranches ne sont pas à jour, réessayez.';

    const valeurs = inputs.map(inp => parseFloat(inp.value) || 0);
    const somme   = valeurs.reduce((s, v) => s + v, 0);

    for (let i = 0; i < valeurs.length; i++) {
        if (valeurs[i] <= 0) return `La tranche ${i + 1} doit être strictement supérieure à 0 %.`;
    }
    if (Math.abs(somme - 100) > 0.01) return `La somme des tranches doit être exactement égale à 100 % (actuellement ${somme.toFixed(2)} %).`;

    return null;
}

/* ── Bloc 2 : lignes de paiement (uniquement les lignes sélectionnées) ── */
function pp_remplirLignes() {
    const body = document.getElementById('bodyPasserPaiement');
    body.innerHTML = '';

    vd_selection.forEach(function (id) {
        const row = vd_ligneParId[id];
        if (!row) return;
        const restant = row.montant_restant;

        body.insertAdjacentHTML('beforeend', `
            <tr data-id="${id}">
                <td>${vd_escapeHtml(row.designation || '')}</td>
                <td><input type="number" class="vd-modal-inp pp-montant" min="0" max="${restant}" step="0.01" value="${restant}"/></td>
            </tr>
        `);
    });

    body.querySelectorAll('.pp-montant').forEach(inp => {
        inp.addEventListener('input', pp_recalculerTotal);
    });
    pp_recalculerTotal();
}

function pp_recalculerTotal() {
    let total = 0;
    document.querySelectorAll('#bodyPasserPaiement tr').forEach(function (tr) {
        total += parseFloat(tr.querySelector('.pp-montant')?.value) || 0;
    });
    document.getElementById('ppMontantTotal').textContent = vd_formatMontant(total);
}

/* ── Bloc 3 : justificatifs de paiement (un ou plusieurs fichiers) ──────── */
function pp_reinitialiserJustificatifs() {
    const input = document.getElementById('ppJustificatifs');
    if (input) input.value = '';
    document.getElementById('ppListeJustificatifs').innerHTML = '';
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('ppJustificatifs')?.addEventListener('change', function () {
        const liste = document.getElementById('ppListeJustificatifs');
        liste.innerHTML = Array.from(this.files).map(f => `
            <li>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
                ${vd_escapeHtml(f.name)}
            </li>
        `).join('');
    });
});

/* ────────────────────── SOUMISSION DE LA MODALE ─────────────────── */
function initModalesSubmit() {
    document.getElementById('submitPasserPaiement')?.addEventListener('click', function () {
        pp_clearErreur();

        // ── Validation bloc 1 ──────────────────────────────────────────────
        const elModeReglement = document.getElementById('ppModeReglement');
        const elModePaiement  = document.getElementById('ppModePaiement');
        const idModeReglement = elModeReglement.value;
        const idModePaiement  = elModePaiement.value;

        if (!idModeReglement) { pp_afficherErreur('Sélectionnez un mode de règlement.', [elModeReglement]); return; }
        if (!idModePaiement)  { pp_afficherErreur('Sélectionnez une modalité de paiement.', [elModePaiement]); return; }

        const erreurTranches = pp_validerTranches();
        if (erreurTranches) { pp_afficherErreur(erreurTranches); return; }

        // ── Validation bloc 2 (lignes) ─────────────────────────────────────
        const lignes = [];
        const champsLigneInvalides = [];

        document.querySelectorAll('#bodyPasserPaiement tr').forEach(function (tr) {
            const montantEl = tr.querySelector('.pp-montant');
            const montant = montantEl.value;
            if (!montant || parseFloat(montant) <= 0) champsLigneInvalides.push(montantEl);

            lignes.push({
                idDemande: tr.dataset.id, // = idDL, contrat déjà en place
                montant: montant,
            });
        });

        if (!lignes.length) { pp_afficherErreur('Aucune ligne à payer.'); return; }
        if (champsLigneInvalides.length) {
            pp_afficherErreur('Vérifiez les montants saisis pour chaque ligne (valeurs manquantes ou nulles).', champsLigneInvalides);
            return;
        }

        // ── Validation bloc 3 (justificatifs) ───────────────────────────────
        const fichiers = document.getElementById('ppJustificatifs').files;
        if (!fichiers.length) {
            pp_afficherErreur('Ajoutez au moins un justificatif de paiement.');
            return;
        }

        // ── Construction du FormData ────────────────────────────────────────
        // Pas de champ idD : les lignes peuvent provenir de plusieurs
        // demandes différentes, le serveur le détermine lui-même par ligne.
        const fd = new FormData();
        fd.append('option', 14);
        fd.append('id_mode_reglement', idModeReglement);
        fd.append('id_mode_paiement', idModePaiement);

        if (parseInt(idModePaiement) === 3) {
            fd.append('nb_tranche', document.getElementById('ppNbTranches').value);
            const tranches = Array.from(document.querySelectorAll('.pp-tranche-inp')).map((inp, idx) => ({
                ordre: idx + 1,
                pourcentage: inp.value,
            }));
            fd.append('tranches', JSON.stringify(tranches));
        }

        fd.append('lignes', JSON.stringify(lignes));
        Array.from(fichiers).forEach(f => fd.append('justificatifs[]', f));

        vd_soumettreCommande(fd, this, 'modalPasserPaiement', function () {
            const chkTout = document.getElementById('checkAllPaiement');
            if (chkTout) chkTout.checked = false;
            chargerLignes();
        }, pp_afficherErreur);
    });
}

/**
 * Envoie un FormData (multipart, upload de fichier(s)) vers le contrôleur
 * drh_basi_controller.php (option 14) qui gère la transaction complète (paiement
 * + tranches + lignes + justificatifs + mise à jour de demandes_ligne).
 */
function vd_soumettreCommande(formData, boutonEl, modalId, onSuccess, onError) {
    vd_toggleModalSpinner(boutonEl, true);

    $.ajax({
        url: PC_CONTROLLER_URL,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
    }).done(function (res) {
        vd_toggleModalSpinner(boutonEl, false);
        try {
            if (res.status === 'success') {
                const modalEl = document.getElementById(modalId);
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                Swal.fire({ title: 'Succès', text: res.message || 'Paiement enregistré avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                if (typeof onSuccess === 'function') onSuccess();
            } else {
                // La modale reste ouverte : l'erreur s'affiche dans le formulaire.
                const message = res.message || 'Une erreur est survenue.';
                if (typeof onError === 'function') onError(message);
                else Swal.fire({ title: 'Erreur', text: message, icon: 'error', confirmButtonColor: '#dc2626' });
            }
        } catch (err) {
            console.error('Erreur traitement de la réponse du paiement :', err);
            const message = 'Une erreur inattendue est survenue.';
            if (typeof onError === 'function') onError(message);
            else Swal.fire('Erreur', message, 'error');
        }
    }).fail(function (xhr) {
        vd_toggleModalSpinner(boutonEl, false);
        const message = vd_ajaxErrorMessage(xhr);
        if (typeof onError === 'function') onError(message);
        else Swal.fire('Erreur', message, 'error');
    });
}

function vd_toggleModalSpinner(boutonEl, loading) {
    if (!boutonEl) return;
    const spinner = boutonEl.querySelector('.vd-spinner');
    if (spinner) spinner.classList.toggle('hidden', !loading);
    boutonEl.disabled = loading;
}

/* ────────────────────────────── UTILITAIRES ────────────────────── */
function vd_showLoader(msg = 'Chargement…') {
    $('#vd-loader').remove();
    $('body').append(`
        <div id="vd-loader">
            <div class="vd-loader-bg"></div>
            <div class="vd-loader-box">
                <svg class="vd-loader-spin" viewBox="0 0 50 50">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
                </svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function vd_hideLoader() { $('#vd-loader').remove(); }

function vd_ajaxErrorMessage(xhr) {
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

function vd_formatMontant(value) {
    const n = Number(value);
    if (isNaN(n)) return vd_escapeHtml(String(value ?? ''));
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' FCFA';
}

function vd_escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function vd_dataTableLangFr() {
    return {
        emptyTable: 'Aucune ligne à afficher.',
        zeroRecords: 'Aucun résultat ne correspond à votre recherche.',
        search: 'Rechercher :',
        lengthMenu: 'Afficher _MENU_ entrées',
        info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
        infoEmpty: 'Aucune entrée',
        paginate: { previous: 'Précédent', next: 'Suivant' }
    };
}