
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.querySelector('.sidebar');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
            });
            
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    if (!sidebar.contains(event.target) && 
                        !mobileMenuToggle.contains(event.target) && 
                        sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                }
            });
            
            // Theme Toggle
            const themeToggle = document.getElementById('themeToggle');
            const body = document.body;
            
            // Check for saved theme preference or default to light
            const savedTheme = localStorage.getItem('theme') || 'light';
            body.setAttribute('data-theme', savedTheme);
            themeToggle.checked = savedTheme === 'dark';
            
            themeToggle.addEventListener('change', function() {
                const theme = this.checked ? 'dark' : 'light';
                body.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
            });
            
            // Status Modal for Superadmin
            const editButtons = document.querySelectorAll('.btn-edit');
            const statusModal = document.getElementById('statusModal');
            
            if (editButtons.length > 0) {
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const queryId = this.getAttribute('data-query-id');
                        const currentStatus = this.getAttribute('data-current-status');
                        
                        document.getElementById('modalQueryId').value = queryId;
                        document.getElementById('modalStatus').value = currentStatus;
                        
                        statusModal.style.display = 'flex';
                    });
                });
            }
            
            // Close modal
            window.closeStatusModal = function() {
                if (statusModal) {
                    statusModal.style.display = 'none';
                }
            };
            
            // Close modal when clicking outside or on close button
            if (statusModal) {
                statusModal.addEventListener('click', function(event) {
                    if (event.target === statusModal || event.target.classList.contains('close-modal')) {
                        closeStatusModal();
                    }
                });
            }
            
            // Table Sorting
            const tableHeaders = document.querySelectorAll('.feedback-table th[data-sort]');
            
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
                        const colIndex = Array.from(tableHeaders).indexOf(this) + 1;
                        const aCell = a.querySelector(`td:nth-child(${colIndex})`);
                        const bCell = b.querySelector(`td:nth-child(${colIndex})`);
                        
                        let aText = aCell.textContent.trim();
                        let bText = bCell.textContent.trim();
                        
                        // For status and category badges
                        const aBadge = aCell.querySelector('.status-badge, .category-badge');
                        const bBadge = bCell.querySelector('.status-badge, .category-badge');
                        
                        if (aBadge) aText = aBadge.textContent;
                        if (bBadge) bText = bBadge.textContent;
                        
                        if (isAscending) {
                            return aText.localeCompare(bText);
                        } else {
                            return bText.localeCompare(aText);
                        }
                    });
                    
                    // Reappend sorted rows
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
            
            // Auto-collapse sidebar on mobile
            function handleResize() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('collapsed');
                    sidebar.classList.remove('show');
                }
            }
            
            window.addEventListener('resize', handleResize);
            handleResize();
        });
