/**
 * vueExecutionBudgetaire.js
 * Page DFC : statistiques globales, graphe mensuel (par mois de FIN du délai
 * prévu sur chaque ligne budgétaire) et tableau complet de toutes les
 * lignes budgétaires (Fonctionnement + Investissement confondus).
 *
 * Toutes les données viennent de dfc_calculerExecutionLignes() côté serveur
 * (options 24/25/26) — stats, graphe et tableau sont donc TOUJOURS cohérents
 * entre eux, et recalculés ensemble au changement de direction.
 */

const VEB_API = '/personnel/dfc_basi_controller';
const veb_api = {
    stats: `${VEB_API}?option=24`,
    lignes: `${VEB_API}?option=25`,
    directions: `${VEB_API}?option=26`,
};

const VEB_MOIS = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const VEB_MOIS_COURT = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];

const VEB_LIBELLES_ETAT = {
    non_execute: 'Non exécuté',
    partiellement_execute: 'Partiellement exécuté',
    entierement_execute: 'Entièrement exécuté',
    aucune_ligne: 'Aucune ligne',
};

let veb_chart = null;
let veb_table = null;
let veb_directionCourante = '';

/* ────────────────────────── Loader ────────────────────────── */
function veb_showLoader(msg = 'Chargement…') {
    $('#veb-loader').remove();
    $('body').append(`
        <div id="veb-loader">
            <div class="veb-loader-bg"></div>
            <div class="veb-loader-box">
                <svg class="veb-loader-spin" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function veb_hideLoader() { $('#veb-loader').remove(); }

/* ────────────────────────── Formatage ────────────────────────── */
function veb_fmtMontant(n) {
    n = parseFloat(n) || 0;
    return n.toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' FCFA';
}
function veb_fmtMontantCourt(n) {
    n = parseFloat(n) || 0;
    if (Math.abs(n) >= 1000000) return (n / 1000000).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' M';
    if (Math.abs(n) >= 1000) return (n / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' K';
    return n.toLocaleString('fr-FR');
}
function veb_escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function veb_ajaxErrorMessage(xhr) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
    if (!xhr || xhr.status === 0) return 'Impossible de contacter le serveur. Vérifiez votre connexion.';
    if (xhr.status === 401) return 'Votre session a expiré. Veuillez vous reconnecter.';
    return 'Impossible de contacter le serveur (code ' + xhr.status + ').';
}

/* ────────────────────────── Directions (filtre) ────────────────────────── */
function veb_chargerDirections() {
    $.ajax({ url: veb_api.directions, method: 'POST', dataType: 'json' }).done(function (res) {
        if (res.status !== 'success') return;
        const sel = document.getElementById('veb-sel-direction');
        (res.data || []).forEach(d => {
            sel.insertAdjacentHTML('beforeend', `<option value="${d.id}">${veb_escapeHtml(d.code_direction || d.nom_direction)}</option>`);
        });
    });
}

/* ────────────────────────── Chargement global ────────────────────────── */
function veb_chargerTout() {
    veb_showLoader('Chargement des données…');
    const payload = veb_directionCourante ? { direction_id: parseInt(veb_directionCourante, 10) } : {};

    $.when(
        $.ajax({ url: veb_api.stats, method: 'POST', contentType: 'application/json', data: JSON.stringify(payload), dataType: 'json' }),
        $.ajax({ url: veb_api.lignes, method: 'POST', contentType: 'application/json', data: JSON.stringify(payload), dataType: 'json' })
    ).done(function (resStats, resLignes) {
        veb_hideLoader();
        const statsData = resStats[0];
        const lignesData = resLignes[0];

        if (statsData.status === 'success') {
            veb_afficherStats(statsData.stats);
            veb_afficherGraphe(statsData.graphe || []);
        } else {
            Swal.fire('Erreur', statsData.message || 'Impossible de charger les statistiques.', 'error');
        }

        if (lignesData.status === 'success') {
            veb_afficherTableau(lignesData.data || []);
        } else {
            Swal.fire('Erreur', lignesData.message || 'Impossible de charger le tableau.', 'error');
        }
    }).fail(function (xhr) {
        veb_hideLoader();
        Swal.fire('Erreur', veb_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── Statistiques ────────────────────────── */
function veb_afficherStats(s) {
    document.getElementById('veb-stat-total').textContent   = veb_fmtMontant(s.budget_total);
    document.getElementById('veb-stat-cours').textContent   = veb_fmtMontant(s.montant_en_cours);
    document.getElementById('veb-stat-execute').textContent = veb_fmtMontant(s.montant_execute);
    document.getElementById('veb-stat-restant').textContent = veb_fmtMontant(s.montant_restant);
    document.getElementById('veb-stat-taux').innerHTML      = `${s.taux_global}<small>%</small>`;
}

/* ────────────────────────── Graphe mensuel (Chart.js) ────────────────────────── */
function veb_afficherGraphe(graphe) {
    const ctx = document.getElementById('veb-chart').getContext('2d');
    const labels = graphe.map((g, i) => VEB_MOIS_COURT[i]);
    const prevu   = graphe.map(g => g.montant_prevu);
    const execute = graphe.map(g => g.montant_execute);
    const enCours = graphe.map(g => g.montant_en_cours);

    if (veb_chart) { veb_chart.destroy(); veb_chart = null; }

    veb_chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Montant prévu',   data: prevu,   backgroundColor: 'rgba(26,122,94,.18)', borderColor: '#1a7a5e', borderWidth: 1.5, borderRadius: 4 },
                { label: 'Montant exécuté', data: execute, backgroundColor: '#059669', borderRadius: 4 },
                { label: 'En cours',        data: enCours, backgroundColor: '#d97706', borderRadius: 4 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => veb_fmtMontantCourt(v) } },
            },
            plugins: {
                legend: { display: false }, // légende déjà affichée dans l'en-tête de la carte
                tooltip: {
                    backgroundColor: '#111827',
                    padding: 12,
                    titleFont: { weight: 'bold' },
                    callbacks: {
                        title: (items) => {
                            const i = items[0].dataIndex;
                            return 'Délai prévu : ' + VEB_MOIS[i];
                        },
                        label: () => '', // on construit tout dans afterBody pour un contrôle total
                        afterBody: (items) => {
                            const i = items[0].dataIndex;
                            const g = graphe[i];
                            if (!g || g.etat_execution === 'aucune_ligne') return ['Aucune ligne prévue ce mois-ci.'];
                            return [
                                `Montant prévu : ${veb_fmtMontant(g.montant_prevu)}`,
                                `Montant exécuté : ${veb_fmtMontant(g.montant_execute)}`,
                                `Taux d'exécution : ${g.taux_execution}%`,
                                `Restant à exécuter : ${veb_fmtMontant(g.montant_restant)}`,
                                `Délai d'exécution prévu : ${VEB_MOIS[i]}`,
                                `État : ${VEB_LIBELLES_ETAT[g.etat_execution] || g.etat_execution}`,
                            ];
                        },
                    },
                },
            },
        },
    });
}

/* ────────────────────────── Tableau de suivi ────────────────────────── */
function veb_afficherTableau(lignes) {
    if (veb_table) { try { veb_table.destroy(); } catch (e) {} veb_table = null; }

    veb_table = $('#veb-table').DataTable({
        data: lignes,
        columns: [
            { data: 'designation' },
            { data: null },
            { data: 'type_budget_nom' },
            { data: 'mois_fin_delai' },
            { data: 'montant_total' },
            { data: 'montant_execute' },
            { data: 'montant_en_cours' },
            { data: 'montant_restant' },
            { data: 'taux_execution' },
            { data: 'etat_execution' },
        ],
        columnDefs: [
            { targets: 0, render: d => `<strong>${veb_escapeHtml(d || '—')}</strong>` },
            { targets: 1, render: (d, t, row) => veb_escapeHtml(row.code_direction || row.nom_direction || '—') },
            { targets: 2, render: d => veb_escapeHtml(d || '—') },
            { targets: 3, render: d => d ? veb_escapeHtml(d) : '<span style="color:#9ca3af;font-style:italic;">Non précisé</span>' },
            { targets: 4, render: d => veb_fmtMontant(d) },
            { targets: 5, render: d => `<span class="veb-montant-execute">${veb_fmtMontant(d)}</span>` },
            { targets: 6, render: d => parseFloat(d) > 0 ? veb_fmtMontant(d) : '<span style="color:#9ca3af;">—</span>' },
            { targets: 7, render: d => `<span class="veb-montant-restant">${veb_fmtMontant(d)}</span>` },
            {
                targets: 8,
                render: d => {
                    const taux = Math.min(100, Math.max(0, parseFloat(d) || 0));
                    return `<span class="veb-barre-taux"><span class="veb-barre-taux-fill" style="width:${taux}%;"></span></span>${taux}%`;
                },
            },
            {
                targets: 9,
                render: d => `<span class="veb-badge-etat veb-etat-${d}">${VEB_LIBELLES_ETAT[d] || d}</span>`,
            },
        ],
        order: [[8, 'asc']], // les moins avancées en premier — utile à la décision
        language: {
            emptyTable: 'Aucune ligne budgétaire pour le moment.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
    });
}

/* ────────────────────────── Init ────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.classList.remove('ld-booting');
    veb_chargerDirections();
    veb_chargerTout();

    document.getElementById('veb-sel-direction')?.addEventListener('change', function () {
        veb_directionCourante = this.value;
        veb_chargerTout();
    });
});