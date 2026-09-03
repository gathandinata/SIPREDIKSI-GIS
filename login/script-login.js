const loginForm = document.getElementById("loginForm");
const username = document.getElementById("username");
const password = document.getElementById("password");
const usernameError = document.getElementById("usernameError");
const passwordError = document.getElementById("passwordError");
const togglePassword = document.getElementById("togglePassword");

togglePassword.addEventListener("click", function () {
    if (password.type === "password") {
        password.type = "text";
        togglePassword.textContent = "Tutup";
    } else {
        password.type = "password";
        togglePassword.textContent = "Lihat";
    }
});

loginForm.addEventListener("submit", function (event) {
    let valid = true;

    usernameError.textContent = "";
    passwordError.textContent = "";

    if (username.value.trim() === "") {
        usernameError.textContent = "Username wajib diisi.";
        valid = false;
    }

    if (password.value.trim() === "") {
        passwordError.textContent = "Password wajib diisi.";
        valid = false;
    }

    if (!valid) {
        event.preventDefault();
    }
});