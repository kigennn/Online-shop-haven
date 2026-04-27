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

// Responsive form handling
document.addEventListener("DOMContentLoaded", function () {
    document.querySelector("form").addEventListener("submit", function (event) {
        event.preventDefault(); // Prevent form from submitting normally

        // Get user input values
        let email = document.querySelector("input[name='email']").value;
        let password = document.querySelector("input[name='password']").value;

        // Check if fields are empty
        if (email.trim() === "" || password.trim() === "") {
            alert("Please fill in both fields.");
            return;
        }

        // Create data object to send to the backend
        let loginData = {
            email: email,
            password: password
        };

        // Send a request to the backend for authentication
        fetch("login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(loginData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Login Successful! Redirecting...");
                window.location.href = "userprofile.html"; // Redirect to user profile
            } else {
                alert("Invalid email or password. Please try again.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Something went wrong. Please try again later.");
        });
    });
});

