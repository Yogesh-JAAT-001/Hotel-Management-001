<?php
require_once '../config.php';

$page_title = 'Contact Us';
include '../includes/header.php';
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-luxury fixed-top">
    <div class="container">
        <a class="navbar-brand" href="../"><?php echo APP_NAME; ?></a>
        
        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
            <i class="fas fa-bars text-white"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rooms.php">Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dining.php">Dining</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="contact.php">Contact</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-mdb-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="reservations.php">My Reservations</a></li>
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-mdb-toggle="modal" data-mdb-target="#loginModal">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-mdb-toggle="modal" data-mdb-target="#registerModal">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Page Header -->
<section class="hero-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3 luxury-header text-white">
                    Contact Us
                </h1>
                <p class="lead text-light mb-4">
                    Get in touch with our team for reservations, inquiries, or any assistance you may need. 
                    We're here to make your stay exceptional.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="text-center">
                    <i class="fas fa-phone fa-5x text-warning opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Contact Information -->
            <div class="col-lg-4 mb-5">
                <div class="card card-luxury h-100">
                    <div class="card-body">
                        <h4 class="luxury-header mb-4">Get in Touch</h4>
                        
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning rounded-circle p-3 me-3">
                                    <i class="fas fa-map-marker-alt text-white"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Address</h6>
                                    <p class="text-muted mb-0">New Delhi, India<br>Luxury Hotel District</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning rounded-circle p-3 me-3">
                                    <i class="fas fa-phone text-white"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Phone</h6>
                                    <p class="text-muted mb-0">
                                        <a href="tel:+911112345678" class="text-decoration-none">+91-11-12345678</a><br>
                                        <small>24/7 Reception</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning rounded-circle p-3 me-3">
                                    <i class="fas fa-envelope text-white"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Email</h6>
                                    <p class="text-muted mb-0">
                                        <a href="mailto:info@heartlandabode.com" class="text-decoration-none">info@heartlandabode.com</a><br>
                                        <a href="mailto:reservations@heartlandabode.com" class="text-decoration-none">reservations@heartlandabode.com</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning rounded-circle p-3 me-3">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Check-in / Check-out</h6>
                                    <p class="text-muted mb-0">
                                        Check-in: 3:00 PM<br>
                                        Check-out: 11:00 AM
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Social Media -->
                        <div class="mt-4">
                            <h6 class="mb-3">Follow Us</h6>
                            <div class="d-flex gap-3">
                                <a href="#" class="btn btn-outline-warning btn-sm">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="btn btn-outline-warning btn-sm">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="btn btn-outline-warning btn-sm">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="btn btn-outline-warning btn-sm">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="col-lg-8">
                <div class="card card-luxury">
                    <div class="card-body">
                        <h4 class="luxury-header mb-4">Send us a Message</h4>
                        
                        <form id="contactForm" onsubmit="handleContactForm(event)">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-outline mb-4">
                                        <input type="text" id="name" name="name" class="form-control" required>
                                        <label class="form-label" for="name">Full Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-outline mb-4">
                                        <input type="email" id="email" name="email" class="form-control" required>
                                        <label class="form-label" for="email">Email Address</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-outline mb-4">
                                        <input type="tel" id="phone" name="phone" class="form-control">
                                        <label class="form-label" for="phone">Phone Number</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-outline mb-4">
                                        <select id="subject" name="subject" class="form-select" required>
                                            <option value="">Select Subject</option>
                                            <option value="reservation">Room Reservation</option>
                                            <option value="dining">Food & Dining</option>
                                            <option value="event">Events & Functions</option>
                                            <option value="complaint">Complaint</option>
                                            <option value="feedback">Feedback</option>
                                            <option value="other">Other</option>
                                        </select>
                                        <label class="form-label" for="subject">Subject</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-outline mb-4">
                                <textarea id="message" name="message" class="form-control" rows="6" required></textarea>
                                <label class="form-label" for="message">Message</label>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter">
                                <label class="form-check-label" for="newsletter">
                                    Subscribe to our newsletter for special offers and updates
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-luxury">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="luxury-header">Frequently Asked Questions</h2>
            <p class="text-muted">Quick answers to common questions</p>
        </div>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq1">
                            <button class="accordion-button" type="button" data-mdb-toggle="collapse" data-mdb-target="#collapse1">
                                What are your check-in and check-out times?
                            </button>
                        </h2>
                        <div id="collapse1" class="accordion-collapse collapse show" data-mdb-parent="#faqAccordion">
                            <div class="accordion-body">
                                Check-in time is 3:00 PM and check-out time is 11:00 AM. Early check-in and late check-out may be available upon request and subject to availability.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq2">
                            <button class="accordion-button collapsed" type="button" data-mdb-toggle="collapse" data-mdb-target="#collapse2">
                                Do you offer airport transportation?
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-mdb-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, we provide airport pickup and drop-off services. Please contact our concierge team to arrange transportation at least 24 hours in advance.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq3">
                            <button class="accordion-button collapsed" type="button" data-mdb-toggle="collapse" data-mdb-target="#collapse3">
                                What amenities are included in the room?
                            </button>
                        </h2>
                        <div id="collapse3" class="accordion-collapse collapse" data-mdb-parent="#faqAccordion">
                            <div class="accordion-body">
                                All rooms include complimentary WiFi, air conditioning, flat-screen TV, mini-bar, room service, and luxury toiletries. Premium rooms also include additional amenities like balcony access and premium bedding.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq4">
                            <button class="accordion-button collapsed" type="button" data-mdb-toggle="collapse" data-mdb-target="#collapse4">
                                Can I cancel or modify my reservation?
                            </button>
                        </h2>
                        <div id="collapse4" class="accordion-collapse collapse" data-mdb-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, you can cancel or modify your reservation up to 24 hours before your check-in date without any charges. Cancellations made within 24 hours may be subject to a one-night charge.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq5">
                            <button class="accordion-button collapsed" type="button" data-mdb-toggle="collapse" data-mdb-target="#collapse5">
                                Do you have facilities for events and meetings?
                            </button>
                        </h2>
                        <div id="collapse5" class="accordion-collapse collapse" data-mdb-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, we have fully equipped conference rooms and banquet halls available for corporate meetings, weddings, and special events. Please contact our events team for more information and booking.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Login to Your Account</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="form-outline mb-3">
                        <input type="email" id="loginEmail" name="email" class="form-control" required>
                        <label class="form-label" for="loginEmail">Email Address</label>
                    </div>
                    <div class="form-outline mb-3">
                        <input type="password" id="loginPassword" name="password" class="form-control" required>
                        <label class="form-label" for="loginPassword">Password</label>
                    </div>
                    <button type="submit" class="btn btn-luxury w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Handle contact form submission
async function handleContactForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const contactData = Object.fromEntries(formData);
    
    // Simulate form submission (in real implementation, this would send to server)
    try {
        // Show loading
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        submitBtn.disabled = true;
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Reset form
        event.target.reset();
        
        // Show success message
        showToast('Thank you for your message! We will get back to you soon.', 'success');
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
    } catch (error) {
        showToast('Failed to send message. Please try again.', 'danger');
        
        // Reset button
        const submitBtn = event.target.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Message';
        submitBtn.disabled = false;
    }
}

// Handle login
async function handleLogin(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const loginData = {
        email: formData.get('email'),
        password: formData.get('password'),
        user_type: 'guest'
    };
    
    try {
        const response = await apiRequest('../api/auth/login.php', {
            method: 'POST',
            body: JSON.stringify(loginData)
        });
        
        if (response.success) {
            showToast('Login successful!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

// Logout function
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await apiRequest('../api/auth/logout.php', { method: 'POST' });
            location.reload();
        } catch (error) {
            showToast('Unable to logout right now. Please try again.', 'danger');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
