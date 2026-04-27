document.addEventListener("DOMContentLoaded", () => {
    const guestPage = document.querySelector(".guest-page");

    if (!guestPage) {
        return;
    }

    const header = document.querySelector(".site-header");
    const toggle = document.querySelector(".site-toggle");
    const navLinks = document.querySelectorAll(".site-nav a");

    if (header && toggle) {
        const closeNav = () => {
            header.classList.remove("is-open");
            toggle.setAttribute("aria-expanded", "false");
        };

        toggle.addEventListener("click", () => {
            const isOpen = header.classList.toggle("is-open");
            toggle.setAttribute("aria-expanded", String(isOpen));
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

    const passwordInput = document.querySelector("[data-password-input]");
    const passwordFeedback = document.querySelector("[data-password-feedback]");

    if (passwordInput && passwordFeedback) {
        const updatePasswordHint = () => {
            if (passwordInput.value.length === 0) {
                passwordFeedback.textContent = "Password must be at least 6 characters long.";
                passwordFeedback.classList.remove("is-valid");
                return;
            }

            if (passwordInput.value.length < 6) {
                passwordFeedback.textContent = "Add a few more characters to reach the 6 character minimum.";
                passwordFeedback.classList.remove("is-valid");
                return;
            }

            passwordFeedback.textContent = "Password length looks good.";
            passwordFeedback.classList.add("is-valid");
        };

        passwordInput.addEventListener("input", updatePasswordHint);
        updatePasswordHint();
    }

    const messageInput = document.querySelector("[data-message-input]");
    const messageCount = document.querySelector("[data-message-count]");

    if (messageInput && messageCount) {
        const maxLength = Number(messageInput.getAttribute("maxlength")) || 0;

        const updateCount = () => {
            messageCount.textContent = `${messageInput.value.length} / ${maxLength}`;
        };

        messageInput.addEventListener("input", updateCount);
        updateCount();
    }

    document.querySelectorAll("[data-current-year]").forEach((element) => {
        element.textContent = String(new Date().getFullYear());
    });
});
