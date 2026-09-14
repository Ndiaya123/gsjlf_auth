// ════════════════════════════════════════════════════════════════════════
// ADD LINES TO DEMANDE — Chef de service
// URL htaccess : /chef_service_associe_ligne_budget_demande/{token}/{type}
// → add-lines-to-demandes.php?token=TOKEN_DEMANDE&type=TOKEN_TYPE
//
// token : token chiffré de la demande (idEB)
// type  : token chiffré du type (tokenencrypt(1) ou tokenencrypt(2))
//         1 = demande_achat | 2 = demande_paiement
//
// Cases : 41=détails demande | 35=lignes budget | 37=ajouter | 38=existantes | 40=services
//
// ── MODIFICATIONS APPORTÉES ────────────────────────────────────────────────
// Contrôle du montant restant pour les lignes "Autre" (paiement), symétrique
// au contrôle de quantité restante déjà existant pour les lignes "Produit"
// (achat). S'appuie sur row.montant_restant renvoyé par le back-end (case 35)
// et vérifié à nouveau côté serveur (case 37).
// ════════════════════════════════════════════════════════════════════════

const AL_API = '/personnel/chef_service_basi_controller';
const al_api = {
    demande      : `${AL_API}?option=41`,
    lignesBudget : `${AL_API}?option=35`,
    ajouterLignes: `${AL_API}?option=37`,
    lignesExist  : `${AL_API}?option=38`,
    modifierLigne: `${AL_API}?option=49`,
    supprimerLigne:`${AL_API}?option=50`,
    soumettre    : `${AL_API}?option=51`,
    services     : `${AL_API}?option=40`,
};

// ─── Paramètres URL (depuis htaccess ?token=X&type=Y) ─────────────────────────
const AL_PARAMS = (() => {
    // Priorité : variables injectées par PHP (window.AL_DEMANDE_TOKEN)
    // Fallback : query string ?token=X&type=Y (htaccess)
    const p = new URLSearchParams(window.location.search);
    return {
        token : window.AL_DEMANDE_TOKEN || p.get('token') || null,
        type  : window.AL_TYPE_TOKEN    || p.get('type')  || null,
    };
})();

// ─── État ─────────────────────────────────────────────────────────────────────
let al_demande        = null;
let al_tableBudget    = null;
let al_tableExist     = null;
let al_lignesChoisies = [];
let al_budgetToken    = null;

// ─── Réseau ───────────────────────────────────────────────────────────────────
function al_expired() {
    Swal.fire({ icon:'warning', title:'Session expirée', text:'Redirection…',
        timer:2500, showConfirmButton:false,
        didClose:()=>{ window.location.href = '/personnel/signin'; }
    });
}
async function al_post(url, body = {}, timeout = 12000) {
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
        if (d?.code === 'sessionExpired') { al_expired(); return null; }
        return d;
    } catch (e) {
        clearTimeout(t);
        if (e.name === 'AbortError') throw new Error('Délai dépassé.');
        throw e;
    }
}

// ─── Loader ───────────────────────────────────────────────────────────────────
function al_showLoader(msg = 'Chargement…') {
    $('#al-loader').remove();
    $('body').append(`
        <div id="al-loader">
            <div class="al-loader-bg"></div>
            <div class="al-loader-box">
                <svg class="al-loader-spin" viewBox="0 0 50 50">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
                </svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function al_hideLoader() { $('#al-loader').remove(); }

// ─── Formatage ────────────────────────────────────────────────────────────────
const al_fmtNum  = v => new Intl.NumberFormat('fr-FR').format(Number(v || 0)) + '\u00a0FCFA';
const al_fmtDate = d => d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—';
const al_fmtK    = v => {
    if (v >= 1e9) return (v / 1e9).toFixed(1) + ' Md';
    if (v >= 1e6) return (v / 1e6).toFixed(1) + ' M';
    if (v >= 1e3) return (v / 1e3).toFixed(0) + ' K';
    return new Intl.NumberFormat('fr-FR').format(v);
};

// ─── Badges ───────────────────────────────────────────────────────────────────
function al_badgeType(t, idType) {
    const isAchat = (t === 'demande_achat' || parseInt(idType) === 1);
    return isAchat
        ? '<span class="al-badge al-badge-achat">Achat</span>'
        : '<span class="al-badge al-badge-paiement">Paiement</span>';
}
function al_badgeStatut(s) {
    const map = {
        'En création': '<span class="al-badge al-badge-draft">En création</span>',
        'Validée'    : '<span class="al-badge al-badge-ok">Validée</span>',
        'Rejetée'    : '<span class="al-badge al-badge-err">Rejetée</span>',
    };
    return map[s] || `<span class="al-badge al-badge-draft">${s || 'En création'}</span>`;
}

// ─── Init ─────────────────────────────────────────────────────────────────────
async function al_init() {
    if (!AL_PARAMS.token) {
        Swal.fire('Erreur', 'Token manquant dans l\'URL.', 'error')
            .then(() => window.history.back());
        return;
    }

    al_showLoader('Chargement de la demande…');

    const [dDemande, dServices] = await Promise.all([
        al_post(al_api.demande,  { token: AL_PARAMS.token }),
        al_post(al_api.services, {}),
    ]);

    if (!dDemande?.success) {
        al_hideLoader();
        Swal.fire('Erreur', dDemande?.message || 'Demande introuvable.', 'error')
            .then(() => window.history.back());
        return;
    }

    al_demande     = dDemande.data;
    al_budgetToken = al_demande.budget_tmp;

    al_renderHeader(al_demande);
    al_renderServices(dServices?.data || []);

    const [dBudget, dExist] = await Promise.all([
        al_post(al_api.lignesBudget, { budgetId: al_budgetToken }),
        al_post(al_api.lignesExist,  { demandeId: parseInt(al_demande.id) }),
    ]);

    al_hideLoader();
    // Filtrer les lignes selon idTypeDemande de la demande
    // idTypeDemande=1 (achat) → n'afficher que les lignes Produit (id_type=1)
    // idTypeDemande=2 (paiement) → n'afficher que les lignes non-Produit (id_type≠1)
    const idType    = parseInt(al_demande.idTypeDemande || 1);
    const lignes    = (dBudget?.data || []).filter(l => {
        const lType = parseInt(l.id_type_budget_investissement);
        return idType === 1 ? lType === 1 : lType !== 1;
    });

    al_renderTableBudget(lignes, dExist?.data || []);
    al_renderTableExist(dExist?.data || []);
}

// ─── Header ───────────────────────────────────────────────────────────────────
function al_renderHeader(d) {
    const set = (id, v) => { const e = document.getElementById(id); if (e) e.innerHTML = v; };
    const txt = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    txt('al-titre',      `Demande #${d.id}`);
    txt('al-sous-titre', `Budget ${d.budget_annee} · ${d.type_budget_nom} · créée le ${al_fmtDate(d.date_creation)}`);
    set('al-badge-statut', al_badgeStatut(d.statut));
    set('al-badge-type',   al_badgeType(d.type_demande, d.idTypeDemande));

    // Afficher le bouton Soumettre seulement si "En création"
    const btnSoumettre = document.getElementById('al-btn-soumettre');
    const btnValider   = document.getElementById('al-btn-valider-wrap');
    if (btnSoumettre) btnSoumettre.style.display = (d.statut === 'En création') ? 'inline-flex' : 'none';
    if (btnValider)   btnValider.style.display   = (d.statut === 'En création') ? 'inline-flex' : 'none';
}

// ─── Select services ──────────────────────────────────────────────────────────
function al_renderServices(services) {
    const sel = document.getElementById('al-sel-service');
    if (!sel) return;
    sel.innerHTML = '<option value="">Tous les services</option>';
    services.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id; o.textContent = s.nom_services;
        sel.appendChild(o);
    });
}

// ─── Helper : montant / quantité restant selon le type de ligne ──────────────
function al_getRestant(row) {
    const isAchat = parseInt(row.id_type_budget_investissement) === 1;
    return isAchat
        ? parseFloat(row.quantite_restante || 0)
        : parseFloat(row.montant_restant ?? row.montant_total ?? 0);
}
function al_estEpuisee(row) {
    return al_getRestant(row) <= 0;
}

// ─── Table lignes disponibles ─────────────────────────────────────────────────
function al_renderTableBudget(lignes, existantes) {
    if (al_tableBudget) { try { al_tableBudget.destroy(); } catch(e){} al_tableBudget = null; }

    const existIds = (existantes || []).map(l => parseInt(l.line_id));

    al_tableBudget = $('#al-table-budget').DataTable({
        data      : lignes,
        responsive: true,
        pageLength: 25,
        dom       : '<"al-dt-top"lf>rt<"al-dt-bottom"ip>',
        language  : {
            emptyTable: 'Aucune ligne disponible',
            info      : '_START_–_END_ / _TOTAL_ lignes',
            infoEmpty : 'Aucune ligne',
            lengthMenu: 'Afficher _MENU_ lignes',
            search    : '_INPUT_', searchPlaceholder: 'Rechercher…',
            zeroRecords: 'Aucun résultat',
            paginate  : { first:'«', last:'»', next:'›', previous:'‹' },
        },
        columns: [
            // ── Checkbox (sélection multiple) ─────────────────────────────────
            {
                data: null, title: '<input type="checkbox" id="al-chk-all" title="Tout sélectionner">',
                orderable: false, searchable: false, width: '38px',
                render: (d, t, row, meta) => {
                    const epuisee = al_estEpuisee(row);
                    const dejaAjt = existIds.includes(parseInt(row.id));
                    const chosen  = al_lignesChoisies.find(l => l.lineId === parseInt(row.id));
                    const checked = chosen ? 'checked' : '';
                    const disabled= epuisee ? 'disabled' : '';
                    const tip     = dejaAjt ? 'Déjà dans la demande — cliquer pour incrémenter'
                        : epuisee ? 'Quantité/Montant épuisé' : 'Sélectionner';
                    return `<input type="checkbox" class="al-chk" ${checked} ${disabled}
                                title="${tip}" data-row="${meta.row}"
                                onchange="al_toggleLigne(this, ${meta.row})">`;
                },
            },
            // ── Sélectionné : qté ou montant saisi, coloré ───────────────────
            {
                data: null, title: 'Sélectionné', orderable: false, searchable: false, width: '115px',
                render: (d, t, row) => {
                    const lineId  = parseInt(row.id);
                    const isAchat = parseInt(row.id_type_budget_investissement) === 1;
                    const chosen  = al_lignesChoisies.find(l => l.lineId === lineId);
                    if (!chosen) return '<span style="color:#d1d5db;font-size:.75rem">—</span>';
                    const fmt = v => new Intl.NumberFormat('fr-FR').format(Number(v||0));
                    return isAchat
                        ? `<span class="al-sel-badge al-sel-badge-qte">${fmt(chosen.quantite)} ${row.unite_nom||''}</span>`
                        : `<span class="al-sel-badge al-sel-badge-mnt">${fmt(chosen.montant||0)} FCFA</span>`;
                },
            },
            // ── Données budget ────────────────────────────────────────────────
            { data: 'nature_nom',        title: 'Nature',        defaultContent: '—' },
            { data: 'nom_rubrique',      title: 'Rubrique',      defaultContent: '—' },
            { data: 'nom_sous_rubrique', title: 'Sous-rubrique', defaultContent: '—' },
            { data: 'service_nom',       title: 'Service',       defaultContent: '—' },
            { data: 'designation',       title: 'Désignation',   defaultContent: '—' },
            {
                // Afficher le RESTANT : quantité restante (Produit) ou montant restant (Autre)
                data: 'quantite_restante', title: 'Qté / Mnt restant', className: 'dt-right',
                render: (v, t, row) => {
                    const isAchat = parseInt(row.id_type_budget_investissement) === 1;
                    if (isAchat) {
                        const r = parseFloat(v || 0);
                        const color = r <= 0 ? '#ef4444' : r < parseFloat(row.quantite) * 0.2 ? '#f59e0b' : '#1a7a5e';
                        return `<span style="font-weight:700;color:${color}">${new Intl.NumberFormat('fr-FR').format(r)}</span>`;
                    } else {
                        const r = parseFloat(row.montant_restant ?? row.montant_total ?? 0);
                        const base = parseFloat(row.montant_total || 0);
                        const color = r <= 0 ? '#ef4444' : (base > 0 && r < base * 0.2) ? '#f59e0b' : '#1a7a5e';
                        return `<span style="font-weight:700;color:${color}">${new Intl.NumberFormat('fr-FR').format(r)} FCFA</span>`;
                    }
                },
            },
            { data: 'unite_nom', title: 'Unité', defaultContent: '—' },
            {
                data: 'prix_unitaire', title: 'P.U.', className: 'dt-right',
                render: v => v ? `<span class="al-amt">${new Intl.NumberFormat('fr-FR').format(v)} FCFA</span>` : '—',
            },
            {
                // Montant restant = P.U. × qté restante (Produit) | montant_restant (Autre)
                data: null, title: 'Montant restant', className: 'dt-right',
                render: (d, t, row) => {
                    const fmt = v => new Intl.NumberFormat('fr-FR').format(v || 0) + ' FCFA';
                    if (parseInt(row.id_type_budget_investissement) === 1) {
                        const restant = parseFloat(row.quantite_restante || 0) * parseFloat(row.prix_unitaire || 0);
                        return `<span class="al-amt-total">${fmt(restant)}</span>`;
                    } else {
                        // Autre : montant réellement disponible (budgété - déjà demandé)
                        return `<span class="al-amt-total">${fmt(row.montant_restant ?? row.montant_total)}</span>`;
                    }
                },
            },
            {
                data: 'type_demande', title: 'Type', width: '80px',
                render: (v, t, row) => al_badgeType(v, row.idTypeLigne),
            },
            // ── Bouton individuel ─────────────────────────────────────────────
            {
                data: null, title: 'Action', orderable: false, searchable: false, width: '90px',
                render: (d, t, row, meta) => {
                    const epuisee = al_estEpuisee(row);
                    const dejaAjt = existIds.includes(parseInt(row.id));
                    const chosen  = al_lignesChoisies.find(l => l.lineId === parseInt(row.id));
                    if (epuisee) {
                        return `<span class="al-btn-indiv al-btn-indiv-disabled" title="Quantité/Montant épuisé">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Épuisé</span>`;
                    }
                    const label = chosen ? '+ Ajouter' : dejaAjt ? '+ Incrém.' : 'Ajouter';
                    const color = chosen ? 'al-btn-indiv-selected' : dejaAjt ? 'al-btn-indiv-existing' : 'al-btn-indiv-add';
                    return `<button class="al-btn-indiv ${color}" onclick="al_ajouterUn(${meta.row})" title="${dejaAjt ? 'Incrémenter la quantité/montant' : 'Ajouter cette ligne'}">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        ${label}</button>`;
                },
            },
        ],
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        },
        rowCallback(row, data) {
            // Grisé si la ligne (Produit ou Autre) est épuisée
            if (al_estEpuisee(data)) {
                $(row).addClass('al-row-epuise');
            }
        },
    });
}

// ─── Table lignes existantes ──────────────────────────────────────────────────
function al_renderTableExist(lignes) {
    if (al_tableExist) { try { al_tableExist.destroy(); } catch(e){} al_tableExist = null; }

    const el = document.getElementById('al-exist-count');
    if (el) el.textContent = lignes.length;

    al_tableExist = $('#al-table-exist').DataTable({
        data      : lignes,
        responsive: true,
        pageLength: 10,
        dom       : '<"al-dt-top"f>rt<"al-dt-bottom"ip>',
        language  : {
            emptyTable: 'Aucune ligne associée', info: '_START_–_END_ / _TOTAL_',
            search: '_INPUT_', searchPlaceholder: 'Filtrer…',
            paginate: { first:'«', last:'»', next:'›', previous:'‹' },
        },
        columns: [
            { data: 'type_demande',  title: 'Type',        render: (v, t, row) => al_badgeType(v, row.idTypeLigne) },
            { data: 'nom_rubrique',  title: 'Rubrique',    defaultContent: '—' },
            { data: 'designation',   title: 'Désignation', defaultContent: '—' },
            {
                data: null, title: 'Qté / Montant', className: 'dt-right',
                render: (d, t, row) => {
                    const isAchat = (row.type_demande === 'demande_achat');
                    return isAchat
                        ? `<strong>${row.quantite}</strong> ${row.unite||''}`
                        : `<strong>${al_fmtNum(row.montant_demande||0)}</strong>`;
                },
            },
            {
                data: 'prix_unitaire', title: 'P.U.', className: 'dt-right',
                render: v => v ? `<span class="al-amt">${al_fmtNum(v)}</span>` : '—',
            },
            {
                data: null, title: 'Total', className: 'dt-right',
                render: (d, t, row) => {
                    const isAchat = (row.type_demande === 'demande_achat');
                    const total = isAchat
                        ? (row.prix_unitaire || 0) * (row.quantite || 1)
                        : (row.montant_demande || 0);
                    return `<span class="al-amt-total">${al_fmtNum(total)}</span>`;
                },
            },
            {
                data: null, title: 'Actions', orderable: false, searchable: false, width: '85px',
                render: (d, t, row) => {
                    const statut = al_demande?.statut || 'En création';
                    if (statut !== 'En création') return '<span style="color:#d1d5db;font-size:.75rem">—</span>';
                    return `<div class="flex justify-end gap-2">
                        <button onclick="al_modifierLigne(${row.id})"
                            class="btn-action btn-action-warning" title="Modifier">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                       m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button onclick="al_supprimerLigne(${row.id})"
                            class="btn-action btn-action-danger" title="Supprimer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858
                                       L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>`;
                },
            },
        ],
    });

    const total = lignes.reduce((s, l) => {
        const isAchat = (l.type_demande === 'demande_achat');
        return s + (isAchat
            ? parseFloat(l.prix_unitaire || 0) * parseFloat(l.quantite || 1)
            : parseFloat(l.montant_demande || 0));
    }, 0);
    const totEl = document.getElementById('al-total-exist');
    if (totEl) totEl.textContent = al_fmtNum(total);
}

// ─── Toggle ligne ─────────────────────────────────────────────────────────────
function al_toggleLigne(chk, rowIdx) {
    const row = al_tableBudget.row(rowIdx).data();
    if (!row) return;
    const lineId  = parseInt(row.id);
    const isAchat = parseInt(row.id_type_budget_investissement) === 1;
    const fmt     = v => new Intl.NumberFormat('fr-FR').format(Number(v||0));

    if (chk.checked) {
        const existing = al_lignesChoisies.find(l => l.lineId === lineId);

        if (isAchat) {
            const max = parseFloat(row.quantite_restante);
            if (max <= 0) { chk.checked = false; return; }
            // Calcul du max restant en tenant compte de ce déjà sélectionné
            const dejaSelQte = existing ? existing.quantite : 0;
            const disponible = max - dejaSelQte;
            if (disponible <= 0) {
                Swal.fire('Info', `Vous avez déjà sélectionné toute la quantité disponible (${fmt(max)} ${row.unite_nom||''}).`, 'info');
                chk.checked = false;
                return;
            }
            Swal.fire({
                title     : existing ? 'Ajouter une quantité supplémentaire' : 'Quantité à demander',
                html      : `<p style="font-size:.875rem;color:#374151;margin-bottom:.5rem">
                                <strong>${row.designation || '—'}</strong><br>
                                Disponible : <strong style="color:#1a7a5e">${fmt(disponible)} ${row.unite_nom||''}</strong>
                                ${existing ? `<br><span style="color:#6b7280;font-size:.8rem">Déjà sélectionné : ${fmt(existing.quantite)}</span>` : ''}
                             </p>`,
                input          : 'number',
                inputValue     : 1,
                inputAttributes: { min: 1, max: disponible, step: 1 },
                showCancelButton : true,
                confirmButtonText: 'Confirmer',
                cancelButtonText : 'Annuler',
                confirmButtonColor: '#1a7a5e',
                inputValidator: v => {
                    if (!v || parseFloat(v) < 1)         return 'Quantité doit être ≥ 1.';
                    if (parseFloat(v) > disponible)      return `Maximum disponible : ${fmt(disponible)}.`;
                    if (!Number.isInteger(Number(v)))    return 'Entier requis.';
                },
            }).then(res => {
                if (res.isConfirmed) {
                    const added = parseInt(res.value);
                    if (existing) { existing.quantite += added; }
                    else          { al_lignesChoisies.push({ lineId, quantite: added }); }
                    al_majBtn();
                } else { chk.checked = false; }
            });

        } else {
            // Autre → saisir montant à ajouter, plafonné au montant RESTANT
            const max = parseFloat(row.montant_restant ?? row.montant_total);
            const dejaSelMnt = existing ? (existing.montant || 0) : 0;
            const disponible = max - dejaSelMnt;

            if (disponible <= 0) {
                Swal.fire('Info', `Vous avez déjà sélectionné tout le montant disponible (${fmt(max)} FCFA).`, 'info');
                chk.checked = false;
                return;
            }

            Swal.fire({
                title     : existing ? 'Ajouter un montant supplémentaire' : 'Montant à demander',
                html      : `<p style="font-size:.875rem;color:#374151;margin-bottom:.5rem">
                                <strong>${row.designation || '—'}</strong><br>
                                Disponible : <strong style="color:#1a7a5e">${fmt(disponible)} FCFA</strong>
                                ${existing ? `<br><span style="color:#6b7280;font-size:.8rem">Déjà sélectionné : ${fmt(existing.montant)} FCFA</span>` : ''}
                             </p>`,
                input          : 'number',
                inputValue     : '',
                inputPlaceholder: 'Montant à ajouter en FCFA',
                inputAttributes: { min: 1, max: disponible, step: 1 },
                showCancelButton : true,
                confirmButtonText: 'Confirmer',
                cancelButtonText : 'Annuler',
                confirmButtonColor: '#1a7a5e',
                inputValidator: v => {
                    if (!v || parseFloat(v) < 1)    return 'Le montant doit être ≥ 1 FCFA.';
                    if (parseFloat(v) > disponible) return `Maximum disponible : ${fmt(disponible)} FCFA.`;
                    if (isNaN(parseFloat(v)))       return 'Montant invalide.';
                },
            }).then(res => {
                if (res.isConfirmed) {
                    const added = parseFloat(res.value);
                    if (existing) { existing.montant += added; }
                    else          { al_lignesChoisies.push({ lineId, quantite: 1, montant: added }); }
                    al_majBtn();
                } else { chk.checked = false; }
            });
        }

    } else {
        // Décocher → retirer complètement de la sélection
        al_lignesChoisies = al_lignesChoisies.filter(l => l.lineId !== lineId);
        al_majBtn();
    }
}

// ── Ajouter une ligne individuellement (bouton par ligne) ────────────────────
function al_ajouterUn(rowIdx) {
    const row = al_tableBudget.row(rowIdx).data();
    if (!row) return;
    const lineId  = parseInt(row.id);
    const isAchat = parseInt(row.id_type_budget_investissement) === 1;
    const existing = al_lignesChoisies.find(l => l.lineId === lineId);
    const fmt = v => new Intl.NumberFormat('fr-FR').format(Number(v||0));

    if (isAchat) {
        const max = parseFloat(row.quantite_restante);
        if (max <= 0) { Swal.fire('Info','Quantité épuisée pour cette ligne.','info'); return; }
        const dejaSelQte = existing ? existing.quantite : 0;
        const disponible = max - dejaSelQte;
        if (disponible <= 0) {
            Swal.fire('Info',`Vous avez déjà sélectionné toute la quantité disponible (${fmt(max)} ${row.unite_nom||''}).`,'info');
            return;
        }
        Swal.fire({
            title     : existing ? 'Ajouter une quantité supplémentaire' : 'Quantité à demander',
            html      : `<p style="font-size:.875rem;color:#374151;margin-bottom:.5rem">
                            <strong>${row.designation || '—'}</strong><br>
                            Disponible : <strong style="color:#1a7a5e">${fmt(disponible)} ${row.unite_nom||''}</strong>
                            ${existing ? `<br><span style="color:#6b7280;font-size:.8rem">Déjà sélectionné : ${fmt(existing.quantite)}</span>` : ''}
                         </p>`,
            input          : 'number',
            inputValue     : 1,
            inputAttributes: { min: 1, max: disponible, step: 1 },
            showCancelButton : true,
            confirmButtonText: 'Confirmer',
            cancelButtonText : 'Annuler',
            confirmButtonColor: '#1a7a5e',
            inputValidator: v => {
                if (!v || parseFloat(v) < 1)       return 'Quantité doit être ≥ 1.';
                if (parseFloat(v) > disponible)    return `Maximum : ${fmt(disponible)}.`;
                if (!Number.isInteger(Number(v)))  return 'Entier requis.';
            },
        }).then(res => {
            if (!res.isConfirmed) return;
            const added = parseInt(res.value);
            if (existing) { existing.quantite += added; }
            else          { al_lignesChoisies.push({ lineId, quantite: added }); }
            al_majBtn();
        });
    } else {
        // Autre → montant plafonné au montant RESTANT
        const max = parseFloat(row.montant_restant ?? row.montant_total);
        const dejaSelMnt = existing ? (existing.montant || 0) : 0;
        const disponible = max - dejaSelMnt;

        if (disponible <= 0) {
            Swal.fire('Info', `Vous avez déjà sélectionné tout le montant disponible (${fmt(max)} FCFA).`, 'info');
            return;
        }

        Swal.fire({
            title     : existing ? 'Ajouter un montant supplémentaire' : 'Montant à demander',
            html      : `<p style="font-size:.875rem;color:#374151;margin-bottom:.5rem">
                            <strong>${row.designation || '—'}</strong><br>
                            Disponible : <strong style="color:#1a7a5e">${fmt(disponible)} FCFA</strong>
                            ${existing ? `<br><span style="color:#6b7280;font-size:.8rem">Déjà sélectionné : ${fmt(existing.montant)} FCFA</span>` : ''}
                         </p>`,
            input          : 'number',
            inputValue     : '',
            inputPlaceholder: 'Montant en FCFA',
            inputAttributes: { min: 1, max: disponible, step: 1 },
            showCancelButton : true,
            confirmButtonText: 'Confirmer',
            cancelButtonText : 'Annuler',
            confirmButtonColor: '#1a7a5e',
            inputValidator: v => {
                if (!v || parseFloat(v) < 1)    return 'Le montant doit être ≥ 1 FCFA.';
                if (parseFloat(v) > disponible) return `Maximum disponible : ${fmt(disponible)} FCFA.`;
                if (isNaN(parseFloat(v)))       return 'Montant invalide.';
            },
        }).then(res => {
            if (!res.isConfirmed) return;
            const added = parseFloat(res.value);
            if (existing) { existing.montant += added; }
            else          { al_lignesChoisies.push({ lineId, quantite: 1, montant: added }); }
            al_majBtn();
        });
    }
}

// ── Sélectionner / désélectionner tout ───────────────────────────────────────
function al_toggleAll(chk) {
    if (chk.checked) {
        // Ouvrir popup groupé pour les lignes non épuisées (Produit ET Autre)
        const rows = al_tableBudget.rows().data().toArray();
        const eligibles = rows.filter(row => !al_estEpuisee(row));
        if (!eligibles.length) { chk.checked = false; return; }

        Swal.fire({
            title: `Sélectionner ${eligibles.length} ligne(s) ?`,
            html : `<p style="font-size:.875rem;color:#374151">
                        Pour les lignes <strong>Produit</strong> : quantité = 1 par défaut.<br>
                        Pour les lignes <strong>Autre</strong> : montant restant par défaut.<br>
                        <small style="color:#6b7280">Vous pouvez ajuster chaque ligne individuellement ensuite.</small>
                    </p>`,
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Tout sélectionner', cancelButtonText: 'Annuler',
            confirmButtonColor: '#1a7a5e',
        }).then(res => {
            if (!res.isConfirmed) { chk.checked = false; return; }
            eligibles.forEach(row => {
                const lineId  = parseInt(row.id);
                const isAchat = parseInt(row.id_type_budget_investissement) === 1;
                const existing = al_lignesChoisies.find(l => l.lineId === lineId);
                if (!existing) {
                    if (isAchat) al_lignesChoisies.push({ lineId, quantite: 0 });
                    else         al_lignesChoisies.push({ lineId, quantite: 0, montant: parseFloat(row.montant_restant ?? row.montant_total ?? 0) });
                }
            });
            al_majBtn();
        });
    } else {
        al_lignesChoisies = [];
        al_majBtn();
    }
}

function al_majBtn() {
    const nb    = al_lignesChoisies.length;
    const btn   = document.getElementById('al-btn-valider');
    const nb_el = document.getElementById('al-nb-sel');
    if (btn) {
        btn.disabled    = nb === 0;
        btn.textContent = nb > 0
            ? `Valider (${nb} ligne${nb > 1 ? 's' : ''})`
            : 'Valider';
    }
    if (nb_el) nb_el.textContent = nb;
    // Redessiner colonnes Sélectionné + boutons
    if (al_tableBudget) al_tableBudget.rows().invalidate().draw(false);
}

async function al_ajouterLignes() {
    if (!al_lignesChoisies.length) return;

    const conf = await Swal.fire({
        title: `Ajouter ${al_lignesChoisies.length} ligne(s) ?`,
        icon : 'question', showCancelButton: true,
        confirmButtonText: 'Confirmer', cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
    });
    if (!conf.isConfirmed) return;

    al_showLoader('Ajout en cours…');
    const d = await al_post(al_api.ajouterLignes, {
        demandeId: parseInt(al_demande.id),
        lignes   : al_lignesChoisies,
    });
    al_hideLoader();

    if (!d?.success) { Swal.fire('Erreur', d?.message || 'Erreur.', 'error'); return; }

    await Swal.fire({
        icon: 'success', title: 'Lignes ajoutées !',
        text: d.message, timer: 1800, showConfirmButton: false,
    });

    al_lignesChoisies = [];
    al_majBtn();

    al_showLoader('Actualisation…');
    const [dBudget, dExist] = await Promise.all([
        al_post(al_api.lignesBudget, { budgetId: al_budgetToken }),
        al_post(al_api.lignesExist,  { demandeId: parseInt(al_demande.id) }),
    ]);
    al_hideLoader();
    // Filtrer les lignes selon idTypeDemande de la demande
    // idTypeDemande=1 (achat) → n'afficher que les lignes Produit (id_type=1)
    // idTypeDemande=2 (paiement) → n'afficher que les lignes non-Produit (id_type≠1)
    const idType    = parseInt(al_demande.idTypeDemande || 1);
    const lignes    = (dBudget?.data || []).filter(l => {
        const lType = parseInt(l.id_type_budget_investissement);
        return idType === 1 ? lType === 1 : lType !== 1;
    });

    al_renderTableBudget(lignes, dExist?.data || []);
    al_renderTableExist(dExist?.data || []);
}

// ─── Filtre service ───────────────────────────────────────────────────────────
async function al_filtrerService() {
    // Vider les lignes sélectionnées avant de recharger (évite des incohérences)
    al_lignesChoisies = [];
    al_majBtn();

    const serviceId = document.getElementById('al-sel-service')?.value || null;
    al_showLoader('Filtrage…');
    const [dBudget, dExist] = await Promise.all([
        al_post(al_api.lignesBudget, {
            budgetId: al_budgetToken,
            ...(serviceId ? { service_id: parseInt(serviceId) } : {}),
        }),
        al_post(al_api.lignesExist, { demandeId: parseInt(al_demande.id) }),
    ]);
    al_hideLoader();
    if (dBudget?.success) {
        const idType = parseInt(al_demande?.idTypeDemande || 1);
        const lignes = (dBudget.data || []).filter(l => {
            const lType = parseInt(l.id_type_budget_investissement);
            return idType === 1 ? lType === 1 : lType !== 1;
        });
        al_renderTableBudget(lignes, dExist?.data || []);
    }
}

// ─── Modifier une ligne ──────────────────────────────────────────────────────
async function al_modifierLigne(ligneId) {
    // Trouver la ligne dans la table existante
    const rows  = al_tableExist.rows().data().toArray();
    const ligne = rows.find(r => parseInt(r.id) === parseInt(ligneId));
    if (!ligne) return;

    const isAchat = (ligne.type_demande === 'demande_achat');
    const fmt     = v => new Intl.NumberFormat('fr-FR').format(Number(v||0));

    if (isAchat) {
        // Modifier la quantité (Produit) — plafonnée à la quantité restante du budget
        const max = parseFloat(ligne.quantite_restante_modif ?? ligne.quantite);
        const res = await Swal.fire({
            title : 'Modifier la quantité',
            html  : `<p style="font-size:.875rem;color:#374151;margin-bottom:.5rem">
                        <strong>${ligne.designation||'—'}</strong><br>
                        <span style="color:#6b7280;font-size:.8rem">Quantité actuelle : <strong>${ligne.quantite}</strong></span><br>
                        <span style="color:#6b7280;font-size:.8rem">Maximum disponible : <strong style="color:#1a7a5e">${fmt(max)}</strong></span>
                     </p>`,
            input          : 'number',
            inputValue     : ligne.quantite,
            inputAttributes: { min: 1, max: max, step: 1 },
            showCancelButton : true,
            confirmButtonText: 'Enregistrer',
            cancelButtonText : 'Annuler',
            confirmButtonColor: '#1a7a5e',
            inputValidator: v => {
                if (!v || parseInt(v) < 1)        return 'Quantité ≥ 1 requise.';
                if (parseFloat(v) > max)           return `Maximum disponible : ${fmt(max)}.`;
                if (!Number.isInteger(Number(v))) return 'Entier requis.';
            },
        });
        if (!res.isConfirmed) return;

        al_showLoader('Modification…');
        const d = await al_post(al_api.modifierLigne, { ligneId, quantite: parseInt(res.value) });
        al_hideLoader();
        if (!d?.success) { Swal.fire('Erreur', d?.message || 'Erreur.', 'error'); return; }

    } else {
        // Modifier le montant (Autre) — plafonné au montant restant du budget
        const max = parseFloat(ligne.montant_restant_modif ?? ligne.montant_demande);
        const res = await Swal.fire({
            title : 'Modifier le montant',
            html  : `<p style="font-size:.875rem;color:#374151;margin-bottom:.5rem">
                        <strong>${ligne.designation||'—'}</strong><br>
                        <span style="color:#6b7280;font-size:.8rem">Montant actuel : <strong>${fmt(ligne.montant_demande||0)} FCFA</strong></span><br>
                        <span style="color:#6b7280;font-size:.8rem">Maximum disponible : <strong style="color:#1a7a5e">${fmt(max)} FCFA</strong></span>
                     </p>`,
            input          : 'number',
            inputValue     : ligne.montant_demande || '',
            inputPlaceholder: 'Nouveau montant FCFA',
            inputAttributes: { min: 1, max: max, step: 1 },
            showCancelButton : true,
            confirmButtonText: 'Enregistrer',
            cancelButtonText : 'Annuler',
            confirmButtonColor: '#1a7a5e',
            inputValidator: v => {
                if (!v || parseFloat(v) < 1) return 'Montant ≥ 1 FCFA requis.';
                if (parseFloat(v) > max)     return `Maximum disponible : ${fmt(max)} FCFA.`;
                if (isNaN(parseFloat(v)))    return 'Montant invalide.';
            },
        });
        if (!res.isConfirmed) return;

        al_showLoader('Modification…');
        const d = await al_post(al_api.modifierLigne, { ligneId, montant: parseFloat(res.value) });
        al_hideLoader();
        if (!d?.success) { Swal.fire('Erreur', d?.message || 'Erreur.', 'error'); return; }
    }

    // Recharger les deux tableaux
    await al_rechargerTableaux();
}

// ─── Supprimer une ligne ──────────────────────────────────────────────────────
async function al_supprimerLigne(ligneId) {
    const rows  = al_tableExist.rows().data().toArray();
    const ligne = rows.find(r => parseInt(r.id) === parseInt(ligneId));
    const label = ligne?.designation || `#${ligneId}`;

    const conf = await Swal.fire({
        title: 'Supprimer cette ligne ?',
        html : `<p style="font-size:.875rem;color:#374151">
                    <strong>${label}</strong> sera retirée de la demande.
                </p>`,
        icon            : 'warning',
        showCancelButton : true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText : 'Annuler',
        confirmButtonColor: '#ef4444',
    });
    if (!conf.isConfirmed) return;

    al_showLoader('Suppression…');
    const d = await al_post(al_api.supprimerLigne, { ligneId });
    al_hideLoader();
    if (!d?.success) { Swal.fire('Erreur', d?.message || 'Erreur.', 'error'); return; }

    await Swal.fire({ icon:'success', title:'Ligne supprimée', text:d.message,
        timer:1500, showConfirmButton:false });
    await al_rechargerTableaux();
}

// ─── Soumettre la demande ────────────────────────────────────────────────────
async function al_soumettre() {
    // Vérifier qu'il y a des lignes
    const nbLignes = parseInt(document.getElementById('al-exist-count')?.textContent || '0');
    if (nbLignes === 0) {
        Swal.fire('Impossible', 'Ajoutez au moins une ligne avant de soumettre.', 'warning');
        return;
    }

    const conf = await Swal.fire({
        title: 'Soumettre la demande ?',
        html : `<p style="font-size:.875rem;color:#374151">
                    La demande <strong>#${al_demande.id}</strong> passera au statut
                    <strong style="color:#1a7a5e">Soumise</strong>.<br>
                    <small style="color:#9ca3af">Vous ne pourrez plus modifier ni supprimer les lignes.</small>
                </p>`,
        icon             : 'question',
        showCancelButton  : true,
        confirmButtonText : 'Soumettre',
        cancelButtonText  : 'Annuler',
        confirmButtonColor: '#1a7a5e',
    });
    if (!conf.isConfirmed) return;

    al_showLoader('Soumission en cours…');
    const d = await al_post(al_api.soumettre, { demandeId: parseInt(al_demande.id) });
    al_hideLoader();

    if (!d?.success) { Swal.fire('Erreur', d?.message || 'Erreur.', 'error'); return; }

    await Swal.fire({
        icon : 'success',
        title: 'Demande soumise !',
        html : `<p style="font-size:.875rem;color:#374151">${d.message}</p>`,
        confirmButtonText : 'Retour à la liste',
        confirmButtonColor: '#1a7a5e',
    });
    window.location.href = '/personnel/chef_service_demande';
}

// ─── Recharger les deux tableaux ─────────────────────────────────────────────
async function al_rechargerTableaux() {
    al_showLoader('Actualisation…');
    const [dBudget, dExist] = await Promise.all([
        al_post(al_api.lignesBudget, { budgetId: al_budgetToken }),
        al_post(al_api.lignesExist,  { demandeId: parseInt(al_demande.id) }),
    ]);
    al_hideLoader();
    al_renderTableExist(dExist?.data || []);
    if (dBudget?.success) {
        const idType = parseInt(al_demande?.idTypeDemande || 1);
        const lignes = (dBudget.data || []).filter(l => {
            const t = parseInt(l.id_type_budget_investissement);
            return idType === 1 ? t === 1 : t !== 1;
        });
        al_renderTableBudget(lignes, dExist?.data || []);
    }
}

// ─── Événements ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    al_init();
    // Délégation pour le checkbox "tout sélectionner" dans l'en-tête
    $(document).on('change', '#al-chk-all', function() { al_toggleAll(this); });
    document.getElementById('al-btn-retour')?.addEventListener('click', () => window.location.href='/personnel/chef_service_demande');
    document.getElementById('al-btn-valider')?.addEventListener('click', al_ajouterLignes);
    document.getElementById('al-btn-soumettre')?.addEventListener('click', al_soumettre);
    document.getElementById('al-sel-service')?.addEventListener('change', al_filtrerService);
    document.getElementById('al-btn-terminer')?.addEventListener('click', () => {
        window.location.href = '/personnel/chef_service_demande';
    });
});