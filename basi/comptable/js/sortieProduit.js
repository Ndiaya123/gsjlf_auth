/**
 * liste-sorties-produits-scripts.bundle.js
 * Page "Sorties de produits" (profil Comptable) : liste des expressions de
 * besoin Validées pas encore entièrement servies, écran de sortie (quantité
 * SAISIE par le comptable, plafonnée à MIN(restant, stock) — 0 si stock nul).
 * Toutes les opérations de sortie sont bloquées si un inventaire est en cours.
 */

const CAISSE_CONTROLLER_URL = '/personnel/cpt_caisse_basi_controller'; // ← ajuster selon le chemin réel

const LIBELLES_STATUT_EB_SORTIE = { 1: 'Brouillon', 2: 'Soumise', 3: 'Validée', 4: 'Rejetée', 5: 'Partiellement livré', 6: 'Terminé'};

let dga_table = null;
let dga_tokenCourant = null;
let dga_inventaireEnCours = false;

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    chargerExpressions();

    document.getElementById('dgaBtnConfirmerSortie')?.addEventListener('click', dga_confirmerSortie);
    document.getElementById('dga-filtre-statut-sortie')?.addEventListener('change', chargerExpressions);
});

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

                    if (statut === 5 || statut === 6) {
                        html += `<button type="button" class="dga-btn-consulter-sortie" onclick="dga_ouvrirConsulterSortie('${d}')">Consulter sortie</button>`;
                    }

                    if (statut !== 6) {
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
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });
}

/* ────────────────────────── OUVERTURE MODALE SORTIE ────────────────── */
function dga_ouvrirSortie(token) {
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

        const lignes = (e.lignes || []).filter(l => !l.entierement_servie);

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
                            <input type="number" class="dga-inp-qte-sortie" data-idebp="${l.idEBP}" data-max="${l.max_sortable}"
                                   min="0" max="${l.max_sortable}" step="0.01" value="${stockNul ? 0 : l.max_sortable}"
                                   oninput="dga_plafonnerQuantiteSortie(this)" ${stockNul ? 'disabled' : ''}/>
                        </td>
                        <td>${stockNul ? '<span class="dga-badge-partiel" style="background:#fee2e2;color:#991b1b;">Stock nul</span>' : ''}</td>
                    </tr>
                `;
            }).join('')
            : '<tr><td colspan="6" style="text-align:center;color:#9ca3af;font-style:italic;">Toutes les lignes sont déjà entièrement servies.</td></tr>';

        new bootstrap.Modal(document.getElementById('modalSortie')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_plafonnerQuantiteSortie(input) {
    const max = parseFloat(input.dataset.max);
    const val = parseFloat(input.value);
    if (!isNaN(val) && !isNaN(max) && val > max) {
        input.value = max;
        input.style.borderColor = '#dc2626';
        setTimeout(() => { input.style.borderColor = ''; }, 800);
    }
    if (!isNaN(val) && val < 0) input.value = 0;
}

/* ────────────────────────── CONFIRMATION SORTIE ────────────────────── */
function dga_confirmerSortie() {
    if (dga_inventaireEnCours) {
        Swal.fire('Inventaire en cours', "Aucune sortie de stock n'est possible tant que l'inventaire est en cours.", 'warning');
        return;
    }

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
        quantites.push({ idEBP: input.dataset.idebp, quantite: val });
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
            data: JSON.stringify({ option: 28, token: dga_tokenCourant, quantites: quantites }),
            contentType: 'application/json',
            dataType: 'json',
        }).done(function (res) {
            btn.disabled = false;
            btn.querySelector('.dga-spinner').classList.add('hidden');

            if (res.status === 'success') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalSortie'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Sortie enregistrée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
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
    });
}

/* ────────────────────────── VOIR (suivi de l'évolution) ─────────────── */
function dga_badgeLigneSortie(statutLigne) {
    const map = {
        'Brouillon': 'dga-ligne-attente',
        'Partiellement livré': 'dga-ligne-partiel',
        'Livré': 'dga-ligne-livre',
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