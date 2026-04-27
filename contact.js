document.addEventListener("DOMContentLoaded", function () {
    const menuToggle = document.createElement("div");
    menuToggle.innerHTML = "&#9776;"; // Hamburger icon
    menuToggle.style.fontSize = "2rem";
    menuToggle.style.cursor = "pointer";
    menuToggle.style.display = "none";
    menuToggle.style.position = "absolute";
    menuToggle.style.top = "15px";
    menuToggle.style.right = "20px";
    
    const navMenu = document.querySelector(".header-main-nav ul");
    document.querySelector(".header-main").appendChild(menuToggle);
    
    menuToggle.addEventListener("click", function () {
        navMenu.classList.toggle("active");
    });
    
    function adjustLayout() {
        if (window.innerWidth <= 768) {
            navMenu.style.display = "none";
            menuToggle.style.display = "block";
        } else {
            navMenu.style.display = "flex";
            menuToggle.style.display = "none";
        }
    }
    
    window.addEventListener("resize", adjustLayout);
    adjustLayout();
    
    // Ensure images and text scale properly
    document.querySelectorAll("img").forEach(img => {
        img.style.maxWidth = "100%";
        img.style.height = "auto";
    });
    
    document.querySelectorAll(".wrapper-main, .footer-main").forEach(el => {
        el.style.maxWidth = "100%";
        el.style.padding = "0 20px";
    });
    
    // Ensure header-main-logo is included
    const headerLogo = document.querySelector(".header-main-logo");
    if (headerLogo) {
        headerLogo.style.display = "flex";
        headerLogo.style.alignItems = "center";
    }

    // Move navigation bar to the bottom
    const headerMain = document.querySelector(".header-main");
    if (headerMain) {
        headerMain.style.position = "fixed";
        headerMain.style.bottom = "0";
        headerMain.style.top = "auto";
        headerMain.style.width = "100%";
        headerMain.style.zIndex = "1000";
        headerMain.style.backgroundColor = "beige";
    }

    // Responsive form handling
    function adjustFormLayout() {
        const formBox = document.querySelector(".form-box");
        if (window.innerWidth <= 480) {
            formBox.style.width = "90%";
            formBox.style.height = "auto";
            formBox.style.padding = "20px";
        } else {
            formBox.style.width = "400px";
            formBox.style.height = "550px";
        }
    }

    window.addEventListener("resize", adjustFormLayout);
    adjustFormLayout();
});
