<?php
// index.php - Main homepage
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Haven - Your Gateway to Wisdom</title>
    
    <!-- FAVICON -->
    <link rel="icon" href="assets/images/logo-library.png" type="image/png">
    
    <!-- EXTERNAL CSS LIBRARIES -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    
    <!-- NAVIGATION BAR -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <div class="logo-container">
                    <div class="logo-img">
                        <img src="assets/images/logo-library.png" alt="Knowledge Haven Logo">
                    </div>
                    <span class="library-name">Knowledge <span>Haven</span></span>
                </div>
            </a>

            <!-- MOBILE TOGGLE BUTTON -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- NAVIGATION LINKS -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home"><i class="fas fa-home me-2"></i>Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about"><i class="fas fa-info-circle me-2"></i>About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact"><i class="fas fa-address-book me-2"></i>Contact</a>
                    </li>
                </ul>

                <!-- AUTHENTICATION BUTTONS OR USER DROPDOWN -->
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): 
                        // Fetch user details from database to get profile image and username
                        include "config.php";
                        $user_id = $_SESSION['user_id'];
                        $query = "SELECT username, profile_image FROM users WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $user = mysqli_fetch_assoc($result);
                        $profile_image = $user['profile_image'] ?? 'assets/images/user_image.jpg';
                        $username = $user['username'] ?? $_SESSION['username'];
                    ?>
                        <!-- User is logged in - Show dropdown -->
                        <div class="dropdown user-dropdown">
                            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                               <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                    alt="Profile" 
                                    style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; position: absolute; right: 110%;" 
                                    onerror="this.src='assets/images/user_image.jpg'">
                                <span><?php echo htmlspecialchars($username); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo $_SESSION['role'] === 'employee' ? 'admin/dashboard.php' : 'user/dashboard.php'; ?>">
                                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="user/profile.php">
                                        <i class="fas fa-user me-2"></i>My Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- User is NOT logged in - Show login/register buttons -->
                        <a href="register.php" class="btn btn-register">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </a>
                        <a href="login.php" class="btn btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- ==================================================================================================================================================================================== -->
    
    <!-- HERO SECTION -->
    
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="hero-title">Welcome to <span class="highlight">Knowledge Haven</span></h1>
                        <p class="hero-subtitle">Where stories come to life and minds expand. Discover your next adventure in our curated collection of books from around the world.</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <img src="assets/images/book-home-img.png" alt="Library Interior" class="img-fluid rounded shadow">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================================================================================================================================================================================== -->
    
    <!-- FEATURES SECTION -->
    
    <section class="features-section py-5">
        <div class="container">
            <div class="section-header text-center mb-5">
                <h2 class="section-title">Why Choose Our Library?</h2>
                <p class="section-subtitle">Experience the perfect blend of traditional charm and modern convenience</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <h4 class="feature-title">Vast Collection</h4>
                        <p>Over 50,000+ books across genres with regular new arrivals and rare editions.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h4 class="feature-title">Expert Staff</h4>
                        <p>Friendly librarians ready to help you find exactly what you're looking for.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <h4 class="feature-title">Modern Facilities</h4>
                        <p>Free Wi-Fi, study rooms, computer access, and comfortable reading areas.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================================================================================================================================================================================== -->
    
    <!-- COLLECTIONS SECTION -->
    
    <section class="collections-section py-5">
        <div class="container">
            <div class="section-header text-center mb-5">
                <h2 class="section-title">Explore Our Collections</h2>
                <p class="section-subtitle">Find your next favorite book in our diverse sections</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="collection-card">
                        <div class="collection-icon">
                            <i class="fas fa-hat-wizard"></i>
                        </div>
                        <h5>Fantasy & Fiction</h5>
                        <p>Escape to magical worlds and alternate realities</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="collection-card">
                        <div class="collection-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        <h5>Science & Tech</h5>
                        <p>Latest discoveries and technological innovations</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="collection-card">
                        <div class="collection-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h5>History & Biography</h5>
                        <p>Learn from the past and inspiring life stories</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="collection-card">
                        <div class="collection-icon">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <h5>Arts & Literature</h5>
                        <p>Classic and contemporary literary masterpieces</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================================================================================================================================================================================== -->
    
    <!-- ABOUT SECTION -->
    
    <div id="about">
        <!-- PAGE HEADER -->
        <header class="page-header">
            <div class="container">
                <h1 class="page-title">About Knowledge Haven</h1>
                <p class="page-subtitle">Discover the story behind our century-old institution dedicated to preserving knowledge and inspiring minds.</p>
            </div>
        </header>

        <!-- OUR STORY SECTION -->
        <section class="about-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <div class="about-content">
                            <h2 class="section-title text-center">Our Story</h2>
                            <p class="lead">Founded in 1875, Knowledge Haven began as a small reading room for scholars and has grown into one of the region's most comprehensive libraries.</p>
                            <p>For nearly 150 years, we have served as a beacon of knowledge and learning in our community. Our journey began with a modest collection of 500 books donated by local philanthropist, Dr. Eleanor Bennett. Today, we house over 500,000 volumes, digital resources, and rare manuscripts.</p>
                            <p>Throughout our history, we have adapted to changing times while staying true to our mission: to provide free access to information for all. From adding electricity in 1920 to launching our digital library in 2010, we've continuously evolved to serve our community better.</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="about-image">
                            <img src="assets/images/books-img.png" alt="Historical library building">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MISSION & VISION SECTION -->
        <section class="about-section">
            <div class="container">
                <div class="row">
                    <div class="col-lg-10 mx-auto">
                        <div class="row">
                            <!-- MISSION CARD -->
                            <div class="col-md-6 mb-4">
                                <div class="value-card">
                                    <div class="value-icon">
                                        <i class="fas fa-bullseye"></i>
                                    </div>
                                    <h3>Our Mission</h3>
                                    <p>To empower individuals and enrich our community by providing free access to information, resources, and spaces that foster lifelong learning, creativity, and connection.</p>
                                </div>
                            </div>
                            
                            <!-- VISION CARD -->
                            <div class="col-md-6 mb-4">
                                <div class="value-card">
                                    <div class="value-icon">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <h3>Our Vision</h3>
                                    <p>To be the heart of our community's intellectual and cultural life, where every person feels welcome to explore, discover, and grow in an inclusive and inspiring environment.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CORE VALUES SECTION -->
        <section class="values-section">
            <div class="container">
                <h2 class="section-title text-center">Our Core Values</h2>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-universal-access"></i>
                            </div>
                            <h4>Accessibility</h4>
                            <p>We believe knowledge should be accessible to everyone, regardless of background, ability, or economic status.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-hands-helping"></i>
                            </div>
                            <h4>Community</h4>
                            <p>We serve as a gathering place where community connections are formed and strengthened.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <h4>Innovation</h4>
                            <p>We embrace new technologies and approaches to better serve our patrons' evolving needs.</p>
                        </div>
                    </div>
                </div>
                
                <div class="row justify-content-center">
                    <div class="col-md-4 mb-4">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <h4>Stewardship</h4>
                            <p>We preserve our collections and resources for future generations while serving current needs.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4>Inclusion</h4>
                            <p>We create welcoming spaces where diverse perspectives are valued and celebrated.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TEAM SECTION-->
        <section class="team-section">
            <div class="container">
                <h2 class="section-title text-center">Meet Our Founder</h2>
                <p class="text-center mb-5">The visionary behind Knowledge Haven's century-long legacy</p>
                
                <div class="row justify-content-center">
                    <!-- SINGLE TEAM CARD FOR OWNER -->
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="team-card">
                            <img src="assets/images/library-owner.jpg" alt="Dr. Eleanor Bennett" class="team-img">
                            <div class="team-info">
                                <h4 class="team-name">Dr. Eleanor Bennett</h4>
                                <div class="team-role">Founder & Visionary</div>
                                <p class="small">Established Knowledge Haven in 1875 with a personal collection of 500 books. His vision was to create a sanctuary of knowledge accessible to all, regardless of social standing or economic means.</p>
                                <div class="team-social">
                                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#"><i class="fas fa-envelope"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- ==================================================================================================================================================================================== -->
    
    <!-- CONTACT SECTION -->
    
    <section class="contact-info-section" id="contact">
        <div class="container">
            <h2 class="section-title text-center text-white">Visit Us Today</h2>
            
            <div class="row">
                <div class="col-lg-8 mb-5">
                    <div class="row">
                        <!-- CONTACT INFO: LOCATION & PHONE -->
                        <div class="col-md-6">
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>Our Location</h5>
                                    <p>123 Wisdom Street<br>Bookville, BK 12345</p>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>Phone Number</h5>
                                    <p>(555) 123-4567</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- CONTACT INFO: EMAIL & WEBSITE -->
                        <div class="col-md-6">
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>Email Address</h5>
                                    <p>info@knowledgehaven.org<br>questions@knowledgehaven.org</p>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <div class="contact-details">
                                    <h5>Website</h5>
                                    <p>www.knowledgehaven.org</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- OPENING HOURS -->
                <div class="col-lg-4">
                    <div class="opening-hours">
                        <h4 class="mb-4">Opening Hours</h4>
                        <table class="hours-table">
                            <tr>
                                <td>Monday - Friday</td>
                                <td>9:00 AM - 8:00 PM</td>
                            </tr>
                            <tr>
                                <td>Saturday</td>
                                <td>10:00 AM - 6:00 PM</td>
                            </tr>
                            <tr>
                                <td>Sunday</td>
                                <td>12:00 PM - 6:00 PM</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================================================================================================================================================================================== -->
    
    <!-- CTA SECTION -->
    
    <section class="cta-section py-5">
        <div class="container">
            <div class="cta-content text-center">
                <h2 class="cta-title">Become Part of Our Story</h2>
                <p class="cta-text mb-4">Join our community of readers, learners, and knowledge seekers.</p>
                <div class="cta-buttons">
                    <a href="register.php" class="btn btn-primary btn-cta">
                        <i class="fas fa-user-plus me-2"></i>Become a Member
                    </a>
                    <a href="#" class="btn btn-outline-light btn-cta">
                        <i class="fas fa-briefcase me-2"></i>Join Our Team
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================================================================================================================================================================================== -->
    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <!-- BRAND AND ABOUT -->
                <div class="col-lg-4 mb-4">
                    <div class="footer-brand mb-3">
                        <div class="logo-container">
                            <div class="logo-img">
                                <img src="assets/images/logo-library.png" alt="Knowledge Haven Logo">
                            </div>
                            <span class="library-name">Knowledge <span>Haven</span></span>
                        </div>
                    </div>
                    <p class="footer-about">A sanctuary for knowledge seekers since 1875, providing access to information, inspiration, and community for generations.</p>
                    <div class="social-icons mt-4">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <!-- QUICK LINKS -->
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-heading">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home"><i class="fas fa-chevron-right me-2"></i>Home</a></li>
                        <li><a href="#about"><i class="fas fa-chevron-right me-2"></i>About Us</a></li>
                        <li><a href="#contact"><i class="fas fa-chevron-right me-2"></i>Contact Us</a></li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <li><a href="login.php"><i class="fas fa-chevron-right me-2"></i>Login</a></li>
                        <li><a href="register.php"><i class="fas fa-chevron-right me-2"></i>Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- RESOURCES -->
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="footer-heading">Resources</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>Digital Library</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>Online Catalog</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>Research Guides</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>Study Rooms</a></li>
                    </ul>
                </div>
                
                <!-- CONTACT INFO -->
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="footer-heading">Contact Info</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i>123 Wisdom Street, Bookville</li>
                        <li><i class="fas fa-phone me-2"></i>(555) 123-4567</li>
                        <li><i class="fas fa-envelope me-2"></i>info@knowledgehaven.org</li>
                        <li><i class="fas fa-clock me-2"></i>Mon-Sat: 9AM-8PM, Sun: 12PM-6PM</li>
                    </ul>
                </div>
            </div>
            
            <!-- COPYRIGHT -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="copyright text-center">
                        <p>&copy; <?php echo date('Y'); ?> Knowledge Haven Library. All rights reserved. | Designed with <i class="fas fa-heart text-danger mx-2"></i> for readers everywhere</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>


    <!-- Bootstrap JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>