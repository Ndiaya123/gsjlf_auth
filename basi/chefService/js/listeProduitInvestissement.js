/**
 * listeProduitInvestissement.js
 * Page "Produits d'investissement" (profil Chef de service) : le tableau
 * de stock sert lui-même de formulaire d'ajout au panier ; "Terminer"
 * déclenche une sortie de stock immédiate (pas de validation hiérarchique).
 */

const EBI_CONTROLLER_URL = '/personnel/chef_service_basi_controller_1';

const LIBELLES_STATUT_EBI = { 1: 'Brouillon', 2: 'Terminé' };

let dga_tableStock = null;
let dga_tableHisto = null;
let dga_panier = [];          // [{ idP, designation, quantite, quotaDisponible }]
let dga_tokenCourant = null;  // token du brouillon en cours (null = nouvelle expression)
let dga_quotasParProduit = {};

document.addEventListener('DOMContentLoaded', function () {
    chargerStock();
    chargerHistorique();

    document.getElementById('dga-histo-toggle')?.addEventListener('click', dga_toggleHistorique);
    document.getElementById('dga-btn-poursuivre')?.addEventListener('click', () => dga_soumettre('poursuivre'));
    document.getElementById('dga-btn-terminer')?.addEventListener('click', dga_confirmerTerminer);
});

/* ────────────────────────── STOCK (tableau = formulaire) ────────────── */
function chargerStock() {
    dga_showLoader('Chargement du stock…');
    $.ajax({
        url: EBI_CONTROLLER_URL, method: 'POST', data: { option: 6 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le stock.', 'error');
            return;
        }
        const produits = res.data || [];
        produits.forEach(p => { dga_quotasParProduit[p.idP] = parseFloat(p.quota_disponible); });
        dga_renderTableStock(produits);
        document.getElementById('dga-stat-nb-produits').textContent = produits.length;
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_renderTableStock(produits) {
    if (dga_tableStock) { try { dga_tableStock.destroy(); } catch (e) {} dga_tableStock = null; }

    dga_tableStock = $('#dga-table-stock').DataTable({
        data: produits,
        columns: [
            { data: 'designation' },
            { data: 'nom_rubrique' },
            { data: 'quota_disponible' },
            { data: null, orderable: false, searchable: false },
            { data: null, orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => `<span style="font-weight:700;color:#111827;">${dga_escapeHtml(d)}</span>` },
            {
                targets: 1,
                render: (d, t, row) => {
                    if (!d) return '<span style="color:#9ca3af;">—</span>';
                    const sousRub = row.nom_sous_rubrique ? ' / ' + dga_escapeHtml(row.nom_sous_rubrique) : '';
                    return `<span class="dga-pill-rubrique">${dga_escapeHtml(d)}${sousRub}</span>`;
                },
            },
            { targets: 2, render: d => `<span class="dga-quota-val">${dga_escapeHtml(d)}</span>` },
            {
                targets: 3,
                render: (d, t, row) => `<input type="number" class="dga-qte-stock" data-idp="${row.idP}" min="1" step="1" max="${row.quota_disponible}" value="1"/>`,
            },
            {
                targets: 4,
                render: (d, t, row) => `<button type="button" class="dga-btn-ajouter-ligne" data-idp="${row.idP}" data-designation="${dga_escapeHtml(row.designation)}">Ajouter</button>`,
            },
        ],
        order: [[0, 'asc']],
        language: {
            emptyTable: 'Aucun produit disponible dans le stock de votre direction pour le moment.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
        initComplete: function () {
            document.documentElement.classList.remove('ld-booting');
        },
    });

    // Délégation d'événement : les boutons/inputs sont recréés à chaque
    // dessin du DataTable (pagination, tri, recherche).
    $('#dga-table-stock tbody').off('click', '.dga-btn-ajouter-ligne').on('click', '.dga-btn-ajouter-ligne', function () {
        const idP = this.dataset.idp;
        const designation = this.dataset.designation;
        const inputQte = document.querySelector(`.dga-qte-stock[data-idp="${idP}"]`);
        const quantite = parseFloat(inputQte?.value) || 0;
        dga_ajouterAuPanier(idP, designation, quantite);
    });
}

/* ────────────────────────── PANIER ───────────────────────────────────── */
function dga_ajouterAuPanier(idP, designation, quantite) {
    const erreurBox = document.getElementById('dgaErreurPanier');
    erreurBox.style.display = 'none';
    const quota = dga_quotasParProduit[idP] ?? 0;

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
    if (dga_panier.some(p => String(p.idP) === String(idP))) {
        erreurBox.textContent = 'Ce produit a déjà été ajouté à votre demande.';
        erreurBox.style.display = 'block';
        return;
    }

    dga_panier.push({ idP: idP, designation: designation, quantite: quantite, quotaDisponible: quota });
    dga_renderPanier();
}

function dga_retirerDuPanier(idP) {
    dga_panier = dga_panier.filter(p => String(p.idP) !== String(idP));
    dga_renderPanier();
}

function dga_renderPanier() {
    const panierEl = document.getElementById('dga-panier');
    const listeEl = document.getElementById('dga-panier-liste');
    const countEl = document.getElementById('dga-panier-count');

    countEl.textContent = dga_panier.length;
    panierEl.classList.toggle('dga-panier-visible', dga_panier.length > 0);

    listeEl.innerHTML = dga_panier.map(function (p) {
        return `
            <div class="dga-panier-ligne">
                <span class="dga-panier-ligne-nom">${dga_escapeHtml(p.designation)}</span>
                <span>
                    <span class="dga-panier-ligne-qte">${dga_escapeHtml(p.quantite)}</span>
                    <button type="button" class="dga-panier-retirer" onclick="dga_retirerDuPanier('${p.idP}')">Retirer</button>
                </span>
            </div>
        `;
    }).join('');
}

/* ────────────────────────── SOUMISSION ───────────────────────────────── */
function dga_soumettre(action) {
    const erreurBox = document.getElementById('dgaErreurPanier');
    erreurBox.style.display = 'none';

    if (!dga_panier.length) {
        erreurBox.textContent = 'Ajoutez au moins un produit à votre demande.';
        erreurBox.style.display = 'block';
        return;
    }

    const btnId = action === 'terminer' ? 'dga-btn-terminer' : 'dga-btn-poursuivre';
    const btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.querySelector('.dga-spinner').classList.remove('hidden');

    $.ajax({
        url: EBI_CONTROLLER_URL,
        method: 'POST',
        data: JSON.stringify({
            option: 9,
            token: dga_tokenCourant || '',
            action: action,
            produits: dga_panier.map(p => ({ idP: p.idP, quantite: p.quantite })),
        }),
        contentType: 'application/json',
        dataType: 'json',
    }).done(function (res) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');

        if (res.status === 'success') {
            if (action === 'terminer') {
                Swal.fire({ title: 'Succès', text: res.message || 'Opération réussie.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                dga_panier = [];
                dga_tokenCourant = null;
                dga_renderPanier();
            } else {
                Swal.fire({ title: 'Brouillon enregistré', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e', timer: 1500, showConfirmButton: false });
                dga_tokenCourant = res.tmp; // permet de poursuivre sans dupliquer
            }
            chargerStock();
            chargerHistorique();
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
function dga_confirmerTerminer() {
    if (!dga_panier.length) {
        const erreurBox = document.getElementById('dgaErreurPanier');
        erreurBox.textContent = 'Ajoutez au moins un produit à votre demande.';
        erreurBox.style.display = 'block';
        return;
    }

    Swal.fire({
        title: 'Confirmer la sortie de stock ?',
        html: `Cette action retire <strong>immédiatement et définitivement</strong> les quantités indiquées du stock de votre direction. Elle ne peut pas être annulée.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, terminer',
        confirmButtonColor: '#1a7a5e',
        cancelButtonText: 'Annuler',
        cancelButtonColor: '#6c757d',
    }).then(function (result) {
        if (result.isConfirmed) dga_soumettre('terminer');
    });
}

/* ────────────────────────── HISTORIQUE (repliable) ───────────────────── */
function dga_toggleHistorique() {
    const toggle = document.getElementById('dga-histo-toggle');
    const contenu = document.getElementById('dga-histo-contenu');
    const estOuvert = toggle.classList.toggle('dga-histo-ouvert');
    contenu.classList.toggle('dga-histo-visible', estOuvert);
}

function chargerHistorique() {
    $.ajax({
        url: EBI_CONTROLLER_URL, method: 'POST', data: { option: 7 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') return;
        dga_renderTableHisto(res.data || []);
        document.getElementById('dga-histo-count').textContent = res.nombre_total ?? 0;
    });
}

function dga_renderTableHisto(expressions) {
    if (dga_tableHisto) { try { dga_tableHisto.destroy(); } catch (e) {} dga_tableHisto = null; }

    dga_tableHisto = $('#dga-table-histo').DataTable({
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
                    let html = `<button type="button" class="dga-btn-voir-eb" onclick="dga_ouvrirDetail('${d}')">Détail</button>`;
                    if (statut === 1) {
                        html += `<button type="button" class="dga-btn-poursuivre" onclick="dga_reprendreBrouillon('${d}')">Poursuivre</button>`;
                    }
                    return html;
                },
            },
        ],
        order: [[1, 'desc']],
        language: {
            emptyTable: 'Aucune expression de besoin investissement pour le moment.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
    });
}

/**
 * "Poursuivre" un brouillon : charge ses lignes dans le panier courant
 * (remplace le panier actuel) et déroule vers le haut de page pour
 * continuer à ajouter des produits depuis le tableau de stock.
 */
function dga_reprendreBrouillon(token) {
    dga_showLoader('Chargement…');
    $.ajax({
        url: EBI_CONTROLLER_URL, method: 'POST', data: { option: 8, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || "Impossible de charger l'expression de besoin.", 'error');
            return;
        }

        const e = res.expression;
        dga_tokenCourant = token;
        dga_panier = (e.produits || []).map(p => ({
            idP: String(p.id_produit), designation: p.designation, quantite: parseFloat(p.quantite_demandee),
            quotaDisponible: dga_quotasParProduit[p.id_produit] ?? 0,
        }));
        dga_renderPanier();

        window.scrollTo({ top: 0, behavior: 'smooth' });
        Swal.fire({ title: 'Brouillon repris', text: 'Continuez à ajouter des produits depuis le tableau de stock.', icon: 'info', confirmButtonColor: '#1a7a5e', timer: 1800, showConfirmButton: false });
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── DÉTAIL (lecture) ─────────────────────────── */
function dga_ouvrirDetail(token) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: EBI_CONTROLLER_URL, method: 'POST', data: { option: 8, token: token }, dataType: 'json'
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

/* ────────────────────────────── UTILITAIRES ────────────────────── */
function dga_showLoader(msg = 'Chargement…') {
    $('#dga-loader').remove();
    $('body').append(`
        <div id="dga-loader">
            <div class="dga-loader-bg"></div>
            <div class="dga-loader-box">
                <svg class="dga-loader-spin" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>
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

function dga_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}