function togglePw(id, el) {
    const input = document.getElementById(id);

    if (input.type === "password") {
        input.type = "text";
        el.textContent = "Hide";
    } else {
        input.type = "password";
        el.textContent = "Show";
    }
}


const form = document.querySelector(".auth-form");

if (form) {

    form.addEventListener("submit", function(e){

        const fullname = document.getElementById("fullname").value.trim();
        const username = document.getElementById("username").value.trim();
        const email = document.getElementById("email").value.trim();
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirm_password").value;

        // Full Name
        const nameRegex = /^[A-Za-z\s.'-]+$/;

        if(!nameRegex.test(fullname)){
            alert("Full name should only contain letters.");
            e.preventDefault();
            return;
        }

        // Username
        const usernameRegex = /^[A-Za-z0-9_]+$/;

        if(!usernameRegex.test(username)){
            alert("Username can only contain letters, numbers, and underscores.");
            e.preventDefault();
            return;
        }

        if(username.length < 3 || username.length > 20){
            alert("Username must be between 3 and 20 characters.");
            e.preventDefault();
            return;
        }

        // Email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if(!emailRegex.test(email)){
            alert("Please enter a valid email address.");
            e.preventDefault();
            return;
        }

        // Password
        if(password.length < 6){
            alert("Password must be at least 6 characters long.");
            e.preventDefault();
            return;
        }

        // Confirm Password
        if(password !== confirmPassword){
            alert("Passwords do not match.");
            e.preventDefault();
            return;
        }

    });

}