<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExpertHub - Online Service Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-users-cog me-2"></i>ExpertHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-light me-2">Sign In</a>
                    <a href="signup.php" class="btn btn-accent">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Find Expert Services for Any Task</h1>
                    <p class="lead mb-4">Connect with verified professionals for web development, design, writing, consulting, and more. Get quality work delivered on time.</p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-lg search-box" placeholder="What service do you need?">
                        </div>
                        <div class="col-md-4">
                            <a href="browse-services.php" class="btn btn-accent btn-lg w-100 search-btn">
                                <i class="fas fa-search me-2"></i>Get Service Now
                            </a>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark">Web Development</span>
                        <span class="badge bg-light text-dark">Graphic Design</span>
                        <span class="badge bg-light text-dark">Digital Marketing</span>
                        <span class="badge bg-light text-dark">Content Writing</span>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-laptop-code" style="font-size: 15rem; opacity: 0.1;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Service Categories -->
    <section id="services" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Popular Service Categories</h2>
                <p class="text-muted">Browse our most requested services</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-code"></i>
                            </div>
                            <h5 class="card-title">Web Development</h5>
                            <p class="card-text text-muted">Custom websites, web apps, and e-commerce solutions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            <h5 class="card-title">Graphic Design</h5>
                            <p class="card-text text-muted">Logos, branding, marketing materials, and UI/UX design</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <h5 class="card-title">Digital Marketing</h5>
                            <p class="card-text text-muted">SEO, social media, content marketing, and advertising</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-pen-fancy"></i>
                            </div>
                            <h5 class="card-title">Content Writing</h5>
                            <p class="card-text text-muted">Articles, blogs, copywriting, and technical documentation</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <h5 class="card-title">Mobile Apps</h5>
                            <p class="card-text text-muted">iOS and Android app development and design</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h5 class="card-title">Business Consulting</h5>
                            <p class="card-text text-muted">Strategy, planning, and business development services</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h5 class="card-title">Online Tutoring</h5>
                            <p class="card-text text-muted">Educational support and skill development courses</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="card service-card">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h5 class="card-title">Government Services</h5>
                            <p class="card-text text-muted">Job applications, scholarships, and Irembo services</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="stat-number">10K+</div>
                    <p class="text-muted">Active Users</p>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number">5K+</div>
                    <p class="text-muted">Completed Projects</p>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number">2K+</div>
                    <p class="text-muted">Expert Professionals</p>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number">98%</div>
                    <p class="text-muted">Client Satisfaction</p>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>