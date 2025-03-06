document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("loginForm").addEventListener("submit", function (e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append("action", "login");

        fetch("auth.php", {
            method: "POST",
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.href = "index.php";
                }
            });
    });

    document.getElementById("registerForm").addEventListener("submit", function (e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append("action", "register");

        fetch("auth.php", {
            method: "POST",
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    document.getElementById("registerForm").reset();
                    document.getElementById("register-form").classList.add("hidden");
                    document.getElementById("login-form").classList.remove("hidden");
                }
            });
    });

    document.getElementById('show-register').addEventListener('click', function (e) {
        e.preventDefault();
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.remove('hidden');
    });

    document.getElementById('show-login').addEventListener('click', function (e) {
        e.preventDefault();
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('login-form').classList.remove('hidden');
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const languageSelector = document.getElementById("language-selector");

    languageSelector.addEventListener("change", function () {
        const selectedLang = this.value;
        fetch(`Includes/language.php?lang=${selectedLang}`)
            .then(() => {
                location.reload(); // Oldal újratöltése az új nyelvvel
            });
    });
});

