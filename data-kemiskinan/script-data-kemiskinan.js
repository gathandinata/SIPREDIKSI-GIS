const modals = document.querySelectorAll(".modal");
const openModalButtons = document.querySelectorAll("[data-open-modal]");
const closeModalButtons = document.querySelectorAll("[data-close-modal]");

function openModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.add("show");
    }
}

function closeAllModals() {
    modals.forEach((modal) => {
        modal.classList.remove("show");
    });
}

openModalButtons.forEach((button) => {
    button.addEventListener("click", () => {
        const modalId = button.getAttribute("data-open-modal");
        openModal(modalId);
    });
});

closeModalButtons.forEach((button) => {
    button.addEventListener("click", closeAllModals);
});

modals.forEach((modal) => {
    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            closeAllModals();
        }
    });
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeAllModals();
    }
});

const detailButtons = document.querySelectorAll("[data-detail]");

detailButtons.forEach((button) => {
    button.addEventListener("click", () => {
        const jumlah = Number(button.dataset.jumlah || 0).toLocaleString("id-ID");

        document.getElementById("detail_kode").textContent = button.dataset.kode || "-";
        document.getElementById("detail_kecamatan").textContent = button.dataset.kecamatan || "-";
        document.getElementById("detail_tahun").textContent = button.dataset.tahun || "-";
        document.getElementById("detail_jumlah").textContent = jumlah + " jiwa";
        document.getElementById("detail_admin").textContent = button.dataset.admin || "-";

        openModal("modalDetailData");
    });
});

const editButtons = document.querySelectorAll("[data-edit]");

editButtons.forEach((button) => {
    button.addEventListener("click", () => {
        document.getElementById("edit_id_data").value = button.dataset.id || "";
        document.getElementById("edit_id_kecamatan").value = button.dataset.idKecamatan || "";
        document.getElementById("edit_tahun").value = button.dataset.tahun || "";
        document.getElementById("edit_jumlah").value = button.dataset.jumlah || "";

        openModal("modalEditData");
    });
});

const deleteButtons = document.querySelectorAll("[data-delete]");
const deleteForm = document.getElementById("deleteForm");
const deleteIdInput = document.getElementById("delete_id_data");

deleteButtons.forEach((button) => {
    button.addEventListener("click", () => {
        const id = button.dataset.id;
        const kecamatan = button.dataset.kecamatan;
        const tahun = button.dataset.tahun;

        const confirmDelete = confirm(
            `Apakah Anda yakin ingin menghapus data kemiskinan Kecamatan ${kecamatan} tahun ${tahun}?`
        );

        if (confirmDelete) {
            deleteIdInput.value = id;
            deleteForm.submit();
        }
    });
});

const animatedElements = document.querySelectorAll(".summary-card, .filter-card, .table-card");

animatedElements.forEach((element, index) => {
    element.style.opacity = "0";
    element.style.transform = "translateY(12px)";

    setTimeout(() => {
        element.style.transition = "0.35s ease";
        element.style.opacity = "1";
        element.style.transform = "translateY(0)";
    }, index * 70);
});

// Add styling to pagination buttons
document.addEventListener("DOMContentLoaded", () => {
    const paginationLinks = document.querySelectorAll(".page-link");
    
    paginationLinks.forEach((link) => {
        const text = link.textContent.trim();
        
        // Style navigation buttons differently
        if (text === "Awal" || text === "Akhir" || text === "Sebelumnya" || text === "Berikutnya") {
            link.style.minWidth = "90px";
            link.style.fontWeight = "700";
            link.style.fontSize = "12px";
        }
    });
});

const checkAllData = document.getElementById("checkAllData");
const rowChecks = document.querySelectorAll(".row-check");
const btnBulkDelete = document.getElementById("btnBulkDelete");
const bulkDeleteForm = document.getElementById("bulkDeleteForm");

function updateBulkDeleteButton() {
    if (!btnBulkDelete) {
        return;
    }

    const checkedRows = document.querySelectorAll(".row-check:checked");
    const checkedCount = checkedRows.length;

    btnBulkDelete.disabled = checkedCount === 0;
    btnBulkDelete.textContent = checkedCount > 0 
        ? `Hapus Terpilih (${checkedCount})`
        : "Hapus Terpilih";
}

if (checkAllData) {
    checkAllData.addEventListener("change", () => {
        rowChecks.forEach((checkbox) => {
            checkbox.checked = checkAllData.checked;
        });

        updateBulkDeleteButton();
    });
}

rowChecks.forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
        const checkedRows = document.querySelectorAll(".row-check:checked");

        if (checkAllData) {
            checkAllData.checked = checkedRows.length === rowChecks.length;
            checkAllData.indeterminate = checkedRows.length > 0 && checkedRows.length < rowChecks.length;
        }

        updateBulkDeleteButton();
    });
});

if (bulkDeleteForm) {
    bulkDeleteForm.addEventListener("submit", (event) => {
        const checkedRows = document.querySelectorAll(".row-check:checked");
        const checkedCount = checkedRows.length;

        if (checkedCount === 0) {
            event.preventDefault();
            alert("Pilih minimal satu data yang akan dihapus.");
            return;
        }

        const confirmDelete = confirm(
            `Apakah Anda yakin ingin menghapus ${checkedCount} data kemiskinan yang dipilih?`
        );

        if (!confirmDelete) {
            event.preventDefault();
        }
    });
}

updateBulkDeleteButton();