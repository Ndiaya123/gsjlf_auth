$(document).ready(function () {

    $.ajax({
        type: 'POST', url: '/personnel/user-controller', data: { option: 1 },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner un agent</option>';
            $('#agentSelectForTask').html(html);

        },
        error: function () {

            Swal.fire('Erreur', "Impossible de charger les qualifications.", 'error');
        }
    });
});




