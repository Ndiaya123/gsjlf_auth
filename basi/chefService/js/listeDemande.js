// ════════════════════════════════════════════════════════════════════════
// LISTE DEMANDES — Chef de service
// Création demande : saisir budget + type_demande UNIQUEMENT
// Les lignes sont associées dans add-lines-to-demandes.php
//
// Budgets visibles : année en cours ET année suivante (case 34)
// type_demande : 'demande_achat' | 'demande_paiement' (choix utilisateur)
// ════════════════════════════════════════════════════════════════════════

const LD_API = '/personnel/chef_service_basi_controller';
const ld_api = {
    liste   : `${LD_API}?option=33`,
    budgets : `${LD_API}?option=34`,
    creer   : `${LD_API}?option=36`,
    supprimer:`${LD_API}?option=39`,
    lignes  : `${LD_API}?option=38`,
    suivi   : `${LD_API}?option=52`,
};

// ─── État ─────────────────────────────────────────────────────────────────────
let ld_table = null;

// ─── Réseau ───────────────────────────────────────────────────────────────────
function ld_expired() {
    ld_hideLoader();
    Swal.fire({ icon:'warning', title:'Session expirée', text:'Redirection…',
        timer:2500, showConfirmButton:false,
        didClose:()=>{ window.location.href = '/personnel/signin'; }
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
            <div class="loader-backdrop"></div>
            <div class="loader-box">
                <svg class="loader-spinner" viewBox="0 0 50 50">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
                </svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function ld_hideLoader() { $('#ld-loader').remove(); }

// ─── Formatage ────────────────────────────────────────────────────────────────
const ld_fmtDate = d => d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—';
const ld_fmtK    = v => {
    if (v >= 1e9) return (v / 1e9).toFixed(1) + ' Md';
    if (v >= 1e6) return (v / 1e6).toFixed(1) + ' M';
    if (v >= 1e3) return (v / 1e3).toFixed(0) + ' K';
    return new Intl.NumberFormat('fr-FR').format(v);
};
const ld_fmtNum = v => new Intl.NumberFormat('fr-FR').format(Number(v || 0)) + '\u00a0FCFA';

// ─── Badges ───────────────────────────────────────────────────────────────────
function ld_badgeStatut(s) {
    const map = {
        'En création': '<span class="ld-badge ld-badge-draft">En création</span>',
        'Validée'    : '<span class="ld-badge ld-badge-ok">Validée</span>',
        'En révision': '<span class="ld-badge ld-badge-warn">En révision</span>',
        'Rejetée'    : '<span class="ld-badge ld-badge-err">Rejetée</span>',
        'Terminée'   : '<span class="ld-badge ld-badge-done">Terminée</span>',
    };
    return map[s] || `<span class="ld-badge ld-badge-draft">${s || 'En création'}</span>`;
}
function ld_badgeType(t) {
    return t === 'demande_achat'
        ? '<span class="ld-badge ld-badge-achat">Achat</span>'
        : t === 'demande_paiement'
            ? '<span class="ld-badge ld-badge-paiement">Paiement</span>'
            : '<span class="ld-badge ld-badge-draft">—</span>';
}

// ─── Badges — suivi complet (commande / livraison) ─────────────────────────────
const LD_STATUT_PAP = { 1: 'En attente', 2: 'Validée DGA', 3: 'Avis DFC', 4: 'Acceptée', 5: 'Rejetée', 6: 'En paiement', 7: 'Terminée' };
function ld_badgePapStatut(idStatut) {
    const map = { 1: 'draft', 2: 'warn', 3: 'warn', 4: 'ok', 5: 'err', 6: 'warn', 7: 'done' };
    const cls = map[idStatut] || 'draft';
    return `<span class="ld-badge ld-badge-${cls}">${LD_STATUT_PAP[idStatut] || 'Inconnu'}</span>`;
}
function ld_badgeLivraisonStatut(idStatut) {
    // idStatut de `livraison` : 1 = seule valeur utilisée dans le module livraisons
    return idStatut == 1
        ? '<span class="ld-badge ld-badge-ok">Enregistrée</span>'
        : `<span class="ld-badge ld-badge-draft">${idStatut ?? '—'}</span>`;
}

// ─── DataTable liste ──────────────────────────────────────────────────────────
function ld_initTable() {
    if (ld_table) { try { ld_table.destroy(); } catch(e){} ld_table = null; }
    if (!document.getElementById('ld-table')) return;

    ld_table = $('#ld-table').DataTable({
        responsive  : true,
        paging      : true,
        pageLength  : 25,
        searching   : true,
        ordering    : true,
        autoWidth   : false,
        dom         : '<"bud-dt-top"lf>rt<"bud-dt-bottom"ip>',
        language    : {
            emptyTable       : 'Aucune demande',
            info             : 'Affichage _START_–_END_ sur _TOTAL_',
            infoEmpty        : 'Aucune demande',
            lengthMenu       : 'Afficher _MENU_ lignes',
            search           : '_INPUT_',
            searchPlaceholder: 'Rechercher…',
            zeroRecords      : 'Aucun résultat',
            paginate         : { first:'«', last:'»', next:'›', previous:'‹' },
        },
        data   : [],
        columns: [
            { data: 'id', title: 'N°', width: '55px',
                render: v => `<span class="ld-id">#${v}</span>` },
            { data: 'budget_annee',    title: 'Année',    width: '65px',  defaultContent: '—' },
            { data: 'type_budget_nom', title: 'Budget',   defaultContent: '—' },
            { data: 'type_demande',    title: 'Type',
                render: v => ld_badgeType(v) },
            { data: 'date_creation',   title: 'Créée le', width: '90px',
                render: ld_fmtDate },
            { data: 'nb_lignes',       title: 'Lignes',   width: '65px',  className: 'dt-center',
                render: v => `<span class="ld-nb">${v || 0}</span>` },
            { data: 'statut',          title: 'Statut',   width: '115px',
                render: ld_badgeStatut },
            { data: null, title: 'Actions', orderable: false, searchable: false, width: '155px',
                render: (d, t, row) => ld_getActions(row) },
        ],
    });
}

// ─── Boutons action ───────────────────────────────────────────────────────────
function ld_btnSuivi(id) {
    return `
        <button onclick="ld_voirSuiviComplet(${id})"
                class="btn-action btn-action-info" title="Voir le suivi complet">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </button>`;
}

function ld_getActions(row) {
    const { id, statut, tmp } = row;
    const s = statut || 'En création';

    // Bouton "Ajouter des lignes" → redirige vers add-lines-to-demandes.php
    const btnLignes = `
        <button onclick="ld_allerAjouterLignes('${tmp || id}')"
                class="btn-action btn-action-success" title="Ajouter des lignes">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v16m8-8H4"/>
            </svg>
        </button>`;

    // Bouton "Voir les lignes"
    const btnVoir = `
        <button onclick="ld_voirLignes(${id})"
                class="btn-action btn-action-primary" title="Voir les lignes">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                       9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </button>`;

    // Bouton supprimer
    const btnSuppr = `
        <button onclick="ld_supprimer(${id})"
                class="btn-action btn-action-danger" title="Supprimer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858
                       L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>`;

    let html = `<div class="flex justify-end gap-2">`;
    html += ld_btnSuivi(id);
    html += btnVoir;
    if (s === 'En création') html += btnLignes + btnSuppr;
    html += '</div>';
    return html;
}

// ─── Charger la liste ─────────────────────────────────────────────────────────
async function ld_loadListe() {
    ld_showLoader('Chargement des demandes…');
    try {
        const d = await ld_post(ld_api.liste);
        ld_hideLoader();
        if (!d) return;
        if (!d.success) { Swal.fire('Erreur', d.message || 'Erreur chargement.', 'error'); return; }

        const rows = d.data || [];
        if (!ld_table) ld_initTable();
        ld_table.clear();
        if (rows.length) ld_table.rows.add(rows);
        ld_table.draw();

        const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        setEl('ld-count-tous',     rows.length);
        setEl('ld-count-creation', rows.filter(r => (r.statut || 'En création') === 'En création').length);
        setEl('ld-count-validee',  rows.filter(r => r.statut === 'Validée').length);
        setEl('ld-count-achat',    rows.filter(r => r.type_demande === 'demande_achat').length);
        setEl('ld-count-paiement', rows.filter(r => r.type_demande === 'demande_paiement').length);
    } catch (err) {
        ld_hideLoader();
        Swal.fire('Erreur', 'Erreur réseau : ' + err.message, 'error');
    }
}

// ─── MODALE — Ouvrir ──────────────────────────────────────────────────────────
async function ld_ouvrirModal() {
    ld_showLoader('Chargement des budgets…');
    const dBudgets = await ld_post(ld_api.budgets);
    ld_hideLoader();

    if (!dBudgets?.success || !dBudgets.data?.length) {
        Swal.fire({
            icon : 'info',
            title: 'Aucun budget disponible',
            html : `<p style="font-size:.875rem;color:#374151">
                        Vous n'avez aucun budget investissement <strong>validé (Accepté)</strong>
                        par la DFC pour l'année en cours ou l'année suivante.
                    </p>`,
        });
        return;
    }

    // Peupler le select budget
    const sel = document.getElementById('ld-sel-budget');
    sel.innerHTML = '<option value="">-- Sélectionner un budget --</option>';
    dBudgets.data.forEach(b => {
        const o         = document.createElement('option');
        o.value         = b.id;
        o.dataset.token = b.tmp;
        o.textContent   = `${b.annee} — ${b.type_budget_nom} (Plafond : ${ld_fmtK(b.plafond)} FCFA)`;
        sel.appendChild(o);
    });

    // Réinitialiser le select type
    const selType = document.getElementById('ld-sel-type');
    if (selType) selType.value = '';

    // Réinitialiser le bouton
    ld_majBtnSuivant();

    document.getElementById('ld-modal')?.classList.remove('hidden');
}

function ld_fermerModal() {
    document.getElementById('ld-modal')?.classList.add('hidden');
}

// ─── Mise à jour bouton Suivant ───────────────────────────────────────────────
function ld_majBtnSuivant() {
    const btn    = document.getElementById('ld-btn-suivant');
    const budget = document.getElementById('ld-sel-budget')?.value;
    const type   = document.getElementById('ld-sel-type')?.value;
    if (btn) btn.disabled = !budget || !type;
}

// ─── Créer la demande et rediriger ────────────────────────────────────────────
async function ld_creerEtRediriger() {
    const selBudget = document.getElementById('ld-sel-budget');
    const selType   = document.getElementById('ld-sel-type');

    if (!selBudget?.value || !selType?.value) {
        Swal.fire('Incomplet', 'Sélectionnez un budget et un type de demande.', 'warning');
        return;
    }

    const opt        = selBudget.options[selBudget.selectedIndex];
    const budgetToken = opt.dataset.token;
    const typeDemande = selType.value;
    const budgetLabel = opt.textContent;

    const conf = await Swal.fire({
        title: 'Créer cette demande ?',
        html : `<div style="text-align:left;font-size:.875rem;color:#374151">
                    <p><strong>Budget :</strong> ${budgetLabel}</p>
                    <p><strong>Type :</strong> ${typeDemande === 'demande_achat' ? 'Demande d\'achat' : 'Demande de paiement'}</p>
                    <p style="margin-top:.75rem;font-size:.8rem;color:#6b7280">
                        Vous pourrez ajouter les lignes budgétaires sur la page suivante.
                    </p>
                </div>`,
        icon            : 'question',
        showCancelButton : true,
        confirmButtonText: 'Créer et ajouter des lignes →',
        cancelButtonText : 'Annuler',
        confirmButtonColor: '#1a7a5e',
    });
    if (!conf.isConfirmed) return;

    ld_showLoader('Création de la demande…');
    const d = await ld_post(ld_api.creer, {
        budgetId    : budgetToken,
        type_demande: typeDemande,
    });
    ld_hideLoader();

    if (!d?.success) {
        Swal.fire('Erreur', d?.message || 'Erreur lors de la création.', 'error');
        return;
    }

    // Rediriger vers la page d'ajout de lignes
    // URL htaccess : /chef_service_associe_ligne_budget_demande/{token}/{typeToken}
    // type est le token chiffré de idTypeDemande (retourné par le contrôleur)
    window.location.href = `/personnel/chef_service_associe_ligne_budget_demande/${encodeURIComponent(d.tmp)}/${encodeURIComponent(d.typeToken || d.idTypeDemande)}`;
}

// ─── Redirection "Ajouter des lignes" depuis la liste ─────────────────────────
function ld_allerAjouterLignes(token) {
    // Depuis la liste : on n'a pas le typeToken → on passe juste le token demande
    // le case 41 retrouvera idTypeDemande depuis la BDD
    window.location.href = `/personnel/chef_service_associe_ligne_budget_demande/${encodeURIComponent(token)}/0`;
}

// ─── Voir les lignes d'une demande ────────────────────────────────────────────
async function ld_voirLignes(id) {
    ld_showLoader('Chargement…');
    const d = await ld_post(ld_api.lignes, { demandeId: id });
    ld_hideLoader();

    if (!d?.success) { Swal.fire('Erreur', d?.message || 'Erreur.', 'error'); return; }

    const lignes = d.data || [];
    if (!lignes.length) {
        Swal.fire('Info', 'Aucune ligne associée à cette demande.', 'info');
        return;
    }

    const total = lignes.reduce((s, l) =>
            s + (
                l.demande_id == 2
                    ? parseFloat(l.montant_demande || 0)
                    : parseFloat(l.prix_unitaire || 0) * parseFloat(l.quantite || 1)
            ),
        0
    );

    const rows = lignes.map(l => `
    <tr style="border-bottom:1px solid #f3f4f6">
        <td style="padding:.55rem .75rem;font-size:.8rem">
            ${ld_badgeType(l.type_demande)}
        </td>

        <td style="padding:.55rem .75rem;font-size:.8rem;color:#374151">
            ${l.nom_rubrique || '—'}
        </td>

        <td style="padding:.55rem .75rem;font-size:.8rem;color:#374151">
            ${l.designation || '—'}
        </td>

        <td style="padding:.55rem .75rem;font-size:.8rem;text-align:right">
            ${l.quantite || '—'}
        </td>

        <td style="padding:.55rem .75rem;font-size:.8rem">
            ${l.unite || '—'}
        </td>

        ${
        l.demande_id == 2
            ? `
                    <td style="padding:.55rem .75rem;font-size:.8rem;text-align:right;font-weight:700;color:#1a7a5e">
                        ${ld_fmtNum(l.montant_demande || 0)}
                    </td>
                  `
            : `
                    <td style="padding:.55rem .75rem;font-size:.8rem;text-align:right;font-weight:700;color:#1a7a5e">
                        ${ld_fmtNum(l.prix_unitaire || 0)}
                    </td>
                  `
    }

    </tr>
`).join('');

    Swal.fire({
        title: `Lignes — Demande #${id}`,
        width: '860px',
        html : `
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-family:inherit">
                    <thead>
                        <tr style="background:#f8f9fa;border-bottom:2px solid #e9ecef">
                            ${['Type','Rubrique','Désignation','Qté','Unité','P.U./Mtn'].map(h =>
            `<th style="padding:.6rem .75rem;font-size:.7rem;font-weight:800;
                                    text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;
                                    text-align:${['Qté','P.U.'].includes(h)?'right':'left'}">${h}</th>`
        ).join('')}
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr style="background:#f0fdf4;border-top:2px solid #a7f3d0">
                            <td colspan="5" style="padding:.6rem .75rem;font-size:.8rem;font-weight:700;color:#065f46">
                                Total estimé
                            </td>
                            <td style="padding:.6rem .75rem;font-size:.9rem;font-weight:900;
                                       text-align:right;color:#1a7a5e;font-variant-numeric:tabular-nums">
                                ${ld_fmtNum(total)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>`,
        confirmButtonText : 'Fermer',
        confirmButtonColor: '#1a7a5e',
    });
}

// ─── Voir le suivi complet d'une demande (par ligne : nb commandes, évolution, livré/payé) ──
async function ld_voirSuiviComplet(id) {
    ld_showLoader('Chargement du suivi complet…');
    const d = await ld_post(ld_api.suivi, { demandeId: id });
    ld_hideLoader();

    if (!d) return;
    if (!d.success) { Swal.fire('Erreur', d.message || 'Impossible de charger le suivi.', 'error'); return; }

    const { demande, lignes } = d.data || {};

    // ── Bloc en-tête demande ────────────────────────────────────────────────
    const enteteHtml = `
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:1rem;">
            <div style="background:#f8f9fa;border-radius:9px;padding:.55rem .75rem;border:1px solid #f3f4f6;">
                <span style="display:block;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;">Type</span>
                <span style="font-size:.82rem;font-weight:700;color:#111827;">${ld_badgeType(demande?.type_demande)}</span>
            </div>
            <div style="background:#f8f9fa;border-radius:9px;padding:.55rem .75rem;border:1px solid #f3f4f6;">
                <span style="display:block;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;">Statut demande</span>
                <span style="font-size:.82rem;font-weight:700;color:#111827;">${ld_badgeStatut(demande?.statut)}</span>
            </div>
            <div style="background:#f8f9fa;border-radius:9px;padding:.55rem .75rem;border:1px solid #f3f4f6;">
                <span style="display:block;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;">Créée le</span>
                <span style="font-size:.82rem;font-weight:700;color:#111827;">${ld_fmtDate(demande?.date_creation)}</span>
            </div>
        </div>
    `;

    // ── Bloc(s) par ligne — nombre de commandes, évolution, livré/payé ──────
    // ⚠️ Investissement : colonne "Rubrique" (nom_rubrique), pas "Catégorie".
    const lignesHtml = (lignes || []).map(l => {
        const commandesHtml = (l.commandes || []).map(cmd => {
            const estAchat = parseInt(cmd.idTypePAP) === 1;
            const statutFinal = estAchat
                ? (cmd.livre
                    ? '<span class="ld-badge ld-badge-ok">Livré</span>'
                    : '<span class="ld-badge ld-badge-warn">Non livré</span>')
                : (cmd.paye
                    ? '<span class="ld-badge ld-badge-ok">Payé</span>'
                    : '<span class="ld-badge ld-badge-warn">Non payé</span>');

            const historiqueRows = (cmd.historique || []).map(h => `
                <li style="padding:.25rem 0;font-size:.74rem;color:#374151;border-bottom:1px dashed #f3f4f6;">
                    <strong>${ld_fmtDate(h.dateEnregistrement)}</strong> — ${h.motif || (LD_STATUT_PAP[h.idStatut] || '')}
                    ${h.utilisateur ? ` <span style="color:#9ca3af;">(par ${h.utilisateur})</span>` : ''}
                </li>
            `).join('') || '<li style="font-size:.74rem;color:#9ca3af;font-style:italic;">Aucun historique.</li>';

            return `
                <div style="margin:.5rem 0;padding:.6rem .75rem;background:#fff;border:1px solid #e9ecef;border-radius:9px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.4rem;margin-bottom:.35rem;">
                        <span style="font-size:.78rem;font-weight:700;color:#111827;">${cmd.nom_commande || ('#' + cmd.idPAP)}</span>
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                            ${ld_badgeType(estAchat ? 'demande_achat' : 'demande_paiement')}
                            ${ld_badgePapStatut(cmd.idStatut)}
                            ${statutFinal}
                        </div>
                    </div>
                    <div style="font-size:.74rem;color:#6b7280;margin-bottom:.4rem;">
                        ${estAchat
                ? `Quantité commandée : <strong style="color:#111827;">${cmd.quantite_commandee ?? '—'}</strong>`
                : `Montant à payer : <strong style="color:#111827;">${ld_fmtNum(cmd.montant_a_payer || 0)}</strong>`}
                    </div>
                    <ul style="list-style:none;padding:0;margin:0;">${historiqueRows}</ul>
                </div>
            `;
        }).join('');

        return `
            <div style="margin-bottom:1.1rem;padding:1rem;border:1.5px solid #e9ecef;border-radius:12px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;margin-bottom:.4rem;">
                    <div>
                        <span style="font-size:.85rem;font-weight:800;color:#111827;">${l.designation || '—'}</span>
                        <div style="font-size:.74rem;color:#9ca3af;">${l.nom_rubrique || '—'} · ${l.quantite || '—'} ${l.unite || ''}</div>
                    </div>
                    <span style="font-size:.72rem;font-weight:800;color:#1a7a5e;background:#ecfdf5;padding:.25rem .6rem;border-radius:99px;white-space:nowrap;">
                        ${l.nombre_commandes} demande${l.nombre_commandes > 1 ? 's' : ''} effectuée${l.nombre_commandes > 1 ? 's' : ''}
                    </span>
                </div>
                ${l.nombre_commandes > 0
            ? commandesHtml
            : '<p style="font-size:.78rem;color:#9ca3af;font-style:italic;margin:0;">Aucune commande/paiement effectué par le DRH pour cette ligne.</p>'}
            </div>
        `;
    }).join('') || '<p style="text-align:center;color:#9ca3af;font-style:italic;">Aucune ligne.</p>';

    Swal.fire({
        title: `Suivi complet — Demande #${id}`,
        width: '900px',
        html: `<div style="text-align:left;max-height:70vh;overflow-y:auto;">
                    ${enteteHtml}
                    <h4 style="font-size:.8rem;font-weight:800;color:#111827;margin:1rem 0 .4rem;">Lignes de la demande</h4>
                    ${lignesHtml}
               </div>`,
        confirmButtonText: 'Fermer',
        confirmButtonColor: '#1a7a5e',
    });
}

// ─── Supprimer une demande ────────────────────────────────────────────────────
async function ld_supprimer(id) {
    const c = await Swal.fire({
        title: 'Supprimer la demande ?',
        html : `<p style="font-size:.875rem;color:#374151">
                    La demande <strong>#${id}</strong> sera supprimée.<br>
                    <small style="color:#9ca3af">Seules les demandes "En création" sans lignes actives peuvent être supprimées.</small>
                </p>`,
        icon            : 'warning',
        showCancelButton : true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText : 'Annuler',
        confirmButtonColor: '#ef4444',
    });
    if (!c.isConfirmed) return;

    ld_showLoader('Suppression…');
    const d = await ld_post(ld_api.supprimer, { demandeId: id });
    ld_hideLoader();

    if (!d?.success) { Swal.fire('Erreur', d?.message || 'Erreur.', 'error'); return; }
    await Swal.fire({ icon:'success', title:'Supprimée', text:d.message, timer:1500, showConfirmButton:false });
    ld_loadListe();
}

// ─── Événements ───────────────────────────────────────────────────────────────
function ld_bindEvents() {
    document.getElementById('ld-btn-nouvelle')?.addEventListener('click', ld_ouvrirModal);
    document.getElementById('ld-btn-fermer')?.addEventListener('click', ld_fermerModal);
    document.getElementById('ld-modal-overlay')?.addEventListener('click', ld_fermerModal);
    document.getElementById('ld-sel-budget')?.addEventListener('change', ld_majBtnSuivant);
    document.getElementById('ld-sel-type')?.addEventListener('change', ld_majBtnSuivant);
    document.getElementById('ld-btn-suivant')?.addEventListener('click', ld_creerEtRediriger);
    document.getElementById('ld-refresh-btn')?.addEventListener('click', ld_loadListe);
}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    ld_initTable();
    ld_loadListe();
    ld_bindEvents();
});