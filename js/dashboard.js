
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
            
            // Table Sorting
            const sortButtons = document.querySelectorAll('.sort-btn');
            
            sortButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tableId = this.getAttribute('data-table') + 'Table';
                    const table = document.getElementById(tableId);
                    if (!table) return;
                    
                    const headers = table.querySelectorAll('th[data-sort]');
                    headers.forEach(header => {
                        header.addEventListener('click', function() {
                            const column = this.getAttribute('data-sort');
                            const tbody = table.querySelector('tbody');
                            const rows = Array.from(tbody.querySelectorAll('tr'));
                            
                            // Determine sort direction
                            const isAscending = !this.classList.contains('asc');
                            this.classList.toggle('asc', isAscending);
                            this.classList.toggle('desc', !isAscending);
                            
                            // Sort rows
                            rows.sort((a, b) => {
                                const aText = a.querySelector(`td:nth-child(${Array.from(headers).indexOf(this) + 1})`).textContent;
                                const bText = b.querySelector(`td:nth-child(${Array.from(headers).indexOf(this) + 1})`).textContent;
                                
                                // Special handling for dates
                                if (column === 'date') {
                                    const aDate = new Date(aText);
                                    const bDate = new Date(bText);
                                    if (isAscending) {
                                        return aDate - bDate;
                                    } else {
                                        return bDate - aDate;
                                    }
                                }
                                
                                // Regular text comparison
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
                    
                    // Trigger click on first sortable header
                    if (headers.length > 0) {
                        headers[0].click();
                    }
                });
            });
            
            // Initialize first table sort
            if (sortButtons.length > 0) {
                sortButtons[0].click();
            }
            
            // Add active class to current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage || (currentPage === '' && linkPage === 'dashboard.php')) {
                    link.classList.add('active');
                }
            });
            
            // Auto-collapse sidebar on mobile
            function handleResize() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('collapsed');
                    sidebar.classList.remove('show');
                }
            }
            
            window.addEventListener('resize', handleResize);
            handleResize(); // Initial check
        });
