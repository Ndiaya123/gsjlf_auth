/**
 * executionBudgetComptable.js
 * Page comptable : statistiques, graphe Gantt et tableau d'exécution — scopés
 * à UN SEUL budget (reçu en paramètre d'URL, chiffré). Mêmes calculs que la
 * vue DFC et la vue chef de service (dfc_calculerExecutionLignes /
 * cs_calculerExecutionLignesBudget) — voir
 * compta_calculerExecutionLignesBudget() dans le contrôleur. Contrairement à
 * la version chef de service, AUCUNE restriction de direction : le
 * comptable, comme le DFC, peut consulter le budget de n'importe quelle
 * direction.
 *
 * N'affiche rien tant que le budget n'est pas confirmé "validé"
 * (statut Accepter/Réajuster) par le serveur.
 */

const VEB_API = '/personnel/resp_achat_basi_controller_3';
const veb_api = {
    detail: `${VEB_API}?option=53`,
    lignes: `${VEB_API}?option=54`,
};
const VEB_BUDGET_TOKEN = window.COMPTA_BUDGET_TOKEN || '';

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
function veb_fmtNombre(n) {
    n = parseFloat(n) || 0;
    return n.toLocaleString('fr-FR', { maximumFractionDigits: Number.isInteger(n) ? 0 : 2 });
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

/* ────────────────────────── Chargement global ────────────────────────── */
function veb_chargerTout() {
    if (!VEB_BUDGET_TOKEN) {
        Swal.fire('Erreur', 'Budget manquant dans le lien — impossible de charger la page.', 'error');
        return;
    }
    veb_showLoader('Chargement des données…');
    const payload = { budgetId: VEB_BUDGET_TOKEN };

    $.when(
        $.ajax({ url: veb_api.detail, method: 'POST', contentType: 'application/json', data: JSON.stringify(payload), dataType: 'json' }),
        $.ajax({ url: veb_api.lignes, method: 'POST', contentType: 'application/json', data: JSON.stringify(payload), dataType: 'json' })
    ).done(function (resDetail, resLignes) {
        veb_hideLoader();
        const detailData = resDetail[0];
        const lignesData = resLignes[0];

        if (detailData.status === 'error') {
            Swal.fire('Erreur', detailData.message || 'Impossible de charger ce budget.', 'error');
            return;
        }

        veb_afficherEnTeteBudget(detailData.budget);

        if (detailData.status === 'non_valide') {
            // Budget pas encore validé par le DFC : rien d'autre ne s'affiche.
            document.getElementById('veb-non-valide').style.display = '';
            document.getElementById('veb-contenu-valide').style.display = 'none';
            return;
        }

        document.getElementById('veb-non-valide').style.display = 'none';
        document.getElementById('veb-contenu-valide').style.display = '';

        veb_afficherStats(detailData.stats);

        if (lignesData.status === 'success') {
            const lignes = lignesData.data || [];
            // Graphe ET tableau viennent des MÊMES lignes — jamais d'écart possible entre les deux.
            veb_afficherGantt(lignes);
            veb_afficherTableau(lignes);
        } else if (lignesData.status !== 'non_valide') {
            Swal.fire('Erreur', lignesData.message || 'Impossible de charger le tableau.', 'error');
        }
    }).fail(function (xhr) {
        veb_hideLoader();
        Swal.fire('Erreur', veb_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── En-tête du budget ────────────────────────── */
function veb_afficherEnTeteBudget(budget) {
    if (!budget) return;
    const nom = `${budget.type_budget_nom || 'Budget'} ${budget.annee || ''}`.trim();
    document.getElementById('veb-nom-budget').textContent = nom;
    document.getElementById('veb-sous-titre-budget').textContent =
        `${budget.nom_direction || budget.code_direction || ''} — statut : ${budget.statut}`;
}

/* ────────────────────────── Statistiques ────────────────────────── */
function veb_afficherStats(s) {
    document.getElementById('veb-stat-total').textContent   = veb_fmtMontant(s.budget_total);
    document.getElementById('veb-stat-cours').textContent   = veb_fmtMontant(s.montant_en_cours);
    document.getElementById('veb-stat-execute').textContent = veb_fmtMontant(s.montant_execute);
    document.getElementById('veb-stat-restant').textContent = veb_fmtMontant(s.montant_restant);
    document.getElementById('veb-stat-taux').innerHTML      = `${s.taux_global}<small>%</small>`;
}

/* ────────────────────────── Graphe Gantt (Chart.js) — une barre par ligne ──────────────────────────
   Chaque ligne budgétaire devient une rangée : une barre pâle représente sa
   période prévue (mois de début → mois de fin, sur l'année sélectionnée),
   colorée selon son état (non / partiellement / entièrement exécuté), avec
   une seconde barre pleine superposée montrant la progression (taux
   d'exécution) à l'intérieur de cette même période. */

const VEB_COULEURS_ETAT = {
    non_execute: '#fecaca',
    partiellement_execute: '#fde68a',
    entierement_execute: '#6ee7b7',
};
const VEB_COULEURS_ETAT_BORDURE = {
    non_execute: '#ef4444',
    partiellement_execute: '#d97706',
    entierement_execute: '#059669',
};

function veb_afficherGantt(lignes) {
    const wrap = document.getElementById('veb-chart-wrap');
    const canvas = document.getElementById('veb-chart');

    // Seules les lignes dont le délai est exploitable (mois de début ET de
    // fin reconnus) peuvent être placées sur l'axe du temps.
    // Chart.js place le premier élément du tableau en HAUT d'une barre
    // horizontale : tri décroissant pour avoir le plus grand taux en haut,
    // le plus petit en bas.
    const plottables = lignes
        .filter(l => l.mois_debut_delai && l.mois_fin_delai)
        .sort((a, b) => (b.taux_execution || 0) - (a.taux_execution || 0));

    if (veb_chart) { veb_chart.destroy(); veb_chart = null; }

    if (!plottables.length) {
        canvas.style.display = 'none';
        if (!document.getElementById('veb-chart-vide')) {
            wrap.insertAdjacentHTML('beforeend', '<div id="veb-chart-vide">Aucune ligne avec un délai d\'exécution exploitable pour cette sélection.</div>');
        }
        return;
    }
    document.getElementById('veb-chart-vide')?.remove();
    canvas.style.display = '';

    const debutIdx = plottables.map(l => VEB_MOIS.indexOf(l.mois_debut_delai));
    const finIdx   = plottables.map(l => VEB_MOIS.indexOf(l.mois_fin_delai));
    // Taux d'exécution affiché au lieu du nom — la désignation complète reste
    // disponible au survol, dans le titre de l'info-bulle.
    const labels   = plottables.map(l => `${Math.min(100, Math.max(0, Math.round(l.taux_execution || 0)))}%`);

    // Barre "période prévue" : du mois de début au mois de fin (inclus → +1).
    // C'est le SEUL dataset Chart.js du graphe — la barre de progression
    // n'est plus un second dataset (Chart.js décale/empile plusieurs jeux de
    // données de façon peu fiable, surtout quand l'un est quasi invisible) :
    // elle est dessinée à la main, directement sur les pixels réels de cette
    // barre, dans le plugin ci-dessous. Aucune ambiguïté possible.
    const dataPeriode = plottables.map((l, i) => [debutIdx[i], finIdx[i] + 1]);
    const tauxFraction = plottables.map(l => Math.min(100, Math.max(0, l.taux_execution || 0)) / 100);
    const couleursPeriode = plottables.map(l => VEB_COULEURS_ETAT[l.etat_execution] || '#e5e7eb');
    const bordurePeriode  = plottables.map(l => VEB_COULEURS_ETAT_BORDURE[l.etat_execution] || '#9ca3af');

    // Hauteur dynamique : une rangée généreuse par ligne, pour rester lisible
    // même avec beaucoup de lignes (défilement vertical dans la carte).
    const hauteurParLigne = 26;
    canvas.style.height = Math.max(320, plottables.length * hauteurParLigne) + 'px';

    // Dessine chaque nom de mois centré entre deux séparateurs, plutôt que
    // de laisser Chart.js aligner l'étiquette sur un tick (donc une limite).
    const veb_moisLabelPlugin = {
        id: 'veb_moisLabelPlugin',
        afterDraw(chart) {
            const { ctx: c, chartArea, scales: { x } } = chart;
            c.save();
            c.font = '11px Poppins, sans-serif';
            c.fillStyle = '#6b7280';
            c.textAlign = 'center';
            c.textBaseline = 'top';
            for (let i = 0; i < 12; i++) {
                const xCentre = (x.getPixelForValue(i) + x.getPixelForValue(i + 1)) / 2;
                c.fillText(VEB_MOIS_COURT[i], xCentre, chartArea.bottom + 6);
            }
            c.restore();
        },
    };

    // Dessine la barre de progression (verte) directement sur les pixels
    // RÉELS de chaque barre "Période prévue" déjà tracée par Chart.js — plus
    // fiable qu'un second dataset, dont le positionnement par rapport au
    // premier n'est pas garanti dans tous les cas (barres très étroites en
    // particulier).
    const veb_progressionPlugin = {
        id: 'veb_progressionPlugin',
        afterDatasetsDraw(chart) {
            const meta = chart.getDatasetMeta(0);
            const { ctx: c } = chart;
            c.save();
            meta.data.forEach((bar, i) => {
                const taux = tauxFraction[i];
                if (taux <= 0) return; // rien à dessiner à 0 %
                // min/max plutôt que de supposer laquelle de x/base correspond au
                // début ou à la fin — robuste quelle que soit la convention de
                // cette version de Chart.js pour une barre horizontale flottante.
                const xDebut = Math.min(bar.x, bar.base);
                const xFin   = Math.max(bar.x, bar.base);
                const { y, height } = bar;
                const largeur = xFin - xDebut;
                const epaisseur = height * 0.46; // plus fine que la barre de fond, centrée dedans
                c.fillStyle = '#059669';
                c.beginPath();
                if (c.roundRect) {
                    c.roundRect(xDebut, y - epaisseur / 2, Math.max(2, largeur * taux), epaisseur, 3);
                } else {
                    c.rect(xDebut, y - epaisseur / 2, Math.max(2, largeur * taux), epaisseur);
                }
                c.fill();
            });
            c.restore();
        },
    };

    const ctx = canvas.getContext('2d');
    veb_chart = new Chart(ctx, {
        type: 'bar',
        plugins: [veb_moisLabelPlugin, veb_progressionPlugin],
        data: {
            labels,
            datasets: [
                {
                    label: 'Période prévue',
                    data: dataPeriode,
                    backgroundColor: couleursPeriode,
                    borderColor: bordurePeriode,
                    borderWidth: 2.5, // volontairement marqué : doit rester visible même avec une progression faible
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8,
                },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { bottom: 18 } }, // place pour les étiquettes de mois dessinées à part
            scales: {
                x: {
                    min: 0, max: 12, stacked: false,
                    // Séparateurs conservés aux VRAIES limites de mois (0,1,2…12) —
                    // c'est là qu'ils doivent être pour border les barres correctement.
                    // Les noms de mois, eux, sont dessinés à part (plugin ci-dessous),
                    // centrés entre deux séparateurs, pour ne jamais tomber sur une limite.
                    ticks: { display: false },
                    grid: { color: '#e5e7eb', drawTicks: false },
                },
                y: { stacked: false, ticks: { font: { size: 11 } } },
            },
            plugins: {
                legend: { display: false }, // légende déjà affichée dans l'en-tête de la carte
                tooltip: {
                    backgroundColor: '#111827',
                    padding: 12,
                    titleFont: { weight: 'bold' },
                    callbacks: {
                        title: (items) => plottables[items[0].dataIndex].designation || '—',
                        label: () => '',
                        afterBody: (items) => {
                            const l = plottables[items[0].dataIndex];
                            const finL = (l.mois_fin_delai || '').trim();
                            const debutL = (l.mois_debut_delai || '').trim();
                            const delai = debutL && debutL.toLowerCase() !== finL.toLowerCase()
                                ? `${debutL} → ${finL}`
                                : finL;
                            return [
                                `Montant prévu : ${veb_fmtMontant(l.montant_total)}`,
                                `Montant exécuté : ${veb_fmtMontant(l.montant_execute)}`,
                                `Taux d'exécution : ${l.taux_execution}%`,
                                `Restant à exécuter : ${veb_fmtMontant(l.montant_restant)}`,
                                `Délai d'exécution prévu : ${delai}`,
                                `État : ${VEB_LIBELLES_ETAT[l.etat_execution] || l.etat_execution}`,
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
            { data: 'type_budget_nom' },
            { data: 'mois_fin_delai' },
            { data: 'montant_total' },
            { data: 'montant_execute' },
            { data: 'montant_en_cours' },
            { data: 'montant_restant' },
            { data: 'taux_execution' },
            { data: 'quantite_prevue' },
            { data: 'quantite_commandee_totale' },
            { data: 'quantite_livree_totale' },
            { data: 'etat_execution' },
        ],
        columnDefs: [
            { targets: 0, render: d => `<strong>${veb_escapeHtml(d || '—')}</strong>` },
            { targets: 1, render: d => veb_escapeHtml(d || '—') },
            {
                targets: 2,
                render: (d, t, row) => {
                    if (!d) return '<span style="color:#9ca3af;font-style:italic;">Non précisé</span>';
                    const fin = String(d).trim();
                    const debut = row.mois_debut_delai ? String(row.mois_debut_delai).trim() : '';
                    // Même mois des deux côtés ("Janvier-Janvier") → un seul affiché.
                    return debut && debut.toLowerCase() !== fin.toLowerCase()
                        ? `${veb_escapeHtml(debut)} → ${veb_escapeHtml(fin)}`
                        : veb_escapeHtml(fin);
                },
            },
            { targets: 3, render: d => veb_fmtMontant(d) },
            { targets: 4, render: d => `<span class="veb-montant-execute">${veb_fmtMontant(d)}</span>` },
            { targets: 5, render: d => parseFloat(d) > 0 ? veb_fmtMontant(d) : '<span style="color:#9ca3af;">—</span>' },
            { targets: 6, render: d => `<span class="veb-montant-restant">${veb_fmtMontant(d)}</span>` },
            {
                targets: 7,
                render: d => {
                    const taux = Math.min(100, Math.max(0, parseFloat(d) || 0));
                    return `<span class="veb-barre-taux"><span class="veb-barre-taux-fill" style="width:${taux}%;"></span></span>${taux}%`;
                },
            },
            // Quantités : uniquement pertinentes pour une ligne de type "Produit" — null (côté serveur) pour "Autre".
            { targets: 8, render: d => d === null ? '<span style="color:#d1d5db;">—</span>' : `<span class="veb-qte-cell">${veb_fmtNombre(d)}</span>` },
            { targets: 9, render: d => d === null ? '<span style="color:#d1d5db;">—</span>' : `<span class="veb-qte-cell">${veb_fmtNombre(d)}</span>` },
            {
                targets: 10,
                render: (d, t, row) => {
                    if (d === null) return '<span style="color:#d1d5db;">—</span>';
                    const commandee = parseFloat(row.quantite_commandee_totale) || 0;
                    const livree = parseFloat(d) || 0;
                    const cls = commandee > 0 && livree >= commandee ? 'veb-qte-livree-ok' : (livree > 0 ? 'veb-qte-livree-partiel' : '');
                    return `<span class="veb-qte-cell ${cls}">${veb_fmtNombre(livree)}</span>`;
                },
            },
            {
                targets: 11,
                render: d => `<span class="veb-badge-etat veb-etat-${d}">${VEB_LIBELLES_ETAT[d] || d}</span>`,
            },
        ],
        order: [[7, 'asc']], // les moins avancées en premier — utile à la décision
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
    veb_chargerTout();
});