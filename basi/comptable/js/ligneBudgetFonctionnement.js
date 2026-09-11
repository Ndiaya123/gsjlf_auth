// ═══════════════════════════════════════════════════════════════════════
// LIGNES DE BUDGET — Fonctionnement (type Produit implicite)
// POST JSON → compta_basi_controller
// Pas de sélection de "nature" : chaque ligne est systématiquement de type
// Produit, rattachée à une catégorie + sous-catégorie (table categorie/
// souscategorie), qui alimente product.id_Sous_categorie.
// ═══════════════════════════════════════════════════════════════════════

const LB_API = '/personnel/compta_basi_controller';
const lb_api = {
    budgetInfo:   `${LB_API}?option=28`, // POST { budgetId } (token chiffré) → un budget
    lines:        `${LB_API}?option=20`, // POST { action:"lines", budgetId }  → lignes
    lineById:     `${LB_API}?option=26`, // POST { lineId }
    createLine:   `${LB_API}?option=21`, // POST { budgetId, ... }
    updateLine:   `${LB_API}?option=22`, // POST { lineId, ... }
    deleteLine:   `${LB_API}?option=23`, // POST { lineId }
    categories:   `${LB_API}?option=1`,  // POST {}
    sousCategories:`${LB_API}?option=2`, // POST { categorie_id }
    services:     `${LB_API}?option=24`, // POST {}
    createCat:    `${LB_API}?option=3`,  // POST { nom }
    createSubCat: `${LB_API}?option=6`,  // POST { nom, categorie_id }
    unites:       `${LB_API}?option=27`, // POST {} → liste unités
    produits:     `${LB_API}?option=29`, // POST { sous_categorie_id } → produits filtrés
    validerBudget:`${LB_API}?option=25`, // POST { budgetId }
};

// ─── État global ──────────────────────────────────────────────────────
let lb_budgetId     = null; // token brut (jamais l'id en clair)
let lb_budgetCeil   = 0;
let lb_budgetStatut = null; // statut actuel du budget
let lb_table        = null;
let lb_totalAmount  = 0;

// ─── Helpers statut budget / lignes ───────────────────────────────────
// Un seul endroit pour décider si le budget est modifiable, s'il est validé,
// et si une ligne est active / verrouillée.
const LB_STATUTS_EDITABLES = ['En cours', 'Sauvegarder', 'Rejeter', 'Réajuster'];
// Statuts qui verrouillent le budget : toutes les lignes ACTIVES sont alors verrouillées
const LB_STATUTS_VERROUILLES = ['Valider', 'Accepter'];

function lb_isBudgetEditable() {
    return LB_STATUTS_EDITABLES.includes(lb_budgetStatut);
}
function lb_isBudgetVerrouille() {
    return LB_STATUTS_VERROUILLES.includes(lb_budgetStatut);
}
// Une ligne est « active » si actif = 1 (ou si le champ n'est pas renvoyé par l'API)
function lb_isLigneActive(l) {
    return l && (l.actif === undefined || l.actif === null || parseInt(l.actif, 10) === 1);
}
// Verrouillage effectif : budget validé → toute ligne active est verrouillée
function lb_isLigneVerrouillee(l) {
    return lb_isBudgetVerrouille()
        ? lb_isLigneActive(l)
        : parseInt(l?.verrouiller, 10) === 1;
}

// ─── Réseau ───────────────────────────────────────────────────────────
function lb_sessionExpired() {
    lb_hideLoader();
    Swal.fire({
        icon:'warning', title:'Session expirée',
        text:'Votre session a expiré. Vous allez être redirigé.',
        timer:2500, showConfirmButton:false,
        didClose:() => { window.location.href='/personnel/signin'; }
    });
}

async function lb_post(url, body={}, timeout=10000) {
    const ctrl = new AbortController();
    const t = setTimeout(()=>ctrl.abort(), timeout);
    try {
        const r = await fetch(url, {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(body),
            signal:ctrl.signal
        });
        clearTimeout(t); return r;
    } catch(e) {
        clearTimeout(t);
        if (e.name==='AbortError') throw new Error('Délai dépassé. Réessayez.');
        throw e;
    }
}

async function lb_checkResp(r) {
    const d = await r.json();
    if (d && d.code==='sessionExpired') { lb_sessionExpired(); return null; }
    return d;
}

// ─── Loader ───────────────────────────────────────────────────────────
function lb_showLoader(msg='Chargement…') {
    document.getElementById('global-loader')?.remove();
    const el = document.createElement('div');
    el.id = 'global-loader';
    el.style.cssText = 'position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;';
    el.innerHTML =
        '<div style="position:absolute;inset:0;background:rgba(4,20,11,.5);backdrop-filter:blur(4px);"></div>'
        +'<div style="position:relative;background:#fff;border-radius:16px;padding:2rem 3rem;display:flex;flex-direction:column;align-items:center;gap:1rem;box-shadow:0 24px 60px rgba(0,0,0,.18);min-width:200px;">'
        +'<svg width="40" height="40" viewBox="0 0 50 50" style="animation:lb-spin .9s linear infinite;">'
        +'<circle cx="25" cy="25" r="20" fill="none" stroke="#1a7a5e" stroke-width="4" stroke-dasharray="80" stroke-dashoffset="60" stroke-linecap="round" style="animation:lb-dash 1.4s ease-in-out infinite;"/>'
        +'</svg><p style="margin:0;font-size:.82rem;font-weight:600;color:#1a7a5e;">'+msg+'</p></div>';
    if (!document.getElementById('lb-spin-style')) {
        const s=document.createElement('style'); s.id='lb-spin-style';
        s.textContent='@keyframes lb-spin{to{transform:rotate(360deg)}}@keyframes lb-dash{0%{stroke-dashoffset:80}50%{stroke-dashoffset:20}100%{stroke-dashoffset:80}}';
        document.head.appendChild(s);
    }
    document.body.appendChild(el);
}
function lb_hideLoader() { document.getElementById('global-loader')?.remove(); }

// ─── Modale principale ────────────────────────────────────────────────
function lb_openModal() {
    document.getElementById('lb-modal').classList.add('open');
}
function lb_closeModal() {
    document.getElementById('lb-modal').classList.remove('open');
    document.getElementById('lb-form').reset();
    document.getElementById('lb-line-id').value = '';
    lb_clearErrors();
}

// ─── Modale inline pour créer catégorie / sous-catégorie ──────────────
// S'affiche À L'INTÉRIEUR de la modale principale (pas derrière)
function lb_openInlineModal(title, onConfirm) {
    let overlay = document.getElementById('lb-inline-modal');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'lb-inline-modal';
        overlay.style.cssText =
            'position:absolute;inset:0;z-index:10;background:rgba(15,23,42,.6);'
            +'backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;'
            +'border-radius:16px;';
        overlay.innerHTML = `
            <div style="background:#fff;border-radius:12px;padding:1.5rem;width:90%;max-width:360px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <h3 id="lb-inline-title" style="margin:0 0 1rem;font-size:.95rem;font-weight:700;color:#111827;"></h3>
                <input id="lb-inline-input" type="text" class="lb-inp" placeholder="Saisir le nom..." style="margin-bottom:1rem;"/>
                <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                    <button id="lb-inline-cancel" class="lb-btn lb-btn--ghost" style="padding:.5rem 1rem;">Annuler</button>
                    <button id="lb-inline-confirm" class="lb-btn lb-btn--primary" style="padding:.5rem 1rem;">Créer</button>
                </div>
            </div>`;
        document.querySelector('.lb-modal').style.position = 'relative';
        document.querySelector('.lb-modal').appendChild(overlay);
    }
    document.getElementById('lb-inline-title').textContent = title;
    document.getElementById('lb-inline-input').value = '';
    overlay.style.display = 'flex';
    document.getElementById('lb-inline-input').focus();

    const cancel = document.getElementById('lb-inline-cancel');
    const confirm = document.getElementById('lb-inline-confirm');
    const closeInline = () => { overlay.style.display='none'; };
    cancel.onclick = closeInline;
    confirm.onclick = () => {
        const val = document.getElementById('lb-inline-input').value.trim();
        if (!val) { document.getElementById('lb-inline-input').focus(); return; }
        closeInline();
        onConfirm(val);
    };
    document.getElementById('lb-inline-input').onkeydown = (e) => {
        if (e.key==='Enter') confirm.click();
        if (e.key==='Escape') closeInline();
    };
}

async function lb_loadUnites(selectId='lb-unite') {
    const r = await lb_post(lb_api.unites, {});
    if (!r.ok) return;
    const d = await lb_checkResp(r); if (!d) return;
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = '<option value="">Sélectionner une unité *</option>';
    (d.data||[]).forEach(u => sel.insertAdjacentHTML('beforeend',
        `<option value="${u.id}">${u.nom}</option>`));
    if (cur) sel.value = cur;
}

async function lb_loadProduits(sousCategorieId, selectId='lb-produit') {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">— Sélectionner un produit —</option>';
    if (!sousCategorieId) { sel.innerHTML = '<option value="">— Sélectionner d&#39;abord une sous-catégorie —</option>'; return; }
    const r = await lb_post(lb_api.produits, {sous_categorie_id: parseInt(sousCategorieId)});
    if (!r || !r.ok) return;
    const d = await lb_checkResp(r); if (!d || d.status!=='success') return;
    if (!d.data || d.data.length === 0) {
        sel.innerHTML = '<option value="">Aucun produit disponible pour cette sous-catégorie</option>';
        return;
    }
    (d.data).forEach(p => sel.insertAdjacentHTML('beforeend',
        `<option value="${p.idP}">${p.nomproduit}${p.code_produit ? ' ('+p.code_produit+')' : ''}</option>`));
}

// ─── Helpers erreurs inline ────────────────────────────────────────────
function lb_showError(inputId, msgId, message) {
    const inp = document.getElementById(inputId);
    const msg = document.getElementById(msgId);
    if (inp) inp.classList.add('lb-error');
    if (msg) { msg.textContent = message; msg.classList.add('visible'); }
}
function lb_clearErrors() {
    document.querySelectorAll('.lb-inp.lb-error').forEach(el => el.classList.remove('lb-error'));
    document.querySelectorAll('.lb-err-msg.visible').forEach(el => {
        el.classList.remove('visible'); el.textContent = '';
    });
}

// ─── Chargement des listes ────────────────────────────────────────────
async function lb_loadCategories(selectId='lb-categorie', filterId=null) {
    const r = await lb_post(lb_api.categories, {});
    if (!r.ok) return;
    const d = await lb_checkResp(r); if (!d) return;
    const sel = document.getElementById(selectId);
    if (sel) {
        const cur = sel.value;
        sel.innerHTML = '<option value="">Choisir une catégorie</option>';
        (d.data||[]).forEach(c => sel.insertAdjacentHTML('beforeend',
            `<option value="${c.id}">${c.nom_categorie}</option>`));
        if (cur) sel.value = cur;
    }
    if (filterId) {
        const flt = document.getElementById(filterId);
        if (flt) {
            flt.innerHTML = '<option value="">Toutes les catégories</option>';
            (d.data||[]).forEach(c => flt.insertAdjacentHTML('beforeend',
                `<option value="${c.id}">${c.nom_categorie}</option>`));
        }
    }
}

async function lb_loadSousCategories(categorieId, selectId='lb-sous-rubrique') {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">Choisir une sous-catégorie</option>';
    if (!categorieId) return;
    const r = await lb_post(lb_api.sousCategories, {categorie_id: categorieId});
    if (!r.ok) return;
    const d = await lb_checkResp(r); if (!d) return;
    (d.data||[]).forEach(s => sel.insertAdjacentHTML('beforeend',
        `<option value="${s.id}">${s.nom}</option>`));
}

// Charger toutes les sous-catégories pour alimenter le filtre tableau (toutes, pas filtrées)
async function lb_loadAllSousCategoriesFilter() {
    const r = await lb_post(lb_api.sousCategories, {});
    if (!r.ok) return [];
    const d = await lb_checkResp(r); if (!d) return [];
    return d.data || [];
}

// ─── Compteurs + alertes ──────────────────────────────────────────────
function lb_updateCounters(donnees) {
    lb_totalAmount = donnees.reduce((sum, l) => sum + (parseFloat(l.montant_total) || 0), 0);

    // ── Lignes actives (actif = 1) ─────────────────────────────────────
    const lignesActives = donnees.filter(lb_isLigneActive);

    // ── Verrouillage : calculé UNIQUEMENT quand le statut du budget est « Valider »
    //    ou « Accepter ». Dans ce cas, toutes les lignes ACTIVES sont verrouillées.
  //  const lockedCount   = lb_isBudgetVerrouille() ? lignesActives.length : 0;

    const lockedCount = lb_isBudgetVerrouille() ? lignesActives.length
        : donnees.filter(l => parseInt(l.verrouiller, 10) === 1).length;

    const editableCount = donnees.length - lockedCount;
    const avgPerLine    = donnees.length > 0 ? lb_totalAmount / donnees.length : 0;

    const fmt  = n => n.toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2});
    const fmtK = n => n >= 1000000
        ? (n/1000000).toLocaleString('fr-FR',{minimumFractionDigits:1,maximumFractionDigits:1})+' M'
        : n >= 1000
            ? (n/1000).toLocaleString('fr-FR',{minimumFractionDigits:0,maximumFractionDigits:0})+' K'
            : n.toLocaleString('fr-FR',{minimumFractionDigits:0});
    const el = id => document.getElementById(id);

    if (el('lb-stat-total'))  el('lb-stat-total').textContent  = fmtK(lb_totalAmount);
    if (el('lb-stat-count'))  el('lb-stat-count').textContent  = donnees.length;
    if (el('lb-stat-locked')) el('lb-stat-locked').textContent = lockedCount;
    if (el('lb-stat-avg'))    el('lb-stat-avg').textContent    = fmtK(avgPerLine)+' FCFA / ligne en moy.';
    if (el('lb-stat-editable')) el('lb-stat-editable').textContent = editableCount+' modifiable'+(editableCount>1?'s':'');

    const badge = el('lb-badge-locked');
    if (badge) {
        badge.style.display = lockedCount > 0 ? 'inline-flex' : 'none';
        const bc = el('lb-badge-locked-count');
        if (bc) bc.textContent = lockedCount;
    }

    if (el('lb-consumed')) el('lb-consumed').textContent = fmtK(lb_totalAmount);

    if (lb_budgetCeil > 0) {
        const pct = Math.min((lb_totalAmount / lb_budgetCeil) * 100, 100);
        const fill = el('lb-progress');
        if (fill) {
            fill.style.width = pct + '%';
            fill.className = 'lb-ceil-fill ' + (pct >= 100 ? 'lb-ceil-fill--over' : pct >= 80 ? 'lb-ceil-fill--warn' : 'lb-ceil-fill--ok');
        }
        if (el('lb-percent'))     el('lb-percent').textContent     = Math.round(pct) + '%';
        if (el('lb-stat-ceiling')) el('lb-stat-ceiling').textContent = fmtK(lb_budgetCeil);

        const alert = el('lb-alert');
        if (alert) {
            if (lb_totalAmount > lb_budgetCeil) {
                alert.classList.add('visible');
                if (el('lb-alert-total'))   el('lb-alert-total').textContent   = fmt(lb_totalAmount);
                if (el('lb-alert-ceiling')) el('lb-alert-ceiling').textContent = fmt(lb_budgetCeil);
            } else alert.classList.remove('visible');
        }
    } else {
        const fill = el('lb-progress');
        if (fill) { fill.style.width = '0%'; fill.className = 'lb-ceil-fill lb-ceil-fill--ok'; }
        if (el('lb-percent'))      el('lb-percent').textContent      = 'N/A';
        if (el('lb-stat-ceiling')) el('lb-stat-ceiling').textContent = '—';
    }
}

// ─── Bouton Valider budget ────────────────────────────────────────────
function lb_updateValidateBtn(donnees) {
    const btn = document.getElementById('lb-validate-btn');
    if (!btn) return;
    btn.style.display = (lb_isBudgetEditable() && donnees.length>0) ? 'inline-flex' : 'none';
}

// ─── DataTable ────────────────────────────────────────────────────────
function lb_buildColumns() {
    const fmt = n => Number(n).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2})+' FCFA';
    const actions = d => {
        const budgetEditable = lb_isBudgetEditable();
        // Budget verrouillé (Valider / Accepter) → toutes les lignes actives sont verrouillées
        const lineVerrouille = lb_isLigneVerrouillee(d);

        if (!budgetEditable || lineVerrouille) {
            return `<span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;color:#7c3aed;font-weight:600;background:#ede9fe;padding:.2rem .6rem;border-radius:20px;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="#7c3aed"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                Verrouillé
            </span>`;
        }
        return `<div class="lb-actions">
            <button class="lb-action lb-action--edit" data-id="${d.id}" title="Modifier">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="lb-action lb-action--del" data-id="${d.id}" title="Supprimer">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
        </div>`;
    };
    return [
        { data:'categorie_nom',         title:'Catégorie',      defaultContent:'—' },
        { data:'sous_categorie_nom',    title:'Sous-catégorie', defaultContent:'—' },
        { data:'service_nom',           title:'Service',        defaultContent:'—' },
        { data:'designation',           title:'Désignation',    defaultContent:'—' },
        { data:'periode_d_utilisation', title:'Période',        defaultContent:'—' },
        { data:'quantite', title:'Quantité', defaultContent:'—' },
        { data:'prix_unitaire', title:'P.U. (FCFA)', render: d => {
                const v = parseFloat(d);
                if (!v || v === 0) return '<span style="color:#d1d5db;">—</span>';
                return `<span style="font-weight:700;color:#111827;">${fmt(v)}</span>`;
            }},
        { data:'montant_total', title:'Total (FCFA)', render: d => {
                const v = parseFloat(d);
                if (!v || v === 0) return '<span style="color:#d1d5db;">—</span>';
                return `<span style="font-weight:600;color:#1a7a5e;">${fmt(v)}</span>`;
            }},
        { data:'date_creation', title:'Date', defaultContent:'—', render: d => {
                if (!d) return '—';
                const dt = new Date(d.replace(' ', 'T'));
                if (isNaN(dt)) return d;
                const pad = n => String(n).padStart(2,'0');
                return `${dt.getFullYear()}/${pad(dt.getMonth()+1)}/${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}:${pad(dt.getSeconds())}`;
            }},
        { data:null, title:'Actions', orderable:false, render: actions }
    ];
}

async function lb_initTable() {
    if (lb_table) { lb_table.destroy(); document.getElementById('lb-table').innerHTML=''; lb_table=null; }
    lb_showLoader('Chargement des lignes…');
    const body = { action:'lines', budgetId: lb_budgetId };

    const r = await lb_post(lb_api.lines, body).catch(()=>null);
    lb_hideLoader();
    if (!r||!r.ok) { Swal.fire('Erreur','Impossible de charger les lignes.','error'); return; }
    const d = await lb_checkResp(r); if (!d) return;
    let donnees = d.lines || d.donnees || [];

    // Filtre catégorie / sous-catégorie appliqué côté client
    const catFlt    = document.getElementById('lb-filter-categorie')?.value;
    const subCatFlt = document.getElementById('lb-filter-sous-categorie')?.value;
    if (catFlt)    donnees = donnees.filter(l => String(l.categorie_id) === String(catFlt));
    if (subCatFlt) donnees = donnees.filter(l => String(l.sous_categorie_id) === String(subCatFlt));

    lb_updateCounters(donnees);
    lb_updateValidateBtn(donnees);

    lb_table = $('#lb-table').DataTable({
        data: donnees,
        columns: lb_buildColumns(),
        pageLength: 25,
        lengthChange: true,
        language: {
            emptyTable: '<div style="padding:2rem;text-align:center;color:#9ca3af;">'
                +'<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" style="margin-bottom:.5rem;"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>'
                +'<div style="font-weight:700;font-size:.875rem;margin-bottom:.25rem;">Aucune ligne budgétaire</div>'
                +'<div style="font-size:.78rem;">Cliquez sur « Ajouter une ligne » pour commencer</div></div>',
            info:        'Affichage de _START_ à _END_ sur _TOTAL_ lignes',
            infoEmpty:   'Aucune ligne',
            lengthMenu:  'Afficher _MENU_ lignes',
            search:      '_INPUT_',
            searchPlaceholder: 'Rechercher...',
            zeroRecords: 'Aucun résultat pour cette recherche',
            paginate:{ first:'«', last:'»', next:'›', previous:'‹' }
        },
        dom: '<"lb-dt-top"lf>rt<"lb-dt-bottom"ip>',
        responsive: true,
        order: [[3,'asc']],
        lengthMenu: [[10,25,50,100],['10','25','50','100']],
    });
}

// ─── Infos budget ─────────────────────────────────────────────────────
// Chargement en parallèle : budget + services + catégories + toutes sous-catégories
async function lb_loadBudgetInfo() {
    lb_showLoader('Chargement…');
    try {
        const [rBudget, rServices, rCategories, rAllSousCat] = await Promise.all([
            lb_post(lb_api.budgetInfo, {budgetId: lb_budgetId}).catch(()=>null),
            lb_post(lb_api.services, {}),
            lb_post(lb_api.categories, {}),
            lb_loadAllSousCategoriesFilter(),
        ]);

        // ── Budget ──────────────────────────────────────────────────
        if (!rBudget || !rBudget.ok) { lb_hideLoader(); Swal.fire('Erreur','Budget introuvable.','error'); return; }
        const dBudget = await lb_checkResp(rBudget); if (!dBudget) { lb_hideLoader(); return; }
        if (dBudget.status!=='success') { lb_hideLoader(); Swal.fire('Erreur', dBudget.message||'Erreur.','error'); return; }

        const b = dBudget.donnees;
        lb_budgetCeil   = parseFloat(b.plafond)||0;
        // IMPORTANT : le statut est posé AVANT lb_initTable()
        // → lb_updateCounters() peut s'appuyer sur lb_isBudgetVerrouille()
        lb_budgetStatut = b.statut;

        const ceil = document.getElementById('lb-stat-ceiling');
        if (ceil) ceil.textContent = lb_budgetCeil.toLocaleString('fr-FR',{minimumFractionDigits:2});
        const infoEl = document.getElementById('lb-budget-info-text');
        if (infoEl) infoEl.textContent = `Budget ${b.annee} — ${b.statut}`;
        // Bouton « Ajouter une ligne » : visible pour tous les statuts modifiables
        // (En cours, Sauvegarder, Rejeter, Réajuster) — cf. LB_STATUTS_EDITABLES
        const isEditable = lb_isBudgetEditable();
        const addBtn = document.getElementById('lb-add-btn');
        if (addBtn) addBtn.style.display = isEditable ? 'inline-flex' : 'none';

        // ── Catégories (modale + filtre tableau) ────────────────────
        if (rCategories.ok) {
            const dCat = await lb_checkResp(rCategories);
            if (dCat) {
                const sel = document.getElementById('lb-categorie');
                const flt = document.getElementById('lb-filter-categorie');
                if (sel) {
                    sel.innerHTML = '<option value="">Choisir une catégorie</option>';
                    (dCat.data||[]).forEach(c => sel.insertAdjacentHTML('beforeend',
                        `<option value="${c.id}">${c.nom_categorie}</option>`));
                }
                if (flt) {
                    flt.innerHTML = '<option value="">Toutes les catégories</option>';
                    (dCat.data||[]).forEach(c => flt.insertAdjacentHTML('beforeend',
                        `<option value="${c.id}">${c.nom_categorie}</option>`));
                }
            }
        }

        // ── Toutes les sous-catégories (filtre tableau uniquement) ──
        const fltSub = document.getElementById('lb-filter-sous-categorie');
        if (fltSub) {
            fltSub.innerHTML = '<option value="">Toutes les sous-catégories</option>';
            (rAllSousCat||[]).forEach(sc => fltSub.insertAdjacentHTML('beforeend',
                `<option value="${sc.id}" data-categorie-id="${sc.categorie_id}">${sc.nom_categorie} — ${sc.nom}</option>`));
        }

        // ── Table lignes (dernier — dépend du budget) ────────────────
        await lb_initTable();

    } catch (err) {
        lb_hideLoader();
        Swal.fire('Erreur', err.message||'Erreur de chargement.', 'error');
    }
}

// ─── Soumission formulaire ────────────────────────────────────────────
async function lb_submitForm(e) {
    e.preventDefault();
    lb_clearErrors();

    const lineId = document.getElementById('lb-line-id').value;
    const isEdit = !!lineId;

    // Période
    const debut = document.getElementById('lb-periode-debut').value;
    const fin   = document.getElementById('lb-periode-fin').value;
    let periode = '';
    if (debut && fin) periode = `${debut}-${fin}`;
    else if (debut)   periode = debut;

    let hasError = false;

    const payload = {
        budgetId:               lb_budgetId,
        description:            document.getElementById('lb-description').value.trim(),
        periode_d_utilisation:  periode,
        categorie_id:           document.getElementById('lb-categorie').value,
        sous_categorie_id:      document.getElementById('lb-sous-rubrique').value,
        id_produit:             document.getElementById('lb-produit').value,
        quantite:               document.getElementById('lb-qte').value !== '' ? parseFloat(document.getElementById('lb-qte').value) : null,
        unite_id:               document.getElementById('lb-unite').value || null,
        prix_unitaire:          parseFloat(document.getElementById('lb-prix').value)||0,
    };
    if (isEdit) payload.lineId = parseInt(lineId);

    if (!payload.categorie_id) {
        lb_showError('lb-categorie','err-categorie','La catégorie est requise.');
        hasError = true;
    }
    if (!payload.sous_categorie_id) {
        lb_showError('lb-sous-rubrique','err-sous-rubrique','La sous-catégorie est requise.');
        hasError = true;
    }
    if (!payload.id_produit) {
        lb_showError('lb-produit','err-produit','Veuillez sélectionner un produit.');
        hasError = true;
    }
    if (!payload.quantite || payload.quantite <= 0) {
        lb_showError('lb-qte','err-qte','La quantité est requise et doit être supérieure à 0.');
        hasError = true;
    }
    if (payload.prix_unitaire <= 0) {
        lb_showError('lb-prix','err-prix','Le prix unitaire doit être supérieur à 0.');
        hasError = true;
    }
    if (!payload.unite_id) {
        lb_showError('lb-unite','err-unite','L\'unité est requise.');
        hasError = true;
    }

    // Période obligatoire + cohérence début ≤ fin
    const MOIS_ORDER = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    if (!debut) {
        lb_showError('lb-periode-debut','err-periode','Le mois de début est requis.');
        hasError = true;
    } else if (!fin) {
        const selFin = document.getElementById('lb-periode-fin');
        if (selFin) selFin.classList.add('lb-error');
        lb_showError('lb-periode-debut','err-periode','Le mois de fin est requis.');
        hasError = true;
    } else {
        const idxDebut = MOIS_ORDER.indexOf(debut);
        const idxFin   = MOIS_ORDER.indexOf(fin);
        if (idxFin < idxDebut) {
            const selFin = document.getElementById('lb-periode-fin');
            if (selFin) selFin.classList.add('lb-error');
            lb_showError('lb-periode-debut','err-periode',
                `Le mois de fin (${fin}) ne peut pas être antérieur au mois de début (${debut}).`);
            hasError = true;
        }
    }

    if (hasError) return;

    lb_showLoader(isEdit ? 'Modification…' : 'Création…');
    const url = isEdit ? lb_api.updateLine : lb_api.createLine;
    const r   = await lb_post(url, payload).catch(err => { lb_hideLoader(); Swal.fire('Erreur',err.message,'error'); return null; });
    lb_hideLoader();
    if (!r) return;
    const data = await lb_checkResp(r); if (!data) return;
    if (!data.success) { Swal.fire('Erreur', data.message||'Erreur.','error'); return; }

    lb_closeModal();
    await Swal.fire({icon:'success', title:'Succès', text:isEdit?'Ligne modifiée.':'Ligne créée.', timer:1500, showConfirmButton:false});
    await lb_initTable();
}

// ─── Édition d'une ligne ──────────────────────────────────────────────
async function lb_editLine(lineId) {
    // Budget verrouillé (Valider / Accepter) → aucune ligne active n'est modifiable
    if (lb_isBudgetVerrouille()) {
        Swal.fire('Budget verrouillé',`Le budget est au statut « ${lb_budgetStatut} » : les lignes ne peuvent plus être modifiées.`,'info');
        return;
    }
    const row = lb_table ? lb_table.rows().data().toArray().find(d => String(d.id)===String(lineId)) : null;
    if (row && lb_isLigneVerrouillee(row)) {
        Swal.fire('Ligne verrouillée','Cette ligne est verrouillée et ne peut pas être modifiée.','info');
        return;
    }
    lb_showLoader('Chargement…');
    const r = await lb_post(lb_api.lineById, {lineId});
    lb_hideLoader();
    if (!r.ok) { Swal.fire('Erreur','Ligne introuvable.','error'); return; }
    const d = await lb_checkResp(r); if (!d) return;
    if (d.status!=='success') { Swal.fire('Erreur',d.message,'error'); return; }

    const l = d.data;
    document.getElementById('lb-modal-title').textContent = 'Modifier la ligne';
    document.getElementById('lb-line-id').value   = l.id;
    document.getElementById('lb-budget-id').value = l.budget_id;
    document.getElementById('lb-description').value = l.description||'';

    const p = l.periode_d_utilisation||'';
    if (p.includes('-')) {
        const [d1,d2] = p.split('-').map(s => s.trim());
        document.getElementById('lb-periode-debut').value = d1;
        document.getElementById('lb-periode-fin').value   = d2;
    } else {
        document.getElementById('lb-periode-debut').value = p;
        document.getElementById('lb-periode-fin').value   = '';
    }

    await lb_loadCategories('lb-categorie');
    document.getElementById('lb-categorie').value = l.categorie_id||'';

    if (l.categorie_id) {
        await lb_loadSousCategories(l.categorie_id, 'lb-sous-rubrique');
    }
    document.getElementById('lb-sous-rubrique').value = l.sous_categorie_id||'';

    if (l.sous_categorie_id) {
        await lb_loadProduits(l.sous_categorie_id, 'lb-produit');
    }
    document.getElementById('lb-produit').value = l.id_produit||'';

    document.getElementById('lb-qte').value = l.quantite||'';
    await lb_loadUnites('lb-unite');
    document.getElementById('lb-unite').value = l.unite_id||'';
    document.getElementById('lb-prix').value  = l.prix_unitaire||'';

    lb_openModal();
}

// ─── Suppression ──────────────────────────────────────────────────────
async function lb_deleteLine(lineId) {
    // Budget verrouillé (Valider / Accepter) → aucune ligne active n'est supprimable
    if (lb_isBudgetVerrouille()) {
        Swal.fire('Budget verrouillé',`Le budget est au statut « ${lb_budgetStatut} » : les lignes ne peuvent plus être supprimées.`,'info');
        return;
    }
    const row = lb_table ? lb_table.rows().data().toArray().find(d => String(d.id)===String(lineId)) : null;
    if (row && lb_isLigneVerrouillee(row)) {
        Swal.fire('Ligne verrouillée','Cette ligne est verrouillée et ne peut pas être supprimée.','info');
        return;
    }
    const conf = await Swal.fire({
        title:'Confirmer', text:'Retirer cette ligne ?',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'Oui, retirer', cancelButtonText:'Annuler', confirmButtonColor:'#1a7a5e'
    });
    if (!conf.isConfirmed) return;
    lb_showLoader('Suppression…');
    const r = await lb_post(lb_api.deleteLine, {lineId:parseInt(lineId)});
    lb_hideLoader();
    const d = await lb_checkResp(r); if (!d) return;
    if (!d.success) { Swal.fire('Erreur',d.message,'error'); return; }
    await Swal.fire({icon:'success',title:'Succès',text:'Ligne retirée.',timer:1500,showConfirmButton:false});
    await lb_initTable();
}

// ─── Valider le budget ────────────────────────────────────────────────
async function lb_validerBudget() {
    const conf = await Swal.fire({
        title:'Valider le budget ?',
        html:'<p style="color:#374151;font-size:.875rem;">Une fois validé, <strong>aucune modification</strong> ne sera plus possible sur les lignes budgétaires.</p>',
        icon:'question', showCancelButton:true,
        confirmButtonText:'Oui, valider', cancelButtonText:'Annuler', confirmButtonColor:'#1a7a5e'
    });
    if (!conf.isConfirmed) return;
    lb_showLoader('Validation du budget…');
    const r = await lb_post(lb_api.validerBudget, {budgetId: lb_budgetId});
    lb_hideLoader();
    const d = await lb_checkResp(r); if (!d) return;
    if (!d.success) { Swal.fire('Erreur',d.message,'error'); return; }
    // IMPORTANT : statut mis à jour AVANT lb_initTable()
    // → lb_updateCounters() comptera toutes les lignes actives comme verrouillées
    lb_budgetStatut = 'Valider';
    document.getElementById('lb-add-btn')?.style && (document.getElementById('lb-add-btn').style.display='none');
    document.getElementById('lb-validate-btn')?.style && (document.getElementById('lb-validate-btn').style.display='none');
    await Swal.fire({icon:'success',title:'Budget validé !',text:d.message,confirmButtonColor:'#1a7a5e'});
    await lb_initTable();
}

// ─── Création rapide catégorie / sous-catégorie ───────────────────────
async function lb_createCategorie() {
    lb_openInlineModal('Nouvelle catégorie', async (nom) => {
        lb_showLoader('Création…');
        const r = await lb_post(lb_api.createCat, {nom});
        lb_hideLoader();
        const d = await lb_checkResp(r); if (!d) return;
        if (d.status!=='success') { Swal.fire('Erreur',d.message,'error'); return; }
        await lb_loadCategories('lb-categorie');
        document.getElementById('lb-categorie').value = d.new_id;
        document.getElementById('lb-sous-rubrique').innerHTML='<option value="">Choisir une sous-catégorie</option>';
    });
}

async function lb_createSousCategorie() {
    const catId = document.getElementById('lb-categorie').value;
    if (!catId) { Swal.fire('Info','Sélectionnez d\'abord une catégorie.','info'); return; }
    lb_openInlineModal('Nouvelle sous-catégorie', async (nom) => {
        lb_showLoader('Création…');
        const r = await lb_post(lb_api.createSubCat, {nom, categorie_id:parseInt(catId)});
        lb_hideLoader();
        const d = await lb_checkResp(r); if (!d) return;
        if (d.status!=='success') { Swal.fire('Erreur',d.message,'error'); return; }
        await lb_loadSousCategories(catId,'lb-sous-rubrique');
        document.getElementById('lb-sous-rubrique').value = d.new_id;
    });
}

// ─── Initialisation ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    lb_budgetId = (typeof window.LB_BUDGET_TOKEN!=='undefined' && window.LB_BUDGET_TOKEN)
        ? window.LB_BUDGET_TOKEN : null;
    if (!lb_budgetId) { Swal.fire('Erreur','Identifiant budget introuvable.','error'); return; }

    document.getElementById('lb-back-btn')?.addEventListener('click', ()=> // Redirect to another page
        window.location.href = "/personnel/compta_liste_budget_fonctionnement");

    document.getElementById('lb-add-btn')?.addEventListener('click', ()=>{
        document.getElementById('lb-modal-title').textContent='Nouvelle ligne budgétaire';
        document.getElementById('lb-form').reset();
        document.getElementById('lb-line-id').value='';
        document.getElementById('lb-budget-id').value=lb_budgetId;
        lb_clearErrors();
        lb_loadCategories('lb-categorie');
        lb_loadUnites('lb-unite');
        document.getElementById('lb-sous-rubrique').innerHTML='<option value="">Choisir une sous-catégorie</option>';
        document.getElementById('lb-produit').innerHTML='<option value="">— Sélectionner une sous-catégorie —</option>';
        lb_openModal();
    });
    document.getElementById('lb-modal-close')?.addEventListener('click', lb_closeModal);
    document.getElementById('lb-cancel')?.addEventListener('click', lb_closeModal);
    document.getElementById('lb-modal')?.addEventListener('mousedown', e=>{
        if (e.target.id==='lb-modal') lb_closeModal();
    });

    document.getElementById('lb-validate-btn')?.addEventListener('click', lb_validerBudget);
    document.getElementById('lb-form')?.addEventListener('submit', lb_submitForm);

    // Catégorie → sous-catégorie (modale)
    document.getElementById('lb-categorie')?.addEventListener('change', async function(){
        await lb_loadSousCategories(this.value,'lb-sous-rubrique');
        document.getElementById('lb-produit').innerHTML='<option value="">— Sélectionner une sous-catégorie —</option>';
    });

    // Sous-catégorie → charger les produits filtrés
    document.getElementById('lb-sous-rubrique')?.addEventListener('change', async function(){
        await lb_loadProduits(this.value, 'lb-produit');
    });

    // Filtres tableau : catégorie → restreint la sous-catégorie affichée
    document.getElementById('lb-filter-categorie')?.addEventListener('change', function() {
        const catId = this.value;
        const fltSub = document.getElementById('lb-filter-sous-categorie');
        if (fltSub) {
            [...fltSub.options].forEach(opt => {
                if (!opt.value) { opt.hidden = false; return; }
                opt.hidden = catId && opt.dataset.categorieId !== catId;
            });
            fltSub.value = '';
        }
        lb_initTable();
    });
    document.getElementById('lb-filter-sous-categorie')?.addEventListener('change', () => lb_initTable());

    document.getElementById('lb-create-rub')?.addEventListener('click', lb_createCategorie);
    document.getElementById('lb-create-subrub')?.addEventListener('click', lb_createSousCategorie);

    document.getElementById('lb-table')?.addEventListener('click', e=>{
        const editBtn = e.target.closest('.lb-action--edit');
        const delBtn  = e.target.closest('.lb-action--del');
        if (editBtn) lb_editLine(editBtn.dataset.id);
        if (delBtn)  lb_deleteLine(delBtn.dataset.id);
    });

    await lb_loadBudgetInfo();
});