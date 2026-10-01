/**
 * listeProduitsInvestissement.js
 * Catalogue des produits Investissement (profil Chef de service) : tous les
 * chefs de service voient tous les produits, mais ne modifient que les
 * leurs, et ne voient le stock global que de ceux qu'ils ont créés.
 */

const CATALOGUE_CONTROLLER_URL = '/personnel/chef_service_basi_controller_1';

let dga_table = null;
let dga_tokenProduitCourant = null; // idP en cours de modification (null = création)
let dga_debounceRecherche = null;

document.addEventListener('DOMContentLoaded', function () {
    chargerCatalogue();

    document.getElementById('dga-btn-nouveau-produit')?.addEventListener('click', dga_ouvrirNouveauProduit);
    document.getElementById('dgaRubriqueProduit')?.addEventListener('change', dga_chargerSousRubriques);
    document.getElementById('dgaBtnEnregistrerProduit')?.addEventListener('click', dga_soumettreProduit);
    document.getElementById('dgaNomProduit')?.addEventListener('input', dga_onSaisieNomProduit);

    // Ferme les suggestions si on clique ailleurs.
    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('dgaNomProduit')?.closest('.dga-autocomplete-wrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('dgaAutocompleteListe').classList.remove('dga-visible');
        }
    });
});

/* ────────────────────────── CHARGEMENT CATALOGUE ─────────────────────── */
function chargerCatalogue() {
    dga_showLoader('Chargement du catalogue…');
    $.ajax({
        url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: { option: 13 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le catalogue.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
        document.getElementById('dga-stat-nombre').textContent = res.nombre_total ?? 0;
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });

    chargerStatistiques();
}

function chargerStatistiques() {
    $.ajax({
        url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: { option: 17 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') return;
        const s = res.stats || {};
        document.getElementById('dga-stat-en-service').textContent = s.en_service ?? 0;
        document.getElementById('dga-stat-hors-service').textContent = s.hors_service ?? 0;
        document.getElementById('dga-stat-mes-produits').textContent = s.mes_produits ?? 0;
        document.getElementById('dga-stat-mon-stock').textContent = dga_fmtNombre(s.mon_stock_total ?? 0);
    });
}

function dga_renderTable(produits) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-catalogue').DataTable({
        data: produits,
        columns: [
            { data: null },
            { data: null },
            { data: 'nomproduit' },
            { data: 'Stock_actuel' },
            { data: 'retrait' },
            { data: 'date_creation' },
            { data: 'id_statut' },
            { data: 'nom_createur' },
            { data: null, orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: (d, t, row) => row.nom_rubrique ? dga_escapeHtml(row.nom_rubrique) : '<span class="dga-cell-muted">—</span>' },
            { targets: 1, render: (d, t, row) => row.nom_sous_rubrique ? dga_escapeHtml(row.nom_sous_rubrique) : '<span class="dga-cell-muted">—</span>' },
            { targets: 2, render: d => `<span style="font-weight:700;color:#111827;">${dga_escapeHtml(d)}</span>` },
            {
                targets: 3,
                render: d => (d === null || d === undefined)
                    ? '<span class="dga-cell-muted" title="Votre direction n\'a jamais reçu de stock de ce produit">—</span>'
                    : `<span class="dga-quota-val">${dga_fmtNombre(d)}</span>`,
            },
            {
                targets: 4,
                render: d => (d === null || d === undefined)
                    ? '<span class="dga-cell-muted" title="Votre direction n\'a jamais reçu de stock de ce produit">—</span>'
                    : dga_fmtNombre(d),
            },
            { targets: 5, render: d => dga_fmtDate(d) },
            {
                targets: 6,
                render: d => parseInt(d) === 1
                    ? '<span class="dga-badge-statut dga-statut-1" style="background:#ecfdf5;color:#059669;">En service</span>'
                    : '<span class="dga-badge-statut" style="background:#fef2f2;color:#991b1b;">Hors service</span>',
            },
            {
                targets: 7,
                render: (d, t, row) => {
                    const estMoi = !!row.modifiable; // modifiable = créateur, dans ce modèle
                    return `<span class="dga-badge-createur ${estMoi ? 'dga-badge-createur-moi' : 'dga-badge-createur-autre'}">${dga_escapeHtml(d || '—')}${estMoi ? ' (vous)' : ''}</span>`;
                },
            },
            {
                targets: 8,
                render: (d, t, row) => {
                    const btnDetail = row.peut_voir_detail
                        ? `<button type="button" class="dga-btn-detail-produit" onclick="dga_ouvrirDetailRepartition(${row.idP})">Détail</button>`
                        : '';
                    if (!row.modifiable) return btnDetail || '<span class="dga-cell-muted">—</span>';
                    const estActif = parseInt(row.id_statut) === 1;
                    return `
                        ${btnDetail}
                        <button type="button" class="dga-btn-modifier-produit" onclick="dga_ouvrirModifierProduit(${row.idP})">Modifier</button>
                        <button type="button" class="dga-btn-toggle-produit ${estActif ? 'dga-btn-desactiver' : 'dga-btn-activer'}" onclick="dga_toggleStatutProduit(${row.idP}, '${dga_escapeHtml(row.nomproduit)}', ${estActif})">
                            ${estActif ? 'Désactiver' : 'Activer'}
                        </button>
                    `;
                },
            },
        ],
        order: [[2, 'asc']],
        language: {
            emptyTable: 'Aucun produit Investissement au catalogue pour le moment.',
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
}

/* ────────────────────────── OUVERTURE MODALE ─────────────────────────── */
function dga_ouvrirNouveauProduit() {
    dga_tokenProduitCourant = null;
    document.getElementById('produitModalTitre').textContent = 'Nouveau produit';
    document.getElementById('dgaErreurGeneraleProduit').style.display = 'none';
    document.getElementById('dgaNomProduit').value = '';
    document.getElementById('dgaNomProduit').disabled = false;
    document.getElementById('dgaSeuilProduit').value = 0;
    document.getElementById('dgaSousRubriqueProduit').innerHTML = '<option value="">—</option>';
    document.getElementById('dgaSousRubriqueProduit').disabled = true;
    dga_chargerRubriques();
    new bootstrap.Modal(document.getElementById('modalProduitInvest')).show();
}

function dga_ouvrirModifierProduit(idP) {
    const row = dga_table.rows().data().toArray().find(r => parseInt(r.idP) === parseInt(idP));
    if (!row) return;

    dga_tokenProduitCourant = idP;
    document.getElementById('produitModalTitre').textContent = 'Modifier — ' + row.nomproduit;
    document.getElementById('dgaErreurGeneraleProduit').style.display = 'none';
    document.getElementById('dgaNomProduit').value = row.nomproduit;
    document.getElementById('dgaNomProduit').disabled = true; // pas de recherche-suggestion en modification
    document.getElementById('dgaSeuilProduit').value = row.Seuil_limite ?? 0;
    document.getElementById('dgaAutocompleteListe').classList.remove('dga-visible');

    dga_chargerRubriques(function () {
        // Présélectionne la rubrique/sous-rubrique actuelles une fois les
        // listes chargées.
        const selRubrique = document.getElementById('dgaRubriqueProduit');
        const rubriqueTrouvee = [...selRubrique.options].find(o => o.text === row.nom_rubrique);
        if (rubriqueTrouvee) {
            selRubrique.value = rubriqueTrouvee.value;
            dga_chargerSousRubriques(function () {
                document.getElementById('dgaSousRubriqueProduit').value = row.idSousRubrique || '';
            });
        }
    });

    new bootstrap.Modal(document.getElementById('modalProduitInvest')).show();
}

/* ────────────────────────── CASCADE RUBRIQUE ─────────────────────────── */
function dga_chargerRubriques(callback) {
    const sel = document.getElementById('dgaRubriqueProduit');
    sel.innerHTML = '<option value="">Chargement…</option>';
    $.ajax({
        url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: { option: 10 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') { sel.innerHTML = '<option value="">Erreur</option>'; return; }
        const rubriques = res.data || [];
        sel.innerHTML = '<option value="">Sélectionner…</option>' + rubriques.map(r => `<option value="${r.id}">${dga_escapeHtml(r.nom_rubrique)}</option>`).join('');
        if (typeof callback === 'function') callback();
    });
}

function dga_chargerSousRubriques(callback) {
    const idRubrique = document.getElementById('dgaRubriqueProduit').value;
    const sel = document.getElementById('dgaSousRubriqueProduit');

    if (!idRubrique) {
        sel.innerHTML = '<option value="">—</option>';
        sel.disabled = true;
        return;
    }

    sel.innerHTML = '<option value="">Chargement…</option>';
    $.ajax({
        url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: { option: 11, idRubrique: idRubrique }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') { sel.innerHTML = '<option value="">Erreur</option>'; return; }
        const sousRubriques = res.data || [];
        sel.innerHTML = '<option value="">Sélectionner…</option>' + sousRubriques.map(sr => `<option value="${sr.id}">${dga_escapeHtml(sr.nom_sous_rubrique)}</option>`).join('');
        sel.disabled = false;
        if (typeof callback === 'function') callback();
    });
}

/* ────────────────────────── RECHERCHE-SUGGESTION ─────────────────────── */
function dga_onSaisieNomProduit() {
    const texte = document.getElementById('dgaNomProduit').value.trim();
    const listeEl = document.getElementById('dgaAutocompleteListe');

    clearTimeout(dga_debounceRecherche);

    if (texte.length < 2) {
        listeEl.classList.remove('dga-visible');
        return;
    }

    // Petite temporisation pour éviter une requête à chaque frappe.
    dga_debounceRecherche = setTimeout(function () {
        $.ajax({
            url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: { option: 12, texte: texte }, dataType: 'json'
        }).done(function (res) {
            if (res.status !== 'success') return;
            dga_renderSuggestions(res.data || []);
        });
    }, 250);
}

function dga_renderSuggestions(produits) {
    const listeEl = document.getElementById('dgaAutocompleteListe');

    if (!produits.length) {
        listeEl.innerHTML = '<div class="dga-autocomplete-vide">Aucun produit existant ne correspond — vous pouvez continuer la saisie pour en créer un nouveau.</div>';
        listeEl.classList.add('dga-visible');
        return;
    }

    listeEl.innerHTML = produits.map(function (p) {
        const rubriqueTxt = p.nom_rubrique ? `${dga_escapeHtml(p.nom_rubrique)}${p.nom_sous_rubrique ? ' / ' + dga_escapeHtml(p.nom_sous_rubrique) : ''}` : 'Rubrique non définie';
        return `
            <div class="dga-autocomplete-item" onclick="dga_choisirSuggestion('${dga_escapeHtml(p.nomproduit)}')">
                <div class="dga-autocomplete-item-nom">${dga_escapeHtml(p.nomproduit)}</div>
                <div class="dga-autocomplete-item-meta">${rubriqueTxt} — déjà existant</div>
            </div>
        `;
    }).join('');
    listeEl.classList.add('dga-visible');
}

function dga_choisirSuggestion(nom) {
    document.getElementById('dgaNomProduit').value = nom;
    document.getElementById('dgaAutocompleteListe').classList.remove('dga-visible');
    Swal.fire({
        title: 'Produit déjà existant',
        text: `« ${nom} » existe déjà au catalogue. Si c'est bien ce produit, annulez cette création — sinon, modifiez le nom pour créer un produit différent.`,
        icon: 'info',
        confirmButtonColor: '#1a7a5e',
    });
}

/* ────────────────────────── SOUMISSION ───────────────────────────────── */
function dga_soumettreProduit() {
    const erreurBox = document.getElementById('dgaErreurGeneraleProduit');
    erreurBox.style.display = 'none';

    const nom = document.getElementById('dgaNomProduit').value.trim();
    const idSousRubrique = document.getElementById('dgaSousRubriqueProduit').value;
    const seuil = document.getElementById('dgaSeuilProduit').value;

    if (!nom) {
        erreurBox.textContent = 'Le nom du produit est requis.';
        erreurBox.style.display = 'block';
        return;
    }
    if (!idSousRubrique) {
        erreurBox.textContent = 'La sous-rubrique est requise.';
        erreurBox.style.display = 'block';
        return;
    }

    const option = dga_tokenProduitCourant ? 15 : 14;
    const payload = { option: option, nom: nom, idSousRubrique: idSousRubrique, seuil: seuil };
    if (dga_tokenProduitCourant) payload.idP = dga_tokenProduitCourant;

    const btn = document.getElementById('dgaBtnEnregistrerProduit');
    btn.disabled = true;
    btn.querySelector('.dga-spinner').classList.remove('hidden');

    $.ajax({
        url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: payload, dataType: 'json'
    }).done(function (res) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');

        if (res.status === 'success') {
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalProduitInvest'));
            if (modalInstance) modalInstance.hide();
            Swal.fire({ title: 'Succès', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e', timer: 1500, showConfirmButton: false });
            chargerCatalogue();
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

/* ────────────────────────── ACTIVER / DÉSACTIVER ─────────────────────── */
function dga_toggleStatutProduit(idP, nom, estActif) {
    Swal.fire({
        title: estActif ? 'Désactiver ce produit ?' : 'Activer ce produit ?',
        text: `« ${nom} » ${estActif ? 'ne sera plus proposé pour les demandes tant qu\'il reste désactivé.' : 'redevient disponible pour les demandes.'}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: estActif ? 'Désactiver' : 'Activer',
        confirmButtonColor: estActif ? '#dc2626' : '#1a7a5e',
        cancelButtonText: 'Annuler',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: { option: 16, idP: idP }, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e', timer: 1500, showConfirmButton: false });
                chargerCatalogue();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────── DÉTAIL PAR DIRECTION ─────────────────────── */
function dga_ouvrirDetailRepartition(idP) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: CATALOGUE_CONTROLLER_URL, method: 'POST', data: { option: 18, idP: idP }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const produit = res.produit;
        const repartition = res.repartition || [];
        document.getElementById('detailRepartitionModalTitre').textContent = 'Répartition — ' + produit.nomproduit;

        const lignes = repartition.map(function (r) {
            return `
                <tr>
                    <td>${dga_escapeHtml(r.code_direction || r.nom_direction || '—')}</td>
                    <td>${dga_fmtNombre(r.quantite_recue)}</td>
                    <td>${dga_fmtNombre(r.quantite_sortie)}</td>
                    <td>${parseFloat(r.quantite_reservee) > 0 ? dga_fmtNombre(r.quantite_reservee) : '<span class="dga-cell-muted">0</span>'}</td>
                    <td><span class="dga-qte-dispo">${dga_fmtNombre(r.quantite_disponible)}</span></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">Aucune direction n\'a encore reçu ce produit en stock.</td></tr>';

        document.getElementById('dgaContenuDetailRepartition').innerHTML = `
            <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                <strong>Stock global actuel :</strong> <span class="dga-qte-dispo">${dga_fmtNombre(produit.stock_actuel)}</span>
            </p>
            <table class="dga-table-produits">
                <thead><tr><th>Direction</th><th>Reçu</th><th>Sorti</th><th>Réservé</th><th>Disponible</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        `;

        new bootstrap.Modal(document.getElementById('modalDetailRepartition')).show();
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

function dga_fmtNombre(v) {
    const n = parseFloat(v);
    return isNaN(n) ? dga_escapeHtml(v) : String(n);
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