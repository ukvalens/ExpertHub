<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExpertHub - Online Service Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=11" rel="stylesheet">

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

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">About ExpertHub</h2>
                <p class="text-muted">Meet our team and explore our services</p>
            </div>
            
            <?php
            require_once 'config/database.php';
            $owner_photos = $conn->query("SELECT * FROM about_photos WHERE type = 'owner' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
            $service_photos = $conn->query("SELECT * FROM about_photos WHERE type = 'service' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <h4 class="text-center mb-4"><i class="fas fa-user-tie me-2"></i>Our Team</h4>
                    <?php if (!empty($owner_photos)): ?>
                        <div id="ownerCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($owner_photos as $i => $photo): ?>
                                    <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                                        <img src="/ExpertHUB/uploads/about/<?php echo $photo['photo_path']; ?>" alt="Team">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#ownerCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#ownerCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5 bg-white rounded">
                            <i class="fas fa-user-circle fa-5x text-muted mb-3"></i>
                            <p class="text-muted">No photos available</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <h4 class="text-center mb-4"><i class="fas fa-briefcase me-2"></i>Our Services</h4>
                    <?php if (!empty($service_photos)): ?>
                        <div id="serviceCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($service_photos as $i => $photo): ?>
                                    <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                                        <img src="/ExpertHUB/uploads/about/<?php echo $photo['photo_path']; ?>" alt="Service">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#serviceCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#serviceCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5 bg-white rounded">
                            <i class="fas fa-briefcase fa-5x text-muted mb-3"></i>
                            <p class="text-muted">No photos available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setInterval(() => {
            bootstrap.Carousel.getOrCreateInstance(document.getElementById('ownerCarousel')).next();
            bootstrap.Carousel.getOrCreateInstance(document.getElementById('serviceCarousel')).next();
        }, 3000);
    </script>
</body>
</html>