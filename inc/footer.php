    <!-- Footer Start -->
    <footer class="main-footer">
        <div class="footer-container">
            <!-- System Title and Description -->
            <div class="footer-logo">
                <h3 class="footer-title">Digital Signature System For Temper Proof Document Verification</h3>
                <p class="footer-description">
                    A secure and efficient platform for managing digital signatures, 
                    ensuring authenticity and integrity of electronic documents.
                </p>
            </div>
            
            <!-- Social Media Icons -->
            <div class="social-icons">
                <a href="#" class="social-link" aria-label="Facebook">
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="#" class="social-link" aria-label="Instagram">
                    <i class="bi bi-instagram"></i>
                </a>
                <a href="#" class="social-link" aria-label="YouTube">
                    <i class="bi bi-youtube"></i>
                </a>
            </div>
            
            <!-- Copyright -->
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> Nur Nadrah Hayati. All rights reserved.</p>
            </div>
            
            <!-- Back to Top Button -->
            <button class="back-to-top" aria-label="Back to top">
                <i class="bi bi-arrow-up"></i>
            </button>
        </div>
    </footer>
    
    <!-- Footer Styles -->
    <style>
        .main-footer {
            background-color: var(--white);
            padding: 4rem 0 2rem;
            border-top: 1px solid #eee;
            position: relative;
        }
        
        .footer-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
        }
        
        .footer-logo {
            margin-bottom: 2.5rem;
        }
        
        .footer-title {
            color: var(--primary-green);
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        
        .footer-description {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: var(--primary-blue);
            color: var(--primary-green);
            border-radius: 50%;
            text-decoration: none;
            font-size: 1.2rem;
            transition: var(--transition);
        }
        
        .social-link:hover {
            background-color: var(--primary-teal);
            color: white;
            transform: translateY(-3px);
        }
        
        .copyright {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .back-to-top {
            position: absolute;
            right: 30px;
            bottom: 30px;
            width: 50px;
            height: 50px;
            background-color: var(--primary-teal);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .back-to-top:hover {
            background-color: var(--primary-green);
            transform: translateY(-5px);
        }
        
        @media (max-width: 768px) {
            .main-footer {
                padding: 3rem 0 2rem;
            }
            
            .footer-title {
                font-size: 1.5rem;
            }
            
            .footer-description {
                font-size: 1rem;
                padding: 0 10px;
            }
            
            .back-to-top {
                position: fixed;
                right: 20px;
                bottom: 20px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .social-icons {
                gap: 1rem;
            }
            
            .social-link {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .footer-title {
                font-size: 1.3rem;
            }
        }
    </style>
    
    <!-- Footer JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const backToTopBtn = document.querySelector('.back-to-top');
            
            // Show/hide back to top button
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTopBtn.style.opacity = '1';
                    backToTopBtn.style.visibility = 'visible';
                } else {
                    backToTopBtn.style.opacity = '0';
                    backToTopBtn.style.visibility = 'hidden';
                }
            });
            
            // Back to top functionality
            backToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>