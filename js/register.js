       document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggles
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            
            function toggleVisibility(input, toggleBtn) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                
                const eyeIcon = toggleBtn.querySelector('i');
                if (type === 'text') {
                    eyeIcon.classList.remove('bi-eye');
                    eyeIcon.classList.add('bi-eye-slash');
                } else {
                    eyeIcon.classList.remove('bi-eye-slash');
                    eyeIcon.classList.add('bi-eye');
                }
            }
            
            togglePassword.addEventListener('click', () => toggleVisibility(passwordInput, togglePassword));
            toggleConfirmPassword.addEventListener('click', () => toggleVisibility(confirmPasswordInput, toggleConfirmPassword));
            
            // Password validation
            const passwordMatch = document.getElementById('passwordMatch');
            const reqLength = document.getElementById('reqLength');
            const reqSpecial = document.getElementById('reqSpecial'); // Add this line
            const reqMatch = document.getElementById('reqMatch');
            
            function validatePassword() {
                const password = passwordInput.value;
                const confirm = confirmPasswordInput.value;
                
                // Length requirement
                if (password.length >= 8) {
                    reqLength.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Minimum 8 characters';
                } else {
                    reqLength.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Minimum 8 characters';
                }
                
                // Special character requirement
                const specialChars = /[!@#$%^&*()]/;
                if (specialChars.test(password)) {
                    reqSpecial.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Contains special character (!@#$%^&*())';
                } else {
                    reqSpecial.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Contains special character (!@#$%^&*())';
                }
                
                // Match requirement
                if (confirm.length > 0) {
                    if (password === confirm) {
                        reqMatch.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Passwords match';
                        passwordMatch.innerHTML = '<span style="color: #00cc66;">✓ Passwords match</span>';
                    } else {
                        reqMatch.innerHTML = '<i class="bi bi-x-circle" style="color: #ff4444;"></i> Passwords do not match';
                        passwordMatch.innerHTML = '<span style="color: #ff4444;">✗ Passwords do not match</span>';
                    }
                } else {
                    reqMatch.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Passwords match';
                    passwordMatch.innerHTML = '';
                }
            }
            
            passwordInput.addEventListener('input', validatePassword);
            confirmPasswordInput.addEventListener('input', validatePassword);
            
            // Form submission
            const registerForm = document.getElementById('registerForm');
            const registerButton = document.getElementById('registerButton');
            
            registerForm.addEventListener('submit', function(e) {
                // Basic client-side validation
                const name = registerForm.querySelector('[name="name"]').value.trim();
                const email = registerForm.querySelector('[name="email"]').value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const agreeTerms = registerForm.querySelector('[name="agree_terms"]').checked;
                
                let errors = [];
                
                if (name.length < 2) {
                    errors.push('Name must be at least 2 characters.');
                }
                
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    errors.push('Please enter a valid email address.');
                }
                
                if (password.length < 8) {
                    errors.push('Password must be at least 8 characters.');
                }

                const specialChars = /[!@#$%^&*()]/;
                if (!specialChars.test(password)) {
                    errors.push('Password must contain at least one special character (!@#$%^&*()).');
                }
                
                if (password !== confirmPassword) {
                    errors.push('Passwords do not match.');
                }
                
                if (!agreeTerms) {
                    errors.push('You must agree to the Terms and Conditions.');
                }
                
                if (errors.length > 0) {
                    e.preventDefault();
                    alert('Please fix the following errors:\n\n' + errors.join('\n'));
                    return;
                }
                
                // Show loading animation
                registerButton.classList.add('btn-loading');
                registerButton.innerHTML = '<span>CREATING ACCOUNT...</span>';
            });
            
            // Reset button
            const resetButton = document.getElementById('resetButton');
            resetButton.addEventListener('click', function() {
                // Clear validation messages
                passwordMatch.innerHTML = '';
                reqLength.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Minimum 8 characters';
                reqMatch.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Passwords match';
                
                // Reset password visibility
                passwordInput.setAttribute('type', 'password');
                confirmPasswordInput.setAttribute('type', 'password');
                
                const eyeIcons = document.querySelectorAll('.password-toggle i');
                eyeIcons.forEach(icon => {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                });
                
                // Focus on name field
                registerForm.querySelector('[name="name"]').focus();
            });
            
            // Initialize validation
            validatePassword();
        });
