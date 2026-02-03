// profile.js - Profile Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    
    // Check for saved theme preference or default to light
    const savedTheme = localStorage.getItem('theme') || 'light';
    body.setAttribute('data-theme', savedTheme);
    if (themeToggle) {
        themeToggle.checked = savedTheme === 'dark';
        
        themeToggle.addEventListener('change', function() {
            const theme = this.checked ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });
    }
    
    // Profile Image Preview (for User/Admin only)
    const profileImageInput = document.getElementById('profileImage');
    const imagePreview = document.getElementById('imagePreview');
    const useGravatarCheckbox = document.getElementById('useGravatar');
    
    if (profileImageInput && imagePreview) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
                
                // Uncheck gravatar when uploading image
                if (useGravatarCheckbox) {
                    useGravatarCheckbox.checked = false;
                }
            }
        });
        
        // Click on image preview to trigger file input
        imagePreview.parentElement.addEventListener('click', function(e) {
            if (e.target !== profileImageInput) {
                profileImageInput.click();
            }
        });
    }
    
    // Gravatar toggle (for User/Admin only)
    if (useGravatarCheckbox) {
        useGravatarCheckbox.addEventListener('change', function() {
            if (this.checked) {
                const email = document.getElementById('email').value;
                const gravatarUrl = `https://www.gravatar.com/avatar/${md5(email.trim().toLowerCase())}?s=200&d=identicon`;
                imagePreview.src = gravatarUrl;
            } else {
                // Reset to uploaded image if available
                if (profileImageInput.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                    }
                    reader.readAsDataURL(profileImageInput.files[0]);
                }
            }
        });
    }
    
    // Password visibility toggles
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input[type="password"], input[type="text"]');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });
    
    // Password validation (for User/Admin only)
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');
    const passwordStrength = document.getElementById('passwordStrength');
    const reqLength = document.getElementById('reqLength');
    const reqSpecial = document.getElementById('reqSpecial');
    const reqMatch = document.getElementById('reqMatch');
    
    function validatePassword() {
        const newPassword = newPasswordInput ? newPasswordInput.value : '';
        const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
        
        // Length requirement
        if (reqLength) {
            if (newPassword.length >= 8) {
                reqLength.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Minimum 8 characters';
            } else {
                reqLength.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Minimum 8 characters';
            }
        }
        
        // Special character requirement
        if (reqSpecial) {
            const specialChars = /[!@#$%^&*()]/;
            if (specialChars.test(newPassword)) {
                reqSpecial.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Contains special character (!@#$%^&*())';
            } else {
                reqSpecial.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Contains special character (!@#$%^&*())';
            }
        }
        
        // Password strength indicator
        if (passwordStrength && newPassword) {
            let strength = 0;
            let message = '';
            let color = '';
            
            // Check length
            if (newPassword.length >= 8) strength++;
            
            // Check for special characters
            if (/[!@#$%^&*()]/.test(newPassword)) strength++;
            
            // Check for numbers
            if (/\d/.test(newPassword)) strength++;
            
            // Check for uppercase and lowercase
            if (/[a-z]/.test(newPassword) && /[A-Z]/.test(newPassword)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = 'Weak';
                    color = '#ff6b6b';
                    break;
                case 2:
                    message = 'Fair';
                    color = '#ffd166';
                    break;
                case 3:
                    message = 'Good';
                    color = '#4ecdc4';
                    break;
                case 4:
                    message = 'Strong';
                    color = '#06d6a0';
                    break;
            }
            
            passwordStrength.innerHTML = `<span style="color: ${color}">${message}</span>`;
        }
        
        // Match requirement
        if (reqMatch && confirmPassword) {
            if (newPassword === confirmPassword) {
                reqMatch.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Passwords match';
                if (passwordMatch) {
                    passwordMatch.innerHTML = '<span style="color: #06d6a0;">✓ Passwords match</span>';
                }
            } else {
                reqMatch.innerHTML = '<i class="bi bi-x-circle" style="color: #ff4444;"></i> Passwords do not match';
                if (passwordMatch) {
                    passwordMatch.innerHTML = '<span style="color: #ff4444;">✗ Passwords do not match</span>';
                }
            }
        }
    }
    
    if (newPasswordInput) newPasswordInput.addEventListener('input', validatePassword);
    if (confirmPasswordInput) confirmPasswordInput.addEventListener('input', validatePassword);
    
    // Reset profile form (for User/Admin only)
    const resetProfileFormBtn = document.getElementById('resetProfileForm');
    if (resetProfileFormBtn) {
        resetProfileFormBtn.addEventListener('click', function() {
            const profileForm = document.getElementById('profileForm');
            profileForm.reset();
            
            // Reset validation messages
            if (passwordMatch) passwordMatch.innerHTML = '';
            if (passwordStrength) passwordStrength.innerHTML = '';
            if (reqLength) reqLength.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Minimum 8 characters';
            if (reqSpecial) reqSpecial.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Contains special character (!@#$%^&*())';
            if (reqMatch) reqMatch.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Passwords match';
            
            // Reset password visibility
            const passwordInputs = profileForm.querySelectorAll('input[type="text"]');
            passwordInputs.forEach(input => {
                if (input.id.includes('password')) {
                    input.type = 'password';
                }
            });
            
            // Reset eye icons
            const eyeIcons = profileForm.querySelectorAll('.password-toggle i');
            eyeIcons.forEach(icon => {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            });
        });
    }
    
    // SUPERADMIN USER MANAGEMENT FUNCTIONS
    const addUserBtn = document.getElementById('addUserBtn');
    const refreshTableBtn = document.getElementById('refreshTable');
    const addUserModal = document.getElementById('addUserModal');
    const editUserModal = document.getElementById('editUserModal');
    const confirmationModal = document.getElementById('confirmationModal');
    const notificationModal = document.getElementById('notificationModal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const applyBulkActionBtn = document.getElementById('applyBulkAction');
    const selectAllCheckbox = document.getElementById('selectAll');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const editButtons = document.querySelectorAll('.btn-edit');
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    // Refresh table button
    if (refreshTableBtn) {
        refreshTableBtn.addEventListener('click', function() {
            window.location.reload();
        });
    }
    
    // Modal Management
    function openModal(modal) {
        if (modal) modal.style.display = 'flex';
    }
    
    function closeAllModals() {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }

    // Close modal when clicking outside or on close button
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });
    
    // Close modal when clicking outside content
    document.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Escape key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Auto-close notification modal after 5 seconds
    if (notificationModal && notificationModal.style.display === 'flex') {
        setTimeout(() => {
            notificationModal.style.display = 'none';
        }, 5000);
    }
    
    // ADD USER MODAL (Superadmin only)
    if (addUserBtn && addUserModal) {
        addUserBtn.addEventListener('click', () => openModal(addUserModal));
        
        // Profile image preview for add user modal
        const profileImageNew = document.getElementById('profileImageNew');
        const imagePreviewNew = document.getElementById('imagePreviewNew');
        const useGravatarNew = document.getElementById('useGravatarNew');
        
        if (profileImageNew && imagePreviewNew) {
            profileImageNew.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreviewNew.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                    
                    // Uncheck gravatar when uploading image
                    if (useGravatarNew) {
                        useGravatarNew.checked = false;
                    }
                }
            });
            
            // Click on image preview to trigger file input
            imagePreviewNew.parentElement.addEventListener('click', function(e) {
                if (e.target !== profileImageNew) {
                    profileImageNew.click();
                }
            });
        }
        
        // Gravatar toggle for add user modal
        if (useGravatarNew) {
            useGravatarNew.addEventListener('change', function() {
                if (this.checked) {
                    const email = document.getElementById('new_email').value;
                    const gravatarUrl = `https://www.gravatar.com/avatar/${md5(email.trim().toLowerCase())}?s=150&d=identicon`;
                    if (email) {
                        imagePreviewNew.src = gravatarUrl;
                    }
                } else {
                    // Reset to uploaded image if available
                    if (profileImageNew.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreviewNew.src = e.target.result;
                        }
                        reader.readAsDataURL(profileImageNew.files[0]);
                    }
                }
            });
            
            // Update gravatar when email changes
            const newEmailInput = document.getElementById('new_email');
            if (newEmailInput) {
                newEmailInput.addEventListener('blur', function() {
                    if (useGravatarNew.checked && this.value) {
                        const gravatarUrl = `https://www.gravatar.com/avatar/${md5(this.value.trim().toLowerCase())}?s=150&d=identicon`;
                        imagePreviewNew.src = gravatarUrl;
                    }
                });
            }
        }
        
        // Password validation for add user modal
        const newPasswordNew = document.getElementById('new_password');
        const confirmPasswordNew = document.getElementById('confirm_password');
        const passwordMatchNew = document.getElementById('passwordMatchNew');
        const passwordStrengthNew = document.getElementById('passwordStrengthNew');
        const reqLengthNew = document.getElementById('reqLengthNew');
        const reqSpecialNew = document.getElementById('reqSpecialNew');
        const reqMatchNew = document.getElementById('reqMatchNew');
        
        function validatePasswordNew() {
            const newPassword = newPasswordNew ? newPasswordNew.value : '';
            const confirmPassword = confirmPasswordNew ? confirmPasswordNew.value : '';
            
            // Length requirement
            if (reqLengthNew) {
                if (newPassword.length >= 8) {
                    reqLengthNew.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Minimum 8 characters';
                } else {
                    reqLengthNew.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Minimum 8 characters';
                }
            }
            
            // Special character requirement
            if (reqSpecialNew) {
                const specialChars = /[!@#$%^&*()]/;
                if (specialChars.test(newPassword)) {
                    reqSpecialNew.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Contains special character (!@#$%^&*())';
                } else {
                    reqSpecialNew.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Contains special character (!@#$%^&*())';
                }
            }
            
            // Password strength indicator
            if (passwordStrengthNew && newPassword) {
                let strength = 0;
                let message = '';
                let color = '';
                
                if (newPassword.length >= 8) strength++;
                if (/[!@#$%^&*()]/.test(newPassword)) strength++;
                if (/\d/.test(newPassword)) strength++;
                if (/[a-z]/.test(newPassword) && /[A-Z]/.test(newPassword)) strength++;
                
                switch(strength) {
                    case 0:
                    case 1:
                        message = 'Weak';
                        color = '#ff6b6b';
                        break;
                    case 2:
                        message = 'Fair';
                        color = '#ffd166';
                        break;
                    case 3:
                        message = 'Good';
                        color = '#4ecdc4';
                        break;
                    case 4:
                        message = 'Strong';
                        color = '#06d6a0';
                        break;
                }
                
                passwordStrengthNew.innerHTML = `<span style="color: ${color}">${message}</span>`;
            }
            
            // Match requirement
            if (reqMatchNew && confirmPassword) {
                if (newPassword === confirmPassword) {
                    reqMatchNew.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Passwords match';
                    if (passwordMatchNew) {
                        passwordMatchNew.innerHTML = '<span style="color: #06d6a0;">✓ Passwords match</span>';
                    }
                } else {
                    reqMatchNew.innerHTML = '<i class="bi bi-x-circle" style="color: #ff4444;"></i> Passwords do not match';
                    if (passwordMatchNew) {
                        passwordMatchNew.innerHTML = '<span style="color: #ff4444;">✗ Passwords do not match</span>';
                    }
                }
            }
        }
        
        if (newPasswordNew) newPasswordNew.addEventListener('input', validatePasswordNew);
        if (confirmPasswordNew) confirmPasswordNew.addEventListener('input', validatePasswordNew);
    }
    
    // EDIT USER MODAL (Superadmin only)
    const changePasswordCheckbox = document.getElementById('changePasswordCheckbox');
    const passwordFieldsEdit = document.getElementById('passwordFieldsEdit');
    
    if (changePasswordCheckbox && passwordFieldsEdit) {
        changePasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordFieldsEdit.style.display = 'block';
            } else {
                passwordFieldsEdit.style.display = 'none';
                // Clear password fields
                document.getElementById('update_password').value = '';
                document.getElementById('update_confirm_password').value = '';
                // Reset validation messages
                const reqLengthEdit = document.getElementById('reqLengthEdit');
                const reqSpecialEdit = document.getElementById('reqSpecialEdit');
                const reqMatchEdit = document.getElementById('reqMatchEdit');
                const passwordStrengthEdit = document.getElementById('passwordStrengthEdit');
                const passwordMatchEdit = document.getElementById('passwordMatchEdit');
                
                if (reqLengthEdit) reqLengthEdit.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Minimum 8 characters';
                if (reqSpecialEdit) reqSpecialEdit.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Contains special character (!@#$%^&*())';
                if (reqMatchEdit) reqMatchEdit.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Passwords match';
                if (passwordStrengthEdit) passwordStrengthEdit.innerHTML = '';
                if (passwordMatchEdit) passwordMatchEdit.innerHTML = '';
            }
        });
        
        // Password validation for edit user modal
        const updatePassword = document.getElementById('update_password');
        const updateConfirmPassword = document.getElementById('update_confirm_password');
        
        function validatePasswordEdit() {
            const newPassword = updatePassword ? updatePassword.value : '';
            const confirmPassword = updateConfirmPassword ? updateConfirmPassword.value : '';
            
            // Only validate if passwords are being changed
            if (!changePasswordCheckbox.checked) return;
            
            const reqLengthEdit = document.getElementById('reqLengthEdit');
            const reqSpecialEdit = document.getElementById('reqSpecialEdit');
            const reqMatchEdit = document.getElementById('reqMatchEdit');
            const passwordStrengthEdit = document.getElementById('passwordStrengthEdit');
            const passwordMatchEdit = document.getElementById('passwordMatchEdit');
            
            // Length requirement
            if (reqLengthEdit) {
                if (newPassword.length >= 8) {
                    reqLengthEdit.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Minimum 8 characters';
                } else {
                    reqLengthEdit.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Minimum 8 characters';
                }
            }
            
            // Special character requirement
            if (reqSpecialEdit) {
                const specialChars = /[!@#$%^&*()]/;
                if (specialChars.test(newPassword)) {
                    reqSpecialEdit.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Contains special character (!@#$%^&*())';
                } else {
                    reqSpecialEdit.innerHTML = '<i class="bi bi-circle requirement-unmet"></i> Contains special character (!@#$%^&*())';
                }
            }
            
            // Password strength indicator
            if (passwordStrengthEdit && newPassword) {
                let strength = 0;
                let message = '';
                let color = '';
                
                if (newPassword.length >= 8) strength++;
                if (/[!@#$%^&*()]/.test(newPassword)) strength++;
                if (/\d/.test(newPassword)) strength++;
                if (/[a-z]/.test(newPassword) && /[A-Z]/.test(newPassword)) strength++;
                
                switch(strength) {
                    case 0:
                    case 1:
                        message = 'Weak';
                        color = '#ff6b6b';
                        break;
                    case 2:
                        message = 'Fair';
                        color = '#ffd166';
                        break;
                    case 3:
                        message = 'Good';
                        color = '#4ecdc4';
                        break;
                    case 4:
                        message = 'Strong';
                        color = '#06d6a0';
                        break;
                }
                
                passwordStrengthEdit.innerHTML = `<span style="color: ${color}">${message}</span>`;
            }
            
            // Match requirement
            if (reqMatchEdit && confirmPassword) {
                if (newPassword === confirmPassword) {
                    reqMatchEdit.innerHTML = '<i class="bi bi-check-circle requirement-met"></i> Passwords match';
                    if (passwordMatchEdit) {
                        passwordMatchEdit.innerHTML = '<span style="color: #06d6a0;">✓ Passwords match</span>';
                    }
                } else {
                    reqMatchEdit.innerHTML = '<i class="bi bi-x-circle" style="color: #ff4444;"></i> Passwords do not match';
                    if (passwordMatchEdit) {
                        passwordMatchEdit.innerHTML = '<span style="color: #ff4444;">✗ Passwords do not match</span>';
                    }
                }
            }
        }
        
        if (updatePassword) updatePassword.addEventListener('input', validatePasswordEdit);
        if (updateConfirmPassword) updateConfirmPassword.addEventListener('input', validatePasswordEdit);
    }
    
    // Bulk Actions
    if (applyBulkActionBtn) {
        applyBulkActionBtn.addEventListener('click', function() {
            const bulkActionSelect = document.getElementById('bulkActionSelect');
            const selectedAction = bulkActionSelect.value;
            const selectedUsers = [];
            
            userCheckboxes.forEach(checkbox => {
                if (checkbox.checked && !checkbox.disabled) {
                    selectedUsers.push(checkbox.value);
                }
            });
            
            if (selectedAction && selectedUsers.length > 0) {
                const confirmation = confirm(`Are you sure you want to ${selectedAction} ${selectedUsers.length} user(s)?`);
                if (confirmation) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'bulk_action';
                    actionInput.value = selectedAction;
                    
                    selectedUsers.forEach(userId => {
                        const userInput = document.createElement('input');
                        userInput.type = 'hidden';
                        userInput.name = 'selected_users[]';
                        userInput.value = userId;
                        form.appendChild(userInput);
                    });
                    
                    form.appendChild(actionInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        });
    }
    
    // Select All checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            userCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = isChecked;
                }
            });
            updateSelectedCount();
        });
    }
    
    // Update selected count
    function updateSelectedCount() {
        const selectedCount = document.querySelector('.selected-count');
        if (selectedCount) {
            const checkedCount = document.querySelectorAll('.user-checkbox:checked:not(:disabled)').length;
            selectedCount.textContent = `${checkedCount} users selected`;
        }
    }
    
    // Individual checkbox changes
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            
            // Update select all checkbox
            if (selectAllCheckbox) {
                const allCheckboxes = document.querySelectorAll('.user-checkbox:not(:disabled)');
                const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
    
    // Edit user button (Superadmin only)
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            const userEmail = this.getAttribute('data-user-email');
            const userRole = this.getAttribute('data-user-role');
            const userStatus = this.getAttribute('data-user-status');
            const profileImg = this.getAttribute('data-profile-img');
            
            // Populate form fields
            document.getElementById('editUserId').value = userId;
            document.getElementById('originalEmail').value = userEmail;
            document.getElementById('currentProfileImg').value = profileImg;
            document.getElementById('editUserName').value = userName;
            document.getElementById('editUserEmail').value = userEmail;
            document.getElementById('editUserRole').value = userRole;
            document.getElementById('editUserStatus').value = userStatus;
            
            // Reset password change checkbox
            const changePasswordCheckbox = document.getElementById('changePasswordCheckbox');
            if (changePasswordCheckbox) {
                changePasswordCheckbox.checked = false;
                const passwordFieldsEdit = document.getElementById('passwordFieldsEdit');
                if (passwordFieldsEdit) {
                    passwordFieldsEdit.style.display = 'none';
                }
            }
            
            // Set profile image preview
            const imagePreviewEdit = document.getElementById('imagePreviewEdit');
            const useGravatarEdit = document.getElementById('useGravatarEdit');
            
            if (profileImg) {
                imagePreviewEdit.src = `uploads/profile/${profileImg}`;
                if (useGravatarEdit) useGravatarEdit.checked = false;
            } else {
                const gravatarUrl = `https://www.gravatar.com/avatar/${md5(userEmail.trim().toLowerCase())}?s=150&d=identicon`;
                imagePreviewEdit.src = gravatarUrl;
                if (useGravatarEdit) useGravatarEdit.checked = true;
            }
            
            // Profile image preview for edit modal
            const profileImageEdit = document.getElementById('profileImageEdit');
            if (profileImageEdit && imagePreviewEdit) {
                profileImageEdit.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreviewEdit.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                        
                        // Uncheck gravatar when uploading image
                        if (useGravatarEdit) {
                            useGravatarEdit.checked = false;
                        }
                    }
                });
                
                // Click on image preview to trigger file input
                imagePreviewEdit.parentElement.addEventListener('click', function(e) {
                    if (e.target !== profileImageEdit) {
                        profileImageEdit.click();
                    }
                });
            }
            
            // Gravatar toggle for edit modal
            if (useGravatarEdit) {
                useGravatarEdit.addEventListener('change', function() {
                    if (this.checked) {
                        const email = document.getElementById('editUserEmail').value;
                        const gravatarUrl = `https://www.gravatar.com/avatar/${md5(email.trim().toLowerCase())}?s=150&d=identicon`;
                        imagePreviewEdit.src = gravatarUrl;
                    } else {
                        // Reset to uploaded image if available
                        if (profileImageEdit.files[0]) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                imagePreviewEdit.src = e.target.result;
                            }
                            reader.readAsDataURL(profileImageEdit.files[0]);
                        }
                    }
                });
            }
            
            openModal(editUserModal);
        });
    });
    
    // Delete user button (Superadmin only)
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('confirmationMessage').textContent = 
                `Are you sure you want to delete user "${userName}"? This action cannot be undone.`;
            
            openModal(confirmationModal);
        });
    });
    
    // Table Sorting (Superadmin only)
    const tableHeaders = document.querySelectorAll('.users-table th[data-sort]');
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Determine sort direction
            const isAscending = !this.classList.contains('asc');
            
            // Reset all headers
            tableHeaders.forEach(h => {
                h.classList.remove('asc', 'desc');
            });
            
            // Set current header
            this.classList.toggle('asc', isAscending);
            this.classList.toggle('desc', !isAscending);
            
            // Sort rows
            rows.sort((a, b) => {
                const colIndex = Array.from(this.parentElement.children).indexOf(this);
                const aCell = a.children[colIndex];
                const bCell = b.children[colIndex];
                
                let aValue = aCell.textContent.trim();
                let bValue = bCell.textContent.trim();
                
                // Special handling for IDs and dates
                if (column === 'id') {
                    aValue = parseInt(aValue);
                    bValue = parseInt(bValue);
                } else if (column === 'created') {
                    aValue = new Date(aValue);
                    bValue = new Date(bValue);
                }
                
                if (isAscending) {
                    return aValue < bValue ? -1 : aValue > bValue ? 1 : 0;
                } else {
                    return aValue > bValue ? -1 : aValue < bValue ? 1 : 0;
                }
            });
            
            // Reappend sorted rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });
    
    // Initialize
    if (newPasswordInput || confirmPasswordInput) {
        validatePassword();
    }
    
    updateSelectedCount();
});

// MD5 function for Gravatar
function md5(string) {
    return CryptoJS.MD5(string).toString();
}