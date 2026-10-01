/**
 * liste-sorties-produits-scripts.bundle.js
 * Page "Sorties de produits" (profil Comptable) : liste des expressions de
 * besoin Validées pas encore entièrement servies, écran de sortie (quantité
 * SAISIE par le comptable, plafonnée à MIN(restant, stock) — 0 si stock nul).
 * Toutes les opérations de sortie sont bloquées si un inventaire est en cours.
 */

const CAISSE_CONTROLLER_URL = '/personnel/cpt_caisse_basi_controller'; // ← ajuster selon le chemin réel

const LIBELLES_STATUT_EB_SORTIE = { 1: 'Brouillon', 2: 'Soumise', 3: 'Validée', 4: 'Rejetée', 5: 'Sortie partielle', 6: 'Sortie totale', 7: 'Livrée', 8: 'Clôturée', 9: 'Clôturée avec solde', 10: 'Annulée'};
const LIBELLES_STATUT_EBI_SORTIE = { 1: 'Brouillon', 2: 'Soumise', 3: 'Partiellement sorti', 4: 'Terminé', 5: 'Livrée', 6: 'Clôturée', 7: 'Clôturée avec solde', 8: 'Annulée' };

let dga_table = null;
let dga_tableInvest = null;
let dga_tokenCourant = null;
let dga_inventaireEnCours = false;
let dga_typeSortieActif = 'fonctionnement'; // 'fonctionnement' | 'investissement' — piloté par la modale Sortie active

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    chargerExpressions();
    dga_initCommutateurSortie();

    document.getElementById('dgaBtnConfirmerSortie')?.addEventListener('click', dga_confirmerSortie);
    document.getElementById('dga-filtre-statut-sortie')?.addEventListener('change', chargerExpressions);
    document.getElementById('dga-filtre-statut-sortie-invest')?.addEventListener('change', chargerExpressionsInvest);
    document.getElementById('dga-btn-ecarts')?.addEventListener('click', dga_ouvrirEcarts);
    document.getElementById('dga-btn-ecarts-invest')?.addEventListener('click', dga_ouvrirEcartsInvest);
});

/* ────────────────────────── COMMUTATEUR TYPE ────────────────────────── */
function dga_initCommutateurSortie() {
    document.querySelectorAll('#dga-switch-type-sortie .dga-switch-btn').forEach(function (btn) {
        btn.addEventListener('click', () => dga_activerPanneauSortie(btn.dataset.cible));
    });
    chargerExpressionsInvest();
}

function dga_activerPanneauSortie(nom) {
    const estFonctionnement = nom === 'fonctionnement';
    document.getElementById('dga-panel-sortie-fonctionnement').style.display = estFonctionnement ? '' : 'none';
    document.getElementById('dga-panel-sortie-investissement').style.display = estFonctionnement ? 'none' : '';
    document.querySelectorAll('#dga-switch-type-sortie .dga-switch-btn').forEach(function (btn) {
        btn.classList.toggle('dga-switch-active', btn.dataset.cible === nom);
    });
}

/* ────────────────────────── CHARGEMENT LISTE ───────────────────────── */
function chargerExpressions() {
    const statutVal = document.getElementById('dga-filtre-statut-sortie')?.value ?? '3,5';

    dga_showLoader('Chargement des expressions de besoin…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 26, statut: statutVal }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les sorties de produits.', 'error');
            return;
        }

        dga_inventaireEnCours = !!res.inventaireEnCours;
        const banniere = document.getElementById('dga-alerte-inventaire');
        if (banniere) banniere.style.display = dga_inventaireEnCours ? 'flex' : 'none';

        dga_renderTable(res.data || []);
        document.getElementById('dga-stat-nombre').textContent = res.nombre_total ?? 0;

        const stats = res.stats || {};
        document.getElementById('dga-stat-a-sortir').textContent = stats['3'] ?? 0;
        document.getElementById('dga-stat-partiel').textContent = stats['5'] ?? 0;
        document.getElementById('dga-stat-termine').textContent = stats['6'] ?? 0;
        const cptEcarts = document.getElementById('dga-ecarts-count');
        if (cptEcarts) cptEcarts.textContent = res.ecartsOuverts ?? 0;
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function dga_renderTable(expressions) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-sortie').DataTable({
        data: expressions,
        columns: [
            { data: 'nom_expression' },
            { data: 'demandeur' },
            { data: 'date_creation' },
            { data: 'nombre_produits' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            { targets: 1, render: d => dga_escapeHtml(d) },
            { targets: 2, render: d => dga_fmtDate(d) },
            { targets: 4, render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT_EB_SORTIE[d] || d}</span>` },
            {
                targets: 5,
                render: (d, t, row) => {
                    const statut = parseInt(row.idStatut);
                    let html = `<button type="button" class="dga-btn-voir-eb" onclick="dga_ouvrirVoirEB('${d}')">Voir</button>`;

                    if (statut >= 5) {
                        html += `<button type="button" class="dga-btn-consulter-sortie" onclick="dga_ouvrirConsulterSortie('${d}')">Consulter sortie</button>`;
                    }

                    // Renoncer au reste à sortir (rupture durable, besoin disparu) : sans
                    // sortie effectuée la demande est annulée, sinon son solde est clôturé.
                    if ((statut === 3 || statut === 5) && parseFloat(row.solde_a_sortir) > 0.001) {
                        html += `<button type="button" class="dga-btn-cloturer" onclick="dga_cloturerSolde('${d}', ${statut}, ${parseFloat(row.solde_a_sortir)})">${statut === 3 ? 'Annuler la demande' : 'Clôturer le solde'}</button>`;
                    }

                    if (statut < 6) {
                        html += `
                            <button type="button" class="dga-btn-sortie" onclick="dga_ouvrirSortie('${d}')" ${dga_inventaireEnCours ? 'disabled title="Inventaire en cours"' : ''}>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v14a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4Z"/></svg>
                                Sortie
                            </button>
                        `;
                    }

                    return html;
                },
            },
        ],
        order: [[2, 'asc']],
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

/* ═══════════════════════════ INVESTISSEMENT ══════════════════════════ */
function chargerExpressionsInvest() {
    const statutVal = document.getElementById('dga-filtre-statut-sortie-invest')?.value ?? '2,3';

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 37, statut: statutVal }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les sorties Investissement.', 'error');
            return;
        }

        dga_inventaireEnCours = !!res.inventaireEnCours;
        const banniere = document.getElementById('dga-alerte-inventaire');
        if (banniere) banniere.style.display = dga_inventaireEnCours ? 'flex' : 'none';

        dga_renderTableInvest(res.data || []);
        document.getElementById('dga-stat-nombre-invest').textContent = res.nombre_total ?? 0;

        const stats = res.stats || {};
        document.getElementById('dga-stat-a-sortir-invest').textContent = stats['2'] ?? 0;
        document.getElementById('dga-stat-partiel-invest').textContent = stats['3'] ?? 0;
        document.getElementById('dga-stat-termine-invest').textContent = stats['4'] ?? 0;
        const cptEcartsInvest = document.getElementById('dga-ecarts-count-invest');
        if (cptEcartsInvest) cptEcartsInvest.textContent = res.ecartsOuverts ?? 0;
    }).fail(function (xhr) {
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_renderTableInvest(expressions) {
    if (dga_tableInvest) { try { dga_tableInvest.destroy(); } catch (e) {} dga_tableInvest = null; }

    dga_tableInvest = $('#dga-table-sortie-invest').DataTable({
        data: expressions,
        columns: [
            { data: 'nom_expression' },
            { data: null },
            { data: 'date_creation' },
            { data: 'nombre_produits' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            { targets: 1, render: (d, t, row) => dga_escapeHtml(row.code_direction || row.nom_direction || '—') },
            { targets: 2, render: d => dga_fmtDate(d) },
            { targets: 4, render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT_EBI_SORTIE[d] || d}</span>` },
            {
                targets: 5,
                render: (d, t, row) => {
                    const statut = parseInt(row.idStatut);
                    let html = '';
                    // Renoncer au reste à sortir — sans sortie effectuée la
                    // demande est annulée, sinon son solde est clôturé.
                    if ((statut === 2 || statut === 3) && parseFloat(row.solde_a_sortir) > 0.001) {
                        html += `<button type="button" class="dga-btn-cloturer" onclick="dga_cloturerSoldeInvest('${d}', ${statut}, ${parseFloat(row.solde_a_sortir)})">${statut === 2 ? 'Annuler la demande' : 'Clôturer le solde'}</button>`;
                    }
                    if (statut !== 4) {
                        html += `
                            <button type="button" class="dga-btn-sortie" onclick="dga_ouvrirSortieInvest('${d}')" ${dga_inventaireEnCours ? 'disabled title="Inventaire en cours"' : ''}>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v14a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4Z"/></svg>
                                Sortie
                            </button>
                        `;
                    }
                    return html;
                },
            },
        ],
        order: [[2, 'asc']],
        language: {
            emptyTable: 'Aucune expression de besoin Investissement à afficher.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
    });
}

/* ────────────────────────── OUVERTURE MODALE SORTIE ────────────────── */
function dga_ouvrirSortie(token) {
    dga_typeSortieActif = 'fonctionnement';
    if (dga_inventaireEnCours) {
        Swal.fire('Inventaire en cours', "Aucune sortie de stock n'est possible tant que l'inventaire est en cours.", 'warning');
        return;
    }

    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 27, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        dga_tokenCourant = token;
        const e = res.expression;
        document.getElementById('sortieModalTitre').textContent = 'Sortie — ' + e.nom_expression;
        document.getElementById('dgaErreurSortie').style.display = 'none';

        const banniereDejaSortie = document.getElementById('dgaInfoDejaSortie');
        if (parseInt(e.idStatut) === 5) {
            const nombreLignesCommencees = (e.lignes || []).filter(l => parseFloat(l.quantite_sortie || 0) > 0).length;
            banniereDejaSortie.textContent = `Cette expression de besoin est partiellement livrée : ${nombreLignesCommencees} produit(s) ont déjà été sortis (au moins en partie).`;
            banniereDejaSortie.style.display = 'flex';
        } else {
            banniereDejaSortie.style.display = 'none';
        }

        dga_renderLignesSortie(e.lignes || [], l => l.idEBP);

        new bootstrap.Modal(document.getElementById('modalSortie')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/**
 * Rend le corps du tableau de la modale Sortie — factorisé entre
 * Fonctionnement (clé idEBP) et Investissement (clé idEBIP), qui partagent
 * la même modale et la même structure de colonnes.
 */
function dga_renderLignesSortie(lignesBrutes, getId) {
    const lignes = lignesBrutes.filter(l => !l.entierement_servie);

    document.getElementById('dgaCorpsSortie').innerHTML = lignes.length
        ? lignes.map(function (l) {
            const stockNul = l.max_sortable <= 0.001;
            const dejaSortie = parseFloat(l.quantite_sortie || 0);
            return `
                <tr>
                    <td>${dga_escapeHtml(l.designation)}</td>
                    <td>${dejaSortie > 0 ? dga_escapeHtml(dejaSortie) : '<span style="color:#d1d5db;">—</span>'}</td>
                    <td>${dga_escapeHtml(l.quantite_restante)}</td>
                    <td>${dga_escapeHtml(l.stock_disponible)}</td>
                    <td>
                        <input type="number" class="dga-inp-qte-sortie" data-id="${getId(l)}" data-max="${l.max_sortable}"
                               min="0" max="${l.max_sortable}" step="1" value="${stockNul ? 0 : l.max_sortable}"
                               oninput="dga_plafonnerQuantiteSortie(this)" ${stockNul ? 'disabled' : ''}/>
                    </td>
                    <td>${stockNul ? '<span class="dga-badge-partiel" style="background:#fee2e2;color:#991b1b;">Stock nul</span>' : ''}</td>
                </tr>
            `;
        }).join('')
        : '<tr><td colspan="6" style="text-align:center;color:#9ca3af;font-style:italic;">Toutes les lignes sont déjà entièrement servies.</td></tr>';
}

/**
 * Ouverture de la modale Sortie pour une expression Investissement — même
 * modale que le Fonctionnement, alimentée par l'option 38.
 */
function dga_ouvrirSortieInvest(token) {
    dga_typeSortieActif = 'investissement';
    if (dga_inventaireEnCours) {
        Swal.fire('Inventaire en cours', "Aucune sortie de stock n'est possible tant que l'inventaire est en cours.", 'warning');
        return;
    }

    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 38, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        dga_tokenCourant = token;
        const e = res.expression;
        document.getElementById('sortieModalTitre').textContent = 'Sortie Investissement — ' + e.nom_expression;
        document.getElementById('dgaErreurSortie').style.display = 'none';

        const banniereDejaSortie = document.getElementById('dgaInfoDejaSortie');
        if (parseInt(e.idStatut) === 3) {
            const nombreLignesCommencees = (e.lignes || []).filter(l => parseFloat(l.quantite_sortie || 0) > 0).length;
            banniereDejaSortie.textContent = `Cette expression de besoin est partiellement sortie : ${nombreLignesCommencees} produit(s) ont déjà été sortis (au moins en partie).`;
            banniereDejaSortie.style.display = 'flex';
        } else {
            banniereDejaSortie.style.display = 'none';
        }

        dga_renderLignesSortie(e.lignes || [], l => l.idEBIP);

        new bootstrap.Modal(document.getElementById('modalSortie')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_plafonnerQuantiteSortie(input) {
    const max = parseFloat(input.dataset.max);
    let val = parseFloat(input.value);
    if (!isNaN(val)) val = Math.round(val); // les produits sont des unités entières
    if (!isNaN(val) && !isNaN(max) && val > max) {
        val = max;
        input.style.borderColor = '#dc2626';
        setTimeout(() => { input.style.borderColor = ''; }, 800);
    }
    if (!isNaN(val) && val < 0) val = 0;
    if (!isNaN(val)) input.value = val;
}

/* ────────────────────────── CONFIRMATION SORTIE ────────────────────── */
function dga_confirmerSortie() {
    if (dga_inventaireEnCours) {
        Swal.fire('Inventaire en cours', "Aucune sortie de stock n'est possible tant que l'inventaire est en cours.", 'warning');
        return;
    }

    const estInvest = dga_typeSortieActif === 'investissement';
    const cleId = estInvest ? 'idEBIP' : 'idEBP';
    const optionSortie = estInvest ? 39 : 28;

    const erreurBox = document.getElementById('dgaErreurSortie');
    erreurBox.style.display = 'none';

    const quantites = [];
    let auMoinsUne = false;
    let depassement = false;
    document.querySelectorAll('.dga-inp-qte-sortie').forEach(function (input) {
        const val = parseFloat(input.value) || 0;
        const max = parseFloat(input.dataset.max) || 0;
        if (val > max + 0.001) depassement = true;
        if (val > 0) auMoinsUne = true;
        quantites.push({ [cleId]: input.dataset.id, quantite: val });
    });

    if (depassement) {
        erreurBox.textContent = 'La quantité saisie ne peut jamais dépasser le maximum autorisé (restant à sortir / stock disponible).';
        erreurBox.style.display = 'block';
        return;
    }
    if (!auMoinsUne) {
        erreurBox.textContent = 'Veuillez saisir au moins une quantité à sortir.';
        erreurBox.style.display = 'block';
        return;
    }

    Swal.fire({
        title: 'Confirmer la sortie',
        text: 'Cette action décrémente le stock et enregistre la sortie. Confirmez-vous ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        const btn = document.getElementById('dgaBtnConfirmerSortie');
        btn.disabled = true;
        btn.querySelector('.dga-spinner').classList.remove('hidden');

        $.ajax({
            url: CAISSE_CONTROLLER_URL,
            method: 'POST',
            data: JSON.stringify({ option: optionSortie, token: dga_tokenCourant, quantites: quantites }),
            contentType: 'application/json',
            dataType: 'json',
        }).done(function (res) {
            btn.disabled = false;
            btn.querySelector('.dga-spinner').classList.add('hidden');

            if (res.status === 'success') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalSortie'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Sortie enregistrée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                if (estInvest) chargerExpressionsInvest(); else chargerExpressions();
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
    });
}

/* ────────────────────────── VOIR (suivi de l'évolution) ─────────────── */
function dga_badgeLigneSortie(statutLigne) {
    const map = {
        'Brouillon': 'dga-ligne-attente',
        'Annulée': 'dga-ligne-attente',
        'Sortie partielle': 'dga-ligne-partiel',
        'Sortie totale': 'dga-ligne-livre',
    };
    const cls = map[statutLigne] || 'dga-ligne-attente';
    return `<span class="dga-badge-ligne ${cls}">${statutLigne}</span>`;
}

function dga_ouvrirVoirEB(token) {
    dga_showLoader('Chargement du suivi…');
    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 35, token: token }, dataType: 'json'
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
                <span style="font-size:.82rem;color:#374151;"><strong>Demandeur :</strong> ${dga_escapeHtml(e.demandeur)} — <strong>Créée le :</strong> ${dga_fmtDate(e.date_creation)}</span>
                <span class="dga-badge-statut dga-statut-${e.idStatut}">${LIBELLES_STATUT_EB_SORTIE[e.idStatut] || e.idStatut}</span>
            </div>
        `;

        const motifRejetHtml = (parseInt(e.idStatut) === 4 && e.motif_rejet)
            ? `<div class="dga-alerte-info" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><strong>Motif du rejet :</strong> ${dga_escapeHtml(e.motif_rejet)}</div>`
            : '';

        const lignesProduits = (e.produits || []).map(function (p) {
            return `
                <tr>
                    <td>${dga_escapeHtml(p.designation)}</td>
                    <td>${dga_escapeHtml(p.quantite)}</td>
                    <td>${p.quantite_reelle !== null && p.quantite_reelle !== undefined ? dga_escapeHtml(p.quantite_reelle) : '—'}</td>
                    <td>${p.quantite_sortie !== null && p.quantite_sortie !== undefined ? dga_escapeHtml(p.quantite_sortie) : '—'}</td>
                    <td>${p.quantite_restante !== null && p.quantite_restante !== undefined ? dga_escapeHtml(p.quantite_restante) : '—'}</td>
                    <td>${dga_badgeLigneSortie(p.statut_ligne || 'En attente')}</td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="6" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        const produitsHtml = `
            <table class="dga-table-produits">
                <thead><tr><th>Désignation</th><th>Demandée</th><th>Validée</th><th>Sortie</th><th>Restante</th><th>Statut</th></tr></thead>
                <tbody>${lignesProduits}</tbody>
            </table>
        `;

        const historiqueRows = (e.historique || []).map(function (h) {
            return `
                <li style="padding:.4rem 0;font-size:.8rem;color:#374151;border-bottom:1px dashed #f3f4f6;">
                    <strong>${dga_fmtDateHeure(h.dateEnregistrement)}</strong>
                    — ${LIBELLES_STATUT_EB_SORTIE[h.idStatut] || h.idStatut}
                    ${h.motif ? `<span style="color:#9ca3af;font-size:.76rem;"> (${dga_escapeHtml(h.motif)})</span>` : ''}
                </li>
            `;
        }).join('') || '<li style="font-size:.8rem;color:#9ca3af;font-style:italic;">Aucun historique.</li>';

        document.getElementById('voirEbContenu').innerHTML = `
            ${enteteHtml}
            ${motifRejetHtml}
            ${produitsHtml}
            <h4 style="font-size:.8rem;font-weight:800;color:#111827;margin:1.2rem 0 .5rem;">Historique</h4>
            <ul style="list-style:none;padding:0;margin:0;">${historiqueRows}</ul>
        `;

        new bootstrap.Modal(document.getElementById('modalVoirEB')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── CONSULTER SORTIE ───────────────────────── */
function dga_ouvrirConsulterSortie(token) {
    dga_showLoader('Chargement des informations…');
    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 36, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les informations sur les sorties.', 'error');
            return;
        }

        const e = res.expression;
        document.getElementById('consulterSortieModalTitre').textContent = 'Sorties — ' + e.nom_expression;

        const lignes = e.lignes || [];
        const sectionsHtml = lignes.length
            ? lignes.map(function (l) {
                const sortiesHtml = (l.sorties || []).length
                    ? l.sorties.map(function (s) {
                        return `<tr><td>${dga_fmtDateHeure(s.date_sortie)}</td><td>${dga_escapeHtml(s.quantite_sortie)}</td><td>${s.utilisateur ? dga_escapeHtml(s.utilisateur) : '—'}</td></tr>`;
                    }).join('')
                    : '<tr><td colspan="3" style="text-align:center;color:#9ca3af;font-style:italic;">Aucune sortie enregistrée pour ce produit.</td></tr>';

                return `
                    <div class="dga-section-produit">
                        <div class="dga-section-produit-titre">${dga_escapeHtml(l.designation)}</div>
                        <div class="dga-section-produit-qtes">
                            Quantité demandée : <strong>${dga_escapeHtml(l.quantite)}</strong>
                            — Quantité réelle : <strong>${l.quantite_reelle !== null ? dga_escapeHtml(l.quantite_reelle) : '—'}</strong>
                            — Quantité sortie : <strong>${dga_escapeHtml(l.quantite_sortie)}</strong>
                        </div>
                        <table class="dga-table-sorties-detail">
                            <thead><tr><th>Date de sortie</th><th>Quantité sortie</th><th>Utilisateur</th></tr></thead>
                            <tbody>${sortiesHtml}</tbody>
                        </table>
                    </div>
                `;
            }).join('')
            : '<p style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</p>';

        document.getElementById('consulterSortieContenu').innerHTML = `
            <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                <strong>Demandeur :</strong> ${dga_escapeHtml(e.demandeur)}<br/>
                <strong>Date de création :</strong> ${dga_fmtDate(e.date_creation)}
            </p>
            ${sectionsHtml}
        `;

        new bootstrap.Modal(document.getElementById('modalConsulterSortie')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── CLÔTURE DU SOLDE ─────────────────────────── */
/**
 * Renonce à sortir le reste d'une demande. Un motif est obligatoire (tracé
 * dans l'historique). Sans sortie effectuée, la demande est annulée ; sinon
 * elle sera close (avec solde) une fois toutes les livraisons reçues.
 */
function dga_cloturerSolde(token, statut, solde) {
    const nb = String(Math.round(solde * 100) / 100);
    const annulation = statut === 3;
    Swal.fire({
        title: annulation ? 'Annuler cette demande ?' : 'Clôturer le solde ?',
        html: annulation
            ? `Aucun produit n'a été sorti : la demande sera <strong>annulée</strong> (${nb} unité(s) non sorties).`
            : `Le reste à sortir (<strong>${nb}</strong> unité(s)) sera <strong>définitivement annulé</strong>. Les produits déjà sortis suivent leur livraison normalement.`,
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Motif (obligatoire)',
        inputPlaceholder: 'Ex. : rupture durable de stock, besoin disparu…',
        inputAttributes: { maxlength: 500 },
        showCancelButton: true,
        confirmButtonText: annulation ? 'Annuler la demande' : 'Clôturer le solde',
        confirmButtonColor: '#9a3412',
        cancelButtonText: 'Retour',
        cancelButtonColor: '#6b7280',
        inputValidator: v => (!v || !v.trim()) ? 'Le motif est obligatoire.' : undefined,
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 45, token: token, commentaire: result.value.trim() }, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerExpressions();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────── ÉCARTS DE RÉCEPTION ──────────────────────── */
function dga_ouvrirEcarts() {
    dga_showLoader('Chargement des écarts…');
    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 43 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les écarts.', 'error');
            return;
        }
        document.getElementById('dgaErreurEcarts').style.display = 'none';
        const nb = v => String(parseFloat(v));
        document.getElementById('dgaCorpsEcarts').innerHTML = (res.data || []).length
            ? res.data.map(e => `
                <tr>
                    <td><strong>${dga_escapeHtml(e.numero_bon)}</strong><div class="dga-ecart-motif">${dga_escapeHtml(e.nom_expression)}</div></td>
                    <td>${dga_escapeHtml(e.designation)}</td>
                    <td><strong style="color:#991b1b;">${nb(e.quantite_ecart)}</strong></td>
                    <td>${dga_escapeHtml(e.demandeur || '—')}
                        <div class="dga-ecart-motif">${dga_fmtDate(e.dateSignalement)} — « ${dga_escapeHtml(e.commentaire)} »</div></td>
                    <td>
                        <div class="dga-ecart-actions">
                            <select id="dgaResolution-${e.id}">
                                <option value="correction_livraison">Correction de la livraison (à remettre)</option>
                                <option value="retour_stock">Retour en stock (redevient à sortir)</option>
                                <option value="perte">Perte (stock inchangé)</option>
                            </select>
                            <input type="text" id="dgaComm-${e.id}" maxlength="500" placeholder="Commentaire (obligatoire)">
                            <button type="button" class="dga-btn-sortie" onclick="dga_regulariserEcart(${e.id})">Régulariser</button>
                        </div>
                    </td>
                </tr>`).join('')
            : '<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun écart à régulariser.</td></tr>';
        const el = document.getElementById('modalEcarts');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_regulariserEcart(idEcart) {
    const resolution = document.getElementById('dgaResolution-' + idEcart).value;
    const commentaire = document.getElementById('dgaComm-' + idEcart).value.trim();
    const erreurBox = document.getElementById('dgaErreurEcarts');
    erreurBox.style.display = 'none';

    if (!commentaire) {
        erreurBox.textContent = 'Un commentaire est obligatoire pour régulariser un écart.';
        erreurBox.style.display = 'block';
        return;
    }
    if (resolution === 'retour_stock' && dga_inventaireEnCours) {
        erreurBox.textContent = "Un inventaire est en cours : aucun retour en stock n'est possible pour le moment.";
        erreurBox.style.display = 'block';
        return;
    }

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 44, idEcart: idEcart, resolution: resolution, commentaire: commentaire }, dataType: 'json'
    }).done(function (res) {
        if (res.status === 'success') {
            chargerExpressions();
            dga_ouvrirEcarts(); // recharge la liste des écarts restants
        } else {
            erreurBox.textContent = res.message || 'Une erreur est survenue.';
            erreurBox.style.display = 'block';
        }
    }).fail(function (xhr) {
        erreurBox.textContent = dga_ajaxErrorMessage(xhr);
        erreurBox.style.display = 'block';
    });
}

/* ────────────────────────── CLÔTURE DU SOLDE — INVESTISSEMENT ────────── */
function dga_cloturerSoldeInvest(token, statut, solde) {
    const nb = String(Math.round(solde * 100) / 100);
    const annulation = statut === 2;
    Swal.fire({
        title: annulation ? 'Annuler cette demande ?' : 'Clôturer le solde ?',
        html: annulation
            ? `Aucun produit n'a été sorti : la demande sera <strong>annulée</strong> (${nb} unité(s) non sorties).`
            : `Le reste à sortir (<strong>${nb}</strong> unité(s)) sera <strong>définitivement annulé</strong>. Les produits déjà sortis suivent leur livraison normalement.`,
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Motif (obligatoire)',
        inputPlaceholder: 'Ex. : rupture durable de stock, besoin disparu…',
        inputAttributes: { maxlength: 500 },
        showCancelButton: true,
        confirmButtonText: annulation ? 'Annuler la demande' : 'Clôturer le solde',
        confirmButtonColor: '#9a3412',
        cancelButtonText: 'Retour',
        cancelButtonColor: '#6b7280',
        inputValidator: v => (!v || !v.trim()) ? 'Le motif est obligatoire.' : undefined,
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 48, token: token, commentaire: result.value.trim() }, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message, icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerExpressionsInvest();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────── ÉCARTS DE RÉCEPTION — INVESTISSEMENT ─────── */
function dga_ouvrirEcartsInvest() {
    dga_showLoader('Chargement des écarts…');
    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 46 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les écarts.', 'error');
            return;
        }
        document.getElementById('dgaErreurEcartsInvest').style.display = 'none';
        const nb = v => String(parseFloat(v));
        document.getElementById('dgaCorpsEcartsInvest').innerHTML = (res.data || []).length
            ? res.data.map(e => `
                <tr>
                    <td><strong>${dga_escapeHtml(e.numero_bon)}</strong><div class="dga-ecart-motif">${dga_escapeHtml(e.nom_expression)} — ${dga_escapeHtml(e.code_direction || e.nom_direction || '')}</div></td>
                    <td>${dga_escapeHtml(e.designation)}</td>
                    <td><strong style="color:#991b1b;">${nb(e.quantite_ecart)}</strong></td>
                    <td><div class="dga-ecart-motif">${dga_fmtDate(e.dateSignalement)} — « ${dga_escapeHtml(e.commentaire)} »</div></td>
                    <td>
                        <div class="dga-ecart-actions">
                            <select id="dgaResolutionInvest-${e.id}">
                                <option value="correction_livraison">Correction de la livraison (à remettre)</option>
                                <option value="retour_stock">Retour en stock (redevient à sortir)</option>
                                <option value="perte">Perte (stock inchangé)</option>
                            </select>
                            <input type="text" id="dgaCommInvest-${e.id}" maxlength="500" placeholder="Commentaire (obligatoire)">
                            <button type="button" class="dga-btn-sortie" onclick="dga_regulariserEcartInvest(${e.id})">Régulariser</button>
                        </div>
                    </td>
                </tr>`).join('')
            : '<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun écart à régulariser.</td></tr>';
        const el = document.getElementById('modalEcartsInvest');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_regulariserEcartInvest(idEcart) {
    const resolution = document.getElementById('dgaResolutionInvest-' + idEcart).value;
    const commentaire = document.getElementById('dgaCommInvest-' + idEcart).value.trim();
    const erreurBox = document.getElementById('dgaErreurEcartsInvest');
    erreurBox.style.display = 'none';

    if (!commentaire) {
        erreurBox.textContent = 'Un commentaire est obligatoire pour régulariser un écart.';
        erreurBox.style.display = 'block';
        return;
    }
    if (resolution === 'retour_stock' && dga_inventaireEnCours) {
        erreurBox.textContent = "Un inventaire est en cours : aucun retour en stock n'est possible pour le moment.";
        erreurBox.style.display = 'block';
        return;
    }

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 47, idEcart: idEcart, resolution: resolution, commentaire: commentaire }, dataType: 'json'
    }).done(function (res) {
        if (res.status === 'success') {
            chargerExpressionsInvest();
            dga_ouvrirEcartsInvest();
        } else {
            erreurBox.textContent = res.message || 'Une erreur est survenue.';
            erreurBox.style.display = 'block';
        }
    }).fail(function (xhr) {
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