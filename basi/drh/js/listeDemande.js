// ════════════════════════════════════════════════════════════════════════
// LISTE DES DEMANDES (Achat / Paiement) — DRH
//
// Par défaut : uniquement les demandes en cours (non épuisées).
//   - Achat    : épuisée quand qte_commandee == qte_restante
//   - Paiement : épuisée quand montant_total (calculé) == mnt_paye
//
// Filtres :
//   - "Toutes les demandes en cours"          → type=toutes,   afficherEpuisees=0
//   - "Toutes les lignes demandes d'achat"    → type=achat,    afficherEpuisees=1
//   - "Toutes les lignes demandes de paiement"→ type=paiement, afficherEpuisees=1
// ════════════════════════════════════════════════════════════════════════

const LD_API = '/drh_basi_controller'; // ← ajuster selon le chemin réel du contrôleur
const ld_api = {
    demandes: `${LD_API}?option=8`,
};

let ld_table    = null;
let ld_filtre   = 'toutes'; // 'toutes' | 'demande_achat' | 'demande_paiement'

// ─── Réseau ───────────────────────────────────────────────────────────────────
function ld_expired() {
    Swal.fire({ icon:'warning', title:'Session expirée', text:'Redirection…',
        timer:2500, showConfirmButton:false,
        didClose:()=>{ window.location.href = '/signin'; }
    });
}
async function ld_post(url, body = {}, timeout = 12000) {
    const ctrl = new AbortController();
    const t    = setTimeout(() => ctrl.abort(), timeout);
    try {
        const r = await fetch(url, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify(body),
            signal : ctrl.signal,
        });
        clearTimeout(t);
        const d = await r.json();
        if (d?.code === 'sessionExpired') { ld_expired(); return null; }
        return d;
    } catch (e) {
        clearTimeout(t);
        if (e.name === 'AbortError') throw new Error('Délai dépassé.');
        throw e;
    }
}

// ─── Loader ───────────────────────────────────────────────────────────────────
function ld_showLoader(msg = 'Chargement…') {
    $('#ld-loader').remove();
    $('body').append(`
        <div id="ld-loader">
            <div class="ld-loader-bg"></div>
            <div class="ld-loader-box">
                <svg class="ld-loader-spin" viewBox="0 0 50 50">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
                </svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function ld_hideLoader() { $('#ld-loader').remove(); }

// ─── Formatage ────────────────────────────────────────────────────────────────
const ld_fmtNum  = v => new Intl.NumberFormat('fr-FR').format(Number(v || 0)) + '\u00a0FCFA';
const ld_fmtDate = d => d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—';

function ld_badgeType(idTypeDemande) {
    const isAchat = parseInt(idTypeDemande) === 1;
    return isAchat
        ? '<span class="ld-badge ld-badge-achat">Achat</span>'
        : '<span class="ld-badge ld-badge-paiement">Paiement</span>';
}

// ─── Chargement des demandes selon le filtre actif ────────────────────────────
async function ld_charger(filtre) {
    ld_filtre = filtre;

    // 'toutes' = uniquement les demandes en cours (comportement par défaut).
    // 'demande_achat' / 'demande_paiement' = toutes les lignes de ce type, épuisées incluses.
    const type             = filtre === 'toutes' ? 'toutes' : filtre;
    const afficherEpuisees = filtre !== 'toutes';

    // Mettre à jour l'état visuel des boutons de filtre
    $('.ld-filter-btn').removeClass('active');
    $(`.ld-filter-btn[data-filtre="${filtre}"]`).addClass('active');

    ld_showLoader('Chargement des demandes…');
    const d = await ld_post(ld_api.demandes, { type, afficherEpuisees: afficherEpuisees ? '1' : '0' });
    ld_hideLoader();

    if (!d?.status || d.status !== 'success') {
        Swal.fire('Erreur', d?.message || 'Impossible de charger les demandes.', 'error');
        return;
    }

    ld_renderTable(d.data || []);
}

// ─── Table des demandes ────────────────────────────────────────────────────────
function ld_renderTable(demandes) {
    if (ld_table) { try { ld_table.destroy(); } catch(e){} ld_table = null; }

    ld_table = $('#ld-table-demandes').DataTable({
        data      : demandes,
        responsive: true,
        pageLength: 25,
        order     : [[3, 'desc']],
        dom       : '<"ld-dt-top"lf>rt<"ld-dt-bottom"ip>',
        language  : {
            emptyTable : 'Aucune demande à afficher',
            info       : '_START_–_END_ / _TOTAL_ demandes',
            infoEmpty  : 'Aucune demande',
            lengthMenu : 'Afficher _MENU_ demandes',
            search     : '_INPUT_', searchPlaceholder: 'Rechercher…',
            zeroRecords: 'Aucun résultat',
            paginate   : { first:'«', last:'»', next:'›', previous:'‹' },
        },
        columns: [
            {
                data: 'idD', title: 'N° demande', width: '100px',
                render: v => `<strong>#${v}</strong>`,
            },
            {
                data: 'idTypeDemande', title: 'Type', width: '110px',
                render: v => ld_badgeType(v),
            },
            {
                data: 'demandeur', title: 'Demandeur', defaultContent: '—',
            },
            {
                data: 'date_creation', title: 'Date de la demande', width: '140px',
                render: v => ld_fmtDate(v),
            },
            {
                data: 'montant_estime', title: 'Montant estimé', className: 'dt-right', width: '150px',
                render: v => `<span class="ld-amt">${ld_fmtNum(v)}</span>`,
            },
            {
                data: null, title: 'Action', orderable: false, searchable: false, width: '100px',
                render: (d, t, row) => {
                    if (row.epuisee) {
                        return '<span style="color:#d1d5db;font-size:.75rem">—</span>';
                    }
                    return `<a href="/drh_demande_voir_lignes/${row.tmp}"
                               class="ld-btn-voir" title="Voir le détail de la demande">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Voir</a>`;
                },
            },
        ],
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });
}

// ─── Événements ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    ld_charger('toutes');

    document.getElementById('ld-btn-toutes')?.addEventListener('click', () => ld_charger('toutes'));
    document.getElementById('ld-btn-achat')?.addEventListener('click', () => ld_charger('demande_achat'));
    document.getElementById('ld-btn-paiement')?.addEventListener('click', () => ld_charger('demande_paiement'));
});