// ================================================
//  VACATION.JS
//  Université Mouloud Mammeri de Tizi-Ouzou
//  Faculté de Génie Électrique et d'Informatique
// ================================================

document.addEventListener('DOMContentLoaded', function () {

    // 1. Navigation TAB entre champs contenteditable
    var champs = Array.from(document.querySelectorAll('[contenteditable="true"]'));

    champs.forEach(function (champ, index) {
        champ.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
            if (e.key === 'Tab') {
                e.preventDefault();
                var suivant = e.shiftKey ? champs[index - 1] : champs[index + 1];
                if (suivant) {
                    suivant.focus();
                    placerCurseurFin(suivant);
                }
            }
        });
    });

    // 2. Message de succes si ?success=1 dans l URL
    var params = new URLSearchParams(window.location.search);
    if (params.get('success') === '1') {
        var msg = document.getElementById('msgSuccess');
        if (msg) {
            msg.style.display = 'block';
            msg.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // 3. Bouton Imprimer
    var btnImprimer = document.getElementById('btnImprimer');
    if (btnImprimer) {
        btnImprimer.addEventListener('click', function () {
            window.print();
        });
    }

    // 4. Bouton Reinitialiser
    var btnReset = document.getElementById('btnReset');
    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (!confirm('Réinitialiser tous les champs ?')) return;

            document.querySelectorAll('#formVacation input').forEach(function (input) {
                input.value = '';
            });

            document.querySelectorAll('[contenteditable="true"]').forEach(function (el) {
                el.textContent = '';
            });
        });
    }

});

function placerCurseurFin(el) {
    var range = document.createRange();
    var sel   = window.getSelection();
    range.selectNodeContents(el);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
}