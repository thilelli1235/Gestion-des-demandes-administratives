document.getElementById("loginForm")?.addEventListener("submit", function(e) {

    let username = document.getElementById("username");
    let password = document.getElementById("password");

    let userError = document.getElementById("userError");
    let passError = document.getElementById("passError");

    userError.textContent = "";
    passError.textContent = "";

    let valid = true;

    if (username.value.trim() === "") {
        userError.textContent = "Veuillez renseigner ce champ";
        valid = false;
    }

    if (password.value.trim() === "") {
        passError.textContent = "Veuillez renseigner ce champ";
        valid = false;
    }

    if (!valid) {
        e.preventDefault(); // seulement si erreur
    }
});