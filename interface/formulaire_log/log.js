// log.js - JavaScript pour la page de connexion

// Attendre que la page soit chargée
document.addEventListener('DOMContentLoaded', function() {
    
    // Récupérer le formulaire
    const form = document.querySelector('form');
    
    if (form) {
        // Ajouter une animation au clic sur le bouton
        const button = form.querySelector('button');
        
        if (button) {
            button.addEventListener('click', function(e) {
                // Ajouter un effet de chargement
                this.innerHTML = '⏳ Connexion...';
                this.style.opacity = '0.7';
                
                // Le formulaire sera soumis normalement
                // Pas de e.preventDefault() pour laisser le formulaire s'envoyer
            });
        }
    }
    
    // Auto-effacement des messages d'erreur après 5 secondes
    const errorMessage = document.querySelector('.error-message');
    const successMessage = document.querySelector('.success-message');
    
    if (errorMessage) {
        setTimeout(function() {
            errorMessage.style.opacity = '0';
            setTimeout(function() {
                if (errorMessage) errorMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
    
    if (successMessage) {
        setTimeout(function() {
            successMessage.style.opacity = '0';
            setTimeout(function() {
                if (successMessage) successMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
});