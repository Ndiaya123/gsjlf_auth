/**
 * avisfavorable_pap-scripts.bundle.js
 * Page "Avis favorable" (DFC) : liste les dossiers déjà validés par la DGA
 * (passer_achat_et_paiement.idStatut = 2), avec 2 actions par ligne :
 *   - Détail : affiche les infos + document(s) associé(s), en fonction du
 *     type de dossier (idTypePAP : 1 = Passer commande → facture définitive,
 *     2 = Passer au paiement → justificatif(s) de paiement).
 *   - Avis favorable : fait passer idStatut de 2 à 3.
 */

const DFC_CONTROLLER_URL = '/personnel/dfc_basi_controller'; // ← ajuster selon le chemin réel

let dga_table = null;
let dga_tokenDossierCourant = null;
let dga_typeDossierCourant = null; // 1 = Achat, 2 = Paiement
let dga_ligneEnEdition = null;     // idPAPL de la ligne actuellement en édition (une seule à la fois)
let dga_modeModal = 'detail';      // 'detail' (lecture seule) | 'traiter' (édition + Valider)

document.addEventListener('DOMContentLoaded', function () {
    // Initialise le DataTable vide immédiatement pour éviter le "flash" de
    // tableau brut non stylé pendant le chargement des données.
    dga_renderTable([]);
    chargerDossiers();
    document.getElementById('dgaBtnEnvoyerRelance')?.addEventListener('click', dga_envoyerRelance);
    document.getElementById('dgaBtnValiderDossier')?.addEventListener('click', function () {
        dga_confirmerAvisFavorable(dga_tokenDossierCourant);
    });
});

/* ────────────────────────── CHARGEMENT LISTE ─────────────────────── */
function chargerDossiers() {
    dga_showLoader('Chargement des dossiers…');

    $.ajax({
        url: DFC_CONTROLLER_URL,
        method: 'POST',
        data: { option: 16 },
        dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') {
            dga_hideLoader();
            Swal.fire('Erreur', res.message || 'Impossible de charger les dossiers.', 'error');
            return;
        }
        // Le tableau est repeuplé AVANT de cacher le loader : le changement de
        // hauteur de page (et donc le déplacement du footer) reste masqué
        // derrière l'overlay au lieu d'être visible à l'écran.
        dga_renderTable(res.data || []);
        dga_hideLoader();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_renderTable(dossiers) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-dossiers').DataTable({
        data: dossiers,
        columns: [
            { data: 'idPAP' },
            { data: 'nom_commande' },
            { data: 'idTypePAP' },
            { data: 'demandeur' },
            { data: 'dateCreation' },
            { data: 'montant_total' },
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
            { targets: 5, render: d => d !== null ? '<span class="dga-cell-amount">' + dga_formatMontant(d) + '</span>' : '<span style="color:#d1d5db;">—</span>' },
            {
                targets: 6,
                render: (d) => `
                    <div style="display:flex;gap:.4rem;">
                        <button type="button" class="dga-btn-avis" onclick="dga_ouvrirTraitement('${d}')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            À traiter
                        </button>
                        <button type="button" class="dga-btn-valider" onclick="dga_ouvrirDetail('${d}')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                            </svg>
                            Détail
                        </button>
                    </div>
                `,
            },
        ],
        order: [[0, 'desc']],
        language: {
            emptyTable: 'Aucun dossier à traiter.',
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

/* ────────────────────────── DÉTAIL DU DOSSIER (lecture seule) ────── */
function dga_ouvrirDetail(token) {
    dga_ouvrirModalDossier(token, 'detail');
}

/* ────────────────────────── À TRAITER (édition + Valider) ───────── */
function dga_ouvrirTraitement(token) {
    dga_ouvrirModalDossier(token, 'traiter');
}

function dga_ouvrirModalDossier(token, mode) {
    dga_showLoader('Chargement…');

    $.ajax({
        url: DFC_CONTROLLER_URL,
        method: 'POST',
        data: { option: 17, token: token },
        dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail du dossier.', 'error');
            return;
        }

        dga_tokenDossierCourant = token;
        dga_typeDossierCourant = parseInt(res.dossier?.idTypePAP) || 1;
        dga_ligneEnEdition = null;
        dga_modeModal = mode;

        const prefixeTitre = mode === 'traiter' ? 'À traiter — ' : 'Détail — ';
        document.getElementById('dgaModalTitre').textContent = prefixeTitre + (res.dossier?.nom_commande || '');
        document.getElementById('dgaEnteteQteOuMontant').textContent = 'Qté commandée';
        document.getElementById('dgaBtnValiderDossier').style.display = mode === 'traiter' ? 'inline-flex' : 'none';

        dga_afficherInfosGenerales(res.dossier);
        dga_afficherLignesDetail(res.lignes || []);
        dga_afficherDocuments(res.dossier, res);
        dga_chargerCommentaires(token);

        new bootstrap.Modal(document.getElementById('modalDetailDossier')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_afficherInfosGenerales(dossier) {
    const estAchat = parseInt(dossier.idTypePAP) === 1;
    const conteneur = document.getElementById('dgaInfosGenerales');
    conteneur.innerHTML = `
        <div class="dga-info-grid">
            <div class="dga-info-item">
                <span class="lbl">N° dossier</span>
                <span class="val">#${dga_escapeHtml(dossier.idPAP)}</span>
            </div>
            <div class="dga-info-item">
                <span class="lbl">Nom</span>
                <span class="val">${dga_escapeHtml(dossier.nom_commande || '—')}</span>
            </div>
            <div class="dga-info-item">
                <span class="lbl">Type</span>
                <span class="val">${estAchat ? 'Passer commande' : 'Passer au paiement'}</span>
            </div>
            <div class="dga-info-item">
                <span class="lbl">Montant total</span>
                <span class="val">${dossier.montant_total !== null ? dga_formatMontant(dossier.montant_total) : '—'}</span>
            </div>
            <div class="dga-info-item">
                <span class="lbl">Date de création</span>
                <span class="val">${dga_fmtDate(dossier.dateCreation)}</span>
            </div>
        </div>
    `;
}

let dga_lignesCourantes = [];

function dga_afficherLignesDetail(lignes) {
    dga_lignesCourantes = lignes;
    const body = document.getElementById('dgaBodyLignesDetail');
    if (!body) return;

    if (!lignes.length) {
        body.innerHTML = '<tr><td colspan="5" style="color:#9ca3af;font-style:italic;">Aucune ligne pour ce dossier.</td></tr>';
        return;
    }

    body.innerHTML = lignes.map(l => dga_rendreLigneDetail(l)).join('');
}

/**
 * Rend UNE ligne du tableau, dans son état courant : affichage normal,
 * édition (si dga_ligneEnEdition === l.idPAPL), ou grisée si annulée.
 */
function dga_rendreLigneDetail(l) {
    const idPAPL = l.idPAPL;
    const estAnnulee = parseInt(l.id_statut_PAPL) === 0;
    const estEnEdition = dga_modeModal === 'traiter' && dga_ligneEnEdition === idPAPL;
    const estAchat = dga_typeDossierCourant === 1;
    const modeEdition = dga_modeModal === 'traiter';

    if (estAnnulee) {
        return `
            <tr class="dga-ligne-annulee">
                <td>${dga_escapeHtml(l.designation || '')}</td>
                <td>${l.quantite_reelle !== null ? dga_escapeHtml(l.quantite_reelle) : '—'}</td>
                <td>${l.prix_reel !== null ? dga_formatMontant(l.prix_reel) : '—'}</td>
                <td>${l.montant_total_ligne !== null ? dga_formatMontant(l.montant_total_ligne) : '—'}</td>
                <td><span style="font-size:.72rem;color:#991b1b;font-weight:700;">Supprimée</span></td>
            </tr>
        `;
    }

    // Cellule éditable : quantité pour Achat, montant pour Paiement. La DFC
    // ne peut QUE diminuer une ligne (jamais l'augmenter) — d'où max="valeur
    // actuelle" et un indice sous le champ ; le serveur revalide de toute
    // façon strictement (>= valeur actuelle → rejeté).
    const celluleQuantite = (estAchat && estEnEdition)
        ? `<input type="number" class="dga-inp-ligne" id="dgaInpQuantite-${idPAPL}" min="0.01" max="${l.quantite_reelle}" step="0.01" value="${l.quantite_reelle}"/>
           <div class="dga-hint-diminution">Max : ${dga_escapeHtml(l.quantite_reelle)} (diminution uniquement)</div>`
        : (l.quantite_reelle !== null ? dga_escapeHtml(l.quantite_reelle) : '—');

    const celluleMontant = (!estAchat && estEnEdition)
        ? `<input type="number" class="dga-inp-ligne" id="dgaInpMontant-${idPAPL}" min="0.01" max="${l.montant_total_ligne}" step="0.01" value="${l.montant_total_ligne}"/>
           <div class="dga-hint-diminution">Max : ${dga_formatMontant(l.montant_total_ligne)} (diminution uniquement)</div>`
        : (l.montant_total_ligne !== null ? dga_formatMontant(l.montant_total_ligne) : '—');

    // La modification/suppression de ligne n'est possible qu'en mode
    // "À traiter" — en mode "Détail" (lecture seule), aucune action ici.
    let celluleAction = '<span style="color:#d1d5db;">—</span>';
    if (modeEdition) {
        celluleAction = estEnEdition
            ? `
                <button type="button" class="dga-btn-enregistrer-ligne" onclick="dga_enregistrerEditionLigne(${idPAPL})">Enregistrer</button>
                <button type="button" class="dga-btn-annuler-edition-ligne" onclick="dga_annulerEditionLigne()">Annuler</button>
            `
            : `
                <button type="button" class="dga-btn-editer-ligne" onclick="dga_activerEditionLigne(${idPAPL})">Modifier</button>
                <button type="button" class="dga-btn-annuler-ligne" onclick="dga_confirmerAnnulationLigne(${idPAPL})">Supprimer</button>
            `;
    }

    return `
        <tr id="dga-tr-ligne-${idPAPL}">
            <td>${dga_escapeHtml(l.designation || '')}</td>
            <td>${celluleQuantite}</td>
            <td>${l.prix_reel !== null ? dga_formatMontant(l.prix_reel) : '—'}</td>
            <td>${celluleMontant}</td>
            <td>${celluleAction}</td>
        </tr>
    `;
}

function dga_activerEditionLigne(idPAPL) {
    if (dga_modeModal !== 'traiter') return;
    dga_ligneEnEdition = idPAPL;
    dga_afficherLignesDetail(dga_lignesCourantes);
}

function dga_annulerEditionLigne() {
    dga_ligneEnEdition = null;
    dga_afficherLignesDetail(dga_lignesCourantes);
}

function dga_enregistrerEditionLigne(idPAPL) {
    const estAchat = dga_typeDossierCourant === 1;
    const option = estAchat ? 19 : 20;
    const champ = estAchat ? 'quantite' : 'montant';
    const input = document.getElementById(estAchat ? `dgaInpQuantite-${idPAPL}` : `dgaInpMontant-${idPAPL}`);
    const valeur = parseFloat(input?.value);

    if (isNaN(valeur) || valeur <= 0) {
        Swal.fire('Erreur', estAchat ? 'La quantité doit être supérieure à zéro.' : 'Le montant doit être supérieur à zéro.', 'error');
        return;
    }

    // La DFC ne peut que diminuer une ligne, jamais l'augmenter — vérifié
    // ici pour un retour immédiat, revalidé de toute façon côté serveur.
    const ligneActuelle = dga_lignesCourantes.find(l => l.idPAPL === idPAPL);
    const valeurActuelle = estAchat ? parseFloat(ligneActuelle?.quantite_reelle) : parseFloat(ligneActuelle?.montant_total_ligne);
    if (ligneActuelle && !isNaN(valeurActuelle) && valeur >= valeurActuelle) {
        Swal.fire('Erreur', `${estAchat ? 'La quantité' : 'Le montant'} ne peut être que diminué(e) par rapport à la valeur actuelle (${estAchat ? valeurActuelle : dga_formatMontant(valeurActuelle)}).`, 'error');
        return;
    }

    const payload = { option: option, token: dga_tokenDossierCourant, idPAPL: idPAPL };
    payload[champ] = valeur;

    $.ajax({
        url: DFC_CONTROLLER_URL, method: 'POST', data: payload, dataType: 'json'
    }).done(function (res) {
        if (res.status === 'success') {
            dga_ligneEnEdition = null;
            Swal.fire({ title: 'Succès', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e', timer: 1400, showConfirmButton: false });
            dga_ouvrirModalDossier(dga_tokenDossierCourant, dga_modeModal); // recharge tout le dossier (lignes + montant total recalculé)
        } else {
            Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
        }
    }).fail(function (xhr) {
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_confirmerAnnulationLigne(idPAPL) {
    Swal.fire({
        title: 'Supprimer cette ligne ?',
        text: 'Cette ligne ne sera plus prise en compte dans le montant total du dossier. Cette action ne peut pas être annulée.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer la ligne',
        confirmButtonColor: '#dc2626',
        cancelButtonText: 'Retour',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: DFC_CONTROLLER_URL, method: 'POST', data: { option: 21, token: dga_tokenDossierCourant, idPAPL: idPAPL }, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e', timer: 1400, showConfirmButton: false });
                dga_ouvrirModalDossier(dga_tokenDossierCourant, dga_modeModal);
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

function dga_afficherDocuments(dossier, res) {
    const estAchat = parseInt(dossier.idTypePAP) === 1;
    const titre = document.getElementById('dgaTitreDocuments');
    const conteneur = document.getElementById('dgaListeDocuments');

    if (estAchat) {
        titre.textContent = 'Facture pro forma';
        const facture = res.facture_definitive;

        if (!facture || !facture.facture_definitive) {
            conteneur.innerHTML = '<p style="color:#9ca3af;font-style:italic;">Aucune facture définitive enregistrée pour ce dossier.</p>';
            return;
        }

        const nomFournisseur = `${facture.prenomF || ''} ${facture.nomF || ''}`.trim() + (facture.entreprise ? ' — ' + facture.entreprise : '');
        conteneur.innerHTML = `
            <div class="dga-doc-item">
                <div class="dga-doc-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="dga-doc-info">
                    <div class="dga-doc-nom">Facture pro forma</div>
                    <div class="dga-doc-sub">Fournisseur retenu : ${dga_escapeHtml(nomFournisseur)}</div>
                </div>
                <a href="${facture.facture_definitive}" target="_blank" class="dga-doc-link">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Ouvrir
                </a>
            </div>
        `;
    } else {
        titre.textContent = 'Justificatif(s) de paiement';
        const justificatifs = res.justificatifs || [];

        if (!justificatifs.length) {
            conteneur.innerHTML = '<p style="color:#9ca3af;font-style:italic;">Aucun justificatif de paiement enregistré pour ce dossier.</p>';
            return;
        }

        conteneur.innerHTML = justificatifs.map(function (j, idx) {
            return `
                <div class="dga-doc-item">
                    <div class="dga-doc-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="dga-doc-info">
                        <div class="dga-doc-nom">Justificatif ${idx + 1}</div>
                        <div class="dga-doc-sub">Enregistré le ${dga_fmtDate(j.dateEnregistrement)}</div>
                    </div>
                    <a href="${j.doc}" target="_blank" class="dga-doc-link">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Ouvrir
                    </a>
                </div>
            `;
        }).join('');
    }
}

/* ────────────────────────── AVIS FAVORABLE ───────────────────────── */
function dga_confirmerAvisFavorable(token) {
    Swal.fire({
        title: 'Avis favorable',
        text: 'Confirmez-vous l\'avis favorable pour ce dossier ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;
        dga_envoyerAvisFavorable(token);
    });
}

function dga_envoyerAvisFavorable(token) {
    dga_showLoader('Enregistrement de l\'avis favorable…');

    $.ajax({
        url: DFC_CONTROLLER_URL,
        method: 'POST',
        data: { option: 18, token: token },
        dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status === 'success') {
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalDetailDossier'));
            if (modalInstance) modalInstance.hide();
            Swal.fire({ title: 'Succès', text: res.message || 'Avis favorable enregistré avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerDossiers();
        } else {
            Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
        }
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── RELANCE & COMMENTAIRES ───────────────── */

function dga_chargerCommentaires(token) {
    $.ajax({
        url: DFC_CONTROLLER_URL, method: 'POST', data: { option: 23, token: token }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') return;
        dga_afficherCommentaires(res.data || []);
    });
}

function dga_afficherCommentaires(commentaires) {
    const conteneur = document.getElementById('dgaListeCommentaires');
    if (!conteneur) return;

    if (!commentaires.length) {
        conteneur.innerHTML = '<p style="color:#9ca3af;font-style:italic;font-size:.82rem;">Aucun commentaire pour ce dossier.</p>';
        return;
    }

    conteneur.innerHTML = commentaires.map(function (c) {
        return `
            <div class="dga-commentaire-item">
                <div class="dga-commentaire-head">
                    <span class="dga-commentaire-auteur">${dga_escapeHtml(c.auteur || '—')}</span>
                </div>
                <div class="dga-commentaire-texte">${dga_escapeHtml(c.commentaire)}</div>
                <div class="dga-commentaire-date">${dga_fmtDate(c.dateEnregistrement)}</div>
            </div>
        `;
    }).join('');
}

function dga_envoyerRelance() {
    const textarea = document.getElementById('dgaTexteCommentaire');
    const commentaire = textarea.value.trim();

    if (!commentaire) {
        Swal.fire('Erreur', 'Veuillez saisir un commentaire avant d\'envoyer la relance.', 'error');
        return;
    }

    const btn = document.getElementById('dgaBtnEnvoyerRelance');
    btn.disabled = true;

    $.ajax({
        url: DFC_CONTROLLER_URL, method: 'POST', data: { option: 22, token: dga_tokenDossierCourant, commentaire: commentaire }, dataType: 'json'
    }).done(function (res) {
        btn.disabled = false;
        if (res.status === 'success') {
            textarea.value = '';
            Swal.fire({ title: 'Succès', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e', timer: 1500, showConfirmButton: false });
            dga_chargerCommentaires(dga_tokenDossierCourant);
        } else {
            Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
        }
    }).fail(function (xhr) {
        btn.disabled = false;
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}


/* ────────────────────────────── UTILITAIRES ──────────────────────── */
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