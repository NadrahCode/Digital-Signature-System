<?php 
// Start session for user authentication
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
</head>
<body>
    <!-- Include the new header -->
    <?php require('inc/header.php'); ?>

    <!-- Main Content Start -->
    <main style="margin-top: 80px;">
        
        <!-- Hero Section (Replaces Swiper Slider) -->
        <section class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <!-- Big Main Logo/Image on Left -->
                    <div class="hero-logo">
                        <img src="images/logo-main.png" alt="Digital Signature System Logo" class="logo-main">
                    </div>
                    
                    <!-- Title, Description and Button on Right -->
                    <div class="hero-text">
                        <h1>Digital Signature System For Temper Proof Document Verification</h1>
                        <p class="hero-description">
                            Streamline your document signing process with our secure, efficient, 
                            and legally compliant digital signature system. Experience seamless 
                            authentication and verification.
                        </p>
                        <div class="cta-buttons">
                            <?php if(!isset($_SESSION['user_id'])): ?>
                                <a href="register.php" class="btn btn-login">Register Here</a>
                            <?php else: ?>
                                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Insight Section (Now Single Card Per Row) -->
        <section class="features-section">
            <div class="container">
                <div class="main-title">
                    <h2 class="text-center">KEY FEATURES</h2>
                    <p class="text-center">Discover the powerful capabilities of our digital signature platform</p>
                </div>
                
                <div class="features-container">
                    <!-- Feature 1 -->
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h3>Better Security</h3>
                        <p>Advanced encryption and authentication protocols ensure your documents remain secure and tamper-proof throughout the entire signing process.</p>
                    </div>
                    
                    <!-- Feature 2 -->
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                        <h3>Lightning Fast Processing</h3>
                        <p>Sign documents in minutes instead of days. Our optimized workflow reduces processing time by up to 90% compared to traditional methods.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="contact-section">
            <div class="container">
                <div class="main-title">
                    <h2 class="text-center">CONTACT INFORMATION</h2>
                    <p class="text-center">Reach out to us for any inquiries or support</p>
                </div>
                
                <div class="contact-container">
                    <!-- Map Section -->
                    <div class="contact-map">
                        <h2>Our Location</h2>
                        <div class="map-container">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3983.8738699084674!2d101.73480567567599!3d3.128029653280666!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc374119aeec81%3A0xa023551a33256eb1!2sUniversiti%20Poly-Tech%20Malaysia!5e0!3m2!1sen!2smy!4v1774503327036!5m2!1sen!2smy" 
                                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="contact-info">
                        <h2>Get In Touch</h2>
                        <div class="contact-item">
                            <h5>Phone</h5>
                            <a href="tel:+60178700689">
                                <i class="bi bi-telephone"></i> +60170000000
                            </a>
                        </div>
                        <div class="contact-item">
                            <h5>Email</h5>
                            <a href="mailto:StudentEmail@@student.uptm.edu.my">
                                <i class="bi bi-envelope"></i> StudentEmail@@student.uptm.edu.my
                            </a>
                        </div>
                        <div class="contact-item">
                            <h5>Follow Us</h5>
                            <div class="social-links">
                                <a href="https://www.facebook.com/uptm.official/" target="_blank">
                                    <i class="bi bi-facebook"></i>
                                </a>
                                <a href="https://www.instagram.com/uptm_official/" target="_blank">
                                    <i class="bi bi-instagram"></i>
                                </a>
                            </div>
                        </div>
                        <div class="contact-item">
                            <h5>Documentation</h5>
                            <a href="pdf/USER.pdf" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i> User Manual
                            </a>
                            <br>
                            <a href="pdf/ADMIN.pdf" target="_blank" class="mt-1">
                                <i class="bi bi-file-earmark-pdf"></i> Admin Manual
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <!-- Main Content End -->

    <!-- Include the new footer -->
    <?php require('inc/footer.php'); ?>
</body>
</html>