/**
 * expression-besoin-scripts.bundle.js
 * Page "Expression de besoin" (tous profils) : liste filtrable par
 * intervalle d'années, création/modification (catégorie → sous-catégorie →
 * produit → quantité), Soumettre et poursuivre / Soumettre / Modifier /
 * Détail / Voir (suivi de l'évolution) selon le statut.
 */

const EB_CONTROLLER_URL = '/personnel/personnel_basi_controller'; // ← corrigé (nom réel du fichier)
const ANNEE_MIN_EB = 2026;

const LIBELLES_STATUT_EB = {
    1: 'En attente', 2: 'Soumise', 3: 'Validée', 4: 'Rejetée',
    5: 'Sortie partielle', 6: 'Sortie totale', 7: 'Livrée', 8: 'Clôturée',
    9: 'Clôturée avec solde', 10: 'Annulée',
};
const LIBELLES_STATUT_LIGNE_EB = {
    'En attente': 'dga-ligne-attente',
    'Sorti du stock — en attente du magasinier': 'dga-ligne-partiel',
    'Livré — en attente de votre confirmation': 'dga-ligne-a-confirmer',
    'Reçu partiellement': 'dga-ligne-partiel',
    'Écart signalé — en attente du comptable': 'dga-ligne-ecart',
    'Reçu — solde annulé': 'dga-ligne-livre',
    'Annulée': 'dga-ligne-attente',
    'Reçu': 'dga-ligne-livre',
};

let dga_table = null;
let dga_tokenVoirCourant = null;
let dga_produitsVoirCourant = [];
let dga_bonsVoirCourant = [];
const LIBELLES_STATUT_BON = { 1: 'Sortie enregistrée', 2: 'Livraison partielle', 3: 'Livré', 4: 'Réception partielle', 5: 'Reçu', 6: 'Écart signalé', 7: 'Clos avec écart' };
const CLASSES_STATUT_BON = { 1: 'dga-ligne-attente', 2: 'dga-ligne-partiel', 3: 'dga-ligne-a-confirmer', 4: 'dga-ligne-partiel', 5: 'dga-ligne-livre', 6: 'dga-ligne-ecart', 7: 'dga-ligne-livre' };
let dga_produitsPanier = []; // [{ idP, designation, quantite }]
let dga_tokenCourant = null; // token de l'EB en cours de modification (null = création)

// ── État Investissement ─────────────────────────────────────────────────
const LIBELLES_STATUT_EBI = { 1: 'Brouillon', 2: 'Terminé' };
let dga_tableInvest = null;
let dga_produitsPanierInvest = []; // [{ idP, designation, quantite, quotaDisponible }]
let dga_tokenCourantInvest = null;
let dga_quotasParProduit = {}; // { idP: quota } — mémorisé pour valider la saisie côté client

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    initSelectsAnnees();
    chargerExpressions();
    dga_initOnglets();

    document.getElementById('dga-btn-appliquer-filtres')?.addEventListener('click', chargerExpressions);
    document.getElementById('dga-btn-reset-filtres')?.addEventListener('click', function () {
        const anneeCourante = new Date().getFullYear();
        document.getElementById('dga-annee-debut').value = '';
        document.getElementById('dga-annee-fin').value = anneeCourante;
        chargerExpressions();
    });

    document.getElementById('dga-btn-nouvelle')?.addEventListener('click', dga_ouvrirNouvelle);
    document.getElementById('dgaCategorie')?.addEventListener('change', dga_chargerSousCategories);
    document.getElementById('dgaSousCategorie')?.addEventListener('change', dga_chargerProduits);
    document.getElementById('dgaBtnAjouterProduit')?.addEventListener('click', dga_ajouterProduitPanier);
    document.getElementById('dgaBtnPoursuivre')?.addEventListener('click', () => dga_soumettreExpression('poursuivre'));
    document.getElementById('dgaBtnSoumettre')?.addEventListener('click', () => dga_soumettreExpression('soumettre'));

    // ── Investissement ──────────────────────────────────────────────────
    document.getElementById('dga-btn-nouvelle-invest')?.addEventListener('click', dga_ouvrirNouvelleInvest);
    document.getElementById('dgaProduitInvest')?.addEventListener('change', dga_onProduitInvestChange);
    document.getElementById('dgaBtnAjouterProduitInvest')?.addEventListener('click', dga_ajouterProduitPanierInvest);
    document.getElementById('dgaBtnPoursuivreInvest')?.addEventListener('click', () => dga_soumettreExpressionInvest('poursuivre'));
    document.getElementById('dgaBtnTerminerInvest')?.addEventListener('click', dga_confirmerTerminerInvest);
});

/* ────────────────────────── ONGLETS ─────────────────────────────────── */
function dga_initOnglets() {
    // Les deux instances du commutateur (une par panneau) partagent le même
    // comportement — un seul écouteur délégué sur chaque bouton .dga-switch-btn.
    document.querySelectorAll('.dga-switch-btn').forEach(function (btn) {
        btn.addEventListener('click', () => dga_activerOnglet(btn.dataset.cible));
    });

    // Le commutateur reste masqué (et la page se comporte exactement comme
    // avant cette fonctionnalité) pour tout utilisateur qui n'est pas chef
    // de service (idDirection absent en session).
    $.ajax({ url: EB_CONTROLLER_URL, method: 'POST', data: { option: 10 }, dataType: 'json' })
        .done(function (res) {
            if (res.status === 'success' && res.aDirection) {
                document.getElementById('dga-switch-type-1').style.display = '';
                const nomDir = document.getElementById('dga-nom-direction');
                if (nomDir) nomDir.textContent = res.nomDirection || '—';
                chargerExpressionsInvest();
            }
        });
}

function dga_activerOnglet(nom) {
    const estFonctionnement = nom === 'fonctionnement';
    document.getElementById('dga-panel-fonctionnement').style.display = estFonctionnement ? '' : 'none';
    document.getElementById('dga-panel-investissement').style.display = estFonctionnement ? 'none' : '';
    document.querySelectorAll('.dga-switch-btn').forEach(function (btn) {
        btn.classList.toggle('dga-switch-active', btn.dataset.cible === nom);
    });
}

/* ────────────────────────── ANNÉES (filtres) ───────────────────────── */
function initSelectsAnnees() {
    const anneeCourante = new Date().getFullYear();
    const debutSel = document.getElementById('dga-annee-debut');
    const finSel   = document.getElementById('dga-annee-fin');
    if (!debutSel || !finSel) return;

    const anneeMax = Math.max(anneeCourante, ANNEE_MIN_EB);
    let options = '';
    for (let a = ANNEE_MIN_EB; a <= anneeMax; a++) {
        options += `<option value="${a}">${a}</option>`;
    }

    debutSel.innerHTML = '<option value="">Toutes</option>' + options;
    finSel.innerHTML   = options;

    debutSel.value = '';
    finSel.value   = anneeCourante;
}

/* ────────────────────────── CHARGEMENT LISTE ───────────────────────── */
function chargerExpressions() {
    const anneeDebutVal = document.getElementById('dga-annee-debut').value;
    const anneeFinVal   = document.getElementById('dga-annee-fin').value;

    if (anneeDebutVal !== '' && anneeFinVal !== '' && parseInt(anneeDebutVal) > parseInt(anneeFinVal)) {
        Swal.fire('Intervalle invalide', "« Année de début » ne peut pas être supérieure à « Année de fin ».", 'warning');
        return;
    }

    dga_showLoader('Chargement des expressions de besoin…');

    $.ajax({
        url: EB_CONTROLLER_URL,
        method: 'POST',
        data: { option: 1, anneeDebut: anneeDebutVal, anneeFin: anneeFinVal },
        dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les expressions de besoin.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
        document.getElementById('dga-stat-nombre').textContent = res.nombre_total ?? 0;
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function dga_renderTable(expressions) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-eb').DataTable({
        data: expressions,
        columns: [
            { data: 'nom_expression' },
            { data: 'date_creation' },
            { data: 'nombre_produits' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            { targets: 1, render: d => dga_fmtDate(d) },
            { targets: 3, render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT_EB[d] || d}</span>` },
            {
                targets: 4,
                render: (d, t, row) => {
                    const statut = parseInt(row.idStatut);
                    let html = '';
                    if (statut === 2) {
                        html += `<button type="button" class="dga-btn-voir-eb" onclick="
dga_ouvrirConsulter('${d}')">Voir</button>`;

                    }else if (statut === 3 || statut === 4 || statut === 5 || statut === 6 || statut === 7 || statut === 8 || statut === 9 || statut === 10)
                    {
                        html += `<button type="button" class="dga-btn-voir-eb" onclick="dga_ouvrirVoir('${d}')">Voir</button>`;

                    }
                    if (statut === 1) {
                        html += `<button type="button" class="dga-btn-poursuivre" onclick="dga_ouvrirModifier('${d}')">Poursuivre</button>`;

                    }
                    if (statut === 4) {
                        html += `<button type="button" class="dga-btn-modifier-eb" onclick="dga_ouvrirModifier('${d}')">Modifier</button>`;
                    }
                    return html;
                },
            },
        ],
        order: [[1, 'desc']],
        language: {
            emptyTable: 'Aucune expression de besoin à afficher.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
        initComplete: function () {
            document.documentElement.classList.remove('ld-booting');
        }
    });
}

/* ────────────────────────── VOIR (suivi de l'évolution) ─────────────── */
function dga_ouvrirVoir(token) {
    dga_showLoader('Chargement du suivi…');
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 7, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le suivi.', 'error');
            return;
        }

        const e = res.expression;
        document.getElementById('voirEbModalTitre').textContent = 'Suivi — ' + e.nom_expression;

        const enteteHtml = `
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
                <span style="font-size:.82rem;color:#374151;"><strong>Créée le :</strong> ${dga_fmtDate(e.date_creation)}</span>
                <span class="dga-badge-statut dga-statut-${e.idStatut}">${LIBELLES_STATUT_EB[e.idStatut] || e.idStatut}</span>
            </div>
        `;

        const motifRejetHtml = (parseInt(e.idStatut) === 4 && e.motif_rejet)
            ? `<div class="dga-alerte-rejet"><strong>Motif du rejet :</strong> ${dga_escapeHtml(e.motif_rejet)}</div>`
            : '';

        dga_tokenVoirCourant = token;
        dga_produitsVoirCourant = e.produits || [];
        dga_bonsVoirCourant = e.bons || [];

        const lignesProduits = (e.produits || []).map(function (p) {
            const classeLigne = LIBELLES_STATUT_LIGNE_EB[p.statut_ligne] || 'dga-ligne-attente';
            return `
                <tr>
                    <td>${dga_escapeHtml(p.designation)}</td>
                    <td>${dga_escapeHtml(p.quantite)}</td>
                    <td>${p.quantite_reelle !== null && p.quantite_reelle !== undefined ? dga_escapeHtml(p.quantite_reelle) : '—'}${parseFloat(p.quantite_annulee) > 0 ? ` <span style="color:#991b1b;font-size:.7rem;">(−${String(parseFloat(p.quantite_annulee))} annulé)</span>` : ''}</td>
                    <td>${p.quantite_sortie !== null && p.quantite_sortie !== undefined ? dga_escapeHtml(p.quantite_sortie) : '—'}</td>
                    <td>${p.quantite_livree !== null && p.quantite_livree !== undefined ? dga_escapeHtml(p.quantite_livree) : '—'}</td>
                    <td>${p.quantite_recue !== null && p.quantite_recue !== undefined ? dga_escapeHtml(p.quantite_recue) : '—'}</td>
                    <td><span class="dga-badge-ligne ${classeLigne}">${p.statut_ligne}</span></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="7" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        const produitsHtml = `
            <table class="dga-table-produits">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th>Demandée</th>
                        <th>Validée</th>
                        <th>Sortie</th>
                        <th>Livrée</th>
                        <th>Reçue</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>${lignesProduits}</tbody>
            </table>
        `;

        // ── Bons de sortie : suivi des livraisons partielles et successives ──
        const bonsHtml = (e.bons || []).length ? `
            <h4 style="font-size:.85rem;font-weight:800;color:#111827;margin:1rem 0 .5rem;">Bons de sortie (${e.bons.length})</h4>
            ${e.bons.map(function (b) {
            const lignesBon = (b.lignes || []).map(l => `
                    <tr>
                        <td>${dga_escapeHtml(l.designation)}</td>
                        <td>${String(parseFloat(l.quantite_sortie))}</td>
                        <td>${String(parseFloat(l.quantite_livree))}</td>
                        <td>${String(parseFloat(l.quantite_recue))}</td>
                        <td>${parseFloat(l.quantite_ecart) > 0 ? `<span style="color:#991b1b;">${String(parseFloat(l.quantite_ecart))} à régulariser</span>` : ''}${parseFloat(l.quantite_perdue) > 0 ? `<span style="color:#6b7280;">${String(parseFloat(l.quantite_perdue))} perdu</span>` : ''}${(parseFloat(l.quantite_ecart) > 0 || parseFloat(l.quantite_perdue) > 0) ? '' : '—'}</td>
                    </tr>`).join('');
            return `
                    <div style="border:1px solid #e9ecef;border-radius:10px;padding:.7rem .85rem;margin-bottom:.6rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
                            <span style="font-weight:800;font-size:.82rem;">${dga_escapeHtml(b.numero_bon)}
                                <span style="font-weight:400;color:#9ca3af;"> — sorti le ${dga_fmtDate(b.dateSortie)}</span></span>
                            <span class="dga-badge-ligne ${CLASSES_STATUT_BON[b.idStatut] || 'dga-ligne-attente'}">${LIBELLES_STATUT_BON[b.idStatut] || b.idStatut}</span>
                        </div>
                        <table class="dga-table-produits">
                            <thead><tr><th>Produit</th><th>Sorti</th><th>Livré</th><th>Reçu</th><th>Écart</th></tr></thead>
                            <tbody>${lignesBon}</tbody>
                        </table>
                    </div>`;
        }).join('')}
        ` : '';

        // Bouton "Reçu" — visible dès qu'au moins une ligne a été remise
        // par le magasinier mais pas encore confirmée par le demandeur.
        const aQuelqueChoseAConfirmer = (e.bons || []).some(b => b.peut_confirmer);
        const receptionHtml = aQuelqueChoseAConfirmer
            ? `<button type="button" class="dga-submit" id="dgaBtnConfirmerReception" style="margin-bottom:1rem;" onclick="dga_confirmerReception()">
                   <span>Reçu — confirmer la réception</span>
               </button>`
            : '';

        const historiqueRows = (e.historique || []).map(function (h) {
            return `
                <li>
                    <strong>${dga_fmtDateHeure(h.dateEnregistrement)}</strong>
                    — ${LIBELLES_STATUT_EB[h.idStatut] || h.idStatut}
                    ${h.motif ? `<span class="dga-histo-motif"> (${dga_escapeHtml(h.motif)})</span>` : ''}
                </li>
            `;
        }).join('') || '<li style="color:#9ca3af;font-style:italic;">Aucun historique.</li>';

        const historiqueHtml = `
            <h4 class="dga-soustitre">Historique</h4>
            <ul class="dga-liste-historique">${historiqueRows}</ul>
        `;

        document.getElementById('voirEbContenu').innerHTML = enteteHtml + motifRejetHtml + receptionHtml + produitsHtml + bonsHtml + historiqueHtml;

        new bootstrap.Modal(document.getElementById('modalVoirEB')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/**
 * Saisie d'un écart : "Non reçu" ajuste automatiquement "Reçu" (reste − écart)
 * et fait apparaître le champ motif, obligatoire dès qu'un écart est déclaré.
 */
function dga_onEcartChange(input) {
    const id = input.dataset.id;
    const max = parseFloat(input.dataset.max) || 0;
    let ecart = parseFloat(input.value) || 0;
    if (ecart < 0) ecart = 0;
    if (ecart > max) { ecart = max; input.value = String(max); }
    const recue = document.querySelector('.dga-swal-qte-recue[data-id="' + id + '"]');
    if (recue) recue.value = String(Math.round((max - ecart) * 100) / 100);
    const ligneComm = document.querySelector('.dga-swal-ligne-comm[data-id="' + id + '"]');
    if (ligneComm) ligneComm.style.display = ecart > 0 ? '' : 'none';
}

/**
 * "Reçu" : le demandeur indique, LIGNE PAR LIGNE ET BON PAR BON :
 *   - Reçu     : la quantité réellement reçue (de 0 au reste à confirmer) ;
 *   - Non reçu : la quantité marquée "livrée" mais qu'il n'a PAS reçue — un
 *                motif est alors obligatoire, l'écart est transmis au comptable.
 * Il peut aussi confirmer une partie seulement (Reçu + Non reçu < remis) ;
 * le reste demeure à confirmer. Le serveur revalide strictement le plafond.
 */
function dga_confirmerReception() {
    const bons = dga_bonsVoirCourant.filter(b => b.peut_confirmer);
    if (!bons.length) return;

    const nb = v => String(parseFloat(v));
    const blocsHtml = bons.map(function (b) {
        const lignes = (b.lignes || []).filter(l => l.peut_confirmer_reception).map(l => `
            <tr>
                <td style="text-align:left;padding:.35rem .5rem;">${dga_escapeHtml(l.designation)}</td>
                <td style="padding:.35rem .5rem;">${nb(l.quantite_restante_a_recevoir)}</td>
                <td style="padding:.35rem .5rem;">
                    <input type="number" class="swal2-input dga-swal-qte-recue" data-id="${l.idBSL}" data-max="${l.quantite_restante_a_recevoir}"
                           min="0" max="${l.quantite_restante_a_recevoir}" step="0.01" value="${l.quantite_restante_a_recevoir}"
                           style="width:90px;margin:0;height:2.2rem;font-size:.9rem;">
                </td>
                <td style="padding:.35rem .5rem;">
                    <input type="number" class="swal2-input dga-swal-ecart" data-id="${l.idBSL}" data-max="${l.quantite_restante_a_recevoir}"
                           min="0" max="${l.quantite_restante_a_recevoir}" step="0.01" value="0" oninput="dga_onEcartChange(this)"
                           style="width:90px;margin:0;height:2.2rem;font-size:.9rem;">
                </td>
            </tr>
            <tr class="dga-swal-ligne-comm" data-id="${l.idBSL}" style="display:none;">
                <td colspan="4" style="padding:0 .5rem .5rem;">
                    <input type="text" class="swal2-input dga-swal-comm" data-id="${l.idBSL}" maxlength="500"
                           placeholder="Motif de l'écart (obligatoire) : ex. colis incomplet, produit non remis…"
                           style="width:100%;margin:0;height:2.2rem;font-size:.85rem;">
                </td>
            </tr>`).join('');
        return `
            <div style="text-align:left;font-weight:800;font-size:.8rem;margin:.8rem 0 .25rem;color:#111827;">
                ${dga_escapeHtml(b.numero_bon)} <span style="font-weight:400;color:#9ca3af;">— sorti le ${dga_fmtDate(b.dateSortie)}</span>
            </div>
            <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
                <thead><tr style="color:#9ca3af;font-size:.68rem;text-transform:uppercase;">
                    <th style="text-align:left;padding:.3rem .5rem;">Produit</th>
                    <th style="padding:.3rem .5rem;">Remis (à confirmer)</th>
                    <th style="padding:.3rem .5rem;">Reçu</th>
                    <th style="padding:.3rem .5rem;">Non reçu</th>
                </tr></thead>
                <tbody>${lignes}</tbody>
            </table>`;
    }).join('');

    Swal.fire({
        title: 'Confirmer la réception',
        width: 720,
        html: `
            <p style="font-size:.85rem;color:#6b7280;margin-bottom:.4rem;">
                Indiquez la quantité <strong>réellement reçue</strong> pour chaque produit. Si un produit marqué « livré »
                ne vous a <strong>pas</strong> été remis, saisissez-le dans « Non reçu » et précisez le motif : le comptable
                régularisera. Vous pouvez aussi confirmer une partie seulement, le reste demeurera à confirmer.
            </p>${blocsHtml}`,
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        confirmButtonColor: '#1a7a5e',
        cancelButtonText: 'Annuler',
        cancelButtonColor: '#6b7280',
        preConfirm: function () {
            const quantites = [];
            let auMoinsUne = false, depasse = false, motifManquant = false;
            document.querySelectorAll('.dga-swal-qte-recue').forEach(function (inp) {
                const id = inp.dataset.id;
                const max = parseFloat(inp.dataset.max) || 0;
                const recue = parseFloat(inp.value) || 0;
                const ecart = parseFloat(document.querySelector('.dga-swal-ecart[data-id="' + id + '"]')?.value) || 0;
                const comm = (document.querySelector('.dga-swal-comm[data-id="' + id + '"]')?.value || '').trim();
                if (recue < 0 || ecart < 0 || recue + ecart > max + 0.001) depasse = true;
                if (ecart > 0 && !comm) motifManquant = true;
                if (recue > 0 || ecart > 0) auMoinsUne = true;
                quantites.push({ idBSL: id, quantite: recue, ecart: ecart, commentaire: comm });
            });
            if (depasse) { Swal.showValidationMessage('Reçu + non reçu dépasse ce qui vous a été remis pour une ligne.'); return false; }
            if (motifManquant) { Swal.showValidationMessage("Précisez le motif de chaque écart déclaré."); return false; }
            if (!auMoinsUne) { Swal.showValidationMessage('Saisissez au moins une quantité reçue ou un écart.'); return false; }
            return quantites;
        },
    }).then(function (result) {
        if (!result.isConfirmed) return;

        const btn = document.getElementById('dgaBtnConfirmerReception');
        if (btn) btn.disabled = true;

        $.ajax({
            url: EB_CONTROLLER_URL,
            method: 'POST',
            data: JSON.stringify({ option: 17, token: dga_tokenVoirCourant, quantites: result.value }),
            contentType: 'application/json',
            dataType: 'json',
        }).done(function (res) {
            if (res.status === 'success') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalVoirEB'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Réception confirmée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerExpressions();
            } else {
                if (btn) btn.disabled = false;
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            if (btn) btn.disabled = false;
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────── OUVERTURE MODALE ───────────────────────── */
function dga_ouvrirNouvelle() {
    dga_tokenCourant = null;
    dga_produitsPanier = [];
    document.getElementById('ebModalTitre').textContent = 'Nouvelle expression de besoin';
    document.getElementById('dgaErreurGenerale').style.display = 'none';
    dga_chargerCategories();
    dga_reinitialiserSelectAjout();
    dga_renderPanier();
    new bootstrap.Modal(document.getElementById('modalExpressionBesoin')).show();
}

function dga_ouvrirModifier(token) {
    dga_showLoader('Chargement…');
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 5, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || "Impossible de charger l'expression de besoin.", 'error');
            return;
        }

        dga_tokenCourant = token;
        const e = res.expression;
        dga_produitsPanier = (e.produits || []).map(p => ({ idP: p.idP, designation: p.designation, quantite: parseFloat(p.quantite) }));

        document.getElementById('ebModalTitre').textContent = 'Modifier — ' + e.nom_expression;
        document.getElementById('dgaErreurGenerale').style.display = 'none';
        dga_chargerCategories();
        dga_reinitialiserSelectAjout();
        dga_renderPanier();

        new bootstrap.Modal(document.getElementById('modalExpressionBesoin')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_reinitialiserSelectAjout() {
    document.getElementById('dgaSousCategorie').innerHTML = '<option value="">—</option>';
    document.getElementById('dgaSousCategorie').disabled = true;
    document.getElementById('dgaProduit').innerHTML = '<option value="">—</option>';
    document.getElementById('dgaProduit').disabled = true;
    document.getElementById('dgaQuantite').value = 1;
}

/* ────────────────────────── CASCADE CATÉGORIE ──────────────────────── */
function dga_chargerCategories() {
    const sel = document.getElementById('dgaCategorie');
    sel.innerHTML = '<option value="">Chargement…</option>';
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 2 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') { sel.innerHTML = '<option value="">Erreur</option>'; return; }
        const cats = res.data || [];
        sel.innerHTML = '<option value="">Sélectionner…</option>' + cats.map(c => `<option value="${c.id}">${dga_escapeHtml(c.nom)}</option>`).join('');
        if (!cats.length) sel.innerHTML = '<option value="">Aucune catégorie disponible</option>';
    });
}

function dga_chargerSousCategories() {
    const idCategorie = document.getElementById('dgaCategorie').value;
    const sel = document.getElementById('dgaSousCategorie');
    const selProduit = document.getElementById('dgaProduit');
    selProduit.innerHTML = '<option value="">—</option>';
    selProduit.disabled = true;

    if (!idCategorie) {
        sel.innerHTML = '<option value="">—</option>';
        sel.disabled = true;
        return;
    }

    sel.innerHTML = '<option value="">Chargement…</option>';
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 3, idCategorie: idCategorie }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') { sel.innerHTML = '<option value="">Erreur</option>'; return; }
        const sousCats = res.data || [];
        sel.innerHTML = '<option value="">Sélectionner…</option>' + sousCats.map(sc => `<option value="${sc.id}">${dga_escapeHtml(sc.nom)}</option>`).join('');
        sel.disabled = false;
        if (!sousCats.length) sel.innerHTML = '<option value="">Aucune sous-catégorie disponible</option>';
    });
}

function dga_chargerProduits() {
    const idSousCategorie = document.getElementById('dgaSousCategorie').value;
    const sel = document.getElementById('dgaProduit');

    if (!idSousCategorie) {
        sel.innerHTML = '<option value="">—</option>';
        sel.disabled = true;
        return;
    }

    sel.innerHTML = '<option value="">Chargement…</option>';
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 4, idSousCategorie: idSousCategorie }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') { sel.innerHTML = '<option value="">Erreur</option>'; return; }
        const produits = res.data || [];
        sel.innerHTML = '<option value="">Sélectionner…</option>' + produits.map(p => `<option value="${p.idP}" data-designation="${dga_escapeHtml(p.designation)}">${dga_escapeHtml(p.designation)}</option>`).join('');
        sel.disabled = false;
        if (!produits.length) sel.innerHTML = '<option value="">Aucun produit disponible</option>';
    });
}

/* ────────────────────────── PANIER DE PRODUITS ─────────────────────── */
function dga_ajouterProduitPanier() {
    const selProduit = document.getElementById('dgaProduit');
    const idP = selProduit.value;
    const designation = selProduit.selectedOptions[0]?.dataset.designation || '';
    const quantite = parseFloat(document.getElementById('dgaQuantite').value) || 0;
    const erreurBox = document.getElementById('dgaErreurGenerale');
    erreurBox.style.display = 'none';

    if (!idP) {
        erreurBox.textContent = 'Veuillez sélectionner un produit.';
        erreurBox.style.display = 'block';
        return;
    }
    if (quantite <= 0) {
        erreurBox.textContent = 'La quantité doit être strictement supérieure à zéro.';
        erreurBox.style.display = 'block';
        return;
    }
    if (dga_produitsPanier.some(p => String(p.idP) === String(idP))) {
        erreurBox.textContent = 'Ce produit a déjà été ajouté à cette expression de besoin.';
        erreurBox.style.display = 'block';
        return;
    }

    dga_produitsPanier.push({ idP: idP, designation: designation, quantite: quantite });
    dga_renderPanier();

    document.getElementById('dgaProduit').value = '';
    document.getElementById('dgaQuantite').value = 1;
}

function dga_retirerProduitPanier(idP) {
    dga_produitsPanier = dga_produitsPanier.filter(p => String(p.idP) !== String(idP));
    dga_renderPanier();
}

function dga_renderPanier() {
    const corps = document.getElementById('dgaCorpsProduits');
    if (!dga_produitsPanier.length) {
        corps.innerHTML = '<tr id="dgaLigneVide"><td colspan="3" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit ajouté.</td></tr>';
        return;
    }
    corps.innerHTML = dga_produitsPanier.map(function (p) {
        return `
            <tr>
                <td>${dga_escapeHtml(p.designation)}</td>
                <td>${dga_escapeHtml(p.quantite)}</td>
                <td><button type="button" class="dga-btn-retirer" onclick="dga_retirerProduitPanier('${p.idP}')">Retirer</button></td>
            </tr>
        `;
    }).join('');
}

/* ────────────────────────── SOUMISSION ─────────────────────────────── */
function dga_soumettreExpression(action) {
    const erreurBox = document.getElementById('dgaErreurGenerale');
    erreurBox.style.display = 'none';

    if (!dga_produitsPanier.length) {
        erreurBox.textContent = 'Veuillez ajouter au moins un produit.';
        erreurBox.style.display = 'block';
        return;
    }

    const btnId = action === 'soumettre' ? 'dgaBtnSoumettre' : 'dgaBtnPoursuivre';
    const btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.querySelector('.dga-spinner').classList.remove('hidden');

    $.ajax({
        url: EB_CONTROLLER_URL,
        method: 'POST',
        data: JSON.stringify({
            option: 6,
            token: dga_tokenCourant || '',
            action: action,
            produits: dga_produitsPanier.map(p => ({ idP: p.idP, quantite: p.quantite })),
        }),
        contentType: 'application/json',
        dataType: 'json',
    }).done(function (res) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');

        if (res.status === 'success') {
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalExpressionBesoin'));
            if (modalInstance) modalInstance.hide();
            Swal.fire({ title: 'Succès', text: res.message || 'Opération réussie.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerExpressions();
        } else {
            erreurBox.textContent = res.message || 'Une erreur est survenue.';
            erreurBox.style.display = 'block';
        }
    }).fail(function (xhr) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');
        erreurBox.textContent = dga_ajaxErrorMessage(xhr);
        erreurBox.style.display = 'block';
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

function dga_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}



function dga_ouvrirConsulter(token) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 5, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const e = res.expression;
        document.getElementById('consulterModalTitre').textContent = 'Détail — ' + e.nom_expression;

        const lignes = (e.produits || []).map(function (p) {
            return `<tr>
                <td>${dga_escapeHtml(p.designation)}</td>
                <td>${dga_escapeHtml(p.quantite)}</td>
                <td>${p.quantite_reelle !== null ? dga_escapeHtml(p.quantite_reelle) : '—'}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="3" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        document.getElementById('dgaContenuConsulter').innerHTML = `
            <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                <strong>Demandeur :</strong> ${dga_escapeHtml(e.demandeur)}<br/>
                <strong>Date de création :</strong> ${dga_fmtDate(e.date_creation)}<br/>
                <strong>Statut :</strong> <span class="dga-badge-statut dga-statut-${e.idStatut}">${LIBELLES_STATUT_EB[e.idStatut] || e.idStatut}</span>
            </p>
            <table class="dga-table-produits">
                <thead><tr><th>Désignation</th><th>Qté demandée</th><th>Qté réelle</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        `;

        new bootstrap.Modal(document.getElementById('modalConsulterEB')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ═══════════════════════════════════════════════════════════════════════
   MODULE — Expression de besoin INVESTISSEMENT
   Même schéma que Fonctionnement, mais adossé au stock investissement de
   la direction (options 10-16 du contrôleur). Cycle : Brouillon → Terminé
   uniquement (pas de validation hiérarchique).
═══════════════════════════════════════════════════════════════════════ */

/* ────────────────────────── CHARGEMENT LISTE ────────────────────────── */
function chargerExpressionsInvest() {
    dga_showLoader('Chargement…');
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 14 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les expressions de besoin investissement.', 'error');
            return;
        }
        dga_renderTableInvest(res.data || []);
        document.getElementById('dga-stat-nombre-invest').textContent = res.nombre_total ?? 0;
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ───────────────────────────── TABLE ────────────────────────────────── */
function dga_renderTableInvest(expressions) {
    if (dga_tableInvest) { try { dga_tableInvest.destroy(); } catch (e) {} dga_tableInvest = null; }

    dga_tableInvest = $('#dga-table-ebi').DataTable({
        data: expressions,
        columns: [
            { data: 'nom_expression' },
            { data: 'date_creation' },
            { data: 'nombre_produits' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            { targets: 1, render: d => dga_fmtDate(d) },
            { targets: 3, render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT_EBI[d] || d}</span>` },
            {
                targets: 4,
                render: (d, t, row) => {
                    const statut = parseInt(row.idStatut);
                    let html = `<button type="button" class="dga-btn-voir-eb" onclick="dga_ouvrirDetailInvest('${d}')">Détail</button>`;
                    if (statut === 1) {
                        html += `<button type="button" class="dga-btn-poursuivre" onclick="dga_ouvrirModifierInvest('${d}')">Poursuivre</button>`;
                    }
                    return html;
                },
            },
        ],
        order: [[1, 'desc']],
        language: {
            emptyTable: 'Aucune expression de besoin investissement à afficher.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
    });
}

/* ────────────────────────── OUVERTURE MODALE ─────────────────────────── */
function dga_ouvrirNouvelleInvest() {
    dga_tokenCourantInvest = null;
    dga_produitsPanierInvest = [];
    document.getElementById('ebInvestModalTitre').textContent = 'Nouvelle expression de besoin — Investissement';
    document.getElementById('dgaErreurGeneraleInvest').style.display = 'none';
    dga_reinitialiserSelectAjoutInvest();
    dga_chargerProduitsInvest();
    dga_renderPanierInvest();
    new bootstrap.Modal(document.getElementById('modalExpressionBesoinInvest')).show();
}

function dga_ouvrirModifierInvest(token) {
    dga_showLoader('Chargement…');
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 15, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || "Impossible de charger l'expression de besoin.", 'error');
            return;
        }

        dga_tokenCourantInvest = token;
        const e = res.expression;
        dga_produitsPanierInvest = (e.produits || []).map(p => ({
            idP: p.id_produit, designation: p.designation, quantite: parseFloat(p.quantite_demandee),
        }));

        document.getElementById('ebInvestModalTitre').textContent = 'Modifier — ' + e.nom_expression;
        document.getElementById('dgaErreurGeneraleInvest').style.display = 'none';
        dga_reinitialiserSelectAjoutInvest();
        dga_chargerProduitsInvest();
        dga_renderPanierInvest();

        new bootstrap.Modal(document.getElementById('modalExpressionBesoinInvest')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_reinitialiserSelectAjoutInvest() {
    document.getElementById('dgaProduitInvest').innerHTML = '<option value="">Sélectionner…</option>';
    document.getElementById('dgaQuantiteInvest').value = 1;
    document.getElementById('dgaQuotaAffiche').textContent = '';
}

/* ────────────────────────── LISTE DIRECTE DES PRODUITS (pas de cascade) ─── */
function dga_chargerProduitsInvest() {
    const sel = document.getElementById('dgaProduitInvest');
    document.getElementById('dgaQuotaAffiche').textContent = '';

    sel.innerHTML = '<option value="">Chargement…</option>';
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 13 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') { sel.innerHTML = '<option value="">Erreur</option>'; return; }
        const produits = res.data || [];
        produits.forEach(p => { dga_quotasParProduit[p.idP] = parseFloat(p.quota_disponible); });
        sel.innerHTML = '<option value="">Sélectionner…</option>' + produits.map(p => {
            const rubriqueTxt = p.nom_rubrique ? ` — ${p.nom_rubrique}${p.nom_sous_rubrique ? ' / ' + p.nom_sous_rubrique : ''}` : '';
            return `<option value="${p.idP}" data-designation="${dga_escapeHtml(p.designation)}" data-quota="${p.quota_disponible}">${dga_escapeHtml(p.designation)}${dga_escapeHtml(rubriqueTxt)} (dispo : ${dga_escapeHtml(p.quota_disponible)})</option>`;
        }).join('');
        if (!produits.length) sel.innerHTML = '<option value="">Aucun produit disponible dans le stock de votre direction</option>';
    }).fail(function () {
        sel.innerHTML = '<option value="">Erreur de chargement</option>';
    });
}

function dga_onProduitInvestChange() {
    const sel = document.getElementById('dgaProduitInvest');
    const quota = sel.selectedOptions[0]?.dataset.quota;
    const quotaEl = document.getElementById('dgaQuotaAffiche');
    const qteInput = document.getElementById('dgaQuantiteInvest');

    if (quota !== undefined && quota !== '') {
        quotaEl.textContent = `(disponible : ${quota})`;
        qteInput.max = quota;
        if (parseFloat(qteInput.value) > parseFloat(quota)) qteInput.value = quota;
    } else {
        quotaEl.textContent = '';
        qteInput.removeAttribute('max');
    }
}

/* ────────────────────────── PANIER DE PRODUITS ───────────────────────── */
function dga_ajouterProduitPanierInvest() {
    const selProduit = document.getElementById('dgaProduitInvest');
    const idP = selProduit.value;
    const designation = selProduit.selectedOptions[0]?.dataset.designation || '';
    const quota = parseFloat(selProduit.selectedOptions[0]?.dataset.quota || 0);
    const quantite = parseFloat(document.getElementById('dgaQuantiteInvest').value) || 0;
    const erreurBox = document.getElementById('dgaErreurGeneraleInvest');
    erreurBox.style.display = 'none';

    if (!idP) {
        erreurBox.textContent = 'Veuillez sélectionner un produit.';
        erreurBox.style.display = 'block';
        return;
    }
    if (quantite <= 0) {
        erreurBox.textContent = 'La quantité doit être strictement supérieure à zéro.';
        erreurBox.style.display = 'block';
        return;
    }
    if (quantite > quota) {
        erreurBox.textContent = `La quantité demandée dépasse le quota disponible pour ce produit (${quota}).`;
        erreurBox.style.display = 'block';
        return;
    }
    if (dga_produitsPanierInvest.some(p => String(p.idP) === String(idP))) {
        erreurBox.textContent = 'Ce produit a déjà été ajouté à cette expression de besoin.';
        erreurBox.style.display = 'block';
        return;
    }

    dga_produitsPanierInvest.push({ idP: idP, designation: designation, quantite: quantite });
    dga_renderPanierInvest();

    document.getElementById('dgaProduitInvest').value = '';
    document.getElementById('dgaQuantiteInvest').value = 1;
    document.getElementById('dgaQuotaAffiche').textContent = '';
}

function dga_retirerProduitPanierInvest(idP) {
    dga_produitsPanierInvest = dga_produitsPanierInvest.filter(p => String(p.idP) !== String(idP));
    dga_renderPanierInvest();
}

function dga_renderPanierInvest() {
    const corps = document.getElementById('dgaCorpsProduitsInvest');
    if (!dga_produitsPanierInvest.length) {
        corps.innerHTML = '<tr id="dgaLigneVideInvest"><td colspan="3" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit ajouté.</td></tr>';
        return;
    }
    corps.innerHTML = dga_produitsPanierInvest.map(function (p) {
        return `
            <tr>
                <td>${dga_escapeHtml(p.designation)}</td>
                <td>${dga_escapeHtml(p.quantite)}</td>
                <td><button type="button" class="dga-btn-retirer" onclick="dga_retirerProduitPanierInvest('${p.idP}')">Retirer</button></td>
            </tr>
        `;
    }).join('');
}

/* ────────────────────────── SOUMISSION ───────────────────────────────── */
function dga_soumettreExpressionInvest(action) {
    const erreurBox = document.getElementById('dgaErreurGeneraleInvest');
    erreurBox.style.display = 'none';

    if (!dga_produitsPanierInvest.length) {
        erreurBox.textContent = 'Veuillez ajouter au moins un produit.';
        erreurBox.style.display = 'block';
        return;
    }

    const btnId = action === 'terminer' ? 'dgaBtnTerminerInvest' : 'dgaBtnPoursuivreInvest';
    const btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.querySelector('.dga-spinner').classList.remove('hidden');

    $.ajax({
        url: EB_CONTROLLER_URL,
        method: 'POST',
        data: JSON.stringify({
            option: 16,
            token: dga_tokenCourantInvest || '',
            action: action,
            produits: dga_produitsPanierInvest.map(p => ({ idP: p.idP, quantite: p.quantite })),
        }),
        contentType: 'application/json',
        dataType: 'json',
    }).done(function (res) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');

        if (res.status === 'success') {
            if (action === 'terminer') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalExpressionBesoinInvest'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Opération réussie.', icon: 'success', confirmButtonColor: '#113B26' });
            } else {
                Swal.fire({ title: 'Brouillon enregistré', text: res.message, icon: 'success', confirmButtonColor: '#113B26', timer: 1500, showConfirmButton: false });
                dga_tokenCourantInvest = res.tmp; // permet de poursuivre l'édition sans dupliquer
            }
            chargerExpressionsInvest();
        } else {
            erreurBox.textContent = res.message || 'Une erreur est survenue.';
            erreurBox.style.display = 'block';
        }
    }).fail(function (xhr) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');
        erreurBox.textContent = dga_ajaxErrorMessage(xhr);
        erreurBox.style.display = 'block';
    });
}

/**
 * "Terminer" déclenche une sortie de stock IMMÉDIATE et définitive — on
 * demande une confirmation explicite avant d'envoyer la requête.
 */
function dga_confirmerTerminerInvest() {
    if (!dga_produitsPanierInvest.length) {
        const erreurBox = document.getElementById('dgaErreurGeneraleInvest');
        erreurBox.textContent = 'Veuillez ajouter au moins un produit.';
        erreurBox.style.display = 'block';
        return;
    }

    Swal.fire({
        title: 'Confirmer la sortie de stock ?',
        html: `Cette action retire <strong>immédiatement et définitivement</strong> les quantités indiquées du stock de votre direction. Elle ne peut pas être annulée.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, terminer',
        confirmButtonColor: '#113B26',
        cancelButtonText: 'Annuler',
        cancelButtonColor: '#6c757d',
    }).then(function (result) {
        if (result.isConfirmed) dga_soumettreExpressionInvest('terminer');
    });
}

/* ────────────────────────── DÉTAIL (lecture) ─────────────────────────── */
function dga_ouvrirDetailInvest(token) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 15, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const e = res.expression;
        document.getElementById('detailEbiModalTitre').textContent = 'Détail — ' + e.nom_expression;

        const lignes = (e.produits || []).map(function (p) {
            return `<tr>
                <td>${dga_escapeHtml(p.designation)}</td>
                <td>${dga_escapeHtml(p.quantite_demandee)}</td>
                <td>${dga_escapeHtml(p.quantite_sortie)}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="3" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        document.getElementById('dgaContenuDetailEBI').innerHTML = `
            <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                <strong>Date de création :</strong> ${dga_fmtDate(e.date_creation)}<br/>
                <strong>Statut :</strong> <span class="dga-badge-statut dga-statut-${e.idStatut}">${LIBELLES_STATUT_EBI[e.idStatut] || e.idStatut}</span>
            </p>
            <table class="dga-table-produits">
                <thead><tr><th>Désignation</th><th>Qté demandée</th><th>Qté sortie</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        `;

        new bootstrap.Modal(document.getElementById('modalDetailEBI')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}