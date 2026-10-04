let nom = document.getElementById("nom");
let prenom = document.getElementById("prenom");
let email = document.getElementById("email");
let username = document.getElementById("username");

function clean(str) {
    return str
        .toLowerCase()
        .trim()
        .normalize("NFD")               // enlève accents
        .replace(/[\u0300-\u036f]/g, "") // supprime accents
        .replace(/\s+/g, "");           // supprime espaces
}

// 🔥 EMAIL AUTOMATIQUE FIABLE
function genererEmail() {

    if (nom.value && prenom.value) {

        let n = clean(nom.value);
        let p = clean(prenom.value);

        // format demandé EXACT
        email.value = n + "." + p + "@fgei.ummto.dz";

        // username
        username.value = n + "_" + p;
    }
}

// déclenchement instantané ✔
nom.addEventListener("input", genererEmail);
prenom.addEventListener("input", genererEmail);
