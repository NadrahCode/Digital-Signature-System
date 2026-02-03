<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- Links from external file -->
    <?php require('inc/links.php'); ?>
</head>
<body>
    <!-- Header Start -->
    <header class="main-header">
        <div class="header-container">
            <!-- Left Navigation -->
            <nav class="nav-left">
                <ul>
                    <li><a href="index.php" class="nav-link">Home</a></li>
                    <li><a href="about.php" class="nav-link">About</a></li>
                    <li><a href="faq.php" class="nav-link">FAQ</a></li>
                </ul>
            </nav>
            
            <!-- Centered Logo -->
            <div class="logo-center">
                <a href="index.php" class="logo">
                    <span class="logo-text">DIGITAL SIGNATURE</span>
                    <span class="logo-subtext">SYSTEM</span>
                </a>
            </div>
            
            <!-- Right Navigation (Login/Register) -->
            <nav class="nav-right">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-login">
                        <i class="bi bi-person-circle"></i> Dashboard
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                <?php endif; ?>
            </nav>
            
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </header>
    
    <!-- Mobile Navigation Menu -->
    <div class="mobile-nav">
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="faq.php">FAQ</a></li>
            <li><a href="queries.php">Queries</a></li>
            <li><a href="login.php" class="btn btn-login">Login</a></li>
        </ul>
    </div>
    
    <!-- Header Styles -->
    <style>
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            padding: 1rem 0;
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-left, .nav-right {
            flex: 1;
        }
        
        .nav-left ul {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
            padding: 0;
        }
        
        .nav-link {
            text-decoration: none;
            color: var(--primary-green);
            font-weight: 500;
            font-size: 1rem;
            padding: 0.5rem 0;
            position: relative;
            transition: var(--transition);
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary-teal);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover {
            color: var(--primary-teal);
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .logo-center {
            text-align: center;
        }
        
        .logo {
            text-decoration: none;
            display: inline-block;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-green);
            letter-spacing: 1px;
        }
        
        .logo-subtext {
            font-size: 0.9rem;
            color: var(--primary-teal);
            display: block;
            margin-top: -5px;
            font-weight: 500;
        }
        
        .nav-right {
            text-align: right;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-green);
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .mobile-nav {
            display: none;
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 1rem;
            z-index: 999;
        }
        
        .mobile-nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .mobile-nav li {
            margin-bottom: 1rem;
        }
        
        .mobile-nav a {
            text-decoration: none;
            color: var(--primary-green);
            font-size: 1.1rem;
            display: block;
            padding: 0.5rem 0;
        }
        
        .mobile-nav a.btn {
            display: inline-block;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .nav-left, .nav-right {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
                position: absolute;
                right: 20px;
                top: 50%;
                transform: translateY(-50%);
            }
            
            .logo-center {
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
            }
            
            .mobile-nav.active {
                display: block;
            }
        }
        
        @media (max-width: 480px) {
            .logo-text {
                font-size: 1.2rem;
            }
            
            .logo-subtext {
                font-size: 0.8rem;
            }
            
            .header-container {
                padding: 0 15px;
            }
        }
    </style>
    
    <!-- Header JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const mobileNav = document.querySelector('.mobile-nav');
            
            mobileMenuBtn.addEventListener('click', function() {
                mobileNav.classList.toggle('active');
                this.innerHTML = mobileNav.classList.contains('active') 
                    ? '<i class="bi bi-x-lg"></i>' 
                    : '<i class="bi bi-list"></i>';
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.mobile-menu-btn') && !event.target.closest('.mobile-nav')) {
                    mobileNav.classList.remove('active');
                    mobileMenuBtn.innerHTML = '<i class="bi bi-list"></i>';
                }
            });
            
            // Close mobile menu when clicking a link
            mobileNav.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function() {
                    mobileNav.classList.remove('active');
                    mobileMenuBtn.innerHTML = '<i class="bi bi-list"></i>';
                });
            });
            
            // Add shadow on scroll
            window.addEventListener('scroll', function() {
                const header = document.querySelector('.main-header');
                if (window.scrollY > 50) {
                    header.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.1)';
                } else {
                    header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
                }
            });
        });
    </script>