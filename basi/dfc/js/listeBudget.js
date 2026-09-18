// ═══════════════════════════════════════════════════════════════════════
// LISTE BUDGETS — vue DFC
// Onglets : tous | en attente | validé | rejeté | réajuster
// Statuts DB :
//   En attente → statut='Valider',   idStatut=6
//   Validé     → statut='Accepter',  idStatut=7
//   Rejeté     → statut='Rejeter',   idStatut=5
//   Réajuster  → statut='Réajuster', idStatut=9
// Actions :
//   En attente  → Voir · Valider · Rejeter
//   Validé      → Voir · Réajuster
//   Rejeté      → Voir seulement
//   Réajuster   → Voir seulement
// Historique : INSERT complet (copie colonnes budget + motif + dateEnregistrement)
// ═══════════════════════════════════════════════════════════════════════

const DFC_API = '/dfc_basi_controller';
const dfc_api = {
    tous      : `${DFC_API}?option=1`,
    pending   : `${DFC_API}?option=2`,
    validated : `${DFC_API}?option=3`,
    rejected  : `${DFC_API}?option=4`,
    reajuster : `${DFC_API}?option=5`,
    valider   : `${DFC_API}?option=6`,
    rejeter   : `${DFC_API}?option=7`,
    reajusterAction:`${DFC_API}?option=8`,
    motif     : `${DFC_API}?option=9`,
    annees    : `${DFC_API}?option=10`,
    stats     : `${DFC_API}?option=11`,
};

// ─── État global ──────────────────────────────────────────────────────
let dfc_currentTab = 'tous';
let dfc_table      = null;
let dfc_anneeDebut = null;
let dfc_anneeFin   = new Date().getFullYear();

let dfc_stats = {
    tous      : {count:0, plafond:0},
    encours   : {count:0, plafond:0},
    accepter  : {count:0, plafond:0},
    rejeter   : {count:0, plafond:0},
    reajuster : {count:0, plafond:0},
};

// ─── Réseau ───────────────────────────────────────────────────────────
function dfc_expired() {
    dfc_hideLoader();
    Swal.fire({ icon:'warning', title:'Session expirée', text:'Redirection en cours…',
        timer:2500, showConfirmButton:false,
        didClose:()=>{ window.location.href='/signin'; }});
}
async function dfc_post(url, body={}, timeout=10000) {
    const ctrl=new AbortController(), t=setTimeout(()=>ctrl.abort(),timeout);
    try {
        const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify(body),signal:ctrl.signal});
        clearTimeout(t); return r;
    } catch(e){ clearTimeout(t); if(e.name==='AbortError') throw new Error('Délai dépassé.'); throw e; }
}
async function dfc_parse(r) {
    const d=await r.json();
    if(d?.code==='sessionExpired'){ dfc_expired(); return null; }
    return d;
}

// ─── Loader ───────────────────────────────────────────────────────────
function dfc_showLoader(msg='Chargement…') {
    $('#dfc-loader').remove();
    $('body').append(
        '<div id="dfc-loader">'
        +'<div class="loader-backdrop"></div>'
        +'<div class="loader-box">'
        +'<svg class="loader-spinner" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>'
        +'<p>'+msg+'</p>'
        +'</div></div>'
    );
}
function dfc_hideLoader(){ $('#dfc-loader').remove(); }

// ─── Formatage ────────────────────────────────────────────────────────
const dfc_fmtNum  = v => new Intl.NumberFormat('fr-FR').format(Number(v||0))+'\u00a0FCFA';
const dfc_fmtDate = d => d ? new Date(d).toLocaleDateString('fr-FR') : '—';
const dfc_fmtK    = v => {
    if(v>=1e9) return (v/1e9).toFixed(1)+' Md';
    if(v>=1e6) return (v/1e6).toFixed(1)+' M';
    if(v>=1e3) return (v/1e3).toFixed(0)+' K';
    return new Intl.NumberFormat('fr-FR').format(v);
};

// ─── Badge statut ─────────────────────────────────────────────────────
function dfc_badge(s) {
    const m = {
        'Valider'   :'<span class="status-badge status-validated">En attente</span>',
        'Accepter'  :'<span class="status-badge status-accepted">Validé</span>',
        'Rejeter'   :'<span class="status-badge status-rejected">Rejeté</span>',
        'Réajuster' :'<span class="status-badge status-reajuster">Réajuster</span>',
    };
    return m[s] || `<span class="status-badge status-pending">${s}</span>`;
}

// ─── Boutons d'action ─────────────────────────────────────────────────
function dfc_getActions(row) {
    const {id, statut: s,tmp,annee} = row;

    const annee_tmp = new Intl.DateTimeFormat("fr-FR", {
        timeZone: "Africa/Dakar",
        year: "numeric",
    }).format(new Date());


    const btnVoir = `
        <button onclick="dfc_voirBudget('${tmp}')" class="btn-action btn-action-primary" title="Voir les lignes">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                       -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </button>`;

    let extra = '';

    if (s === 'Valider') {
        extra = `
        <button onclick="dfc_doValider(${id})" class="btn-action btn-action-success" title="Valider">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </button>
        <button onclick="dfc_doRejeter(${id})" class="btn-action btn-action-danger" title="Rejeter">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>`;
    } else if (s === 'Accepter' && (annee >= annee_tmp)) {


        extra = `
        <button onclick="dfc_doReajuster(${id})" class="btn-action btn-action-warning" title="Réajuster">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9
                       m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
        </button>`;
    }
    // Rejeter et Réajuster → Voir uniquement (extra reste vide)

    return `<div class="flex justify-end gap-2">${btnVoir}${extra}</div>`;
}

// ─── Corps de la requête avec filtre années ───────────────────────────
function dfc_anneeBody(extra={}) {
    const b = {...extra};
    if (dfc_anneeDebut) b.anneeDebut = parseInt(dfc_anneeDebut);
    if (dfc_anneeFin)   b.anneeFin   = parseInt(dfc_anneeFin);
    return b;
}

// ─── URL de l'onglet courant ──────────────────────────────────────────
function dfc_tabUrl() {
    const map = {
        tous      : dfc_api.tous,
        encours   : dfc_api.pending,
        accepter  : dfc_api.validated,
        rejeter   : dfc_api.rejected,
        reajuster : dfc_api.reajuster,
    };
    return map[dfc_currentTab] || dfc_api.tous;
}

// ─── DataTable ────────────────────────────────────────────────────────
function dfc_initTable() {
    if (dfc_table) { try { dfc_table.destroy(); } catch(e){} dfc_table=null; }
    if (!document.getElementById('budgetTable')) return;

    dfc_table = $('#budgetTable').DataTable({
        responsive:true, paging:true, pageLength:25, lengthChange:true,
        searching:true, ordering:true, info:true, autoWidth:false,
        language:{
            emptyTable   :'Aucun budget disponible',
            info         :'Affichage de _START_ à _END_ sur _TOTAL_ budgets',
            infoEmpty    :'Aucun budget',
            infoFiltered :'(filtré sur _MAX_)',
            lengthMenu   :'Afficher _MENU_ lignes',
            loadingRecords:'Chargement…',
            processing   :'Traitement…',
            search       :'_INPUT_',
            searchPlaceholder:'Rechercher...',
            zeroRecords  :'Aucun budget correspondant',
            paginate     :{ first:'«', last:'»', next:'›', previous:'‹' }
        },
        dom:'<"bud-dt-top"lf>rt<"bud-dt-bottom"ip>',
        data:[],
        columns:[
            { data:'annee',        width:'70px' },
            { data:'type_budget_nom', defaultContent:'—' },
            { data:'plafond', render: v => {
                    if (!v) return '<span class="bud-cell-muted">N/A</span>';
                    return `<span class="bud-cell-amount">${dfc_fmtNum(v)}</span>`;
                }},
            { data:'date_creation', render: dfc_fmtDate, width:'90px' },
            { data:'statut', render: dfc_badge, width:'110px' },
            { data:null, render:(d,t,row)=>dfc_getActions(row),
                orderable:false, searchable:false, width:'130px' },
        ],
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });
}

// ─── Chargement données + stats ───────────────────────────────────────
async function dfc_load() {
    dfc_showLoader('Chargement des budgets…');
    try {
        const body = dfc_anneeBody();

        // Stats (case 110) + données tableau (case selon onglet) en parallèle
        const [rStats, rData] = await Promise.all([
            dfc_post(dfc_api.stats, body),
            dfc_post(dfc_tabUrl(),  body),
        ]);

        const [dStats, dData] = await Promise.all([
            dfc_parse(rStats),
            dfc_parse(rData),
        ]);
        if (!dStats || !dData) return;

        // ── Stats ────────────────────────────────────────────────────
        if (dStats.success) {
            dfc_stats = dStats.data;
            dfc_updateCounters();
            dfc_updateStatsCards();
        }

        // ── Table ────────────────────────────────────────────────────
        let rows = dData.success ? (dData.data || []) : [];

        // Filtre côté client sur l'onglet actif
        if (dfc_currentTab === 'encours') {
            rows = rows.filter(b => b.statut === 'Valider');
        }

        // Peupler les selects années si vides
        const allAnnees = [...new Set(rows.map(b=>b.annee))].sort((a,b)=>a-b);
        ['filter_annee_debut','filter_annee_fin'].forEach(selId => {
            const sel = document.getElementById(selId);
            if (sel && sel.querySelectorAll('option[value]').length <= 1) {
                const isDebut = selId.includes('debut');
                sel.innerHTML = `<option value="">${isDebut?'Toutes':''}</option>`;
                allAnnees.forEach(a => {
                    const o=document.createElement('option'); o.value=a; o.textContent=a;
                    if (!isDebut && a===dfc_anneeFin) o.selected=true;
                    sel.appendChild(o);
                });
            }
        });

        if (!dfc_table) dfc_initTable();
        if (dfc_table) {
            dfc_table.clear();
            if (rows.length) dfc_table.rows.add(rows);
            dfc_table.draw();
        }

        dfc_hideLoader();
    } catch (err) {
        dfc_hideLoader();
        console.error(err);
        Swal.fire('Erreur','Erreur chargement : '+err.message,'error');
    }
}

// ─── Compteurs onglets ────────────────────────────────────────────────
function dfc_updateCounters() {
    const map = {
        'count-tous'     : dfc_stats.tous.count,
        'count-encours'  : dfc_stats.encours.count,
        'count-accepter' : dfc_stats.accepter.count,
        'count-rejeter'  : dfc_stats.rejeter.count,
        'count-reajuster': dfc_stats.reajuster.count,
    };
    Object.entries(map).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    });
}

// ─── Cartes statistiques ──────────────────────────────────────────────
function dfc_updateStatsCards() {
    const fmt = n => `${dfc_fmtK(n)} FCFA`;
    const map = {
        'stats-tous-count'         : dfc_stats.tous.count,
        'stats-tous-plafond'       : fmt(dfc_stats.tous.plafond),
        'stats-encours-count'      : dfc_stats.encours.count,
        'stats-encours-plafond'    : fmt(dfc_stats.encours.plafond),
        'stats-accepter-count'     : dfc_stats.accepter.count,
        'stats-accepter-plafond'   : fmt(dfc_stats.accepter.plafond),
        'stats-rejeter-count'      : dfc_stats.rejeter.count,
        'stats-rejeter-plafond'    : fmt(dfc_stats.rejeter.plafond),
        'stats-reajuster-count'    : dfc_stats.reajuster.count,
        'stats-reajuster-plafond'  : fmt(dfc_stats.reajuster.plafond),
    };
    Object.entries(map).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    });

    // Carte visible selon onglet
    document.querySelectorAll('#dfc-stats-grid .tab-content').forEach(c => c.classList.remove('active'));
    const activeCard = document.querySelector(`#dfc-stats-grid .tab-content[data-tab="${dfc_currentTab}"]`);
    if (activeCard) activeCard.classList.add('active');

    const grid = document.getElementById('dfc-stats-grid');
    if (grid) grid.classList.toggle('bud-show-all', dfc_currentTab === 'tous');
}

// ─── Onglets ──────────────────────────────────────────────────────────
function dfc_initTabs() {
    document.querySelectorAll('.bud-tabs-bar button[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-tab');
            if (tab === dfc_currentTab) return;
            dfc_currentTab = tab;

            document.querySelectorAll('.bud-tabs-bar button[data-tab]').forEach(b => {
                b.classList.remove('active','bg-white','border','border-b-0','border-gray-200','text-emerald-700');
                b.classList.add('text-gray-600');
            });
            btn.classList.remove('text-gray-600');
            btn.classList.add('active','bg-white','border','border-b-0','border-gray-200','text-emerald-700');

            dfc_load();
        });
    });
}

// ─── Actions ──────────────────────────────────────────────────────────
function dfc_voirBudget(id) {

    window.location.href = `/dfc-consultation-budget/${id}`;

}

async function dfc_doValider(id) {
    const c = await Swal.fire({
        title:'Valider ce budget ?',
        html:`<p style="font-size:.875rem;color:#374151">
                Cette action va valider le budget et notifier l'équipe par email.
              </p>`,
        icon:'question', showCancelButton:true,
        confirmButtonText:'Oui, valider', cancelButtonText:'Annuler',
        confirmButtonColor:'#1a7a5e'
    });
    if (!c.isConfirmed) return;

    dfc_showLoader('Validation en cours…');
    const r = await dfc_post(dfc_api.valider, {budgetId: id});
    dfc_hideLoader();
    const d = await dfc_parse(r); if (!d) return;
    if (!d.success) { Swal.fire('Erreur', d.message, 'error'); return; }

    await Swal.fire({ icon:'success', title:'Budget validé !', text:d.message,
        timer:1800, showConfirmButton:false });
    dfc_load();
}

async function dfc_doRejeter(id) {
    const c = await Swal.fire({
        title:'Rejeter ce budget ?',
        input:'textarea',
        inputLabel:'Motif du rejet (obligatoire)',
        inputPlaceholder:'Entrez le motif du rejet…',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Confirmer le rejet', cancelButtonText:'Annuler',
        confirmButtonColor:'#ef4444',
        inputValidator: v => { if (!v || !v.trim()) return 'Un motif est obligatoire.'; }
    });
    if (!c.isConfirmed) return;

    dfc_showLoader('Rejet en cours…');
    const r = await dfc_post(dfc_api.rejeter, {budgetId: id, motif: c.value.trim()});
    dfc_hideLoader();
    const d = await dfc_parse(r); if (!d) return;
    if (!d.success) { Swal.fire('Erreur', d.message, 'error'); return; }

    await Swal.fire({ icon:'success', title:'Budget rejeté', text:d.message,
        timer:1800, showConfirmButton:false });
    dfc_load();
}

async function dfc_doReajuster(id) {
    const c = await Swal.fire({
        title:'Réajuster ce budget ?',
        html:`<div style="text-align:left;font-size:.875rem;color:#374151;line-height:1.7;margin-bottom:.85rem">
                En confirmant, vous :
                <ul style="list-style:disc;padding-left:1.25rem;font-size:.825rem;color:#6b7280;margin:.5rem 0">
                    <li>Verrouillez les lignes budgétaires approuvées</li>
                    <li>Permettez l'ajout de nouvelles lignes</li>
                    <li>Notifiez l'équipe par email</li>
                </ul>
              </div>
              <label style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;
                            letter-spacing:.05em;color:#9ca3af;text-align:left;margin-bottom:.35rem">
                Motif (optionnel)
              </label>
              <textarea id="reaj-motif" class="swal2-textarea"
                style="height:80px;font-size:.875rem"
                placeholder="Motif du réajustement…"></textarea>`,
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Confirmer le réajustement', cancelButtonText:'Annuler',
        confirmButtonColor:'#d97706',
        preConfirm: () => document.getElementById('reaj-motif')?.value?.trim()
            || 'Réajustement demandé par la DFC'
    });
    if (!c.isConfirmed) return;

    dfc_showLoader('Réajustement en cours…');
    const r = await dfc_post(dfc_api.reajusterAction, {budgetId: id, motif: c.value});
    dfc_hideLoader();
    const d = await dfc_parse(r); if (!d) return;
    if (!d.success) { Swal.fire('Erreur', d.message, 'error'); return; }

    await Swal.fire({
        icon:'success', title:'Réajustement effectué', text:d.message,
        confirmButtonColor:'#d97706'
    });
    dfc_load();
}

// ─── Filtres années ────────────────────────────────────────────────────
function dfc_bindFilters() {
    document.getElementById('filter_annee_debut')?.addEventListener('change', function () {
        const debut = this.value ? parseInt(this.value) : null;
        const fin   = dfc_anneeFin ? parseInt(dfc_anneeFin) : null;
        if (debut && fin && debut > fin) {
            Swal.fire('Attention', 'L\'année début doit être ≤ à l\'année fin.', 'warning');
            this.value = ''; return;
        }
        dfc_anneeDebut = debut;
        dfc_load();
    });

    document.getElementById('filter_annee_fin')?.addEventListener('change', function () {
        const fin   = this.value ? parseInt(this.value) : null;
        const debut = dfc_anneeDebut ? parseInt(dfc_anneeDebut) : null;
        if (debut && fin && debut > fin) {
            Swal.fire('Attention', 'L\'année fin doit être ≥ à l\'année début.', 'warning');
            this.value = new Date().getFullYear(); return;
        }
        dfc_anneeFin = fin;
        dfc_load();
    });

    document.getElementById('dfc-reset-filters')?.addEventListener('click', () => {
        const sD = document.getElementById('filter_annee_debut');
        const sF = document.getElementById('filter_annee_fin');
        if (sD) sD.value = '';
        if (sF) { sF.value = new Date().getFullYear(); }
        dfc_anneeDebut = null;
        dfc_anneeFin   = new Date().getFullYear();
        dfc_load();
    });

    document.getElementById('dfc-apply-filters')?.addEventListener('click', () => dfc_load());
}

// ─── Initialisation ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Prépeupler année fin avec l'année courante
    const curYear = new Date().getFullYear();
    const sfin = document.getElementById('filter_annee_fin');
    if (sfin) sfin.innerHTML = `<option value="${curYear}" selected>${curYear}</option>`;

    dfc_initTabs();
    dfc_initTable();
    dfc_load();
    dfc_bindFilters();

    document.getElementById('dfc-refresh-btn')?.addEventListener('click', () => dfc_load());
});