const KT_EYE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
const KT_EYE_OFF_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.49 18.49 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a18.49 18.49 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';

function openAuthModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('open');
}

function closeAuthModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.tc-modal-overlay.open').forEach(function(modal) {
            modal.classList.remove('open');
        });
    }
});

function togglePw(id, el) {
    const input = document.getElementById(id);

    if (input.type === "password") {
        input.type = "text";
        el.innerHTML = KT_EYE_OFF_ICON;
        el.setAttribute('aria-label', 'Hide password');
    } else {
        input.type = "password";
        el.innerHTML = KT_EYE_ICON;
        el.setAttribute('aria-label', 'Show password');
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
        if(password.length < 8){
            alert("Password must be at least 8 characters long.");
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