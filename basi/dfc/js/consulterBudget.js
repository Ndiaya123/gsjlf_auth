// ════════════════════════════════════════════════════════════════════════
// CONSULTATION BUDGET DFC
// • Détecte auto le type (type_budget_id === 1 → fonctionnement, sinon investissement)
// • Colonnes adaptées selon le type
// • Onglets : Lignes | Historique
// • Actions DFC selon statut (Valider/Rejeter/Réajuster)
// ════════════════════════════════════════════════════════════════════════

const DFC_API = '/personnel/dfc_basi_controller'; // dfcController.php

const cb = {
    budget    : `${DFC_API}?option=12`,  // détails budget (token chiffré)
    lignesInv : `${DFC_API}?option=13`,  // lignes investissement (token)
    lignesFon : `${DFC_API}?option=14`,  // lignes fonctionnement (token)
    valider   : `${DFC_API}?option=6`,   // action valider
    rejeter   : `${DFC_API}?option=7`,   // action rejeter
    reajuster : `${DFC_API}?option=8`,   // action réajuster
};

// Token chiffré : injecté par PHP (window.CB_BUDGET_TOKEN) ou depuis ?token= dans l'URL
// Fallback : ?budgetId=N pour rétrocompatibilité (liste DFC qui navigue par budgetId)
const CB_TOKEN = (() => {
    if (window.CB_BUDGET_TOKEN) return window.CB_BUDGET_TOKEN;
    const p = new URLSearchParams(window.location.search);
    return p.get('token') || null;
})();
const CB_ID = (() => {
    const p = new URLSearchParams(window.location.search);
    return p.get('budgetId') ? parseInt(p.get('budgetId')) : null;
})();

// ── État ──────────────────────────────────────────────────────────────────────
let cb_budget      = null;
let cb_lignesTable = null;

// ── Helpers statut / lignes ───────────────────────────────────────────────────
// Budget au statut « Valider » (soumis par le chef de service, en attente DFC)
// → toutes les lignes ACTIVES sont considérées comme verrouillées.
function cb_isBudgetValide() {
    return cb_budget?.statut === 'Valider';
}
// Une ligne est « active » si actif = 1 (ou si le champ n'est pas renvoyé par l'API)
function cb_isLigneActive(l) {
    return l && (l.actif === undefined || l.actif === null || parseInt(l.actif) === 1);
}
// Verrouillage effectif d'une ligne, selon le statut du budget
function cb_isLigneVerrouillee(l) {
    return cb_isBudgetValide() ? cb_isLigneActive(l) : parseInt(l?.verrouiller) === 1;
}

// ── Réseau ────────────────────────────────────────────────────────────────────
function cb_expired() {
    Swal.fire({ icon:'warning', title:'Session expirée', text:'Redirection…',
        timer:2500, showConfirmButton:false,
        didClose:()=>{ window.location.href='/personnel/signin'; }
    });
}
async function cb_post(url, body={}) {
    const r = await fetch(url, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(body)
    });
    const d = await r.json();
    if (d?.code === 'sessionExpired') { cb_expired(); return null; }
    return d;
}

// ── Loader ────────────────────────────────────────────────────────────────────
function cb_showLoader(msg='Chargement…') {
    $('#cb-loader').remove();
    $('body').append(
        `<div id="cb-loader">
           <div class="cb-loader-bg"></div>
           <div class="cb-loader-box">
             <svg viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>
             <p>${msg}</p>
           </div>
         </div>`
    );
}
function cb_hideLoader() { $('#cb-loader').remove(); }

// ── Formatage ─────────────────────────────────────────────────────────────────
const fmtN  = v => new Intl.NumberFormat('fr-FR').format(Number(v||0)) + ' FCFA';
const fmtD  = d => d ? new Date(d.replace(' ','T')).toLocaleDateString('fr-FR') : '—';
const fmtDT = d => d ? new Date(d.replace(' ','T')).toLocaleString('fr-FR') : '—';
const fmtK  = v => {
    if (v >= 1e9) return (v/1e9).toFixed(1)+' Md';
    if (v >= 1e6) return (v/1e6).toFixed(1)+' M';
    if (v >= 1e3) return (v/1e3).toFixed(0)+' K';
    return new Intl.NumberFormat('fr-FR').format(v);
};

// ── Badges ────────────────────────────────────────────────────────────────────
function badgeStatut(s) {
    const map = {
        'Valider'    : '<span class="cb-badge cb-badge-warn">En attente DFC</span>',
        'Accepter'   : '<span class="cb-badge cb-badge-ok">Validé ✓</span>',
        'Rejeter'    : '<span class="cb-badge cb-badge-err">Rejeté ✗</span>',
        'Réajuster'  : '<span class="cb-badge cb-badge-purple">Réajuster</span>',
        'Sauvegarder': '<span class="cb-badge cb-badge-gray">En cours</span>',
        'En cours'   : '<span class="cb-badge cb-badge-gray">En cours</span>',
        'Terminer'   : '<span class="cb-badge cb-badge-ok">Terminé</span>',
    };
    return map[s] || `<span class="cb-badge cb-badge-gray">${s}</span>`;
}
// Reçoit la LIGNE complète : le verrouillage dépend du statut du budget
function badgeVerr(ligne) {
    return cb_isLigneVerrouillee(ligne)
        ? `<span class="cb-verr">
             <svg width="11" height="11" viewBox="0 0 24 24" fill="#7c3aed">
               <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12
                        c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6
                        c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
             </svg>
             Verrouillé
           </span>`
        : '';
}

// ── Init principale ────────────────────────────────────────────────────────────
async function cb_init() {
    if (!CB_TOKEN && !CB_ID) {
        const err = document.getElementById('cb-error');
        if (err) { err.textContent = 'Identifiant budget manquant dans l\'URL (?token=... ou ?budgetId=N).'; err.classList.remove('hidden'); }
        return;
    }

    // Corps de requête : token chiffré prioritaire, sinon budgetId brut
    const cb_body = CB_TOKEN ? { token: CB_TOKEN } : { budgetId: CB_ID };

    cb_showLoader('Chargement du budget…');

    // Chargement du budget
    const dBudget = await cb_post(cb.budget, cb_body);
    const dHist   = null;

    if (!dBudget || !dBudget.success) {
        cb_hideLoader();
        Swal.fire('Erreur', dBudget?.message || 'Budget introuvable.', 'error');
        return;
    }

    // IMPORTANT : cb_budget (donc le statut) est renseigné AVANT le rendu des lignes
    // → cb_renderKPI / cb_renderLignes peuvent s'appuyer sur cb_isBudgetValide()
    cb_budget = dBudget.data;
    const isFonc = parseInt(cb_budget.type_budget_id) === 1; // 1 = Fonctionnement

    // Chargement des lignes selon le type
    const dLignes = await cb_post(isFonc ? cb.lignesFon : cb.lignesInv, cb_body);

    cb_hideLoader();

    cb_renderHead(cb_budget, isFonc);
    cb_renderKPI(cb_budget, dLignes);
    cb_renderProgress(cb_budget, dLignes);
    cb_renderInfo(cb_budget);
    cb_renderLignes(dLignes, isFonc);
    cb_renderActions(cb_budget);
}

// ── En-tête gradient ──────────────────────────────────────────────────────────
function cb_renderHead(b, isFonc) {
    const el = id => document.getElementById(id);

    if (el('cb-titre'))      el('cb-titre').textContent     = `Budget ${b.annee}`;
    if (el('cb-sous-titre')) el('cb-sous-titre').textContent = `Référence #${b.id} · ${b.type_budget_nom} · créé le ${fmtD(b.date_creation)}`;
    if (el('cb-statut'))     el('cb-statut').innerHTML       = badgeStatut(b.statut);
    if (el('cb-type-chip'))  el('cb-type-chip').textContent  = isFonc ? 'Fonctionnement' : 'Investissement';
}

// ── KPI cards ─────────────────────────────────────────────────────────────────
function cb_renderKPI(b, dL) {
    const plafond     = parseFloat(b.plafond) || 0;
    const totalLignes = dL?.totalMontant ? parseFloat(dL.totalMontant) : 0;
    const reste       = Math.max(plafond - totalLignes, 0);
    const lignes      = dL?.data || [];
    const nbLignes    = dL?.lineCount || 0;

    // ── Verrouillage : calculé UNIQUEMENT quand le statut du budget est « Valider »
    //    Dans ce cas, toutes les lignes ACTIVES sont verrouillées.
    const nbVerr = cb_isBudgetValide()
        ? lignes.filter(cb_isLigneActive).length
        : 0;

    const set = (id, v) => { const e=document.getElementById(id); if(e) e.textContent=v; };
    set('cb-kpi-plafond',  fmtK(plafond)+' FCFA');
    set('cb-kpi-consomme', fmtK(totalLignes)+' FCFA');
    set('cb-kpi-reste',    fmtK(reste)+' FCFA');
    set('cb-kpi-lignes',   nbLignes+' ligne'+(nbLignes!==1?'s':''));
    set('cb-kpi-verr',     nbVerr > 0 ? nbVerr+' verrouillée'+(nbVerr!==1?'s':'') : 'Aucune');
    set('cb-kpi-annee',    b.annee);
}

// ── Barre de progression ──────────────────────────────────────────────────────
function cb_renderProgress(b, dL) {
    const plafond = parseFloat(b.plafond) || 0;
    const total   = dL?.totalMontant ? parseFloat(dL.totalMontant) : 0;
    const pct     = plafond > 0 ? Math.min((total/plafond)*100, 100) : 0;

    const bar   = document.getElementById('cb-prog-bar');
    const pctEl = document.getElementById('cb-prog-pct');
    const alert = document.getElementById('cb-alert-depass');

    if (bar) {
        bar.style.width = pct + '%';
        bar.classList.remove('ok','warn','over');
        bar.classList.add(pct >= 100 ? 'over' : pct >= 80 ? 'warn' : 'ok');
    }
    if (pctEl) pctEl.textContent = Math.round(pct) + '%';
    if (alert) alert.classList.toggle('hidden', total <= plafond);
}

// ── Fiche info budget ─────────────────────────────────────────────────────────
function cb_renderInfo(b) {
    const el = document.getElementById('cb-info-grid');
    if (!el) return;
    const items = [
        { label:'Direction',   val: b.code_direction || '—' },
        { label:'Matricule',       val: b.matricule       || '—' },
        { label:'Type de budget',  val: b.type_budget_nom || '—' },
        { label:'Plafond',         val: b.plafond ? fmtN(b.plafond) : '—' },
        { label:'Statut',          val: b.statut          || '—' },
        { label:'Année',           val: b.annee           || '—' },
        { label:'Date création',   val: fmtD(b.date_creation) },
    ];
    el.innerHTML = items.map(i => `
        <div class="cb-info-item">
            <span class="cb-info-lbl">${i.label}</span>
            <span class="cb-info-val">${i.val}</span>
        </div>`).join('');
}

// ── Table des lignes ──────────────────────────────────────────────────────────
function cb_renderLignes(dL, isFonc) {
    if (cb_lignesTable) { try { cb_lignesTable.destroy(); } catch(e){} cb_lignesTable = null; }

    const lignes = dL?.data || [];

    // Colonne « verrouillé » commune aux deux types :
    // on rend à partir de la LIGNE complète (row), pas de la seule valeur `verrouiller`
    const colVerr = {
        data:'verrouiller', title:'', orderable:false, searchable:false,
        render: (v, t, row) => badgeVerr(row)
    };

    // Colonnes selon le type de budget
    const colsFonc = [
        { data:'nom_categorie',      title:'Catégorie',       defaultContent:'—' },
        { data:'nom_sous_categorie', title:'Sous-catégorie',  defaultContent:'—' },
        { data:'nomproduit',         title:'Produit',         defaultContent:'—' },
        { data:'designation',        title:'Désignation',     defaultContent:'—' },
        { data:'periode_d_utilisation', title:'Période',      defaultContent:'—' },
        { data:'quantite',           title:'Qté',             className:'dt-right', defaultContent:'—' },
        { data:'unite_nom',          title:'Unité',           defaultContent:'—' },
        { data:'prix_unitaire',      title:'P.U.',            className:'dt-right',
            render: v => v ? `<span class="cb-amt">${fmtN(v)}</span>` : '—' },
        { data:'montant_total',      title:'Total',           className:'dt-right',
            render: v => `<span class="cb-amt-total">${fmtN(v)}</span>` },
        colVerr,
    ];
    const colsInv = [
        { data:'nature_nom',         title:'Nature',          defaultContent:'—' },
        { data:'nom_rubrique',       title:'Rubrique',        defaultContent:'—' },
        { data:'nom_sous_rubrique',  title:'Sous-rubrique',   defaultContent:'—' },
        { data:'service_nom',        title:'Service',         defaultContent:'—' },
        { data:'nom_direction',      title:'Direction',       defaultContent:'—' },
        { data:'designation',        title:'Désignation',     defaultContent:'—' },
        { data:'description',        title:'Description',     defaultContent:'—' },
        { data:'periode_d_utilisation', title:'Période',      defaultContent:'—' },
        { data:'quantite',           title:'Qté',             className:'dt-right', defaultContent:'—' },
        { data:'unite_nom',          title:'Unité',           defaultContent:'—' },
        { data:'prix_unitaire',      title:'P.U.',            className:'dt-right',
            render: v => v ? `<span class="cb-amt">${fmtN(v)}</span>` : '—' },
        { data:'montant_total',      title:'Total',           className:'dt-right',
            render: v => `<span class="cb-amt-total">${fmtN(v)}</span>` },
        colVerr,
    ];

    cb_lignesTable = $('#cb-table-lignes').DataTable({
        data     : lignes,
        columns  : isFonc ? colsFonc : colsInv,
        responsive: true,
        pageLength: 25,
        order    : [[0,'asc']],
        dom      : '<"cb-dt-top"lf>rt<"cb-dt-bottom"ip>',
        language : {
            emptyTable  : '<div class="cb-empty">Aucune ligne budgétaire</div>',
            info        : 'Lignes _START_–_END_ / _TOTAL_',
            infoEmpty   : 'Aucune ligne',
            lengthMenu  : 'Afficher _MENU_ lignes',
            search      : '_INPUT_',
            searchPlaceholder: 'Rechercher…',
            zeroRecords : 'Aucun résultat',
            paginate    : { first:'«', last:'»', next:'›', previous:'‹' }
        },
        rowCallback(row, data) {
           // if (cb_isLigneVerrouillee(data)) $(row).addClass('cb-row-locked');
        },
    });

    // Total
    const total = lignes.reduce((s, l) => s + parseFloat(l.montant_total||0), 0);
    const el = document.getElementById('cb-table-total');
    if (el) el.textContent = fmtN(total);
}


// ── Boutons d'action DFC ──────────────────────────────────────────────────────
function cb_renderActions(b) {
    const wrap = document.getElementById('cb-actions-wrap');
    if (!wrap) return;

    let html = '';
    if (b.statut === 'Valider') {
        html = `
        <button onclick="cb_doValider()" class="cb-act-btn cb-act-green">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
            Valider
        </button>
        <button onclick="cb_doRejeter()" class="cb-act-btn cb-act-red">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12"/></svg>
            Rejeter
        </button>`;
    } else if (b.statut === 'Accepter') {
        html = `
        <button onclick="cb_doReajuster()" class="cb-act-btn cb-act-orange">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Réajuster
        </button>`;
    } else {
        html = `<span class="cb-no-action">Aucune action disponible — statut : <strong>${b.statut}</strong></span>`;
    }
    wrap.innerHTML = html;
}

// ── Actions ───────────────────────────────────────────────────────────────────
async function cb_doValider() {
    const c = await Swal.fire({
        title:'Valider ce budget ?', icon:'question', showCancelButton:true,
        confirmButtonText:'Oui, valider', cancelButtonText:'Annuler', confirmButtonColor:'#1a7a5e',
    });
    if (!c.isConfirmed) return;
    cb_showLoader('Validation en cours…');
    const d = await cb_post(cb.valider, { budgetId: parseInt(cb_budget.id) });
    cb_hideLoader();
    if (!d?.success) { Swal.fire('Erreur', d?.message||'Erreur.', 'error'); return; }
    await Swal.fire({ icon:'success', title:'Budget validé !', text:d.message, timer:1800, showConfirmButton:false });
    cb_init();
}
async function cb_doRejeter() {
    const c = await Swal.fire({
        title:'Rejeter ce budget ?', input:'textarea',
        inputLabel:'Motif (obligatoire)', inputPlaceholder:'Entrez le motif du rejet…',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Confirmer le rejet', cancelButtonText:'Annuler', confirmButtonColor:'#ef4444',
        inputValidator: v => { if (!v||!v.trim()) return 'Un motif est obligatoire.'; }
    });
    if (!c.isConfirmed) return;
    cb_showLoader('Rejet en cours…');
    const d = await cb_post(cb.rejeter, { budgetId: parseInt(cb_budget.id), motif: c.value.trim() });
    cb_hideLoader();
    if (!d?.success) { Swal.fire('Erreur', d?.message||'Erreur.', 'error'); return; }
    await Swal.fire({ icon:'success', title:'Budget rejeté', text:d.message, timer:1800, showConfirmButton:false });
    cb_init();
}
async function cb_doReajuster() {
    const c = await Swal.fire({
        title:'Réajuster ce budget ?',
        html:`<div style="text-align:left;font-size:.875rem;color:#374151;margin-bottom:.85rem">
                En confirmant, vous verrouillez les lignes approuvées et permettez l'ajout de nouvelles lignes.
              </div>
              <label style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#9ca3af;text-align:left;margin-bottom:.3rem">Motif (optionnel)</label>
              <textarea id="reaj-motif" class="swal2-textarea" style="height:70px" placeholder="Motif du réajustement…"></textarea>`,
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Confirmer', cancelButtonText:'Annuler', confirmButtonColor:'#d97706',
        preConfirm: () => document.getElementById('reaj-motif')?.value?.trim() || 'Réajustement demandé par la DFC'
    });
    if (!c.isConfirmed) return;
    cb_showLoader('Réajustement en cours…');
    const d = await cb_post(cb.reajuster, { budgetId: parseInt(cb_budget.id), motif: c.value });
    cb_hideLoader();
    if (!d?.success) { Swal.fire('Erreur', d?.message||'Erreur.', 'error'); return; }
    await Swal.fire({ icon:'success', title:'Réajustement effectué', text:d.message, confirmButtonColor:'#d97706' });
    cb_init();
}

// ── Onglets ───────────────────────────────────────────────────────────────────
function cb_initTabs() {
    document.querySelectorAll('.cb-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            document.querySelectorAll('.cb-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.cb-tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('cb-panel-' + tab)?.classList.add('active');
        });
    });
}

// ── DOMContentLoaded ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cb_initTabs();
    cb_init();
    document.getElementById('cb-back-btn')?.addEventListener('click', () => window.location.href = "/personnel/dfc-liste_budget");
});