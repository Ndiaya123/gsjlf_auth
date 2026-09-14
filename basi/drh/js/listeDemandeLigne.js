/**
 * voir-demande-scripts.bundle.js
 * Page "Voir la demande" : affiche les lignes d'UNE demande (Achat ou
 * Paiement), avec colonnes et actions différentes selon le type.
 *
 * Récupère le détail via drh_basi_controller.php (option 9), puis réutilise les
 * actions groupées déjà en place sur /personnel/drh_basi_controller1
 * (options 3 = facture proforma, 4 = passer commande, 5 = passer au
 * paiement) — chaque ligne y est identifiée par la clé `idDemande`, qui
 * porte ici en réalité demandes_ligne.idDL (contrat déjà établi côté
 * back-end existant).
 */

const VD_INFO_URL  = '/personnel/drh_basi_controller?option=9';   // ← ajuster selon le chemin réel

let vd_demande   = null;   // { idD, idTypeDemande, type_demande, demandeur, date_creation, tmp }
let vd_estAchat  = true;
let vd_table     = null;   // instance DataTable active (achat OU paiement)
let vd_ligneParId = {};    // lignes indexées par idDemande (= idDL), pour retrouver les données sans redemander
let vd_selection  = new Set();

document.addEventListener('DOMContentLoaded', function () {
    if (!window.VD_DEMANDE_TOKEN) {
        Swal.fire('Erreur', "Token de demande manquant dans l'URL.", 'error')
            .then(() => window.history.back());
        return;
    }

    document.getElementById('vd-btn-retour')?.addEventListener('click', () => window.history.back());
    initModalesSubmit();
    chargerDemande();
});

/* ────────────────────────── CHARGEMENT INITIAL ─────────────────── */
function chargerDemande() {
    vd_showLoader('Chargement de la demande…');

    $.ajax({
        url: VD_INFO_URL,
        method: 'POST',
        data: { token: window.VD_DEMANDE_TOKEN },
        dataType: 'json'
    }).done(function (res) {
        vd_hideLoader();
        try {
            if (res.status !== 'success') {
                Swal.fire('Erreur', res.message || 'Demande introuvable.', 'error')
                    .then(() => window.history.back());
                return;
            }

            vd_demande  = res.demande;
            vd_estAchat = parseInt(vd_demande.idTypeDemande) === 1;

            vd_renderHeader();
            vd_setupPanelsForType();
            vd_indexerLignes(res.data || []);
            vd_renderTable(res.data || []);
        } catch (err) {
            console.error('Erreur traitement demande :', err);
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

/* ────────────────────────────── EN-TÊTE ────────────────────────── */
function vd_renderHeader() {
    const set = (id, v) => { const e = document.getElementById(id); if (e) e.innerHTML = v; };
    const txt = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };

    txt('vd-titre', `Demande #${vd_demande.idD}`);
    txt('vd-sous-titre', `${vd_demande.demandeur || '—'} · créée le ${vd_fmtDate(vd_demande.date_creation)}`);
    set('vd-badge-type', vd_estAchat
        ? '<span class="vd-badge">Achat</span>'
        : '<span class="vd-badge">Paiement</span>');
}

/* ─────────────────── AFFICHAGE DES PANNEAUX SELON LE TYPE ──────── */
function vd_setupPanelsForType() {
    document.getElementById('vd-panel-achat').style.display    = vd_estAchat ? '' : 'none';
    document.getElementById('vd-panel-paiement').style.display = vd_estAchat ? 'none' : '';
    document.getElementById('vd-toolbar-achat').style.display    = vd_estAchat ? '' : 'none';
    document.getElementById('vd-toolbar-paiement').style.display = vd_estAchat ? 'none' : '';
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function vd_renderTable(lignes) {
    if (vd_table) { try { vd_table.destroy(); } catch (e) {} vd_table = null; }

    if (vd_estAchat) {
        vd_table = $('#vd-table-achat').DataTable({
            data: lignes,
            columns: [
                { data: 'idDemande', orderable: false, searchable: false },
                { data: 'designation' },
                { data: 'quantite_demandee' },
                { data: 'quantite_commandee' },
                { data: 'quantite_restante' },
                { data: 'prix_unitaire' },
                { data: 'montant_total' },
                { data: 'rubrique' },
                { data: 'sous_rubrique' }
            ],
            columnDefs: [
                { targets: 0, render: d => vd_renderCheckbox(d) },
                { targets: 1, render: d => '<span class="vd-cell-name">' + vd_escapeHtml(d || '') + '</span>' },
                { targets: [2, 3, 4], render: d => '<span class="vd-cell-qty">' + vd_formatNumber(d) + '</span>' },
                { targets: 5, render: d => vd_formatMontant(d) },
                { targets: 6, render: d => '<span class="vd-cell-amount">' + vd_formatMontant(d) + '</span>' },
                { targets: [7, 8], render: d => d ? '<span class="vd-pill">' + vd_escapeHtml(d) + '</span>' : '<span class="vd-cell-muted">—</span>' },
            ],
            language: vd_dataTableLangFr(),
            drawCallback: () => vd_reappliquerSelection('vd-table-achat'),
        });

        $('#vd-table-achat tbody').off('change', '.vd-row-check').on('change', '.vd-row-check', vd_onRowCheckChange);
        document.getElementById('checkAllAchat').onchange = function () { vd_toggleAll('#vd-table-achat', this.checked); };

    } else {
        vd_table = $('#vd-table-paiement').DataTable({
            data: lignes,
            columns: [
                { data: 'idDemande', orderable: false, searchable: false },
                { data: 'designation' },
                { data: 'montant_a_payer' },
                { data: 'montant_paye' },
                { data: 'montant_restant' }
            ],
            columnDefs: [
                { targets: 0, render: d => vd_renderCheckbox(d) },
                { targets: 1, render: d => '<span class="vd-cell-name">' + vd_escapeHtml(d || '') + '</span>' },
                { targets: 2, render: d => '<span class="vd-cell-amount">' + vd_formatMontant(d) + '</span>' },
                { targets: 3, render: d => vd_formatMontant(d) },
                { targets: 4, render: d => '<span class="vd-cell-amount">' + vd_formatMontant(d) + '</span>' },
            ],
            language: vd_dataTableLangFr(),
            drawCallback: () => vd_reappliquerSelection('vd-table-paiement'),
            initComplete: function () {

                document.documentElement.classList.remove('ld-booting');
                document.getElementById('lb-table')?.classList.add('lb-ready');

            }
        });

        $('#vd-table-paiement tbody').off('change', '.vd-row-check').on('change', '.vd-row-check', vd_onRowCheckChange);
        document.getElementById('checkAllPaiement').onchange = function () { vd_toggleAll('#vd-table-paiement', this.checked); };
    }

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

    const btnCommande = document.getElementById('btnPasserCommande');
    const btnProforma = document.getElementById('btnDemandeFacture');
    const btnPaiement = document.getElementById('btnPasserPaiement');
    if (btnCommande) btnCommande.disabled = count === 0;
    if (btnProforma) btnProforma.disabled = count === 0;
    if (btnPaiement) btnPaiement.disabled = count === 0;
}

/* ──────────────────────── BOUTONS DE LA BARRE ──────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btnDemandeFacture')?.addEventListener('click', vd_ouvrirModaleDemandeFacture);
    document.getElementById('btnPasserCommande')?.addEventListener('click', vd_ouvrirModalePasserCommande);
    document.getElementById('btnPasserPaiement')?.addEventListener('click', vd_ouvrirModalePasserPaiement);
});

/* ═══════════════════════════ DEMANDE DE FACTURE (pro forma) ══════════ */
// Sélection obligatoire d'EXACTEMENT 3 fournisseurs, pour les lignes cochées.
let df_fournisseurs = [];
let df_listeChargee = false;

function vd_ouvrirModaleDemandeFacture() {
    df_chargerFournisseursSiNecessaire().then(function () {
        df_reset();
        df_afficherListe();
        new bootstrap.Modal(document.getElementById('modalDemandeFacture')).show();
    });
}

function df_chargerFournisseursSiNecessaire() {
    if (df_listeChargee) return Promise.resolve();

    return $.ajax({ url: PC_CONTROLLER_URL, method: 'POST', data: { option: 1 }, dataType: 'json' })
        .then(function (res) {
            df_fournisseurs = (res && res.status === 'success') ? (res.data || []).filter(f => f.statut === 'actif') : [];
            df_listeChargee = true;
        }).catch(function () {
            Swal.fire('Erreur', 'Impossible de charger la liste des fournisseurs.', 'error');
        });
}

function df_reset() {
    const banniere = document.getElementById('dfErreurGenerale');
    if (banniere) { banniere.style.display = 'none'; banniere.innerHTML = ''; }
}

function df_afficherErreur(message) {
    const banniere = document.getElementById('dfErreurGenerale');
    if (!banniere) return;
    banniere.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>${vd_escapeHtml(message)}</span>
    `;
    banniere.style.display = 'flex';
}

function df_afficherListe() {
    const conteneur = document.getElementById('dfListeFournisseurs');
    conteneur.innerHTML = df_fournisseurs.map(function (f) {
        const nom = `${f.prenomF || ''} ${f.nomF || ''}`.trim() + (f.entreprise ? ' — ' + f.entreprise : '');
        return `
            <label class="vd-df-item" data-idf="${f.idF}">
                <input type="checkbox" class="df-check" value="${f.idF}"/>
                <span>${vd_escapeHtml(nom)}</span>
            </label>
        `;
    }).join('') || '<p class="vd-cell-muted">Aucun fournisseur disponible.</p>';

    document.querySelectorAll('.df-check').forEach(cb => {
        cb.addEventListener('change', df_onCheckChange);
    });
    df_majCompteur();
}

/** Empêche de cocher un 4ᵉ fournisseur : les cases non cochées se désactivent une fois 3 atteintes. */
function df_onCheckChange() {
    df_majCompteur();
}

function df_majCompteur() {
    const cases = Array.from(document.querySelectorAll('.df-check'));
    const nbCoches = cases.filter(cb => cb.checked).length;

    document.getElementById('dfCompteur').textContent = nbCoches;

    cases.forEach(cb => {
        const item = cb.closest('.vd-df-item');
        if (!cb.checked && nbCoches >= 3) {
            cb.disabled = true;
            item.classList.add('vd-df-disabled');
        } else {
            cb.disabled = false;
            item.classList.remove('vd-df-disabled');
        }
    });
}

/* ═══════════════════════════ PASSER COMMANDE ═════════════════════════ */
/* ═══════════════════════════ PASSER COMMANDE ═════════════════════════ */
// Même contrôleur que VD_INFO_URL (drh_basi_controller.php), options 10/11/12.
const PC_CONTROLLER_URL = '/personnel/drh_basi_controller'; // ← ajuster selon le chemin réel

let pc_listesChargees  = false;
let pc_fournisseurs    = [];
let pc_modesReglement  = [];
let pc_modesPaiement   = [];

function vd_ouvrirModalePasserCommande() {
    pc_chargerListesSiNecessaire().then(function () {
        pc_resetBloc1();
        pc_remplirLignes();
        new bootstrap.Modal(document.getElementById('modalPasserCommande')).show();
    });
}

/* ── Bloc 1 : chargement des listes déroulantes (une seule fois) ─────── */
function pc_chargerListesSiNecessaire() {
    if (pc_listesChargees) return Promise.resolve();

    return Promise.all([
        $.ajax({ url: PC_CONTROLLER_URL, method: 'POST', data: { option: 1 },  dataType: 'json' }), // fournisseurs
        $.ajax({ url: PC_CONTROLLER_URL, method: 'POST', data: { option: 10 }, dataType: 'json' }), // mode_reglement
        $.ajax({ url: PC_CONTROLLER_URL, method: 'POST', data: { option: 11 }, dataType: 'json' }), // mode_paiement
    ]).then(function (res) {
        const [rF, rMR, rMP] = res;
        pc_fournisseurs   = (rF && rF.status === 'success')  ? (rF.data  || []).filter(f => f.statut === 'actif') : [];
        pc_modesReglement = (rMR && rMR.status === 'success') ? (rMR.data || []) : [];
        pc_modesPaiement  = (rMP && rMP.status === 'success') ? (rMP.data || []) : [];

        // Le fournisseur est désormais associé à CHAQUE pro forma (bloc 3),
        // plus à la commande globale (bloc 1).
        document.querySelectorAll('.pc-proforma-fournisseur').forEach(sel => {
            pc_remplirSelectEl(sel, pc_fournisseurs,
                f => f.idF, f => `${f.prenomF || ''} ${f.nomF || ''}`.trim() + (f.entreprise ? ' — ' + f.entreprise : ''));
        });
        pc_remplirSelect('pcModeReglement', pc_modesReglement, m => m.id, m => m.nom);
        pc_remplirSelect('pcModePaiement',  pc_modesPaiement,  m => m.id, m => m.nom);

        pc_listesChargees = true;
    }).catch(function () {
        Swal.fire('Erreur', 'Impossible de charger les listes (fournisseurs / modes de règlement / modalités de paiement).', 'error');
    });
}

function pc_remplirSelect(id, items, getValue, getLabel) {
    const sel = document.getElementById(id);
    if (!sel) return;
    pc_remplirSelectEl(sel, items, getValue, getLabel);
}

function pc_remplirSelectEl(sel, items, getValue, getLabel) {
    if (!sel) return;
    sel.innerHTML = '<option value="">Sélectionner…</option>' +
        items.map(it => `<option value="${getValue(it)}">${vd_escapeHtml(getLabel(it))}</option>`).join('');
}

function pc_resetBloc1() {
    const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v; };
    set('pcModeReglement', '');
    set('pcModePaiement', '');
    set('pcNbTranches', '');
    document.getElementById('pcNbTranchesWrap').style.display = 'none';
    document.getElementById('pcTranchesWrap').style.display = 'none';
    document.getElementById('pcTranchesFields').innerHTML = '';
    document.getElementById('pcTranchesErreur').style.display = 'none';

    // Bloc 3 : réinitialiser les 3 emplacements pro forma
    document.querySelectorAll('.pc-proforma-file').forEach(inp => { inp.value = ''; });
    document.querySelectorAll('.pc-proforma-fournisseur').forEach(sel => { sel.value = ''; });

    pc_clearErreur();
}

/* ── Bannière d'erreur générale du formulaire (remplace les popups) ──── */
function pc_afficherErreur(message, champsInvalides) {
    const banniere = document.getElementById('pcErreurGenerale');
    banniere.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>${vd_escapeHtml(message)}</span>
    `;
    banniere.style.display = 'flex';
    banniere.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    document.querySelectorAll('#modalPasserCommande .pc-invalid').forEach(el => el.classList.remove('pc-invalid'));
    (champsInvalides || []).forEach(el => { if (el) el.classList.add('pc-invalid'); });
}

function pc_clearErreur() {
    const banniere = document.getElementById('pcErreurGenerale');
    banniere.style.display = 'none';
    banniere.innerHTML = '';
    document.querySelectorAll('#modalPasserCommande .pc-invalid').forEach(el => el.classList.remove('pc-invalid'));
}

/* ── Gestion des tranches (affichées seulement si modalité de paiement = 3) ── */
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('pcModePaiement')?.addEventListener('change', function () {
        const estTranche = parseInt(this.value) === 3;
        document.getElementById('pcNbTranchesWrap').style.display = estTranche ? '' : 'none';
        document.getElementById('pcTranchesWrap').style.display   = estTranche ? '' : 'none';
        if (!estTranche) {
            document.getElementById('pcNbTranches').value = '';
            document.getElementById('pcTranchesFields').innerHTML = '';
            document.getElementById('pcTranchesErreur').style.display = 'none';
        }
    });

    document.getElementById('pcNbTranches')?.addEventListener('input', pc_genererChampsTranches);
});

function pc_genererChampsTranches() {
    const n = parseInt(document.getElementById('pcNbTranches').value);
    const wrap = document.getElementById('pcTranchesFields');
    wrap.innerHTML = '';
    document.getElementById('pcTranchesErreur').style.display = 'none';

    if (!n || n < 2) return;

    for (let i = 1; i <= n; i++) {
        const estDerniere = (i === n);
        wrap.insertAdjacentHTML('beforeend', `
            <div class="vd-pc-tranche-field">
                <label>Tranche ${i} (%)</label>
                <input type="number" class="vd-modal-inp pc-tranche-inp" data-ordre="${i}"
                       min="0" max="100" step="0.01"
                       ${estDerniere ? 'readonly placeholder="Calculé automatiquement"' : 'placeholder="0"'}/>
            </div>
        `);
    }

    wrap.querySelectorAll('.pc-tranche-inp:not([readonly])').forEach(inp => {
        inp.addEventListener('input', pc_recalculerDerniereTranche);
    });
    pc_recalculerDerniereTranche();
}

function pc_recalculerDerniereTranche() {
    const inputs = Array.from(document.querySelectorAll('.pc-tranche-inp'));
    if (!inputs.length) return;

    const derniere = inputs[inputs.length - 1];
    const autres   = inputs.slice(0, -1);
    const somme    = autres.reduce((s, inp) => s + (parseFloat(inp.value) || 0), 0);
    const calc     = Math.round((100 - somme) * 100) / 100;
    derniere.value = calc;

    const err = document.getElementById('pcTranchesErreur');
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

/** Retourne un message d'erreur si la répartition des tranches est invalide, sinon null. */
function pc_validerTranches() {
    const estTranche = parseInt(document.getElementById('pcModePaiement').value) === 3;
    if (!estTranche) return null;

    const n = parseInt(document.getElementById('pcNbTranches').value);
    if (!n || n < 2) return 'Renseignez un nombre de tranches valide (2 ou plus).';

    const inputs = Array.from(document.querySelectorAll('.pc-tranche-inp'));
    if (inputs.length !== n) return 'Les champs de tranches ne sont pas à jour, réessayez.';

    const valeurs = inputs.map(inp => parseFloat(inp.value) || 0);
    const somme   = valeurs.reduce((s, v) => s + v, 0);

    for (let i = 0; i < valeurs.length; i++) {
        if (valeurs[i] <= 0) return `La tranche ${i + 1} doit être strictement supérieure à 0 %.`;
    }
    if (Math.abs(somme - 100) > 0.01) return `La somme des tranches doit être exactement égale à 100 % (actuellement ${somme.toFixed(2)} %).`;

    return null;
}

/* ── Bloc 2 : lignes de commande (uniquement les lignes sélectionnées) ── */

/**
 * Filtre la saisie d'un champ <input type="number"> pour ne garder que des
 * chiffres entiers (bloque ".", "-", "e" que le type="number" laisse
 * pourtant taper au clavier malgré step="1").
 */
function pc_forcerEntier(e) {
    const el = e.target;
    const nettoye = el.value.replace(/[^\d]/g, '');
    if (nettoye !== el.value) el.value = nettoye;
}

function pc_remplirLignes() {
    const body = document.getElementById('bodyPasserCommande');
    body.innerHTML = '';

    vd_selection.forEach(function (id) {
        const row = vd_ligneParId[id];
        if (!row) return;
        const idUnite      = row.id_unite;
        const afficherPPU  = String(idUnite) !== '1';
        const qteMax       = Math.max(1, Math.floor(parseFloat(row.quantite_restante) || 1));

        // La cellule "Pièces par unité" reste toujours présente dans la ligne
        // (même quand elle n'est pas applicable) pour ne jamais décaler les
        // colonnes suivantes — seul son CONTENU change (champ ou "—").
        const cellulePPU = afficherPPU
            ? '<input type="number" class="vd-modal-inp pc-ppu" min="1" step="1" inputmode="numeric" value="1"/>'
            : '<span class="vd-cell-muted">—</span>';

        // Le prix n'est pas saisi à cette étape (renseigné plus tard).
        body.insertAdjacentHTML('beforeend', `
            <tr data-id="${id}" data-id-unite="${idUnite ?? ''}" data-ppu-applicable="${afficherPPU ? '1' : '0'}">
                <td>${vd_escapeHtml(row.designation || '')}</td>
                <td class="vd-cell-muted">${vd_escapeHtml(row.unite || '—')}</td>
                <td><input type="number" class="vd-modal-inp pc-nb-pieces" min="1" step="1" inputmode="numeric"
                           max="${qteMax}" value="${qteMax}"/></td>
                <td class="pc-cell-ppu">${cellulePPU}</td>
            </tr>
        `);
    });

    // Saisie strictement entière : filtre tout caractère non numérique (bloque
    // ".", "-", "e" que le type="number" laisse pourtant taper au clavier).
    body.querySelectorAll('.pc-nb-pieces, .pc-ppu').forEach(inp => {
        inp.addEventListener('input', pc_forcerEntier);
    });
}

/* ═══════════════════════════ PASSER AU PAIEMENT ══════════════════════ */
/* ═══════════════════════════════════════════════════════════════════════════
   PASSER AU PAIEMENT
═══════════════════════════════════════════════════════════════════════════ */

function vd_ouvrirModalePasserPaiement() {
    pp_chargerListesSiNecessaire().then(function () {
        pp_resetBloc1();
        pp_remplirLignes();
        pp_reinitialiserJustificatifs();
        new bootstrap.Modal(document.getElementById('modalPasserPaiement')).show();
    });
}

/* ── Bloc 1 : réutilise les mêmes listes que "Passer commande" ────────── */
function pp_chargerListesSiNecessaire() {
    return pc_chargerListesSiNecessaire().then(function () {
        pc_remplirSelectEl(document.getElementById('ppModeReglement'), pc_modesReglement, m => m.id, m => m.nom);
        pc_remplirSelectEl(document.getElementById('ppModePaiement'),  pc_modesPaiement,  m => m.id, m => m.nom);
    });
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

/* ── Tranches (modalité de paiement = 3), même logique que "Passer commande" ── */
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

/* ────────────────────── SOUMISSION DES MODALES ─────────────────── */
function initModalesSubmit() {
    document.getElementById('submitDemandeFacture')?.addEventListener('click', function () {
        df_reset();

        const fournisseursCoches = Array.from(document.querySelectorAll('.df-check:checked')).map(cb => cb.value);
        if (fournisseursCoches.length !== 3) {
            df_afficherErreur('Vous devez sélectionner exactement 3 fournisseurs.');
            return;
        }

        const idDLListe = Array.from(vd_selection);
        if (!idDLListe.length) {
            df_afficherErreur('Aucune ligne sélectionnée.');
            return;
        }

        const boutonEl = this;
        vd_toggleModalSpinner(boutonEl, true);

        $.ajax({
            url: PC_CONTROLLER_URL,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                option: 13,
                idDL_liste: idDLListe,
                fournisseurs: fournisseursCoches,
            }),
            dataType: 'json',
        }).done(function (res) {
            vd_toggleModalSpinner(boutonEl, false);
            if (res.status === 'success') {
                // Ouvre la génération des pro forma dans un nouvel onglet
                // (téléchargement du ZIP), ferme la modale, puis actualise
                // la page courante pour refléter le nouvel état.
                window.open('/personnel/drh_demande_facture_proforma', '_blank');

                const modalEl = document.getElementById('modalDemandeFacture');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                const chkTout = document.getElementById('checkAllAchat');
                if (chkTout) chkTout.checked = false;

                window.location.reload();
            } else {
                df_afficherErreur(res.message || 'Une erreur est survenue.');
            }
        }).fail(function (xhr) {
            vd_toggleModalSpinner(boutonEl, false);
            df_afficherErreur(vd_ajaxErrorMessage(xhr));
        });
    });

    document.getElementById('submitPasserCommande')?.addEventListener('click', function () {
        pc_clearErreur();

        // ── Validation bloc 1 ──────────────────────────────────────────────
        const elModeReglement = document.getElementById('pcModeReglement');
        const elModePaiement  = document.getElementById('pcModePaiement');
        const idModeReglement = elModeReglement.value;
        const idModePaiement  = elModePaiement.value;

        if (!idModeReglement) { pc_afficherErreur('Sélectionnez un mode de règlement.', [elModeReglement]); return; }
        if (!idModePaiement)  { pc_afficherErreur('Sélectionnez une modalité de paiement.', [elModePaiement]); return; }

        const erreurTranches = pc_validerTranches();
        if (erreurTranches) { pc_afficherErreur(erreurTranches); return; }

        // ── Validation bloc 2 (lignes) — le prix n'est plus saisi ici ──────
        const lignes = [];
        const champsLigneInvalides = [];

        document.querySelectorAll('#bodyPasserCommande tr').forEach(function (tr) {
            const idUnite = tr.dataset.idUnite;
            const estAutreUnite = String(idUnite) !== '1';
            const qteEl = tr.querySelector('.pc-nb-pieces');
            const ppuEl = tr.querySelector('.pc-ppu');
            const qte = qteEl.value;

            if (!qte || parseFloat(qte) <= 0) champsLigneInvalides.push(qteEl);

            lignes.push({
                idDemande: tr.dataset.id, // = idDL, contrat déjà en place
                quantite_reelle: qte,
                id_unite: idUnite,
                nb_unites: estAutreUnite ? (ppuEl ? ppuEl.value : '') : '',
            });
        });

        if (!lignes.length) { pc_afficherErreur('Aucune ligne à commander.'); return; }
        if (champsLigneInvalides.length) {
            pc_afficherErreur('Vérifiez les quantités saisies pour chaque ligne (valeurs manquantes ou nulles).', champsLigneInvalides);
            return;
        }

        // ── Validation bloc 3 (pro forma) : fichier + fournisseur obligatoires
        // ensemble sur chaque emplacement rempli, au moins un emplacement requis.
        const fd = new FormData();
        let auMoinsUnProforma = false;
        const champsProformaInvalides = [];

        for (let i = 1; i <= 3; i++) {
            const fileEl = document.querySelector(`.pc-proforma-file[data-ordre="${i}"]`);
            const selEl  = document.querySelector(`.pc-proforma-fournisseur[data-ordre="${i}"]`);
            const fichier = fileEl.files[0] || null;
            const idFournisseurPF = selEl.value;

            if (!fichier && !idFournisseurPF) continue; // emplacement non utilisé

            if (!fichier || !idFournisseurPF) {
                if (!fichier)          champsProformaInvalides.push(fileEl);
                if (!idFournisseurPF)  champsProformaInvalides.push(selEl);
                pc_afficherErreur(
                    `Pro forma ${i} : le fichier PDF et le fournisseur sont tous les deux obligatoires si l'un des deux est renseigné.`,
                    champsProformaInvalides
                );
                return;
            }

            fd.append(`proforma_file_${i}`, fichier);
            fd.append(`proforma_fournisseur_${i}`, idFournisseurPF);
            auMoinsUnProforma = true;
        }

        if (!auMoinsUnProforma) {
            pc_afficherErreur('Ajoutez au moins une facture pro forma avec son fournisseur.');
            return;
        }

        // ── Construction du FormData ────────────────────────────────────────
        fd.append('option', 12);
        fd.append('id_mode_reglement', idModeReglement);
        fd.append('id_mode_paiement', idModePaiement);

        if (parseInt(idModePaiement) === 3) {
            fd.append('nb_tranche', document.getElementById('pcNbTranches').value);
            const tranches = Array.from(document.querySelectorAll('.pc-tranche-inp')).map((inp, idx) => ({
                ordre: idx + 1,
                pourcentage: inp.value,
            }));
            fd.append('tranches', JSON.stringify(tranches));
        }

        fd.append('lignes', JSON.stringify(lignes));

        vd_soumettreCommande(fd, this, 'modalPasserCommande', function () {
            const chkTout = document.getElementById('checkAllAchat');
            if (chkTout) chkTout.checked = false;
            chargerDemande();
        }, pc_afficherErreur);
    });

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

        vd_soumettreCommande(fd, this, 'modalPasserPaiement', chargerDemande, pp_afficherErreur);
    });
}

/**
 * Envoie un FormData (multipart, upload de fichier(s)) plutôt qu'un body
 * JSON, vers le contrôleur drh_basi_controller.php (options 12/14) qui gère la
 * transaction complète (commande/paiement + tranches + lignes + mise à jour
 * de demandes_ligne). Réutilisée par "Passer commande" et "Passer au paiement".
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

                Swal.fire({ title: 'Succès', text: res.message || 'Commande enregistrée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                if (typeof onSuccess === 'function') onSuccess();
            } else {
                // La modale reste ouverte : l'erreur s'affiche dans le formulaire.
                const message = res.message || 'Une erreur est survenue.';
                if (typeof onError === 'function') onError(message);
                else Swal.fire({ title: 'Erreur', text: message, icon: 'error', confirmButtonColor: '#dc2626' });
            }
        } catch (err) {
            console.error('Erreur traitement de la réponse de la commande :', err);
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

function vd_fmtDate(d) { return d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—'; }

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

function vd_formatNumber(value) {
    const n = Number(value);
    if (isNaN(n)) return vd_escapeHtml(String(value ?? ''));
    return n.toLocaleString('fr-FR');
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
        emptyTable: 'Aucune ligne pour cette demande.',
        zeroRecords: 'Aucun résultat ne correspond à votre recherche.',
        search: 'Rechercher :',
        lengthMenu: 'Afficher _MENU_ entrées',
        info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
        infoEmpty: 'Aucune entrée',
        paginate: { previous: 'Précédent', next: 'Suivant' }
    };
}