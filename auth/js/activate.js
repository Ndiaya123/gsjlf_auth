document.getElementById("annee_en_cours1").textContent = new Date().getFullYear();


const SLIDES=[{logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',name:'UAHB',desc:'Université Amadou Hampâté Bâ'},{logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png',name:'CMJLF',desc:'Collège Moderne Jean de la Fontaine'},{logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',name:'CTD',desc:'Collège Technique de Dakar'}];
let cur=0,timer=null;
const slides=document.querySelectorAll('.slide');
const dotsEl=document.getElementById('slide-dots');
SLIDES.forEach((_,i)=>{const d=document.createElement('div');d.className='slide-dot'+(i===0?' active':'');d.style.width=i===0?'28px':'8px';d.addEventListener('click',()=>goSlide(i));dotsEl.appendChild(d);});
function getDots(){return dotsEl.querySelectorAll('.slide-dot')}
function goSlide(n,restart=true){slides[cur].classList.remove('active');getDots()[cur].classList.remove('active');getDots()[cur].style.width='8px';cur=(n+SLIDES.length)%SLIDES.length;slides[cur].classList.add('active');getDots()[cur].classList.add('active');getDots()[cur].style.width='28px';const el=document.getElementById('ent-logo'),en=document.getElementById('ent-name'),ed=document.getElementById('ent-desc');[el,en,ed].forEach(x=>{x.style.transition='opacity .3s';x.style.opacity='0';});setTimeout(()=>{el.src=SLIDES[cur].logo;en.textContent=SLIDES[cur].name;ed.textContent=SLIDES[cur].desc;[el,en,ed].forEach(x=>x.style.opacity='1');},300);if(restart){clearInterval(timer);timer=setInterval(()=>goSlide(cur+1,false),5000);}}
timer=setInterval(()=>goSlide(cur+1,false),5000);

let timerInt=null,seconds=899,codeSent=false;
const codeInputs=document.querySelectorAll('.code-grid input');
codeInputs.forEach((inp,i)=>{inp.addEventListener('input',()=>{inp.value=inp.value.replace(/\D/g,'').slice(0,1);if(inp.value&&codeInputs[i+1])codeInputs[i+1].focus();if([...codeInputs].every(x=>x.value))verifyCode();});inp.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!inp.value&&codeInputs[i-1])codeInputs[i-1].focus();});});
function startTimer(){timerInt=setInterval(()=>{seconds--;const m=Math.floor(seconds/60).toString().padStart(2,'0'),s=(seconds%60).toString().padStart(2,'0');document.getElementById('timer-display').textContent=m+':'+s;if(seconds<=0){clearInterval(timerInt);document.getElementById('resend-btn').style.display='inline-flex';const tp=document.getElementById('timer-pill');tp.style.background='rgba(229,57,53,.1)';tp.style.color='#e53935';}},1000);}
function handleActivate(){const email=document.getElementById('email-input').value;if(!email){document.getElementById('email-input').focus();return;}const btn=document.getElementById('main-btn');if(!codeSent){btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Envoi…';btn.disabled=true;setTimeout(()=>{codeSent=true;document.getElementById('code-section').style.display='block';document.getElementById('resend-btn').style.display='inline-flex';btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px">verified</span> Activer le compte';btn.disabled=false;startTimer();codeInputs[0].focus();},1200);}else verifyCode(true);}
function verifyCode(manual=false){const code=[...codeInputs].map(x=>x.value).join('');if(code.length===6||manual){const btn=document.getElementById('main-btn');btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Vérification…';btn.disabled=true;setTimeout(()=>{clearInterval(timerInt);document.getElementById('main-form').style.display='none';document.getElementById('success-state').style.display='flex';},1200);}}
function resend(){seconds=899;clearInterval(timerInt);startTimer();codeInputs.forEach(x=>x.value='');codeInputs[0].focus();const tp=document.getElementById('timer-pill');tp.style.background='';tp.style.color='';}


document.addEventListener('DOMContentLoaded',otpSetup);
function otpSetup(){
    document.querySelectorAll('.otp-cell').forEach((inp,i,arr)=>{
        inp.addEventListener('input',()=>{
            inp.value=inp.value.replace(/\D/,'');
            inp.classList.toggle('filled',!!inp.value);
            if(inp.value&&arr[i+1])arr[i+1].focus();
        });
        inp.addEventListener('keydown',e=>{
            if(e.key==='Backspace'&&!inp.value&&arr[i-1]){arr[i-1].focus();arr[i-1].value='';arr[i-1].classList.remove('filled')}
        });
    });
}



function showAlert(title,message, type = "error", redirect = null, resetForm = false, btn = null,nomBtn = "OK") {
    const normalContent = `
       <span class="material-symbols-outlined" style="font-size:13px;vertical-align:middle">refresh</span> Renvoyer le lien
    `;
    Swal.fire({
        title : title,
        text: message,
        icon: type,
        timer: 4000,
        timerProgressBar: true,
        confirmButtonText: nomBtn,
        buttonsStyling: false,
        customClass: { confirmButton: "btn" },
        didOpen: () => {
            const confirmBtn = Swal.getConfirmButton();
            const colors = {
                success: "#113B26",
                warning: "#ffc107",
                error: "#dc3545",
                info: "#0d6efd",
                question: "#6c757d"
            };
            confirmBtn.style.backgroundColor =
                colors[type] || "#0d6efd";

            // confirmBtn.style.backgroundColor = type === "success" ? "#113B26" : "#dc3545";
            confirmBtn.style.color = "#fff";
        }
    }).then(() => {
        if (resetForm)
        {
        }
        if (btn) { btn.innerHTML = normalContent; btn.disabled = false; }
        if (redirect) window.location.href = redirect;
    });
}


function resetActivation()
{
   const  matricule = document.getElementById("matricule").value;

    const t = document.getElementById('btnResetActivation');

    t.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> En cours …';
    t.disabled  = true;

    $.ajax({
        type: 'post',
        url: '/personnel/auth-controller',
        data: {option : 3,matricule : matricule},
        success: function (resp) {
            if      (resp === "erreurConnexion")    showAlert("Erreur de connexion","Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, false, t,"OK");
            else if (resp === "dejaActive")         showAlert("Compte déjà actif", "Votre compte est déjà activé. Veuillez vous connecter pour accéder à votre espace personnel.", "warning", "/personnel/signin", true, t, "Se connecter");
            else if (resp === "pasContrat")         showAlert("Aucun contrat","Aucun contrat trouvé. Veuillez contacter le DRH.", "error", null, true, t,"OK");
            else if (resp === "finContrat")         showAlert("Contrat expiré","Votre contrat arrive à échéance. Veuillez contacter le DRH.", "error", null, true, t,"OK");
            else if (resp === "erreurMail")         showAlert("Erreur d’envoi","Erreur lors de l'envoi de l'email de confirmation. Veuillez réessayer ultérieurement.", "error", null, true, t,"OK");
            else if (resp === "succès") {
                setTimeout(() => {
                    document.getElementById('success-state').style.display   = 'none';
                    document.getElementById('success-state-mail').style.display = 'flex';
                }, 500);
            } else {
                showAlert( "Erreur inattendue","Une erreur inattendue est survenue. Veuillez réessayer.", "error", null, true, t);
            }
        },
        error: function () {
            showAlert("Erreur réseau", "Vérifiez votre connexion et réessayez.", "error", null, false, t);
        }
    })

}
