// ═══════════════════════════════════════════════════════════════════════
// COMPTABLE — Liste des produits (stock)
// POST JSON → comptableController
// Design system identique au module Lignes Budget (modales overlay custom)
// ═══════════════════════════════════════════════════════════════════════

const CP_API = '/personnel/compta_basi_controller';
const cp_api = {
    categories:       `${CP_API}?option=1`,
    sousCategories:   `${CP_API}?option=2`,
    createCategorie:  `${CP_API}?option=3`,
    updateCategorie:  `${CP_API}?option=4`,
    deleteCategorie:  `${CP_API}?option=5`,
    createSousCat:    `${CP_API}?option=6`,
    updateSousCat:    `${CP_API}?option=7`,
    deleteSousCat:    `${CP_API}?option=8`,
    statuts:          `${CP_API}?option=9`,
    produits:         `${CP_API}?option=10`,
    createProduits:   `${CP_API}?option=11`,
    updateProduit:    `${CP_API}?option=13`,
    toggleStatut:     `${CP_API}?option=14`,
    alertes:          `${CP_API}?option=15`,
};

// ─── État global ──────────────────────────────────────────────────────
let cp_table            = null;
let cp_allSousCategories = [];
let cp_allProduits       = [];

// ─── Réseau ───────────────────────────────────────────────────────────
function cp_sessionExpired() {
    cp_hideLoader();
    Swal.fire({
        icon:'warning', title:'Session expirée',
        text:'Votre session a expiré. Vous allez être redirigé.',
        timer:2500, showConfirmButton:false,
        didClose:() => { window.location.href='/signin'; }
    });
}

async function cp_post(url, body={}, timeout=10000) {
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

async function cp_checkResp(r) {
    const d = await r.json();
    if (d && d.code==='sessionExpired') { cp_sessionExpired(); return null; }
    return d;
}

// ─── Loader ───────────────────────────────────────────────────────────
function cp_showLoader(msg='Chargement…') {
    document.getElementById('global-loader')?.remove();
    const el = document.createElement('div');
    el.id = 'global-loader';
    el.style.cssText = 'position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;';
    el.innerHTML =
        '<div style="position:absolute;inset:0;background:rgba(4,20,11,.5);backdrop-filter:blur(4px);"></div>'
        +'<div style="position:relative;background:#fff;border-radius:16px;padding:2rem 3rem;display:flex;flex-direction:column;align-items:center;gap:1rem;box-shadow:0 24px 60px rgba(0,0,0,.18);min-width:200px;">'
        +'<svg width="40" height="40" viewBox="0 0 50 50" style="animation:cp-spin .9s linear infinite;">'
        +'<circle cx="25" cy="25" r="20" fill="none" stroke="#1a7a5e" stroke-width="4" stroke-dasharray="80" stroke-dashoffset="60" stroke-linecap="round" style="animation:cp-dash 1.4s ease-in-out infinite;"/>'
        +'</svg><p style="margin:0;font-size:.82rem;font-weight:600;color:#1a7a5e;">'+msg+'</p></div>';
    if (!document.getElementById('cp-spin-style')) {
        const s=document.createElement('style'); s.id='cp-spin-style';
        s.textContent='@keyframes cp-spin{to{transform:rotate(360deg)}}@keyframes cp-dash{0%{stroke-dashoffset:80}50%{stroke-dashoffset:20}100%{stroke-dashoffset:80}}';
        document.head.appendChild(s);
    }
    document.body.appendChild(el);
}
function cp_hideLoader() { document.getElementById('global-loader')?.remove(); }

// ─── Modales overlay (mêmes patterns que le module lignes budget) ─────
function cp_openModal(id) { document.getElementById(id)?.classList.add('open'); }
function cp_closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

function cp_clearErrors(scopeId) {
    const scope = scopeId ? document.getElementById(scopeId) : document;
    scope?.querySelectorAll('.cp-inp.cp-error').forEach(el => el.classList.remove('cp-error'));
    scope?.querySelectorAll('.cp-err-msg.visible').forEach(el => { el.classList.remove('visible'); el.textContent=''; });
}
function cp_showError(inputId, msgId, message) {
    const inp = document.getElementById(inputId);
    const msg = document.getElementById(msgId);
    if (inp) inp.classList.add('cp-error');
    if (msg) { msg.textContent = message; msg.classList.add('visible'); }
}

// ─── Chargement initial — tout en parallèle ───────────────────────────
async function cp_init() {
    cp_showLoader('Chargement…');
    try {
        const [rCat, rSubCat, rStatut, rProd] = await Promise.all([
            cp_post(cp_api.categories, {}),
            cp_post(cp_api.sousCategories, {}),
            cp_post(cp_api.statuts, {}),
            cp_post(cp_api.produits, {}),
        ]);

        const dCat = await cp_checkResp(rCat);
        if (dCat && dCat.status==='success') {
            const sel = document.getElementById('cp-cat-select');
            if (sel) {
                sel.innerHTML = '<option value="">Toutes</option>';
                dCat.data.forEach(c => sel.insertAdjacentHTML('beforeend', `<option value="${c.id}">${c.nom_categorie}</option>`));
            }
        }

        const dSubCat = await cp_checkResp(rSubCat);
        if (dSubCat && dSubCat.status==='success') {
            cp_allSousCategories = dSubCat.data || [];
            cp_remplirSubcatSelect();
        }

        const dStatut = await cp_checkResp(rStatut);
        if (dStatut && dStatut.status==='success') {
            const sel = document.getElementById('cp-etat-select');
            if (sel) {
                sel.innerHTML = '<option value="">Tous</option>';
                dStatut.data.forEach(s => sel.insertAdjacentHTML('beforeend', `<option value="${s.id_statut}">${s.nom_statut}</option>`));
            }
        }

        const dProd = await cp_checkResp(rProd);
        cp_hideLoader();
        if (dProd && dProd.status==='success') {
            cp_allProduits = dProd.data || [];
            cp_updateStats(cp_allProduits);
            cp_renderTable(cp_allProduits);
        } else {
            Swal.fire('Erreur','Impossible de charger les produits.','error');
        }

    } catch (err) {
        cp_hideLoader();
        Swal.fire('Erreur', err.message||'Erreur de chargement.', 'error');
    }
}

function cp_remplirSubcatSelect(filtreCategorieId='') {
    const sel = document.getElementById('cp-subcat-select');
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = '<option value="">Toutes</option>';
    const filtered = filtreCategorieId
        ? cp_allSousCategories.filter(sc => String(sc.categorie_id) === String(filtreCategorieId))
        : cp_allSousCategories;
    filtered.forEach(sc => sel.insertAdjacentHTML('beforeend', `<option value="${sc.id}">${sc.nom}</option>`));
    if ([...sel.options].some(o => o.value === cur)) sel.value = cur;
}

// ─── Stats cards ──────────────────────────────────────────────────────
function cp_updateStats(produits) {
    const total    = produits.length;
    const actifs   = produits.filter(p => parseInt(p.id_statut) === 1).length;
    const inactifs = total - actifs;
    const alertes  = produits.filter(p => parseFloat(p.Stock_actuel) <= parseFloat(p.Seuil_limite||0)).length;
    const pct      = total > 0 ? Math.round((actifs/total)*100) : 0;

    const el = id => document.getElementById(id);
    if (el('cp-stat-total'))       el('cp-stat-total').textContent = total;
    if (el('cp-stat-actifs'))      el('cp-stat-actifs').textContent = actifs;
    if (el('cp-stat-actifs-pct'))  el('cp-stat-actifs-pct').textContent = pct+'% du catalogue';
    if (el('cp-stat-inactifs'))    el('cp-stat-inactifs').textContent = inactifs;
    if (el('cp-stat-alertes'))     el('cp-stat-alertes').textContent = alertes;
}

// ─── DataTable produits ───────────────────────────────────────────────
function cp_renderTable(produits) {
    if (cp_table) { cp_table.destroy(); document.getElementById('cp-sales-table').innerHTML=''; cp_table=null; }

    const columns = [
        { data:'nom_categorie',  title:'Catégorie',      defaultContent:'—' },
        { data:'nom_souscat',    title:'Sous-catégorie', defaultContent:'—' },
        { data:'nomproduit',     title:'Nom du produit', defaultContent:'—' },
        { data:null, title:'Stock', render: d => {
                const stock = parseFloat(d.Stock_actuel)||0;
                const seuil = parseFloat(d.Seuil_limite)||0;
                const sousLeSeuil = stock <= seuil;
                return `<span style="font-weight:700;${sousLeSeuil?'color:#dc2626;':'color:#111827;'}">${stock}</span>`
                    + (sousLeSeuil ? ' <span class="cp-badge cp-badge--alert">⚠ seuil</span>' : '');
            }},
        { data:'Total',   title:'Total',   defaultContent:'0' },
        { data:'retrait', title:'Retrait', defaultContent:'0' },
        { data:'date_creation', title:'Date', defaultContent:'—', render: d => {
                if (!d) return '—';
                const dt = new Date(d.replace(' ', 'T'));
                if (isNaN(dt)) return d;
                const pad = n => String(n).padStart(2,'0');
                return `${dt.getFullYear()}/${pad(dt.getMonth()+1)}/${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}:${pad(dt.getSeconds())}`;
            }},
        { data:null, title:'État', render: d => {
                const actif = parseInt(d.id_statut) === 1;
                return actif
                    ? `<span class="cp-badge cp-badge--ok">${d.nom_statut||'Actif'}</span>`
                    : `<span class="cp-badge cp-badge--off">${d.nom_statut||'Inactif'}</span>`;
            }},
        { data:null, title:'Actions', orderable:false, render: d => {
                const modifierBtn = parseInt(d.utilise) === 0
                    ? `<button class="cp-action cp-action--edit" data-id="${d.idP}" title="Modifier">
                       <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                       Modifier
                   </button>` : '';
                const toggleBtn = `<button class="cp-action cp-action--toggle" data-id="${d.idP}" data-statut="${d.id_statut}">
                    ${parseInt(d.id_statut)===1 ? '⛔ Désactiver' : '✅ Activer'}
                </button>`;
                return `<div class="cp-actions">${modifierBtn}${toggleBtn}</div>`;
            }},
    ];

    cp_table = $('#cp-sales-table').DataTable({
        data: produits,
        columns,
        pageLength: 25,
        lengthChange: true,
        language: {
            emptyTable: '<div style="padding:2rem;text-align:center;color:#9ca3af;">'
                +'<div style="font-weight:700;font-size:.875rem;margin-bottom:.25rem;">Aucun produit</div>'
                +'<div style="font-size:.78rem;">Cliquez sur « Nouveau produit » pour commencer</div></div>',
            info: '<span style="font-size:.78rem;color:#6b7280;">Affichage <strong>_START_</strong> à <strong>_END_</strong> sur <strong>_TOTAL_</strong> produits</span>',
            infoEmpty: '<span style="font-size:.78rem;color:#6b7280;">Aucun produit</span>',
            search: '<span style="font-size:.75rem;font-weight:600;color:#9ca3af;">Rechercher :</span>',
            zeroRecords: 'Aucun résultat pour cette recherche',
            paginate: { first:'«', last:'»', next:'›', previous:'‹' },
            lengthMenu: 'Afficher _MENU_ produits'
        },
        dom: '<"cp-dt-top d-flex align-items-center justify-content-between gap-2"lf>rt<"cp-dt-bottom d-flex align-items-center justify-content-between gap-2"ip>',
        order: [[2,'asc']],
        responsive: true,
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });

    // Filtres custom DataTable
    $.fn.dataTable.ext.search = [];
    $.fn.dataTable.ext.search.push((settings, searchData, idx, rowData) => {
        const catId    = document.getElementById('cp-cat-select')?.value;
        const subcatId = document.getElementById('cp-subcat-select')?.value;
        const etatId   = document.getElementById('cp-etat-select')?.value;
        const stockFlt = document.getElementById('cp-stock-select')?.value;

        if (catId) {
            const sc = cp_allSousCategories.find(s => String(s.id) === String(rowData.id_Sous_categorie));
            if (!sc || String(sc.categorie_id) !== String(catId)) return false;
        }
        if (subcatId && String(rowData.id_Sous_categorie) !== String(subcatId)) return false;
        if (etatId && String(rowData.id_statut) !== String(etatId)) return false;

        if (stockFlt) {
            const stock = parseFloat(rowData.Stock_actuel)||0;
            const seuil = parseFloat(rowData.Seuil_limite)||0;
            if (stockFlt === 'en_stock' && stock <= 0) return false;
            if (stockFlt === 'rupture'  && stock > 0)  return false;
            if (stockFlt === 'alerte'   && (stock > seuil || stock <= 0)) return false;
        }
        return true;
    });

    if (document.getElementById('cp-stock-select')?.children.length <= 1) {
        document.getElementById('cp-stock-select').innerHTML = `
            <option value="">Tous</option>
            <option value="en_stock">En stock</option>
            <option value="alerte">Alerte de stock</option>
            <option value="rupture">Rupture de stock</option>`;
    }

    document.getElementById('cp-cat-select')?.addEventListener('change', function () {
        cp_remplirSubcatSelect(this.value);
        document.getElementById('cp-subcat-select').value = '';
        cp_table.draw();
    });
    ['cp-subcat-select','cp-stock-select','cp-etat-select'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => cp_table.draw());
    });
    document.getElementById('cp-name-input')?.addEventListener('keyup', function () {
        cp_table.column(2).search(this.value).draw();
    });
    document.getElementById('cp-reset-filters')?.addEventListener('click', () => {
        ['cp-cat-select','cp-stock-select','cp-etat-select','cp-subcat-select'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value='';
        });
        document.getElementById('cp-name-input').value = '';
        cp_table.search('').columns().search('').draw();
    });

    cp_table.draw();
}

// ─── Modale Ajout de produits (multiple) ──────────────────────────────
async function cp_remplirSousCatSelect(target) {
    const r = await cp_post(cp_api.sousCategories, {});
    const d = await cp_checkResp(r); if (!d || d.status!=='success') return;
    target.innerHTML = '<option value="">Sélectionner une sous-catégorie</option>';
    d.data.forEach(sc => target.insertAdjacentHTML('beforeend', `<option value="${sc.id}">${sc.nom_categorie} — ${sc.nom}</option>`));
}

function cp_ajouterLigneProduit() {
    const container = document.getElementById('cp-zone-multi-produits');
    const idx = container.children.length + 1;
    const div = document.createElement('div');
    div.className = 'cp-prod-row';
    div.innerHTML = `
        <div class="cp-prod-row__title">Produit ${idx}</div>
        <button type="button" class="cp-prod-row__remove" title="Retirer">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="cp-grid-2">
            <div class="cp-field">
                <label class="cp-lbl">Sous-catégorie <span style="color:#ef4444;">*</span></label>
                <select name="idSC[]" class="cp-inp" required></select>
            </div>
            <div class="cp-field">
                <label class="cp-lbl">Nom du produit <span style="color:#ef4444;">*</span></label>
                <input type="text" name="productName[]" class="cp-inp" placeholder="Nom" required>
            </div>
        </div>
        <div class="cp-field" style="margin-bottom:0;">
            <label class="cp-lbl">Seuil d'alerte <span style="color:#ef4444;">*</span></label>
            <input type="number" name="seuil[]" class="cp-inp" placeholder="Seuil" min="1" required>
        </div>`;
    container.appendChild(div);
    cp_remplirSousCatSelect(div.querySelector('select'));
    div.querySelector('.cp-prod-row__remove').addEventListener('click', () => {
        div.remove();
        // Renuméroter les titres restants
        [...container.children].forEach((row, i) => {
            row.querySelector('.cp-prod-row__title').textContent = `Produit ${i+1}`;
        });
    });
}

async function cp_submitCreateProduits(e) {
    e.preventDefault();
    const noms   = [...document.querySelectorAll('input[name="productName[]"]')].map(i => i.value.trim());
    const seuils = [...document.querySelectorAll('input[name="seuil[]"]')].map(i => i.value);
    const scIds  = [...document.querySelectorAll('select[name="idSC[]"]')].map(s => s.value);

    const produits = noms.map((nom, i) => ({
        nom, seuil: parseInt(seuils[i])||0, sous_categorie_id: parseInt(scIds[i])||null,
    })).filter(p => p.nom && p.sous_categorie_id);

    if (!produits.length) { Swal.fire('Erreur','Aucun produit valide à créer.','error'); return; }

    cp_showLoader('Création…');
    const r = await cp_post(cp_api.createProduits, {produits}).catch(err => { cp_hideLoader(); Swal.fire('Erreur',err.message,'error'); return null; });
    cp_hideLoader();
    if (!r) return;
    const data = await cp_checkResp(r); if (!data) return;

    if (data.status!=='success') { Swal.fire('Erreur', data.message||'Erreur.','error'); return; }

    document.getElementById('cp-form-ajout-multiple').reset();
    document.getElementById('cp-zone-multi-produits').innerHTML = '';
    cp_closeModal('cp-modal-ajout-produit');
    await Swal.fire({icon:'success', title:'Succès', text:data.message, timer:2000, showConfirmButton:false});
    await cp_init();
}

// ─── Édition d'un produit ─────────────────────────────────────────────
// Seuls le nom, la sous-catégorie et le seuil d'alerte sont modifiables.
async function cp_editProduit(id) {
    const prod = cp_allProduits.find(p => String(p.idP) === String(id));
    if (!prod) return;

    document.getElementById('cp-idP').value = id;
    document.getElementById('cp-nomProduit').value = prod.nomproduit;
    document.getElementById('cp-seuil').value = prod.Seuil_limite;

    await cp_remplirSousCatSelect(document.getElementById('cp-sousCategorie'));
    document.getElementById('cp-sousCategorie').value = prod.id_Sous_categorie;

    cp_openModal('cp-modal-produit');
}

async function cp_submitUpdateProduit(e) {
    e.preventDefault();
    cp_clearErrors();

    const payload = {
        id:                parseInt(document.getElementById('cp-idP').value),
        nom:               document.getElementById('cp-nomProduit').value.trim(),
        sous_categorie_id: parseInt(document.getElementById('cp-sousCategorie').value)||null,
        seuil:             parseInt(document.getElementById('cp-seuil').value)||0,
    };

    let hasError = false;
    if (!payload.nom) { cp_showError('cp-nomProduit', 'cp-err-nom', 'Le nom du produit est requis.'); hasError = true; }
    if (!payload.sous_categorie_id) { cp_showError('cp-sousCategorie', 'cp-err-souscat', 'La sous-catégorie est requise.'); hasError = true; }
    if (hasError) return;

    cp_showLoader('Modification…');
    const r = await cp_post(cp_api.updateProduit, payload).catch(err => { cp_hideLoader(); Swal.fire('Erreur',err.message,'error'); return null; });
    cp_hideLoader();
    if (!r) return;
    const data = await cp_checkResp(r); if (!data) return;

    if (data.status!=='success') { Swal.fire('Erreur', data.message||'Erreur.','error'); return; }

    cp_closeModal('cp-modal-produit');
    await Swal.fire({icon:'success', title:'Succès', text:data.message, timer:1500, showConfirmButton:false});
    await cp_init();
}

// ─── Activation / désactivation ──────────────────────────────────────
async function cp_toggleStatut(id, currentStatut) {
    const newStatut = String(currentStatut) === '1' ? 2 : 1;
    const conf = await Swal.fire({
        title: newStatut === 1 ? 'Activer ce produit ?' : 'Désactiver ce produit ?',
        icon:'question', showCancelButton:true,
        confirmButtonText:'Oui', cancelButtonText:'Annuler', confirmButtonColor:'#1a7a5e'
    });
    if (!conf.isConfirmed) return;

    cp_showLoader('Mise à jour…');
    const r = await cp_post(cp_api.toggleStatut, {id: parseInt(id), statut:newStatut});
    cp_hideLoader();
    const d = await cp_checkResp(r); if (!d) return;
    if (d.status!=='success') { Swal.fire('Erreur', d.message,'error'); return; }
    await Swal.fire({icon:'success', title:'Succès', text:d.message, timer:1500, showConfirmButton:false});
    await cp_init();
}

// ─── Notifications de réapprovisionnement ─────────────────────────────
async function cp_chargerNotifications() {
    const r = await cp_post(cp_api.alertes, {}).catch(()=>null);
    if (!r) return;
    const d = await cp_checkResp(r); if (!d) return;

    const badge   = document.getElementById('cp-notif-count');
    const contenu = document.getElementById('cp-contenu-alertes');

    if (d.status !== 'success') {
        if (badge) badge.style.display = 'none';
        if (contenu) contenu.innerHTML = '<p style="color:#9ca3af;">Erreur lors du chargement.</p>';
        return;
    }

    let produits = d.data || [];

    if (!produits.length) {
        if (badge) badge.style.display = 'none';
        if (contenu) contenu.innerHTML = '<p style="color:#065f46;background:#d1fae5;padding:.85rem 1rem;border-radius:10px;text-align:center;">Aucune alerte de réapprovisionnement.</p>';
        return;
    }

    // Badge : compteur plafonné à 99+ pour rester lisible
    if (badge) {
        badge.textContent = produits.length > 99 ? '99+' : produits.length;
        badge.style.display = 'flex';
    }

    // Tri par sévérité : rupture totale (stock=0) d'abord, puis par écart au seuil croissant
    produits = [...produits].sort((a, b) => {
        const stockA = parseFloat(a.Stock_actuel)||0, stockB = parseFloat(b.Stock_actuel)||0;
        if (stockA === 0 && stockB !== 0) return -1;
        if (stockB === 0 && stockA !== 0) return 1;
        const ecartA = (parseFloat(a.Seuil_limite)||0) - stockA;
        const ecartB = (parseFloat(b.Seuil_limite)||0) - stockB;
        return ecartB - ecartA; // écart le plus critique en premier
    });

    const ruptures = produits.filter(p => (parseFloat(p.Stock_actuel)||0) === 0).length;

    if (contenu) {
        const recap = `
            <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;padding-bottom:.85rem;border-bottom:1px solid #f3f4f6;flex-wrap:wrap;">
                <span class="cp-badge cp-badge--alert">${produits.length} produit${produits.length>1?'s':''} sous le seuil</span>
                ${ruptures > 0 ? `<span class="cp-badge cp-badge--off">${ruptures} en rupture totale</span>` : ''}
            </div>`;

        const items = produits.map(p => {
            const enRupture = (parseFloat(p.Stock_actuel)||0) === 0;
            return `
            <div class="cp-alert-item" style="${enRupture ? 'border-left-color:#991b1b;' : ''}">
                <div>
                    <div class="cp-alert-item__name">${p.nomproduit}${enRupture ? ' <span class="cp-badge cp-badge--off" style="margin-left:.3rem;">Rupture</span>' : ''}</div>
                    <div class="cp-alert-item__meta">${p.nom_categorie||''} ${p.souscat ? '› '+p.souscat : ''}</div>
                    <div style="margin-top:.4rem;display:flex;gap:.4rem;">
                        <span class="cp-badge cp-badge--off">Stock : ${p.Stock_actuel}</span>
                        <span class="cp-badge cp-badge--alert">Seuil : ${p.Seuil_limite}</span>
                    </div>
                </div>
                <button class="cp-btn cp-btn--ghost" style="padding:.4rem .75rem;font-size:.72rem;flex-shrink:0;" onclick="cp_filtrerProduit('${p.nomproduit.replace(/'/g,"\\'")}')">📌 Voir</button>
            </div>`;
        }).join('');

        contenu.innerHTML = recap + items;
    }
}

function cp_filtrerProduit(nom) {
    document.getElementById('cp-name-input').value = nom;
    cp_table?.column(2).search(nom).draw();
    cp_closeModal('cp-modal-alerte-stock');
    document.getElementById('cp-sales-table')?.scrollIntoView({behavior:'smooth'});
}

// ─── Initialisation ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cp_init();
    cp_chargerNotifications();
    setInterval(cp_chargerNotifications, 60000);

    // Modale ajout multiple
    document.getElementById('cp-btn-ajout-produit')?.addEventListener('click', () => {
        document.getElementById('cp-zone-multi-produits').innerHTML = '';
        cp_ajouterLigneProduit();
        cp_openModal('cp-modal-ajout-produit');
    });
    document.getElementById('cp-modal-ajout-close')?.addEventListener('click', () => cp_closeModal('cp-modal-ajout-produit'));
    document.getElementById('cp-btn-add-row')?.addEventListener('click', cp_ajouterLigneProduit);
    document.getElementById('cp-form-ajout-multiple')?.addEventListener('submit', cp_submitCreateProduits);

    // Modale modification produit
    document.getElementById('cp-modal-produit-close')?.addEventListener('click', () => cp_closeModal('cp-modal-produit'));
    document.getElementById('cp-produit-cancel')?.addEventListener('click', () => cp_closeModal('cp-modal-produit'));
    document.getElementById('cp-form-produit')?.addEventListener('submit', cp_submitUpdateProduit);

    // Modale alertes de réapprovisionnement
    document.getElementById('cp-modal-alerte-close')?.addEventListener('click', () => cp_closeModal('cp-modal-alerte-stock'));

    // Fermeture overlay au clic en dehors
    ['cp-modal-ajout-produit','cp-modal-produit','cp-modal-alerte-stock'].forEach(id => {
        document.getElementById(id)?.addEventListener('mousedown', e => {
            if (e.target.id === id) cp_closeModal(id);
        });
    });

    // Délégation actions table
    document.getElementById('cp-sales-table')?.addEventListener('click', e => {
        const editBtn   = e.target.closest('.cp-action--edit');
        const toggleBtn = e.target.closest('.cp-action--toggle');
        if (editBtn)   cp_editProduit(editBtn.dataset.id);
        if (toggleBtn) cp_toggleStatut(toggleBtn.dataset.id, toggleBtn.dataset.statut);
    });

    // Notifications
    document.getElementById('cp-btn-notifications')?.addEventListener('click', () => {
        cp_openModal('cp-modal-alerte-stock');
        cp_chargerNotifications();
    });
});