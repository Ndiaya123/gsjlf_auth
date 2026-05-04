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

function showAlert(message, type = "error", redirect = null, reload = false, btn = null) {

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

        if (reload) {
            location.reload(true);        }

        // ✅ corriger ici
        if (btn) {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
        }

        if (redirect) {
            window.location.href = redirect;
        }
    });
}


function resetActivation()
{
    document.getElementById("btnActivate").disabled = true;
   const  matricule = document.getElementById("matricule").value;


    const t = document.getElementById('btnResetActivation');

    t.setAttribute('data-kt-indicator', 'on');
    t.disabled = true;

    $.ajax({
        type: 'post',
        url: '/personnel/auth-controller',
        data: {option : 3,matricule : matricule},
        success: function (resp) {
            alert(resp);
            document.getElementById("btnActivate").disabled = false;



            if (resp == "erreurConnexion") {
                showAlert("Erreur de connexion. Veuillez réessayer ultérieurement.", "error", null, true, t);

            } else if (resp === "champsObligatoire") {
                showAlert("Les champs marqués d'un astérisque (*) sont obligatoires.", "error", null, false, t);

            } else if (resp == "erreurMail") {
                showAlert("L’adresse e-mail n’est pas valide ou une erreur est survenue lors de l’envoi du mail.", "error", null, true, t);

            } else if (resp.substr(0, 6) == "succès") {
                showAlert("Compte créé avec succès !", "success", "/personnel/activate-account/"+resp.substr(6), false, t);

            } else {
                showAlert("Une erreur est survenue. Veuillez réessayer ultérieurement.", "error", null, true, t);
            }


        }
    })

}

function activate()
{

    const code1 = document.getElementById("code1").value;
    const code2 = document.getElementById("code2").value;
    const code3 = document.getElementById("code3").value;
    const code4 = document.getElementById("code4").value;
    const code5 = document.getElementById("code5").value;
    const code6 = document.getElementById("code6").value;

    const matricule = document.getElementById('matricule').value;
    const code = code1+""+code2+""+code3+""+code4+""+code5+""+code6;

    const t = document.getElementById('btnActivate');
    t.setAttribute('data-kt-indicator', 'on');
    t.disabled = true;


    alert("activer compte");

    $.ajax({
        type: 'post',
        url: '/personnel/auth-controller',
        data: { option: 4, matricule: matricule, code: code },
        dataType: 'text',
        success: function (resp) {
            console.log("RESP:", resp);

        }
    });



}