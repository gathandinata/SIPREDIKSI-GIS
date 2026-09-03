const sidebar = document.getElementById("sidebar");
const sidebarToggle = document.getElementById("sidebarToggle");

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener("click", function () {
        sidebar.classList.toggle("show");
    });
}