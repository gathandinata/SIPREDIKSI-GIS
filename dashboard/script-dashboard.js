const cards = document.querySelectorAll(".summary-card, .panel, .quick-card");

cards.forEach((card, index) => {
    card.style.opacity = "0";
    card.style.transform = "translateY(12px)";

    setTimeout(() => {
        card.style.transition = "0.35s ease";
        card.style.opacity = "1";
        card.style.transform = "translateY(0)";
    }, index * 70);
});