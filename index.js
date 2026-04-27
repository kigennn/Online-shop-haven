document.addEventListener("DOMContentLoaded", () => {
    const homePage = document.querySelector(".home-page");

    if (!homePage) {
        return;
    }

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const header = document.querySelector(".header-main");
    const navToggle = document.querySelector(".nav-toggle");
    const navLinks = document.querySelectorAll(".header-main-nav a");

    if (header && navToggle) {
        const closeNav = () => {
            header.classList.remove("is-open");
            navToggle.setAttribute("aria-expanded", "false");
        };

        navToggle.addEventListener("click", () => {
            const isOpen = header.classList.toggle("is-open");
            navToggle.setAttribute("aria-expanded", String(isOpen));
        });

        navLinks.forEach((link) => {
            link.addEventListener("click", closeNav);
        });

        document.addEventListener("click", (event) => {
            if (!header.contains(event.target)) {
                closeNav();
            }
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        const targetSelector = anchor.getAttribute("href");

        if (!targetSelector || targetSelector === "#") {
            return;
        }

        const target = document.querySelector(targetSelector);

        if (!target) {
            return;
        }

        anchor.addEventListener("click", (event) => {
            event.preventDefault();
            target.scrollIntoView({
                behavior: prefersReducedMotion ? "auto" : "smooth",
                block: "start"
            });
        });
    });

    const shelfButtons = Array.from(document.querySelectorAll("[data-shelf-button]"));
    const shelfChips = Array.from(document.querySelectorAll("[data-shelf-trigger]"));
    const spotlightMedia = document.getElementById("spotlightMedia");
    const spotlightBadge = document.getElementById("spotlightBadge");
    const spotlightIndex = document.getElementById("spotlightIndex");
    const spotlightTag = document.getElementById("spotlightTag");
    const spotlightTitle = document.getElementById("spotlightTitle");
    const spotlightDescription = document.getElementById("spotlightDescription");
    const spotlightAudience = document.getElementById("spotlightAudience");
    const spotlightPace = document.getElementById("spotlightPace");
    const spotlightLink = document.getElementById("spotlightLink");

    let hasUserSelectedShelf = false;
    let rotationTimer = null;

    const setActiveShelf = (shelfId, fromUser = false) => {
        const activeShelf = shelfButtons.find((button) => button.dataset.shelf === shelfId);

        if (!activeShelf) {
            return;
        }

        const shelfIndex = shelfButtons.indexOf(activeShelf) + 1;

        if (spotlightMedia) {
            spotlightMedia.style.backgroundImage = [
                "linear-gradient(180deg, rgba(15, 20, 21, 0.08), rgba(15, 20, 21, 0.52))",
                `url("${activeShelf.dataset.image}")`
            ].join(", ");
        }

        if (spotlightBadge) {
            spotlightBadge.textContent = activeShelf.dataset.badge || "";
        }

        if (spotlightIndex) {
            spotlightIndex.textContent = String(shelfIndex).padStart(2, "0");
        }

        if (spotlightTag) {
            spotlightTag.textContent = activeShelf.dataset.tag || "";
        }

        if (spotlightTitle) {
            spotlightTitle.textContent = activeShelf.dataset.title || "";
        }

        if (spotlightDescription) {
            spotlightDescription.textContent = activeShelf.dataset.description || "";
        }

        if (spotlightAudience) {
            spotlightAudience.textContent = activeShelf.dataset.audience || "";
        }

        if (spotlightPace) {
            spotlightPace.textContent = activeShelf.dataset.pace || "";
        }

        if (spotlightLink) {
            spotlightLink.textContent = activeShelf.dataset.linkLabel || "Explore this shelf";
        }

        shelfButtons.forEach((button) => {
            const isActive = button.dataset.shelf === shelfId;
            button.classList.toggle("is-active", isActive);
            button.setAttribute("aria-pressed", String(isActive));
        });

        shelfChips.forEach((chip) => {
            const isActive = chip.dataset.shelfTrigger === shelfId;
            chip.classList.toggle("is-active", isActive);
            chip.setAttribute("aria-pressed", String(isActive));
        });

        if (fromUser) {
            hasUserSelectedShelf = true;
        }
    };

    shelfButtons.forEach((button) => {
        button.addEventListener("click", () => {
            setActiveShelf(button.dataset.shelf, true);
        });

        button.addEventListener("mouseenter", () => {
            setActiveShelf(button.dataset.shelf);
        });

        button.addEventListener("focus", () => {
            setActiveShelf(button.dataset.shelf);
        });
    });

    shelfChips.forEach((chip) => {
        chip.addEventListener("click", () => {
            setActiveShelf(chip.dataset.shelfTrigger, true);
        });

        chip.addEventListener("mouseenter", () => {
            setActiveShelf(chip.dataset.shelfTrigger);
        });

        chip.addEventListener("focus", () => {
            setActiveShelf(chip.dataset.shelfTrigger);
        });
    });

    if (shelfButtons.length > 0) {
        setActiveShelf(shelfButtons[0].dataset.shelf);
    }

    if (!prefersReducedMotion && shelfButtons.length > 1) {
        rotationTimer = window.setInterval(() => {
            if (hasUserSelectedShelf) {
                window.clearInterval(rotationTimer);
                return;
            }

            const currentIndex = shelfButtons.findIndex((button) => button.classList.contains("is-active"));
            const nextIndex = (currentIndex + 1) % shelfButtons.length;
            setActiveShelf(shelfButtons[nextIndex].dataset.shelf);
        }, 5000);
    }

    const revealItems = document.querySelectorAll(".reveal");

    if (prefersReducedMotion) {
        revealItems.forEach((item) => item.classList.add("is-visible"));
    } else {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.2
        });

        revealItems.forEach((item) => {
            if (!item.classList.contains("is-visible")) {
                revealObserver.observe(item);
            }
        });
    }

    const spotlightCard = document.querySelector(".spotlight-card");

    if (!prefersReducedMotion && spotlightCard) {
        spotlightCard.addEventListener("mousemove", (event) => {
            const bounds = spotlightCard.getBoundingClientRect();
            const offsetX = (event.clientX - bounds.left) / bounds.width;
            const offsetY = (event.clientY - bounds.top) / bounds.height;
            const rotateY = (offsetX - 0.5) * 8;
            const rotateX = (0.5 - offsetY) * 8;

            spotlightCard.style.transform = `perspective(1200px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });

        spotlightCard.addEventListener("mouseleave", () => {
            spotlightCard.style.transform = "";
        });
    }
});
