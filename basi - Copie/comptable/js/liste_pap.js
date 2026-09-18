/**
 * liste-operations-pap-scripts.bundle.js
 * Page "Liste des demandes" : tableau de bord des statistiques (par statut,
 * cliquables pour filtrer) + liste complète des commandes/paiements, tous
 * statuts confondus. Actions par ligne :
 *   - Détail (toujours) : infos + lignes + document(s).
 *   - Suivi (toujours) : historique/timeline des étapes de traitement.
 *   - Modifier (idStatut = 5 uniquement) : réutilise les modales déjà
 *     construites pour la page "Modification des opérations rejetées".
 *   - Générer le PDF (idStatut ∈ {4,6,7,8}) : ouvre bon_pap_pdf.php.
 *   - Envoyer à la caisse (idStatut = 4 uniquement) : idStatut 4 → 6.
 */

const CAISSE_CONTROLLER_URL = '/personnel/cpt_caisse_basi_controller'; // ← ajuster selon le chemin réel

const LIBELLES_STATUT = {
    1: 'En attente', 2: 'Validée', 3: 'Avis favorable', 4: 'Acceptée',
    5: 'Rejetée', 6: 'En paiement / Livrée', 7: 'Terminée',
};
const CLES_STATS = ['tous', '1', '2', '3', '4', '5', '6', '7'];
const LIBELLES_STATS = {
    tous: 'Toutes', '1': 'En attente', '2': 'Validées', '3': 'Avis favorables',
    '4': 'Acceptées', '5': 'Rejetées', '6': 'En paiement / Livrée', '7': 'Terminées',
};
const TENDANCES_STATS = {
    tous: 'Total', '1': 'Attente', '2': 'Validé', '3': 'Avis fav.',
    '4': 'Accepté', '5': 'Rejeté', '6': 'Paiement', '7': 'Terminé',
};

let dga_statutFiltre = 0; // 0 = toutes

let dga_table       = null;
let mod_dossier     = null;   // dossier courant en cours de modification
let mod_lignes      = [];
let mod_documents   = [];
let mod_tranches    = [];
let mod_fournisseurs = [];
let mod_modesReglement = [];
let mod_modesPaiement  = [];
let mod_listesChargees = false;

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]); // évite le "flash" de tableau non stylé
    chargerDemandes();
    document.getElementById('mcSubmit')?.addEventListener('click', soumettreModifierCommande);
    document.getElementById('mpSubmit')?.addEventListener('click', soumettreModifierPaiement);
    initTranchesListeners('mc');
    initTranchesListeners('mp');
});

/* ────────────────────────── CHARGEMENT LISTE + STATS ───────────────── */
function chargerDemandes() {
    dga_showLoader('Chargement des demandes…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL,
        method: 'POST',
        data: { option: 17, statut: dga_statutFiltre },
        dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') {
            dga_hideLoader();
            Swal.fire('Erreur', res.message || 'Impossible de charger les demandes.', 'error');
            return;
        }
        dga_renderStats(res.stats || {});
        dga_renderTabs(res.stats || {});
        dga_renderTable(res.data || []);
        dga_hideLoader();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

const ICONES_STATS = {
    tous: '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>',
    '1': '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    '2': '<polyline points="20 6 9 17 4 12"/>',
    '3': '<path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/>',
    '4': '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
    '5': '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
    '6': '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
    '7': '<path d="M4 22V4a1 1 0 011-1h13l-2 4 2 4H6"/>',
};

function dga_renderStats(stats) {
    const wrap = document.getElementById('dga-stats');
    if (!wrap) return;

    wrap.innerHTML = CLES_STATS.map(function (cle) {
        const valeur = stats[cle] ?? 0;
        const estActive = (cle === 'tous' && dga_statutFiltre === 0) || (cle !== 'tous' && parseInt(cle) === dga_statutFiltre);
        return `
            <button type="button" class="dga-stat-card${estActive ? ' active' : ''}" data-cle="${cle}" onclick="dga_filtrerParStatut(${cle === 'tous' ? 0 : cle})">
                <div class="dga-stat-header">
                    <div class="dga-stat-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">${ICONES_STATS[cle] || ''}</svg>
                    </div>
                    <span class="dga-stat-trend">${TENDANCES_STATS[cle]}</span>
                </div>
                <span class="dga-stat-label">${LIBELLES_STATS[cle]}</span>
                <span class="dga-stat-value">${valeur}</span>
                <div class="dga-stat-footer">Cliquer pour filtrer</div>
            </button>
        `;
    }).join('');
}

function dga_renderTabs(stats) {
    const wrap = document.getElementById('dga-tabs-bar');
    if (!wrap) return;

    wrap.innerHTML = CLES_STATS.map(function (cle) {
        const valeur = stats[cle] ?? 0;
        const estActive = (cle === 'tous' && dga_statutFiltre === 0) || (cle !== 'tous' && parseInt(cle) === dga_statutFiltre);
        return `
            <button type="button" data-tab="${cle}" class="${estActive ? 'active' : ''}" onclick="dga_filtrerParStatut(${cle === 'tous' ? 0 : cle})">
                ${LIBELLES_STATS[cle]} <span class="bud-tab-badge">${valeur}</span>
            </button>
        `;
    }).join('');
}

function dga_filtrerParStatut(statut) {
    dga_statutFiltre = statut;
    const titre = document.getElementById('dga-titre-filtre');
    if (titre) titre.textContent = statut === 0 ? '' : ' — ' + LIBELLES_STATUT[statut];
    chargerDemandes();
}

function dga_renderTable(dossiers) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-commandes').DataTable({
        data: dossiers,
        dom: 'lrtip',
        columns: [
            { data: 'idPAP' },
            { data: 'nom_commande' },
            { data: 'idTypePAP' },
            { data: 'demandeur' },
            { data: 'dateCreation' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => `#${d}` },
            {
                targets: 2,
                render: d => parseInt(d) === 1
                    ? '<span class="dga-badge-type dga-badge-achat">Achat</span>'
                    : '<span class="dga-badge-type dga-badge-paiement">Paiement</span>',
            },
            { targets: 4, render: d => dga_fmtDate(d) },
            {
                targets: 5,
                render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT[d] || d}</span>`,
            },
            {
                targets: 6,
                render: (d, t, row) => dga_renderActions(d, row),
            },
        ],
        order: [[0, 'desc']],
        language: {
            emptyTable: 'Aucune demande à afficher.',
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

    // Ré-applique la recherche libre + le filtre de type, puisque le tableau
    // est détruit/recréé à chaque rechargement (perte de l'état DataTables).
    const recherche = document.getElementById('dga-recherche');
    const filtreType = document.getElementById('dga-filtre-type');
    if (recherche && recherche.value) dga_table.search(recherche.value);
    if (filtreType && filtreType.value) dga_table.column(2).search('^' + filtreType.value + '$', true, false);
    dga_table.draw();
}

/* ────────────────────────── FILTRES (recherche + type) ─────────────── */
document.addEventListener('DOMContentLoaded', function () {
    let dga_rechercheTimeout = null;
    document.getElementById('dga-recherche')?.addEventListener('input', function () {
        clearTimeout(dga_rechercheTimeout);
        const valeur = this.value;
        dga_rechercheTimeout = setTimeout(function () {
            if (dga_table) dga_table.search(valeur).draw();
        }, 250);
    });

    document.getElementById('dga-filtre-type')?.addEventListener('change', function () {
        if (!dga_table) return;
        const valeur = this.value;
        dga_table.column(2).search(valeur ? '^' + valeur + '$' : '', true, false).draw();
    });
});

function dga_renderActions(token, row) {
    const statut = parseInt(row.idStatut);
    let html = '<div style="display:flex;flex-wrap:wrap;gap:.35rem;">';

    html += `
        <button type="button" class="dga-btn-detail" onclick="dga_ouvrirDetail('${token}')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/></svg>
            Détail
        </button>
        <button type="button" class="dga-btn-suivi" onclick="dga_ouvrirSuivi('${token}')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Suivi
        </button>
    `;

    // Page Comptable : consultation uniquement (pas de Modifier / Uploader
    // le BC / Envoyer en caisse — ces actions de workflow restent propres à
    // la page DRH). Le PDF reste disponible, simple consultation/impression.


    if (statut === 4 && parseInt(row.idTypePAP) === 1 && row.bc_uploade !== true) {
          html += `
          <button type="button" class="dga-btn-bc" onclick="dga_ouvrirUploadBC('${token}')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
         Uploader le BC
         </button>
         `;
    }

    // "Passer en caisse" : pour un paiement, toujours disponible à ce statut ;
    // pour un achat, uniquement si le BC a déjà été téléversé (bc_uploade).
    const peutPasserEnCaisse = statut === 4 && (parseInt(row.idTypePAP) === 2 || row.bc_uploade === true);
    if (peutPasserEnCaisse) {
         html += `
            <button type="button" class="dga-btn-caisse" onclick="dga_confirmerEnvoyerCaisse('${token}')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
           Caisse
          </button>
            `;
    }

    if ([4, 6, 7].includes(statut)) {
        html += `
            <button type="button" class="dga-btn-pdf" onclick="dga_genererPdf('${token}')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                PDF
            </button>
        `;
    }

    if (statut === 7) {
        html += `
            <button type="button" class="dga-btn-dossier" onclick="dga_ouvrirDossier('${token}')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                Dossier
            </button>
            <button type="button" class="dga-btn-pieces" onclick="dga_ouvrirPieces('${token}')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                Pièces
            </button>
        `;
    }

    html += '</div>';
    return html;
}

/* ────────────────────────── ACTION : DÉTAIL ────────────────────────── */
function dga_ouvrirDetail(token) {
    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 18, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const d = res.dossier;
        document.getElementById('detailModalTitre').textContent = 'Détail — ' + (d.nom_commande || '');

        const estAchat = parseInt(d.idTypePAP) === 1;
        document.getElementById('detailInfosGenerales').innerHTML = `
            <div class="dga-info-item"><span class="lbl">N° dossier</span><span class="val">#${vd_escapeHtml(d.idPAP)}</span></div>
            <div class="dga-info-item"><span class="lbl">Nom</span><span class="val">${vd_escapeHtml(d.nom_commande || '—')}</span></div>
            <div class="dga-info-item"><span class="lbl">Type</span><span class="val">${estAchat ? 'Passer commande' : 'Passer au paiement'}</span></div>
            <div class="dga-info-item"><span class="lbl">Statut</span><span class="val">${LIBELLES_STATUT[d.idStatut] || d.idStatut}</span></div>
            <div class="dga-info-item"><span class="lbl">Demandeur</span><span class="val">${vd_escapeHtml(d.demandeur || '—')}</span></div>
            <div class="dga-info-item"><span class="lbl">Montant total</span><span class="val">${d.montant_total !== null ? vd_formatMontant(d.montant_total) : '—'}</span></div>
            <div class="dga-info-item"><span class="lbl">Date de création</span><span class="val">${dga_fmtDate(d.dateCreation)}</span></div>
            ${d.motifRejet ? `<div class="dga-info-item"><span class="lbl">Motif de rejet</span><span class="val" style="color:#dc2626;">${vd_escapeHtml(d.motifRejet)}</span></div>` : ''}
        `;

        const bodyLignes = document.getElementById('detailBodyLignes');
        const lignes = res.lignes || [];
        bodyLignes.innerHTML = lignes.length ? lignes.map(function (l) {
            const valeur = estAchat ? l.prix_reel : l.montant_total_ligne;
            return `<tr><td>${vd_escapeHtml(l.designation || '')}</td><td>${l.quantite_reelle ?? '—'}</td><td>${valeur !== null && valeur !== undefined ? vd_formatMontant(valeur) : '—'}</td></tr>`;
        }).join('') : '<tr><td colspan="3" style="color:#9ca3af;font-style:italic;">Aucune ligne.</td></tr>';

        const titreDoc = document.getElementById('detailTitreDocuments');
        const listeDoc = document.getElementById('detailListeDocuments');

        if (estAchat) {
            if (res.facture_definitive) {
                titreDoc.textContent = 'Facture définitive';
                const f = res.facture_definitive;
                const nomF = `${f.prenomF || ''} ${f.nomF || ''}`.trim() + (f.entreprise ? ' — ' + f.entreprise : '');
                listeDoc.innerHTML = `
                    <div class="dga-doc-item">
                        <div class="dga-doc-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                        <div class="dga-doc-info"><div class="dga-doc-nom">Facture définitive</div><div class="dga-doc-sub">Fournisseur retenu : ${vd_escapeHtml(nomF)}</div></div>
                        <a href="${f.facture_definitive}" target="_blank" class="dga-doc-link">Ouvrir</a>
                    </div>
                `;
            } else {
                titreDoc.textContent = 'Pro forma proposés';
                const docs = res.documents || [];
                listeDoc.innerHTML = docs.length ? docs.map(function (dd) {
                    const nomF = `${dd.prenomF || ''} ${dd.nomF || ''}`.trim() + (dd.entreprise ? ' — ' + dd.entreprise : '');
                    return `
                        <div class="dga-doc-item">
                            <div class="dga-doc-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                            <div class="dga-doc-info"><div class="dga-doc-nom">${vd_escapeHtml(nomF)}</div></div>
                            <a href="${dd.doc}" target="_blank" class="dga-doc-link">Ouvrir</a>
                        </div>
                    `;
                }).join('') : '<p style="color:#9ca3af;font-style:italic;">Aucun document.</p>';
            }
        } else {
            titreDoc.textContent = 'Justificatif(s) de paiement';
            const justifs = res.justificatifs || [];
            listeDoc.innerHTML = justifs.length ? justifs.map(function (j, idx) {
                return `
                    <div class="dga-doc-item">
                        <div class="dga-doc-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                        <div class="dga-doc-info"><div class="dga-doc-nom">Justificatif ${idx + 1}</div><div class="dga-doc-sub">Enregistré le ${dga_fmtDate(j.dateEnregistrement)}</div></div>
                        <a href="${j.doc}" target="_blank" class="dga-doc-link">Ouvrir</a>
                    </div>
                `;
            }).join('') : '<p style="color:#9ca3af;font-style:italic;">Aucun justificatif.</p>';
        }

        new bootstrap.Modal(document.getElementById('modalDetailDemande')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── ACTION : SUIVI ─────────────────────────── */
function dga_ouvrirSuivi(token) {
    dga_showLoader('Chargement du suivi…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 19, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le suivi.', 'error');
            return;
        }

        const historique = res.historique || [];
        const wrap = document.getElementById('suiviTimeline');
        wrap.innerHTML = historique.length ? historique.map(function (h) {
            return `
                <div class="dga-timeline-item">
                    <div class="dga-timeline-motif">${vd_escapeHtml(h.motif || '')}</div>
                    <div class="dga-timeline-meta">
                        ${dga_fmtDateHeure(h.dateEnregistrement)} · ${vd_escapeHtml(h.utilisateur || '—')} ·
                        <span class="dga-badge-statut dga-statut-${h.idStatut}" style="margin-left:.3rem;">${LIBELLES_STATUT[h.idStatut] || h.idStatut}</span>
                    </div>
                    ${h.motifRejet ? `<div class="dga-timeline-motif-rejet">Motif de rejet : ${vd_escapeHtml(h.motifRejet)}</div>` : ''}
                </div>
            `;
        }).join('') : '<p style="color:#9ca3af;font-style:italic;">Aucun historique disponible.</p>';

        new bootstrap.Modal(document.getElementById('modalSuiviDemande')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── ACTION : GÉNÉRER PDF ───────────────────── */
function dga_genererPdf(token) {
    window.open('/personnel/compta_facture/' + encodeURIComponent(token), '_blank');
}

const LIBELLES_MODE_DOC = { 1: 'Liquide', 4: 'Wave', 5: 'Orange Money' };

/* ────────────────────────── ACTION : DOSSIER ───────────────────────── */
function dga_ouvrirDossier(token) {
    dga_showLoader('Chargement du dossier…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 25, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le dossier.', 'error');
            return;
        }

        const c = res.commande;
        document.getElementById('dossierModalTitre').textContent = 'Dossier — ' + (c.nom_commande || c.numero);

        const lignesHtml = (res.lignes || []).map(function (l) {
            return res.estAchat
                ? `<tr><td>${vd_escapeHtml(l.designation || '')}</td><td>${vd_escapeHtml(l.quantite_reelle)}</td><td>${vd_escapeHtml(l.quantite_livree)}</td><td>${vd_formatMontant(l.prix_reel)}</td><td>${vd_formatMontant(l.montant_total_ligne)}</td></tr>`
                : `<tr><td>${vd_escapeHtml(l.designation || '')}</td><td>${vd_formatMontant(l.montant_total_ligne)}</td></tr>`;
        }).join('') || '<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">Aucune ligne.</td></tr>';

        const lignesEntete = res.estAchat
            ? '<tr><th>Désignation</th><th>Qté commandée</th><th>Qté livrée</th><th>Prix</th><th>Montant</th></tr>'
            : '<tr><th>Désignation</th><th>Montant</th></tr>';

        const paiementsHtml = (res.paiements || []).map(function (p) {
            return `<tr>
                <td>${dga_fmtDateHeure(p.date_paiement)}</td>
                <td>${vd_escapeHtml(p.mode_reglement_nom || '—')}</td>
                <td>${vd_escapeHtml(p.caissier)}</td>
                <td>${vd_formatMontant(p.montant)}</td>
                <td>${p.recu ? `<a href="${p.recu}" target="_blank" class="dga-doc-link">Voir</a>` : '<span style="color:#d1d5db;">—</span>'}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun paiement.</td></tr>';

        document.getElementById('dossierContenu').innerHTML = `
            <div class="dga-info-grid" style="margin-bottom:1rem;">
                <div class="dga-info-item"><span class="lbl">N° commande</span><span class="val">${vd_escapeHtml(c.numero)}</span></div>
                <div class="dga-info-item"><span class="lbl">Type</span><span class="val">${res.estAchat ? 'Achat' : 'Paiement'}</span></div>
                <div class="dga-info-item"><span class="lbl">Demandeur</span><span class="val">${vd_escapeHtml(c.demandeur || '—')}</span></div>
                <div class="dga-info-item"><span class="lbl">Montant total</span><span class="val">${vd_formatMontant(c.montant_total)}</span></div>
                <div class="dga-info-item"><span class="lbl">Montant payé</span><span class="val">${vd_formatMontant(c.montant_paye)}</span></div>
                <div class="dga-info-item"><span class="lbl">Mode de règlement</span><span class="val">${vd_escapeHtml(c.mode_reglement_nom || '—')}</span></div>
            </div>

            <h3 class="dga-section-titre">Lignes de la commande</h3>
            <table class="dga-table-doc"><thead>${lignesEntete}</thead><tbody>${lignesHtml}</tbody></table>

            <h3 class="dga-section-titre">Paiements</h3>
            <table class="dga-table-doc"><thead><tr><th>Date</th><th>Mode</th><th>Caissier</th><th>Montant</th><th>Preuve</th></tr></thead><tbody>${paiementsHtml}</tbody></table>

            <h3 class="dga-section-titre">Documents</h3>
            ${dga_rendreDocuments(res.documents, res.estAchat)}
        `;

        new bootstrap.Modal(document.getElementById('modalDossier')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── ACTION : PIÈCES ────────────────────────── */
function dga_ouvrirPieces(token) {
    dga_showLoader('Chargement des pièces…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 25, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les pièces.', 'error');
            return;
        }

        document.getElementById('piecesModalTitre').textContent = 'Pièces — ' + (res.commande.nom_commande || res.commande.numero);
        document.getElementById('piecesContenu').innerHTML = dga_rendreDocuments(res.documents, res.estAchat);

        new bootstrap.Modal(document.getElementById('modalPieces')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/**
 * Rendu commun de la section "documents" pour Dossier et Pièces.
 */
function dga_rendreDocuments(docs, estAchat) {
    if (!docs) return '<p style="color:#9ca3af;font-style:italic;">Aucun document.</p>';

    if (estAchat) {
        const livraisonsHtml = (docs.livraisons || []).length
            ? docs.livraisons.map(function (l) {
                return `
                    <div class="dga-doc-item">
                        <span>Bon de livraison n° ${vd_escapeHtml(l.numero_livraison || l.id)} — ${dga_fmtDate(l.date_livraison)}</span>
                        ${l.fichier_bon_livraison ? `<a href="${l.fichier_bon_livraison}" target="_blank" class="dga-doc-link">Ouvrir</a>` : '<span style="color:#d1d5db;">—</span>'}
                    </div>
                `;
            }).join('')
            : '<div class="dga-doc-item"><span style="color:#9ca3af;font-style:italic;">Aucune livraison enregistrée.</span></div>';

        return `
            <div class="dga-doc-item">
                <span>Facture choisie</span>
                ${docs.facture_choisie ? `<a href="${docs.facture_choisie}" target="_blank" class="dga-doc-link">Ouvrir</a>` : '<span style="color:#d1d5db;">—</span>'}
            </div>
            <div class="dga-doc-item">
                <span>Facture définitive (BC uploadé)</span>
                ${docs.facture_definitive ? `<a href="${docs.facture_definitive}" target="_blank" class="dga-doc-link">Ouvrir</a>` : '<span style="color:#d1d5db;">—</span>'}
            </div>
            <div class="dga-doc-item">
                <span>Bon de commande (généré par le système)</span>
                <a href="${docs.bon_commande_url}" target="_blank" class="dga-doc-link">Ouvrir</a>
            </div>
            <h4 class="dga-section-titre" style="margin-top:1rem;">Bon(s) de livraison</h4>
            ${livraisonsHtml}
        `;
    }

    const justificatifsHtml = (docs.justificatifs || []).length
        ? docs.justificatifs.map(function (j) {
            return `
                <div class="dga-doc-item">
                    <span>Justificatif du ${dga_fmtDate(j.dateEnregistrement)}</span>
                    ${j.doc ? `<a href="${j.doc}" target="_blank" class="dga-doc-link">Ouvrir</a>` : '<span style="color:#d1d5db;">—</span>'}
                </div>
            `;
        }).join('')
        : '<div class="dga-doc-item"><span style="color:#9ca3af;font-style:italic;">Aucun justificatif.</span></div>';

    return justificatifsHtml;
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

function vd_formatMontant(value) {
    const n = Number(value);
    if (isNaN(n)) return vd_escapeHtml(String(value ?? ''));
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' FCFA';
}

function vd_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}



function dga_ouvrirUploadBC(token) {
    Swal.fire({
        title: 'Uploader le bon de commande',
        html: '<p style="margin-bottom:.5rem;text-align:left;font-size:.85rem;color:#6b7280;">Sélectionnez le bon de commande (PDF) émis pour le fournisseur retenu.</p>',
        input: 'file',
        inputAttributes: { accept: 'application/pdf' },
        showCancelButton: true,
        confirmButtonText: 'Téléverser',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
        preConfirm: (fichier) => {
            if (!fichier) {
                Swal.showValidationMessage('Le bon de commande (PDF) est obligatoire.');
                return false;
            }
            if (fichier.type !== 'application/pdf') {
                Swal.showValidationMessage('Le fichier doit être un PDF.');
                return false;
            }
            return fichier;
        },
    }).then(function (result) {
        if (!result.isConfirmed || !result.value) return;

        const fd = new FormData();
        fd.append('option', 33);
        fd.append('token', token);
        fd.append('bc', result.value);

        dga_showLoader('Envoi du bon de commande…');
        $.ajax({
            url: CAISSE_CONTROLLER_URL,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
        }).done(function (res) {
            dga_hideLoader();
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message || 'Bon de commande téléversé avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerDemandes();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            dga_hideLoader();
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────── ACTION : ENVOYER À LA CAISSE ───────────── */
function dga_confirmerEnvoyerCaisse(token) {
    Swal.fire({
        title: 'Envoyer à la caisse',
        text: 'Confirmez-vous l\'envoi de ce dossier à la caisse ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        dga_showLoader('Envoi à la caisse…');
        $.ajax({
            url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 34, token: token }, dataType: 'json'
        }).done(function (res) {
            dga_hideLoader();
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message || 'Dossier envoyé à la caisse avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerDemandes();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            dga_hideLoader();
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}
