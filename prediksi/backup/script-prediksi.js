document.addEventListener("DOMContentLoaded", function () {
    const tooltipWrappers = document.querySelectorAll(".metric-help-wrap");

    function closeAllTooltips(except = null) {
        tooltipWrappers.forEach((wrapper) => {
            if (wrapper !== except) {
                wrapper.classList.remove("is-open");
            }
        });
    }

    tooltipWrappers.forEach((wrapper) => {
        const button = wrapper.querySelector(".metric-help-btn");

        if (!button) {
            return;
        }

        button.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = wrapper.classList.contains("is-open");

            closeAllTooltips(wrapper);

            if (isOpen) {
                wrapper.classList.remove("is-open");
            } else {
                wrapper.classList.add("is-open");
            }
        });
    });

    document.addEventListener("click", function () {
        closeAllTooltips();
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeAllTooltips();
        }
    });

        /*
    |--------------------------------------------------------------------------
    | PROSES TOMBOL SIMPAN HASIL PREDIKSI
    |--------------------------------------------------------------------------
    | Ketika form dikirim:
    | 1. Tombol dinonaktifkan agar tidak diklik berulang kali.
    | 2. Teks tombol berubah menjadi "Menyimpan...".
    | 3. Form tetap dikirim normal ke backend.
    |
    | Tidak menggunakan preventDefault(), sehingga proses POST tetap berjalan.
    |--------------------------------------------------------------------------
    */
    const savePredictionForm = document.getElementById(
        "savePredictionForm"
    );

    const savePredictionButton = document.getElementById(
        "btnSavePrediction"
    );

    if (savePredictionForm && savePredictionButton) {
        savePredictionForm.addEventListener("submit", function () {
            savePredictionButton.disabled = true;

            savePredictionButton.innerHTML = `
                <span class="btn-save-icon" aria-hidden="true">⏳</span>
                <span class="btn-save-text">Menyimpan...</span>
            `;
        });
    }
});