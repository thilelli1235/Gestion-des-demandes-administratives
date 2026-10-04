// ================================================
//  CONVENTION DE STAGE — CNV.JS
//  Université Mouloud Mammeri de Tizi-Ouzou
//  Faculté de Génie Électrique et d'Informatique
// ================================================

const EMAIL_REGEX = /^[a-zA-ZÀ-ÿ]+\.[a-zA-ZÀ-ÿ]+@fgei\.ummto\.dz$/;

const VALEURS_DEFAUT = [
  "136",
  "2026",
  "HASNAOUA II – BP 17 RP TIZI-OUZOU.",
  "020 94 24 41",
  "",
  "",
  "",
  "",
  "",
];

// Validation email
function validerEmail(inputId, msgId) {
  const input = document.getElementById(inputId);
  const msg   = document.getElementById(msgId);
  if (!input || !msg) return;

  const val = input.value.trim();

  if (val === "") {
    input.classList.remove("valid", "invalid");
    msg.textContent = "";
    msg.className = "email-msg";
    return;
  }

  if (EMAIL_REGEX.test(val)) {
    input.classList.add("valid");
    input.classList.remove("invalid");
    msg.textContent = "✔ Valide";
    msg.className = "email-msg ok";
  } else {
    input.classList.add("invalid");
    input.classList.remove("valid");
    msg.textContent = "✘ prenom.nom@fgei.ummto.dz";
    msg.className = "email-msg err";
  }
}

// Placer curseur à la fin
function placerCurseurFin(el) {
  const range = document.createRange();
  const sel   = window.getSelection();
  range.selectNodeContents(el);
  range.collapse(false);
  sel.removeAllRanges();
  sel.addRange(range);
}

document.addEventListener("DOMContentLoaded", function () {

  // 1. Validation email
  const input1 = document.getElementById("email1");
  if (input1) {
    input1.addEventListener("input", () => validerEmail("email1", "email1-msg"));
    input1.addEventListener("blur",  () => validerEmail("email1", "email1-msg"));
  }

  // 2. Message succes si ?success=1
  var params = new URLSearchParams(window.location.search);
  if (params.get("success") === "1") {
    var msg = document.getElementById("msgSuccess");
    if (msg) {
      msg.style.display = "block";
      msg.scrollIntoView({ behavior: "smooth" });
    }
  }

  // 3. Bouton Imprimer
  var btnImprimer = document.getElementById("btnImprimer");
  if (btnImprimer) {
    btnImprimer.addEventListener("click", function () {
      const e1 = document.getElementById("email1").value.trim();
      if (e1 && !EMAIL_REGEX.test(e1)) {
        alert("Email invalide.\nFormat : prenom.nom@fgei.ummto.dz");
        document.getElementById("email1").focus();
        return;
      }
      window.print();
    });
  }

  // 4. Bouton Reinitialiser
  var btnReset = document.getElementById("btnReset");
  if (btnReset) {
    btnReset.addEventListener("click", function () {
      if (!confirm("Réinitialiser tous les champs ?")) return;

      document.querySelectorAll("[contenteditable='true']").forEach(function (el, i) {
        el.textContent = VALEURS_DEFAUT[i] !== undefined ? VALEURS_DEFAUT[i] : "";
      });

      const input = document.getElementById("email1");
      const msgEl = document.getElementById("email1-msg");
      if (input) {
        input.value = "";
        input.classList.remove("valid", "invalid");
      }
      if (msgEl) {
        msgEl.textContent = "";
        msgEl.className = "email-msg";
      }

      // Vider les inputs du formulaire
      document.querySelectorAll("#formConvention input[type='text'], #formConvention input[type='date']").forEach(function (el) {
        el.value = "";
      });
    });
  }

  // 5. Navigation Tab entre champs éditables
  const champs = Array.from(document.querySelectorAll("[contenteditable='true']"));
  champs.forEach(function (champ, index) {
    champ.addEventListener("keydown", function (e) {
      if (e.key === "Enter") e.preventDefault();
      if (e.key === "Tab") {
        e.preventDefault();
        const suivant = e.shiftKey ? champs[index - 1] : champs[index + 1];
        if (suivant) {
          suivant.focus();
          placerCurseurFin(suivant);
        }
      }
    });
  });

});