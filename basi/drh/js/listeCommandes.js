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

const drh_basi_controller_URL = '/drh_basi_controller'; // ← ajuster selon le chemin réel

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
        url: drh_basi_controller_URL,
        method: 'POST',
        data: { option: 23, statut: dga_statutFiltre },
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

    if (statut === 5) {
        html += `
            <button type="button" class="dga-btn-valider" onclick="dga_ouvrirModifier('${token}', ${row.idTypePAP})">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Modifier
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

    if (statut === 4 && parseInt(row.idTypePAP) === 1 && row.bc_uploade !== true) {
      //  html += `
         //   <button type="button" class="dga-btn-bc" onclick="dga_ouvrirUploadBC('${token}')">
             //   <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              //  Uploader le BC
           // </button>
       // `;
    }

    // "Passer en caisse" : pour un paiement, toujours disponible à ce statut ;
    // pour un achat, uniquement si le BC a déjà été téléversé (bc_uploade).
    const peutPasserEnCaisse = statut === 4 && (parseInt(row.idTypePAP) === 2 || row.bc_uploade === true);
    if (peutPasserEnCaisse) {
       // html += `
       //     <button type="button" class="dga-btn-caisse" onclick="dga_confirmerEnvoyerCaisse('${token}')">
             //   <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            //   Caisse
         //  </button>
    //    `;
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
        url: drh_basi_controller_URL, method: 'POST', data: { option: 24, token: token }, dataType: 'json'
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
                titreDoc.textContent = 'Facture choisie';
                const f = res.facture_definitive;
                const nomF = `${f.prenomF || ''} ${f.nomF || ''}`.trim() + (f.entreprise ? ' — ' + f.entreprise : '');
                listeDoc.innerHTML = `
                    <div class="dga-doc-item">
                        <div class="dga-doc-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                        <div class="dga-doc-info"><div class="dga-doc-nom">Facture choisie</div><div class="dga-doc-sub">Fournisseur retenu : ${vd_escapeHtml(nomF)}</div></div>
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
        url: drh_basi_controller_URL, method: 'POST', data: { option: 25, token: token }, dataType: 'json'
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
    window.open('/drh_facture/' + encodeURIComponent(token), '_blank');
}

/* ────────────────────────── ACTION : DOSSIER ───────────────────────── */
function dga_ouvrirDossier(token) {
    dga_showLoader('Chargement du dossier…');

    $.ajax({
        url: drh_basi_controller_URL, method: 'POST', data: { option: 28, token: token }, dataType: 'json'
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
        url: drh_basi_controller_URL, method: 'POST', data: { option: 28, token: token }, dataType: 'json'
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
                <span>Facture définitive (FD uploadée)</span>
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

/* ────────────────────────── ACTION : UPLOADER LE BC ────────────────── */
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
        fd.append('option', 27);
        fd.append('token', token);
        fd.append('bc', result.value);

        dga_showLoader('Envoi du bon de commande…');
        $.ajax({
            url: drh_basi_controller_URL,
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
            url: drh_basi_controller_URL, method: 'POST', data: { option: 26, token: token }, dataType: 'json'
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

/* ──────────────────── CHARGEMENT DES LISTES PARTAGÉES ──────────────────── */
function chargerListesSiNecessaire() {
    if (mod_listesChargees) return Promise.resolve();

    return Promise.all([
        $.ajax({ url: drh_basi_controller_URL, method: 'POST', data: { option: 1 },  dataType: 'json' }), // fournisseurs
        $.ajax({ url: drh_basi_controller_URL, method: 'POST', data: { option: 10 }, dataType: 'json' }), // mode_reglement
        $.ajax({ url: drh_basi_controller_URL, method: 'POST', data: { option: 11 }, dataType: 'json' }), // mode_paiement
    ]).then(function (res) {
        const [rF, rMR, rMP] = res;
        mod_fournisseurs    = (rF  && rF.status  === 'success') ? (rF.data  || []).filter(f => f.statut === 'actif') : [];
        mod_modesReglement  = (rMR && rMR.status === 'success') ? (rMR.data || []) : [];
        mod_modesPaiement   = (rMP && rMP.status === 'success') ? (rMP.data || []) : [];
        mod_listesChargees  = true;
    }).catch(function () {
        Swal.fire('Erreur', 'Impossible de charger les listes (fournisseurs / modes).', 'error');
    });
}

function remplirSelect(id, items, getValue, getLabel) {
    const sel = document.getElementById(id);
    if (!sel) return;
    sel.innerHTML = '<option value="">Sélectionner…</option>' +
        items.map(it => `<option value="${getValue(it)}">${vd_escapeHtml(getLabel(it))}</option>`).join('');
}

/* ────────────────────────── OUVERTURE MODALE ─────────────────────── */
function dga_ouvrirModifier(token, idTypePAP) {
    dga_showLoader('Chargement du détail…');

    Promise.all([
        chargerListesSiNecessaire(),
        $.ajax({ url: drh_basi_controller_URL, method: 'POST', data: { option: 20, token: token }, dataType: 'json' }),
    ]).then(function (results) {
        dga_hideLoader();
        const res = results[1];
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail du dossier.', 'error');
            return;
        }


        mod_dossier   = res.dossier;
        mod_dossier.token = token;
        mod_lignes    = res.lignes || [];
        mod_documents = res.documents || [];
        mod_tranches  = res.tranches || [];

        if (parseInt(idTypePAP) === 1) {
            ouvrirModaleCommande();
        } else {
            ouvrirModalePaiement();
        }
    }).catch(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ═══════════════════════════ MODIFIER COMMANDE ═══════════════════════ */
/**
 * Affiche le motif de rejet du DG (s'il existe) en tête de la modale, pour
 * que le comptable (DRH) sache pourquoi le dossier a été rejeté avant de le
 * corriger.
 */
function afficherMotifRejet(prefix, motifRejet) {
    const banniere = document.getElementById(prefix + 'MotifRejet');
    if (!banniere) return;
    if (motifRejet) {
        banniere.innerHTML = `<strong>Motif de rejet :</strong> ${vd_escapeHtml(motifRejet)}`;
        banniere.style.display = 'block';
    } else {
        banniere.style.display = 'none';
    }
}

function ouvrirModaleCommande() {
    document.getElementById('mcModalTitre').textContent = 'Modifier — ' + (mod_dossier.nom_commande || '');
    mcClearErreur();
    afficherMotifRejet('mc', mod_dossier.motifRejet);

    remplirSelect('mcModeReglement', mod_modesReglement, m => m.id, m => m.nom);
    remplirSelect('mcModePaiement', mod_modesPaiement, m => m.id, m => m.nom);
    document.getElementById('mcModeReglement').value = mod_dossier.id_mode_reglement || '';
    document.getElementById('mcModePaiement').value = mod_dossier.id_mode_paiement || '';

    const estTranche = parseInt(mod_dossier.id_mode_paiement) === 3;
    document.getElementById('mcNbTranchesWrap').style.display = estTranche ? '' : 'none';
    document.getElementById('mcTranchesWrap').style.display = estTranche ? '' : 'none';
    document.getElementById('mcNbTranches').value = estTranche ? (mod_dossier.nb_tranche || '') : '';
    if (estTranche && mod_dossier.nb_tranche) {
        genererChampsTranches('mc', mod_dossier.nb_tranche);
        preRemplirTranches('mc', mod_tranches);
    } else {
        document.getElementById('mcTranchesFields').innerHTML = '';
    }

    // Lignes
    const body = document.getElementById('mcBodyLignes');
    body.innerHTML = mod_lignes.map(function (l) {
        const afficherPPU = String(l.id_unite) !== '1';
        const cellulePPU = afficherPPU
            ? `<input type="number" class="vd-modal-inp mc-ppu" min="1" step="1" value="${l.nb_unites || 1}"/>`
            : '<span style="color:#9ca3af;">—</span>';
        return `
            <tr data-idpapl="${l.idPAPL}" data-id-unite="${l.id_unite ?? ''}">
                <td style="text-align:center;"><input type="checkbox" class="vd-checkbox mc-conserver" checked/></td>
                <td>${vd_escapeHtml(l.designation || '')}</td>
                <td style="color:#9ca3af;">${vd_escapeHtml(l.unite || '—')}</td>
                <td><input type="number" class="vd-modal-inp mc-nb-pieces" min="1" step="1" max="${l.quantite_disponible_max}" value="${l.quantite_reelle ?? ''}"/></td>
                <td class="mc-cell-ppu">${cellulePPU}</td>
            </tr>
        `;
    }).join('');

    body.querySelectorAll('.mc-conserver').forEach(cb => cb.addEventListener('change', mcMajLigneSupprimee));
    body.querySelectorAll('.mc-nb-pieces, .mc-ppu').forEach(inp => inp.addEventListener('input', function () {
        this.value = this.value.replace(/[^\d]/g, '');
    }));

    // Documents pro forma existants (lecture seule)
    const docsWrap = document.getElementById('mcDocumentsExistants');
    if (mod_documents.length) {
        docsWrap.innerHTML = mod_documents.map(function (d) {
            const nom = `${d.prenomF || ''} ${d.nomF || ''}`.trim() + (d.entreprise ? ' — ' + d.entreprise : '');
            return `<div class="vd-doc-existant">Pro forma existant — ${vd_escapeHtml(nom)} : <a href="${d.doc}" target="_blank">Ouvrir</a></div>`;
        }).join('');
    } else {
        docsWrap.innerHTML = '';
    }

    // Fournisseurs des 3 emplacements + réinitialisation des fichiers
    document.querySelectorAll('.mc-proforma-fournisseur').forEach(sel => {
        sel.innerHTML = '<option value="">Sélectionner…</option>' +
            mod_fournisseurs.map(f => `<option value="${f.idF}">${vd_escapeHtml(`${f.prenomF || ''} ${f.nomF || ''}`.trim() + (f.entreprise ? ' — ' + f.entreprise : ''))}</option>`).join('');
        sel.value = '';
    });
    document.querySelectorAll('.mc-proforma-file').forEach(inp => { inp.value = ''; });

    new bootstrap.Modal(document.getElementById('modalModifierCommande')).show();
}

function mcMajLigneSupprimee() {
    const tr = this.closest('tr');
    tr.classList.toggle('vd-ligne-supprimee', !this.checked);
    tr.querySelectorAll('input').forEach(inp => { if (inp !== this) inp.disabled = !this.checked; });
}

function soumettreModifierCommande() {
    mcClearErreur();

    const idModeReglement = document.getElementById('mcModeReglement').value;
    const idModePaiement  = document.getElementById('mcModePaiement').value;
    if (!idModeReglement) { mcAfficherErreur('Sélectionnez un mode de règlement.'); return; }
    if (!idModePaiement)  { mcAfficherErreur('Sélectionnez une modalité de paiement.'); return; }

    const erreurTranches = validerTranches('mc', idModePaiement);
    if (erreurTranches) { mcAfficherErreur(erreurTranches); return; }

    const lignes = [];
    let nbConservees = 0;
    let erreurLigne = null;
    document.querySelectorAll('#mcBodyLignes tr').forEach(function (tr) {
        const idPAPL = tr.dataset.idpapl;
        const conserve = tr.querySelector('.mc-conserver').checked;
        if (!conserve) {
            lignes.push({ idPAPL, action: 'supprimer' });
            return;
        }
        nbConservees++;
        const qte = tr.querySelector('.mc-nb-pieces').value;
        if (!qte || parseFloat(qte) <= 0) erreurLigne = 'Renseignez une quantité valide pour chaque ligne conservée.';
        const idUnite = tr.dataset.idUnite;
        const ppuEl = tr.querySelector('.mc-ppu');
        lignes.push({
            idPAPL, action: 'garder',
            quantite_reelle: qte,
            id_unite: idUnite,
            nb_unites: String(idUnite) !== '1' ? (ppuEl ? ppuEl.value : '') : '',
        });
    });

    if (nbConservees === 0) { mcAfficherErreur('Vous devez conserver au moins une ligne.'); return; }
    if (erreurLigne) { mcAfficherErreur(erreurLigne); return; }

    const fd = new FormData();
    let auMoinsUnProforma = false;
    for (let i = 1; i <= 3; i++) {
        const fileEl = document.querySelector(`.mc-proforma-file[data-ordre="${i}"]`);
        const selEl  = document.querySelector(`.mc-proforma-fournisseur[data-ordre="${i}"]`);
        const fichier = fileEl.files[0] || null;
        const idFournisseurPF = selEl.value;
        if (!fichier && !idFournisseurPF) continue;
        if (!fichier || !idFournisseurPF) {
            mcAfficherErreur(`Pro forma ${i} : le fichier PDF et le fournisseur sont tous les deux obligatoires si l'un des deux est renseigné.`);
            return;
        }
        fd.append(`proforma_file_${i}`, fichier);
        fd.append(`proforma_fournisseur_${i}`, idFournisseurPF);
        auMoinsUnProforma = true;
    }
    if (!auMoinsUnProforma) { mcAfficherErreur('Vous devez téléverser à nouveau au moins une facture pro forma (avec son fournisseur).'); return; }

    fd.append('option', 21);
    fd.append('idPAP', mod_dossier.idPAP);
    fd.append('id_mode_reglement', idModeReglement);
    fd.append('id_mode_paiement', idModePaiement);
    if (parseInt(idModePaiement) === 3) {
        fd.append('nb_tranche', document.getElementById('mcNbTranches').value);
        fd.append('tranches', JSON.stringify(collecterTranches('mc')));
    }
    fd.append('lignes', JSON.stringify(lignes));

    envoyerModification(fd, 'mcSubmit', 'modalModifierCommande', mcAfficherErreur);
}

function mcAfficherErreur(message) {
    const b = document.getElementById('mcErreurGenerale');
    b.innerHTML = `<span>${vd_escapeHtml(message)}</span>`;
    b.style.display = 'flex';
    b.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function mcClearErreur() {
    const b = document.getElementById('mcErreurGenerale');
    b.style.display = 'none';
    b.innerHTML = '';
}

/* ═══════════════════════════ MODIFIER PAIEMENT ════════════════════════ */
function ouvrirModalePaiement() {
    document.getElementById('mpModalTitre').textContent = 'Modifier — ' + (mod_dossier.nom_commande || '');
    mpClearErreur();
    afficherMotifRejet('mp', mod_dossier.motifRejet);

    remplirSelect('mpModeReglement', mod_modesReglement, m => m.id, m => m.nom);
    remplirSelect('mpModePaiement', mod_modesPaiement, m => m.id, m => m.nom);
    document.getElementById('mpModeReglement').value = mod_dossier.id_mode_reglement || '';
    document.getElementById('mpModePaiement').value = mod_dossier.id_mode_paiement || '';

    const estTranche = parseInt(mod_dossier.id_mode_paiement) === 3;
    document.getElementById('mpNbTranchesWrap').style.display = estTranche ? '' : 'none';
    document.getElementById('mpTranchesWrap').style.display = estTranche ? '' : 'none';
    document.getElementById('mpNbTranches').value = estTranche ? (mod_dossier.nb_tranche || '') : '';
    if (estTranche && mod_dossier.nb_tranche) {
        genererChampsTranches('mp', mod_dossier.nb_tranche);
        preRemplirTranches('mp', mod_tranches);
    } else {
        document.getElementById('mpTranchesFields').innerHTML = '';
    }

    const body = document.getElementById('mpBodyLignes');
    body.innerHTML = mod_lignes.map(function (l) {
        return `
            <tr data-idpapl="${l.idPAPL}">
                <td style="text-align:center;"><input type="checkbox" class="vd-checkbox mp-conserver" checked/></td>
                <td>${vd_escapeHtml(l.designation || '')}</td>
                <td><input type="number" class="vd-modal-inp mp-montant" min="0.01" step="0.01" max="${l.montant_disponible_max}" value="${l.montant_total_ligne ?? ''}"/></td>
            </tr>
        `;
    }).join('');

    body.querySelectorAll('.mp-conserver').forEach(cb => cb.addEventListener('change', mpMajLigneSupprimee));
    body.querySelectorAll('.mp-montant').forEach(inp => inp.addEventListener('input', mpRecalculerTotal));
    mpRecalculerTotal();

    const docsWrap = document.getElementById('mpDocumentsExistants');
    if (mod_documents.length) {
        docsWrap.innerHTML = mod_documents.map(function (d, idx) {
            return `<div class="vd-doc-existant">Justificatif existant ${idx + 1} : <a href="${d.doc}" target="_blank">Ouvrir</a></div>`;
        }).join('');
    } else {
        docsWrap.innerHTML = '';
    }
    document.getElementById('mpJustificatifs').value = '';
    document.getElementById('mpListeJustificatifs').innerHTML = '';

    new bootstrap.Modal(document.getElementById('modalModifierPaiement')).show();
}

function mpMajLigneSupprimee() {
    const tr = this.closest('tr');
    tr.classList.toggle('vd-ligne-supprimee', !this.checked);
    tr.querySelectorAll('input[type="number"]').forEach(inp => { inp.disabled = !this.checked; });
    mpRecalculerTotal();
}

function mpRecalculerTotal() {
    let total = 0;
    document.querySelectorAll('#mpBodyLignes tr').forEach(function (tr) {
        if (!tr.querySelector('.mp-conserver').checked) return;
        total += parseFloat(tr.querySelector('.mp-montant')?.value) || 0;
    });
    document.getElementById('mpMontantTotal').textContent = vd_formatMontant(total);
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('mpJustificatifs')?.addEventListener('change', function () {
        const liste = document.getElementById('mpListeJustificatifs');
        liste.innerHTML = Array.from(this.files).map(f => `<li>${vd_escapeHtml(f.name)}</li>`).join('');
    });
});

function soumettreModifierPaiement() {
    mpClearErreur();

    const idModeReglement = document.getElementById('mpModeReglement').value;
    const idModePaiement  = document.getElementById('mpModePaiement').value;
    if (!idModeReglement) { mpAfficherErreur('Sélectionnez un mode de règlement.'); return; }
    if (!idModePaiement)  { mpAfficherErreur('Sélectionnez une modalité de paiement.'); return; }

    const erreurTranches = validerTranches('mp', idModePaiement);
    if (erreurTranches) { mpAfficherErreur(erreurTranches); return; }

    const lignes = [];
    let nbConservees = 0;
    let erreurLigne = null;
    document.querySelectorAll('#mpBodyLignes tr').forEach(function (tr) {
        const idPAPL = tr.dataset.idpapl;
        const conserve = tr.querySelector('.mp-conserver').checked;
        if (!conserve) {
            lignes.push({ idPAPL, action: 'supprimer' });
            return;
        }
        nbConservees++;
        const montant = tr.querySelector('.mp-montant').value;
        if (!montant || parseFloat(montant) <= 0) erreurLigne = 'Renseignez un montant valide pour chaque ligne conservée.';
        lignes.push({ idPAPL, action: 'garder', montant });
    });

    if (nbConservees === 0) { mpAfficherErreur('Vous devez conserver au moins une ligne.'); return; }
    if (erreurLigne) { mpAfficherErreur(erreurLigne); return; }

    const fichiers = document.getElementById('mpJustificatifs').files;
    if (!fichiers.length) { mpAfficherErreur('Le justificatif de paiement est obligatoire.'); return; }

    const fd = new FormData();
    fd.append('option', 22);
    fd.append('idPAP', mod_dossier.idPAP);
    fd.append('id_mode_reglement', idModeReglement);
    fd.append('id_mode_paiement', idModePaiement);
    if (parseInt(idModePaiement) === 3) {
        fd.append('nb_tranche', document.getElementById('mpNbTranches').value);
        fd.append('tranches', JSON.stringify(collecterTranches('mp')));
    }
    fd.append('lignes', JSON.stringify(lignes));
    Array.from(fichiers).forEach(f => fd.append('justificatifs[]', f));

    envoyerModification(fd, 'mpSubmit', 'modalModifierPaiement', mpAfficherErreur);
}

function mpAfficherErreur(message) {
    const b = document.getElementById('mpErreurGenerale');
    b.innerHTML = `<span>${vd_escapeHtml(message)}</span>`;
    b.style.display = 'flex';
    b.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function mpClearErreur() {
    const b = document.getElementById('mpErreurGenerale');
    b.style.display = 'none';
    b.innerHTML = '';
}

/* ─────────────────────── TRANCHES (partagé mc / mp) ────────────────────── */
function initTranchesListeners(prefix) {
    document.getElementById(prefix + 'ModePaiement')?.addEventListener('change', function () {
        const estTranche = parseInt(this.value) === 3;
        document.getElementById(prefix + 'NbTranchesWrap').style.display = estTranche ? '' : 'none';
        document.getElementById(prefix + 'TranchesWrap').style.display = estTranche ? '' : 'none';
        if (!estTranche) {
            document.getElementById(prefix + 'NbTranches').value = '';
            document.getElementById(prefix + 'TranchesFields').innerHTML = '';
        }
    });
    document.getElementById(prefix + 'NbTranches')?.addEventListener('input', function () {
        genererChampsTranches(prefix, this.value);
    });
}

function genererChampsTranches(prefix, n) {
    n = parseInt(n);
    const wrap = document.getElementById(prefix + 'TranchesFields');
    wrap.innerHTML = '';
    document.getElementById(prefix + 'TranchesErreur').style.display = 'none';
    if (!n || n < 2) return;

    for (let i = 1; i <= n; i++) {
        const estDerniere = (i === n);
        wrap.insertAdjacentHTML('beforeend', `
            <div class="vd-pc-tranche-field">
                <label>Tranche ${i} (%)</label>
                <input type="number" class="vd-modal-inp ${prefix}-tranche-inp" data-ordre="${i}"
                       min="0" max="100" step="0.01"
                       ${estDerniere ? 'readonly placeholder="Calculé automatiquement"' : 'placeholder="0"'}/>
            </div>
        `);
    }
    wrap.querySelectorAll(`.${prefix}-tranche-inp:not([readonly])`).forEach(inp => {
        inp.addEventListener('input', () => recalculerDerniereTranche(prefix));
    });
    recalculerDerniereTranche(prefix);
}

/**
 * Pré-remplit les champs de tranches déjà générés (genererChampsTranches)
 * avec les pourcentages sauvegardés — sans ça, la modale rouvre le bon
 * nombre de champs mais tous vides.
 */
function preRemplirTranches(prefix, tranches) {
    if (!tranches || !tranches.length) return;
    const inputs = Array.from(document.querySelectorAll(`.${prefix}-tranche-inp`));
    tranches.forEach(function (t) {
        const inp = inputs.find(el => parseInt(el.dataset.ordre) === parseInt(t.ordre));
        if (inp && !inp.readOnly) {
            inp.value = t.pourcentage;
        }
    });
    // Recalcule la dernière tranche (readonly) à partir des valeurs désormais
    // renseignées, plutôt que de la laisser à sa valeur par défaut (100).
    recalculerDerniereTranche(prefix);
}

function recalculerDerniereTranche(prefix) {
    const inputs = Array.from(document.querySelectorAll(`.${prefix}-tranche-inp`));
    if (!inputs.length) return;
    const derniere = inputs[inputs.length - 1];
    const autres = inputs.slice(0, -1);
    const somme = autres.reduce((s, inp) => s + (parseFloat(inp.value) || 0), 0);
    const calc = Math.round((100 - somme) * 100) / 100;
    derniere.value = calc;

    const err = document.getElementById(prefix + 'TranchesErreur');
    const uneAutreEstNulle = autres.some(inp => (parseFloat(inp.value) || 0) <= 0);
    if (uneAutreEstNulle) {
        err.textContent = 'Chaque tranche doit être strictement supérieure à 0 %.';
        err.style.display = '';
    } else if (calc <= 0) {
        err.textContent = 'La dernière tranche calculée doit être strictement supérieure à 0 %.';
        err.style.display = '';
    } else {
        err.style.display = 'none';
    }
}

function validerTranches(prefix, idModePaiement) {
    if (parseInt(idModePaiement) !== 3) return null;
    const n = parseInt(document.getElementById(prefix + 'NbTranches').value);
    if (!n || n < 2) return 'Renseignez un nombre de tranches valide (2 ou plus).';
    const inputs = Array.from(document.querySelectorAll(`.${prefix}-tranche-inp`));
    if (inputs.length !== n) return 'Les champs de tranches ne sont pas à jour, réessayez.';
    const valeurs = inputs.map(inp => parseFloat(inp.value) || 0);
    for (let i = 0; i < valeurs.length; i++) {
        if (valeurs[i] <= 0) return `La tranche ${i + 1} doit être strictement supérieure à 0 %.`;
    }
    const somme = valeurs.reduce((s, v) => s + v, 0);
    if (Math.abs(somme - 100) > 0.01) return `La somme des tranches doit être exactement égale à 100 % (actuellement ${somme.toFixed(2)} %).`;
    return null;
}

function collecterTranches(prefix) {
    return Array.from(document.querySelectorAll(`.${prefix}-tranche-inp`)).map((inp, idx) => ({
        ordre: idx + 1,
        pourcentage: inp.value,
    }));
}

/* ────────────────────────── SOUMISSION COMMUNE ─────────────────────────── */
function envoyerModification(formData, boutonId, modalId, onError) {
    const btn = document.getElementById(boutonId);
    const spinner = btn.querySelector('.vd-spinner');
    if (spinner) spinner.classList.remove('hidden');
    btn.disabled = true;

    $.ajax({
        url: drh_basi_controller_URL,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
    }).done(function (res) {
        if (spinner) spinner.classList.add('hidden');
        btn.disabled = false;

        if (res.status === 'success') {
            const modalEl = document.getElementById(modalId);
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();
            Swal.fire({ title: 'Succès', text: res.message || 'Modification enregistrée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerDemandes();
        } else {
            onError(res.message || 'Une erreur est survenue.');
        }
    }).fail(function (xhr) {
        if (spinner) spinner.classList.add('hidden');
        btn.disabled = false;
        onError(dga_ajaxErrorMessage(xhr));
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