document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("signup-form");
    const username = document.querySelector("input[name='username']");
    const email = document.querySelector("input[name='email']");
    const password = document.querySelector("input[name='pwd']");
    const result = document.getElementById("result");

    // Responsive Enhancements
    window.addEventListener("resize", function () {
        document.body.style.fontSize = window.innerWidth < 768 ? "14px" : "16px";
    });
});

