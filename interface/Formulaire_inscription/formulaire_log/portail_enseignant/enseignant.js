document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Déclenchement des animations d'entrée
    const elements = document.querySelectorAll('.reveal, .reveal-delayed');
    setTimeout(() => {
        elements.forEach(el => el.classList.add('active'));
    }, 150);

    
    
    setInterval(updateClock, 1000);
    updateClock();

    // 3. Effet de survol magnétique sur les cartes
     const cartes = document.querySelectorAll('.card');
 
    cartes.forEach(carte => {
        carte.addEventListener('mousemove', (e) => {
            const zone = carte.getBoundingClientRect(); // Dimensions de la carte
            const x = e.clientX - zone.left;           // Position X relative à la carte
            const y = e.clientY - zone.top;            // Position Y relative à la carte
            carte.style.setProperty('--mouse-x', `${x}px`);
            carte.style.setProperty('--mouse-y', `${y}px`);
        });
    });
 
});

// 4. Fonction de déconnexion avec style
function confirmerDeconnexion() {
    const confirmation = confirm("Voulez-vous vraiment quitter votre session ?");
 
    if (confirmation) {
        // Animation de fondu avant la redirection
        document.body.style.transition = "opacity 0.5s ease";
        document.body.style.opacity    = "0";
 
        // Redirection vers la page de connexion après le fondu
        setTimeout(() => {
            window.location.href = "acceuil1.html";
        }, 500);
    }
    // Si l'utilisateur clique "Annuler", on ne fait rien
}