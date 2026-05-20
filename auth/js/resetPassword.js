const SLIDES=[{logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',name:'UAHB',desc:'Université Amadou Hampâté Bâ'},{logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png',name:'CMJLF',desc:'Collège Moderne Jean de la Fontaine'},{logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',name:'CTD',desc:'Collège Technique de Dakar'}];
let cur=0,timer=null;
const slides=document.querySelectorAll('.ps_slide');
const dotsEl=document.getElementById('slide-dots');
SLIDES.forEach((_,i)=>{const d=document.createElement('div');d.className='ps_slide-dot'+(i===0?' ps_active':'');d.style.width=i===0?'28px':'8px';d.addEventListener('click',()=>goSlide(i));dotsEl.appendChild(d);});
function getDots(){return dotsEl.querySelectorAll('.ps_slide-dot')}
function goSlide(n,restart=true){slides[cur].classList.remove('ps_active');getDots()[cur].classList.remove('ps_active');getDots()[cur].style.width='8px';cur=(n+SLIDES.length)%SLIDES.length;slides[cur].classList.add('ps_active');getDots()[cur].classList.add('ps_active');getDots()[cur].style.width='28px';const el=document.getElementById('ent-logo'),en=document.getElementById('ent-name'),ed=document.getElementById('ent-desc');[el,en,ed].forEach(x=>{x.style.transition='opacity .3s';x.style.opacity='0';});setTimeout(()=>{el.src=SLIDES[cur].logo;en.textContent=SLIDES[cur].name;ed.textContent=SLIDES[cur].desc;[el,en,ed].forEach(x=>x.style.opacity='1');},300);if(restart){clearInterval(timer);timer=setInterval(()=>goSlide(cur+1,false),5000);}}
timer=setInterval(()=>goSlide(cur+1,false),5000);

function goPhase(n){[1,2,3].forEach(i=>{const el=document.getElementById('phase'+i);if(el){el.style.display=i===n?'block':'none';if(i===n)el.className='ps_phase';}});}
function sendLink(){const email=document.getElementById('email1').value;if(!email){document.getElementById('email1').focus();return;}document.getElementById('email-display').textContent=email;const btn=document.getElementById('btn-p1');btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> Envoi…';btn.disabled=true;setTimeout(()=>goPhase(2),1300);}
function togglePw(fid,iid){const f=document.getElementById(fid),i=document.getElementById(iid);f.type=f.type==='password'?'text':'password';i.textContent=f.type==='password'?'visibility':'visibility_off';}
function checkStrength(v) {
    let s = 0;
    if(v.length >= 8) s++;
    if(/[A-Z]/.test(v)) s++;
    if(/[0-9]/.test(v)) s++;
    if(/[^A-Za-z0-9]/.test(v)) s++;
    const pct  = [0, 25, 50, 75, 100][s];
    const col  = ['', '#e53935', '#ff9800', '#fbc02d', '#2e7d32'][s];
    const lbl  = ['—', 'Trop court', 'Faible', 'Moyen', 'Fort'][s];
    document.getElementById('sfill').style.cssText = `width:${pct}%;background:${col}`;
    document.getElementById('slabel').textContent  = lbl;
    checkMatch();

    return s;
}
// function checkStrength(v){let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const f=document.getElementById('sfill');if(f){f.style.width=[0,25,50,75,100][s]+'%';f.style.background=['','#e53935','#ff9800','#fbc02d','#2e7d32'][s];}const sl=document.getElementById('slabel');if(sl)sl.textContent=['—','Trop court','Faible','Moyen','Fort'][s];checkMatch();}
function checkMatch(){const p1=document.getElementById('pw1').value,p2=document.getElementById('pw2').value,err=document.getElementById('match-err'),f2=document.getElementById('pw2');if(!p2)return;const bad=p2.length>0&&p1!==p2;err.classList.toggle('ps_show',bad);f2.style.borderColor=bad?'#e53935':'';f2.style.boxShadow=bad?'0 0 0 4px rgba(229,57,53,.1)':'';}
function handleReset(){const p1=document.getElementById('pw1').value,p2=document.getElementById('pw2').value;if(!p1||!p2)return;if(p1!==p2){checkMatch();document.getElementById('pw2').focus();return;}const btn=document.getElementById('btn-reset');btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> Enregistrement…';btn.disabled=true;setTimeout(()=>{[1,2,3].forEach(i=>{const el=document.getElementById('phase'+i);if(el)el.style.display='none';});document.getElementById('success-state').style.display='flex';},1300);}



function resetPassword()
{

    window.location.href = '/personnel/reset-password';


}




function showAlert(message, type = "error", redirect = null, resetForm = false, btn = null) {


    const normalContent = `
        <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>Enregistrer le nouveau mot de passe
    `;

    Swal.fire({
        text: message,
        icon: type,
        timer: 4000,
        timerProgressBar: true,
        confirmButtonText: "OK",
        buttonsStyling: false,
        customClass: {
            confirmButton: "btn"
        },
        didOpen: () => {
            const confirmBtn = Swal.getConfirmButton();

            if (type === "success") {
                confirmBtn.style.backgroundColor = "#113B26";
                confirmBtn.style.color = "#fff";
            } else {
                confirmBtn.style.backgroundColor = "#dc3545";
                confirmBtn.style.color = "#fff";
            }
        }
    }).then(() => {

        if (resetForm) {
            $("#formReset")[0].reset();
        }

        // ✅ corriger ici
        if (btn) {


            // btn.removeAttribute('data-kt-indicator');
            // btn.disabled = false;

            btn.innerHTML = normalContent;
            btn.disabled = false;
        }

        if (redirect) {
            window.location.href = redirect;
        }
    });
}




const formReset = document.getElementById('formReset');
var validator = FormValidation.formValidation(
    formReset,
    {
        fields: {

            password: {
                validators: {
                    notEmpty: {
                        message: "Le nouveau mot de passe est un champ obligatoire. Veuillez le résigner."
                    },
                    // callback: {
                    //   message: "Veuillez entrer un mot de passe valide",
                    //  callback: function (e) {
                    //    if (e.value.length > 0) return s()
                    // }
                    //}

                    callback: {
                        message: "Mot de passe trop faible. Utilisez au moins 8 caractères, une majuscule, un chiffre et un symbole.",
                        callback: function (input) {
                            const value = input.value;

                            const score = checkStrength(value);

                            // ❌ Refuser si < Bon
                            return score >= 3;
                        }
                    }
                }
            },
            "confirm-password": {
                validators: {
                    notEmpty: {
                        message: "Le mot de passe de confirmation est obligatoire. Veuilez le renseigner."
                    },
                    identical: {
                        compare: function () {
                            return e.querySelector('[name="password"]').value
                        },
                        message: "Le nouveau mot de passe et le mot de passe de confirmation ne correspondent pas."
                    }
                }
            }

        },

        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap: new FormValidation.plugins.Bootstrap5({
                rowSelector: '.ps_field'
            })
        }
    }
);

const t = document.getElementById('formReset_submit');
t.addEventListener('click', function (e) {
    e.preventDefault();
    if (validator) {
        validator.validate().then(function (status) {
            if (status == 'Valid') {
                //  t.setAttribute('data-kt-indicator', 'on');
                // t.disabled = true;

                t.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Enregistrement…';
                t.disabled  = true;

                setTimeout(function () {
                    var form_data = $("#formReset").serialize();
                    $.ajax({
                        type: 'post',
                        url: '/personnel/auth-controller',
                        data: form_data,
                        success: function (resp) {

                            alert(resp);

                            if (resp === "erreurConnexion") {
                                showAlert("Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t);

                            } else if (resp === "champsObligatoire") {
                                showAlert("Les champs marqués d'un astérisque (*) sont obligatoires.", "error", null, false, t);

                            } else if (resp === "pasContrat") {
                                showAlert("Aucun contrat n’a été trouvé. Veuillez vous rapprocher du DRH.", "error", null, true, t);

                            } else if (resp === "pasCompte") {
                                showAlert("Vous n’avez pas de compte. Veuillez vous inscrire.", "error", "/personnel/signup", true, t);

                            } else if (resp === "finContrat") {
                                showAlert("Votre contrat arrive à échéance. Veuillez contacter le DRH.", "error", null, true, t);

                            } else if (resp === "pasCorrespondantPWD") {
                                showAlert("Les mots de passe ne correspondent pas.", "error", null, false, t);

                            }else if (resp === "compteInactive") {

                                showAlert("Le compte n'est pas encore activé. Veuillez cliquer sur le lien envoyé par mail pour l'activer.", "error", null, true, t);

                            }else if (resp === "bloquer") {

                                showAlert("Le compte est bloqué. Veuillez contacter le service informatique.b", "error", null, true, t);

                            } else if (resp.substr(0, 6) === "succès") {
                                // showAlert("Compte créé avec succès !", "success", "/personnel/activate-account/"+resp.substr(6), false, t);

                                setTimeout(() => {

                                    // cacher le formulaire
                                    document.getElementById('phase3').style.display = 'none';

                                    // afficher succès
                                    document.getElementById('success-state-reset').style.display = 'flex';
                                    // attendre 2 secondes avant redirection
                                    // setTimeout(() => {

                                    //  window.location.href = "/personnel/accueil";

                                    //  }, 5000);

                                }, 500);

                            } else {
                                showAlert("Une erreur est survenue. Veuillez réessayer ultérieurement.", "error", null, true, t);
                            }


                        }
                    })
                }, 2000);
            }
        });
    }
});



