/**
 * expression-besoin-scripts.bundle.js
 * Page "Expression de besoin" (tous profils) : liste filtrable par
 * intervalle d'années, création/modification (catégorie → sous-catégorie →
 * produit → quantité), Soumettre et poursuivre / Soumettre / Modifier /
 * Détail / Voir (suivi de l'évolution) selon le statut.
 */

const EB_CONTROLLER_URL = '/personnel/personnel_basi_controller'; // ← ajuster selon le chemin réel
const ANNEE_MIN_EB = 2026;

const LIBELLES_STATUT_EB = { 1: 'En attente', 2: 'Soumise', 3: 'Validée', 4: 'Rejetée', 5: 'Partiellement livré', 6: 'Terminé' };
const LIBELLES_STATUT_LIGNE_EB = { 'En attente': 'dga-ligne-attente', 'Partiellement livré': 'dga-ligne-partiel', 'Livré': 'dga-ligne-livre' };

let dga_table = null;
let dga_produitsPanier = []; // [{ idP, designation, quantite }]
let dga_tokenCourant = null; // token de l'EB en cours de modification (null = création)

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    initSelectsAnnees();
    chargerExpressions();

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
});

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
                    html += `<button type="button" class="dga-btn-voir-eb" onclick="dga_ouvrirVoir('${d}')">Voir</button>`;
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

        const lignesProduits = (e.produits || []).map(function (p) {
            const classeLigne = LIBELLES_STATUT_LIGNE_EB[p.statut_ligne] || 'dga-ligne-attente';
            return `
                <tr>
                    <td>${dga_escapeHtml(p.designation)}</td>
                    <td>${dga_escapeHtml(p.quantite)}</td>
                    <td>${p.quantite_reelle !== null && p.quantite_reelle !== undefined ? dga_escapeHtml(p.quantite_reelle) : '—'}</td>
                    <td>${p.quantite_sortie !== null && p.quantite_sortie !== undefined ? dga_escapeHtml(p.quantite_sortie) : '—'}</td>
                    <td>${p.quantite_restante !== null && p.quantite_restante !== undefined ? dga_escapeHtml(p.quantite_restante) : '—'}</td>
                    <td><span class="dga-badge-ligne ${classeLigne}">${p.statut_ligne}</span></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="6" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        const produitsHtml = `
            <table class="dga-table-produits">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th>Demandée</th>
                        <th>Validée</th>
                        <th>Sortie</th>
                        <th>Restante</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>${lignesProduits}</tbody>
            </table>
        `;

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

        document.getElementById('voirEbContenu').innerHTML = enteteHtml + motifRejetHtml + produitsHtml + historiqueHtml;

        new bootstrap.Modal(document.getElementById('modalVoirEB')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
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