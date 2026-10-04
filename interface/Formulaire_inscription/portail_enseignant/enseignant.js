// Aucun confirm() — modal custom uniquement

function confirmerDeconnexion() {
    document.getElementById('modalLogout').classList.add('open');
}

function fermerModal() {
    document.getElementById('modalLogout').classList.remove('open');
}

function deconnecter() {
    document.body.style.transition = 'opacity 0.4s ease';
    document.body.style.opacity = '0';
    setTimeout(function() {
        window.location.href = '../formulaire_log/log.html';
    }, 400);
}

document.addEventListener('DOMContentLoaded', function() {

    // Fermer modal en cliquant sur le fond
    document.getElementById('modalLogout').addEventListener('click', function(e) {
        if (e.target === this) fermerModal();
    });

    // Horloge live
    function updateClock() {
        var el = document.getElementById('live-clock');
        if (!el) return;
        var now = new Date();
        var h = String(now.getHours()).padStart(2,'0');
        var m = String(now.getMinutes()).padStart(2,'0');
        var s = String(now.getSeconds()).padStart(2,'0');
        el.innerHTML = '<i class="fa-regular fa-clock"></i> ' + h + ':' + m + ':' + s;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Effet magnétique cartes
    document.querySelectorAll('.card').forEach(function(carte) {
        carte.addEventListener('mousemove', function(e) {
            var zone = carte.getBoundingClientRect();
            carte.style.setProperty('--mouse-x', (e.clientX - zone.left) + 'px');
            carte.style.setProperty('--mouse-y', (e.clientY - zone.top)  + 'px');
        });
    });

});