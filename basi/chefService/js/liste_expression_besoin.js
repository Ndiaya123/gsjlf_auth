/**
 * chef-direction-expression-besoin-scripts.bundle.js
 * Page "Expressions de besoin — Ma direction" (profil Chef de direction) :
 * liste filtrable par intervalle d'années, Valider (quantite_reelle par
 * ligne, jamais > quantité demandée) / Rejeter (motif obligatoire, e-mail
 * automatique au créateur), Consulter en lecture seule.
 */

const CHEF_DIR_EB_CONTROLLER_URL = '/personnel/chef_service_basi_controller_1'; // ← ajuster selon le chemin réel
const ANNEE_MIN_EB = 2026;

const LIBELLES_STATUT_EB = { 1: 'Brouillon', 2: 'Soumise', 3: 'Validée', 4: 'Rejetée', 5: 'Partiellement livré', 6: 'Terminé' };

let dga_table = null;
let dga_tokenCourant = null;

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    initSelectsAnnees();
    chargerExpressions();

    document.getElementById('dga-btn-appliquer-filtres')?.addEventListener('click', chargerExpressions);
    document.getElementById('dga-filtre-statut')?.addEventListener('change', chargerExpressions);
    document.getElementById('dga-btn-reset-filtres')?.addEventListener('click', function () {
        const anneeCourante = new Date().getFullYear();
        document.getElementById('dga-annee-debut').value = '';
        document.getElementById('dga-annee-fin').value = anneeCourante;
        document.getElementById('dga-filtre-statut').value = '2';
        chargerExpressions();
    });

    document.getElementById('dgaBtnConfirmerValider')?.addEventListener('click', dga_confirmerValidation);
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
    const statutVal     = document.getElementById('dga-filtre-statut').value;

    if (anneeDebutVal !== '' && anneeFinVal !== '' && parseInt(anneeDebutVal) > parseInt(anneeFinVal)) {
        Swal.fire('Intervalle invalide', "« Année de début » ne peut pas être supérieure à « Année de fin ».", 'warning');
        return;
    }

    dga_showLoader('Chargement des expressions de besoin…');

    $.ajax({
        url: CHEF_DIR_EB_CONTROLLER_URL,
        method: 'POST',
        data: { option: 1, anneeDebut: anneeDebutVal, anneeFin: anneeFinVal, statut: statutVal },
        dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les expressions de besoin.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
        document.getElementById('dga-stat-nombre').textContent = res.nombre_total ?? 0;

        const stats = res.stats || {};
        document.getElementById('dga-stat-soumise').textContent = stats['2'] ?? 0;
        document.getElementById('dga-stat-validee').textContent = stats['3'] ?? 0;
        document.getElementById('dga-stat-rejetee').textContent = stats['4'] ?? 0;
        document.getElementById('dga-stat-partiel').textContent = stats['5'] ?? 0;
        document.getElementById('dga-stat-terminee').textContent = stats['6'] ?? 0;
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
            { targets: 4, render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT_EB[d] || d}</span>` },
            {
                targets: 5,
                render: (d, t, row) => {
                    const statut = parseInt(row.idStatut);
                    let html = '';
                    if (statut === 2) {
                        html += `<button type="button" class="dga-btn-valider" onclick="dga_ouvrirValidation('${d}')">Valider</button>`;
                        html += `<button type="button" class="dga-btn-rejeter" onclick="dga_confirmerRejet('${d}')">Rejeter</button>`;
                        html += `<button type="button" class="dga-btn-consulter" onclick="dga_ouvrirConsulter('${d}')">Consulter</button>`;
                    } else if (statut === 5 || statut === 6) {
                        html += `<button type="button" class="dga-btn-consulter" onclick="dga_ouvrirConsulter('${d}')">Consulter</button>`;
                        html += `<button type="button" class="dga-btn-consulter dga-btn-sorties-info" onclick="dga_ouvrirInfoSorties('${d}')">Informations sur les sorties</button>`;
                    } else {
                        html += `<button type="button" class="dga-btn-consulter" onclick="dga_ouvrirConsulter('${d}')">Consulter</button>`;
                    }
                    return html;
                },
            },
        ],
        order: [[2, 'desc']],
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

/* ────────────────────────── VALIDATION ─────────────────────────────── */
function dga_ouvrirValidation(token) {
    dga_showLoader('Chargement…');
    $.ajax({
        url: CHEF_DIR_EB_CONTROLLER_URL, method: 'POST', data: { option: 2, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        dga_tokenCourant = token;
        const e = res.expression;
        document.getElementById('validerModalTitre').textContent = 'Valider — ' + e.nom_expression;
        document.getElementById('dgaErreurValider').style.display = 'none';

        document.getElementById('dgaCorpsValider').innerHTML = (e.produits || []).map(function (p) {
            return `
                <tr>
                    <td>${dga_escapeHtml(p.designation)}</td>
                    <td>${dga_escapeHtml(p.quantite)}</td>
                    <td>
                        <input type="number" class="dga-inp-qte-reelle" data-idebp="${p.idEBP}" data-max="${p.quantite}"
                               min="0" max="${p.quantite}" step="0.01" value="${p.quantite}"
                               oninput="dga_plafonnerQuantiteReelle(this)"/>
                    </td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="3" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        new bootstrap.Modal(document.getElementById('modalValider')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_plafonnerQuantiteReelle(input) {
    const max = parseFloat(input.dataset.max);
    const val = parseFloat(input.value);
    if (!isNaN(val) && !isNaN(max) && val > max) {
        input.value = max;
        input.style.borderColor = '#dc2626';
        setTimeout(() => { input.style.borderColor = ''; }, 800);
    }
}

function dga_confirmerValidation() {
    const erreurBox = document.getElementById('dgaErreurValider');
    erreurBox.style.display = 'none';

    const lignes = [];
    let depassement = false;
    document.querySelectorAll('.dga-inp-qte-reelle').forEach(function (input) {
        const val = parseFloat(input.value) || 0;
        const max = parseFloat(input.dataset.max) || 0;
        if (val > max + 0.001) depassement = true;
        lignes.push({ idEBP: input.dataset.idebp, quantite_reelle: val });
    });

    if (depassement) {
        erreurBox.textContent = 'La quantité réelle ne peut jamais dépasser la quantité demandée.';
        erreurBox.style.display = 'block';
        return;
    }

    const btn = document.getElementById('dgaBtnConfirmerValider');
    btn.disabled = true;
    btn.querySelector('.dga-spinner').classList.remove('hidden');

    $.ajax({
        url: CHEF_DIR_EB_CONTROLLER_URL,
        method: 'POST',
        data: JSON.stringify({ option: 3, token: dga_tokenCourant, lignes: lignes }),
        contentType: 'application/json',
        dataType: 'json',
    }).done(function (res) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');

        if (res.status === 'success') {
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalValider'));
            if (modalInstance) modalInstance.hide();
            Swal.fire({ title: 'Succès', text: res.message || 'Expression de besoin validée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
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

/* ────────────────────────── REJET ──────────────────────────────────── */
function dga_confirmerRejet(token) {
    Swal.fire({
        title: "Rejeter l'expression de besoin",
        input: 'textarea',
        inputLabel: 'Motif du rejet *',
        inputPlaceholder: 'Précisez le motif du rejet…',
        showCancelButton: true,
        confirmButtonText: 'Rejeter',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        inputValidator: (value) => {
            if (!value || !value.trim()) return 'Le motif du rejet est obligatoire.';
        },
    }).then(function (result) {
        if (!result.isConfirmed) return;

        dga_showLoader('Rejet en cours…');
        $.ajax({
            url: CHEF_DIR_EB_CONTROLLER_URL, method: 'POST', data: { option: 4, token: token, motif: result.value.trim() }, dataType: 'json'
        }).done(function (res) {
            dga_hideLoader();
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message || 'Expression de besoin rejetée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerExpressions();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            dga_hideLoader();
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ────────────────────────── CONSULTER ──────────────────────────────── */
function dga_ouvrirConsulter(token) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: CHEF_DIR_EB_CONTROLLER_URL, method: 'POST', data: { option: 2, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const e = res.expression;
        document.getElementById('consulterModalTitre').textContent = 'Détail — ' + e.nom_expression;

        const motifRejetHtml = (parseInt(e.idStatut) === 4 && e.motif_rejet)
            ? `<div style="margin-bottom:1rem;padding:.7rem .9rem;border-radius:9px;background:#fef2f2;border:1.5px solid #fecaca;color:#991b1b;font-size:.82rem;font-weight:600;">
                   <strong>Motif du rejet :</strong> ${dga_escapeHtml(e.motif_rejet)}
               </div>`
            : '';

        const badgeLigne = (statutLigne) => {
            const map = {
                'Brouillon ': 'background:#fef3c7;color:#92400e;',
                'Partiellement livré': 'background:#dbeafe;color:#1d4ed8;',
                'Livré': 'background:#d1fae5;color:#047857;',
            };
            const style = map[statutLigne] || map['En attente'];
            return `<span style="display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:99px;font-size:.66rem;font-weight:800;text-transform:uppercase;${style}">${statutLigne}</span>`;
        };

        const lignes = (e.produits || []).map(function (p) {
            return `<tr>
                <td>${dga_escapeHtml(p.designation)}</td>
                <td>${dga_escapeHtml(p.quantite)}</td>
                <td>${p.quantite_reelle !== null && p.quantite_reelle !== undefined ? dga_escapeHtml(p.quantite_reelle) : '—'}</td>
                <td>${p.quantite_sortie !== null && p.quantite_sortie !== undefined ? dga_escapeHtml(p.quantite_sortie) : '—'}</td>
                <td>${p.quantite_restante !== null && p.quantite_restante !== undefined ? dga_escapeHtml(p.quantite_restante) : '—'}</td>
                <td>${badgeLigne(p.statut_ligne || 'En attente')}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        const historiqueRows = (e.historique || []).map(function (h) {
            return `
                <li style="padding:.4rem 0;font-size:.8rem;color:#374151;border-bottom:1px dashed #f3f4f6;">
                    <strong>${dga_fmtDateHeure(h.dateEnregistrement)}</strong>
                    — ${LIBELLES_STATUT_EB[h.idStatut] || h.idStatut}
                    ${h.motif ? `<span style="color:#9ca3af;font-size:.76rem;"> (${dga_escapeHtml(h.motif)})</span>` : ''}
                </li>
            `;
        }).join('') || '<li style="font-size:.8rem;color:#9ca3af;font-style:italic;">Aucun historique.</li>';

        document.getElementById('dgaContenuConsulter').innerHTML = `
            <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                <strong>Demandeur :</strong> ${dga_escapeHtml(e.demandeur)}<br/>
                <strong>Date de création :</strong> ${dga_fmtDate(e.date_creation)}<br/>
                <strong>Statut :</strong> <span class="dga-badge-statut dga-statut-${e.idStatut}">${LIBELLES_STATUT_EB[e.idStatut] || e.idStatut}</span>
            </p>
            ${motifRejetHtml}
            <table class="dga-table-produits">
                <thead><tr><th>Désignation</th><th>Demandée</th><th>Validée</th><th>Sortie</th><th>Restante</th><th>Statut</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
            <h4 style="font-size:.8rem;font-weight:800;color:#111827;margin:1.2rem 0 .5rem;">Historique</h4>
            <ul style="list-style:none;padding:0;margin:0;">${historiqueRows}</ul>
        `;

        new bootstrap.Modal(document.getElementById('modalConsulterEB')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── INFORMATIONS SUR LES SORTIES ───────────── */
function dga_ouvrirInfoSorties(token) {
    dga_showLoader('Chargement des informations…');
    $.ajax({
        url: CHEF_DIR_EB_CONTROLLER_URL, method: 'POST', data: { option: 5, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les informations sur les sorties.', 'error');
            return;
        }

        const e = res.expression;
        document.getElementById('infoSortiesModalTitre').textContent = 'Sorties — ' + e.nom_expression;

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

        document.getElementById('dgaContenuInfoSorties').innerHTML = `
            <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                <strong>Demandeur :</strong> ${dga_escapeHtml(e.demandeur)}<br/>
                <strong>Date de création :</strong> ${dga_fmtDate(e.date_creation)}
            </p>
            ${sectionsHtml}
        `;

        new bootstrap.Modal(document.getElementById('modalInfoSorties')).show();
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