<?php 
// Start session for user authentication
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ | DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- FAQ Page Specific CSS -->
    <link rel="stylesheet" href="css/faq.css">
    
    <style>
        .highlight {
            background-color: rgba(199, 232, 243, 0.3);
            padding: 2px 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <?php require('inc/header.php'); ?>

    <main style="margin-top: 80px;">
        
        <!-- Title & Search Section -->
        <section class="faq-title-section">
            <div class="container">
                <div class="faq-title">
                    <h1>Frequently Asked Questions</h1>
                </div>
                <p class="title-tagline">
                    Find quick answers to common questions about our Digital Signature System. 
                    Can't find what you're looking for? Contact our support team.
                </p>
                
                <!-- Search Bar -->
                <div class="search-container">
                    <div class="search-wrapper">
                        <input 
                            type="text" 
                            class="search-input" 
                            id="faq-search" 
                            placeholder="Search for questions..." 
                            aria-label="Search FAQ questions"
                        >
                        <button type="button" class="clear-search" id="clear-search" aria-label="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <button type="button" class="search-button" id="search-button" aria-label="Search">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div class="search-results-info" id="results-info"></div>
                </div>
            </div>
        </section>

        <!-- FAQ Accordion Section -->
        <section class="faq-section">
            <div class="container">
                <div class="faq-list" id="faq-list">
                    <!-- FAQ Item 1 -->
                    <div class="faq-item" data-question="What is a digital signature?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">01</span>
                                <h3 class="question-text">What is a digital signature and how does it work?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>A digital signature is a mathematical technique used to validate the authenticity and integrity of a digital document. It's the digital equivalent of a handwritten signature, but offers far more inherent security.</p>
                            </div>
                        </div>
                    </div>
                    <!-- FAQ Item 3 -->
                    <div class="faq-item" data-question="How secure is your platform?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">02</span>
                                <h3 class="question-text">How secure is your digital signature platform?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>We implement multiple layers of security including 256-bit AES encryption and multi-factor authentication.</p>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Item 4 -->
                    <div class="faq-item" data-question="What file formats are supported?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">03</span>
                                <h3 class="question-text">What file formats can I sign with your system?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>Our platform supports DOC AND DOCX.</p>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Item 5 -->
                    <div class="faq-item" data-question="How do I get started?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">04</span>
                                <h3 class="question-text">How do I get started with the Digital Signature System?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>Getting started is simple: create an account, admin will send the signed files, and start scanning the QR.</p>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Item 6 -->
                    <div class="faq-item" data-question="Can multiple people sign?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">05</span>
                                <h3 class="question-text">Can multiple people sign the same document?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>Yes, we support parallel signing, sequential signing, and mixed workflows.</p>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Item 7 -->
                    <div class="faq-item" data-question="What happens if I lose access?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">06</span>
                                <h3 class="question-text">What happens if I lose access to my account?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>We offer support team assistance.</p>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Item 9 -->
                    <div class="faq-item" data-question="Do you offer integration?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">07</span>
                                <h3 class="question-text">Do you offer API integration with other systems?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>Yes, we provide Libraries API and pre-built integrations.</p>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Item 10 -->
                    <div class="faq-item" data-question="What support do you offer?">
                        <button class="faq-question">
                            <div class="question-content">
                                <span class="question-number">08</span>
                                <h3 class="question-text">What kind of support and training do you offer?</h3>
                            </div>
                            <span class="arrow-icon"><i class="bi bi-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <p>Knowledge base video tutorials and user manual documentation.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Results Message -->
                <div class="no-results" id="no-results" style="display: none;">
                    <div class="no-results-icon">
                        <i class="bi bi-question-circle"></i>
                    </div>
                    <h3>No questions found</h3>
                    <p>We couldn't find any questions matching your search. Try different keywords.</p>
                </div>
            </div>
        </section>
    </main>

    <?php require('inc/footer.php'); ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // FAQ Accordion Functionality
            const faqItems = document.querySelectorAll('.faq-item');
            let activeItem = null;
            
            // Initialize with first FAQ open
            if (faqItems.length > 0) {
                faqItems[0].classList.add('active');
                activeItem = faqItems[0];
            }
            
            faqItems.forEach(item => {
                const questionBtn = item.querySelector('.faq-question');
                
                questionBtn.addEventListener('click', () => {
                    if (item === activeItem) {
                        item.classList.remove('active');
                        activeItem = null;
                    } else {
                        if (activeItem) {
                            activeItem.classList.remove('active');
                        }
                        item.classList.add('active');
                        activeItem = item;
                    }
                });
            });
            
            // Search Functionality
            const searchInput = document.getElementById('faq-search');
            const clearButton = document.getElementById('clear-search');
            const searchButton = document.getElementById('search-button');
            const resultsInfo = document.getElementById('results-info');
            const noResults = document.getElementById('no-results');
            
            function performSearch() {
                const searchTerm = searchInput.value.trim().toLowerCase();
                let visibleCount = 0;
                
                if (searchTerm === '') {
                    faqItems.forEach(item => {
                        item.style.display = 'block';
                        visibleCount++;
                        
                        // Remove highlights
                        const questionText = item.querySelector('.question-text');
                        const originalText = questionText.getAttribute('data-original') || questionText.textContent;
                        questionText.textContent = originalText;
                    });
                    
                    noResults.style.display = 'none';
                    clearButton.style.display = 'none';
                    resultsInfo.textContent = '';
                    
                    return;
                }
                
                clearButton.style.display = 'flex';
                
                faqItems.forEach(item => {
                    const questionText = item.getAttribute('data-question').toLowerCase();
                    
                    if (questionText.includes(searchTerm)) {
                        item.style.display = 'block';
                        visibleCount++;
                        
                        // Highlight matching text
                        const questionElement = item.querySelector('.question-text');
                        const originalText = questionElement.textContent;
                        questionElement.setAttribute('data-original', originalText);
                        
                        const regex = new RegExp(`(${searchTerm})`, 'gi');
                        questionElement.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
                    } else {
                        item.style.display = 'none';
                        item.classList.remove('active');
                    }
                });
                
                resultsInfo.textContent = `${visibleCount} result${visibleCount !== 1 ? 's' : ''} found`;
                
                if (visibleCount === 0) {
                    noResults.style.display = 'block';
                } else {
                    noResults.style.display = 'none';
                }
            }
            
            searchInput.addEventListener('input', performSearch);
            
            searchButton.addEventListener('click', () => {
                searchInput.focus();
                performSearch();
            });
            
            clearButton.addEventListener('click', () => {
                searchInput.value = '';
                performSearch();
                searchInput.focus();
            });
            
            searchInput.addEventListener('keyup', (event) => {
                if (event.key === 'Enter') {
                    performSearch();
                }
            });
            
            performSearch();
        });
    </script>
</body>
</html>