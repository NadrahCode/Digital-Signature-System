<?php 
// Start session for user authentication
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- About Page Specific CSS -->
    <link rel="stylesheet" href="css/about.css">
</head>
<body>
    <!-- Include the new header -->
    <?php require('inc/header.php'); ?>

    <!-- Main Content Start -->
    <main style="margin-top: 80px;">
        
        <!-- Title Section -->
        <section class="about-title-section">
            <div class="container">
                <div class="about-title">
                    <h1>ABOUT US</h1>
                </div>
                <p class="title-tagline">
                    Discover the story behind our secure digital signature platform 
                    and the mission that drives us to provide exceptional service.
                </p>
            </div>
        </section>

        <!-- Developer Container -->
        <section class="developer-section">
            <div class="container">
                <div class="developer-container">
                    <img src="images/developer.jpg" alt="Developer" class="developer-image">
                    <div class="developer-info">
                        <h3>Project Lead Developer</h3>
                        <p class="developer-role">Senior Software Engineer</p>
                        <p class="developer-description">
                            Project developed by a dedicated team of cybersecurity and software engineering 
                            professionals with over 10 years of experience in digital authentication systems. 
                            Our commitment is to provide secure, reliable, and user-friendly digital signature 
                            solutions for organizations of all sizes.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Story & Mission Section -->
        <section class="story-mission-section">
            <div class="container">
                <div class="columns-container">
                    <!-- Left Column: Our Story -->
                    <div class="column" id="story-column">
                        <div class="column-icon">
                            <i class="bi bi-journal-bookmark"></i>
                        </div>
                        <h3>Our Story</h3>
                        <div class="column-content">
                            <p>
                                The Digital Signature System was born from a need to streamline document 
                                authentication processes in educational and corporate environments. What 
                                started as a university project has evolved into a comprehensive platform 
                                serving hundreds of institutions nationwide.
                            </p>
                            <p>
                                Our journey began in 2020 when we identified the inefficiencies in 
                                traditional paper-based signing processes. We set out to create a solution 
                                that would not only digitize signatures but also ensure their legal validity 
                                and security.
                            </p>
                            <p>
                                Today, we pride ourselves on providing a platform that combines cutting-edge 
                                security with unparalleled ease of use, helping organizations transition 
                                seamlessly into the digital age.
                            </p>
                        </div>
                    </div>

                    <!-- Right Column: Our Mission -->
                    <div class="column" id="mission-column">
                        <div class="column-icon">
                            <i class="bi bi-bullseye"></i>
                        </div>
                        <h3>Our Mission</h3>
                        <div class="column-content">
                            <p>
                                To revolutionize document authentication by providing secure, efficient, 
                                and legally compliant digital signature solutions that empower organizations 
                                to operate more effectively in the digital world.
                            </p>
                            <p>
                                We are committed to maintaining the highest standards of security while 
                                ensuring our platform remains accessible and user-friendly for all skill levels.
                            </p>
                            <p>
                                Our mission extends beyond technology - we aim to educate and support our 
                                users in understanding digital authentication, helping them make informed 
                                decisions about their document security needs.
                            </p>
                            <p>
                                We believe in continuous improvement and innovation, constantly updating 
                                our systems to meet evolving security challenges and user requirements.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section (Similar to index but with different content) -->
        <section class="features-section" style="background-color: var(--primary-blue);">
            <div class="container">
                <div class="main-title">
                    <h2 class="text-center">WHY CHOOSE US</h2>
                    <p class="text-center">Key advantages of our digital signature platform</p>
                </div>
                
                <div class="features-container">
                    <!-- Feature 1 -->
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3>Bank-Level Security</h3>
                        <p>256-bit encryption and multi-factor authentication ensure your documents are protected with the same security standards used by financial institutions.</p>
                    </div>
                    
                    <!-- Feature 2 -->
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h3>24/7 Availability</h3>
                        <p>Our platform operates around the clock with 99.9% uptime guarantee, ensuring you can sign documents whenever you need.</p>
                    </div>
                    
                    <!-- Feature 3 -->
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3>Dedicated Support</h3>
                        <p>Our team of experts is always ready to assist with setup, troubleshooting, and best practices for digital signature implementation.</p>
                    </div>
                </div>
            </div>
        </section>

    </main>
    <!-- Main Content End -->

    <!-- Include the new footer -->
    <?php require('inc/footer.php'); ?>
    
    <!-- JavaScript for animations -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate columns on scroll
            const storyColumn = document.getElementById('story-column');
            const missionColumn = document.getElementById('mission-column');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.3
            });
            
            if (storyColumn) observer.observe(storyColumn);
            if (missionColumn) observer.observe(missionColumn);
            
            // Add smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>