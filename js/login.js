
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = togglePassword.querySelector('i');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle eye icon
                if (type === 'text') {
                    eyeIcon.classList.remove('bi-eye');
                    eyeIcon.classList.add('bi-eye-slash');
                } else {
                    eyeIcon.classList.remove('bi-eye-slash');
                    eyeIcon.classList.add('bi-eye');
                }
            });
            
            // Password strength indicator
            const passwordStrength = document.getElementById('passwordStrength');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Check password criteria
                if (password.length >= 8) strength += 25;
                if (/[A-Z]/.test(password)) strength += 25;
                if (/[0-9]/.test(password)) strength += 25;
                if (/[^A-Za-z0-9]/.test(password)) strength += 25;
                
                // Update strength bar
                passwordStrength.style.width = strength + '%';
                
                // Change color based on strength
                if (strength <= 25) {
                    passwordStrength.style.backgroundColor = '#ff4444';
                } else if (strength <= 50) {
                    passwordStrength.style.backgroundColor = '#ffaa00';
                } else if (strength <= 75) {
                    passwordStrength.style.backgroundColor = '#00aaff';
                } else {
                    passwordStrength.style.backgroundColor = '#00cc66';
                }
            });
            
            // Form submission loading animation
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            
            loginForm.addEventListener('submit', function(e) {
                // Basic client-side validation
                const email = loginForm.querySelector('[name="email"]').value.trim();
                const password = loginForm.querySelector('[name="password"]').value.trim();
                
                if (!email || !password) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return;
                }
                
                // Validate email format
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address.');
                    return;
                }
                
                // Show loading animation
                loginButton.classList.add('btn-loading');
                loginButton.innerHTML = '<span>LOGIN</span>';
            });
            
            // Reset button functionality
            const resetButton = document.getElementById('resetButton');
            
            resetButton.addEventListener('click', function() {
                // Clear form fields
                loginForm.reset();
                
                // Reset password strength indicator
                passwordStrength.style.width = '0%';
                
                // Reset password visibility
                passwordInput.setAttribute('type', 'password');
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
                
                // Focus on email field
                loginForm.querySelector('[name="email"]').focus();
            });
            
            // Enter key to submit form
            loginForm.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault();
                    loginButton.click();
                }
            });
            
            // Auto-focus on email field on page load
            loginForm.querySelector('[name="email"]').focus();
            
            // Add smooth transitions for form elements
            const inputs = document.querySelectorAll('.login-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });