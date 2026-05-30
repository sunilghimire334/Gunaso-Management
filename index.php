<?php
require_once 'config.php';
require_once 'cache_control.php';

// Fetch stats for display - FIXED
$stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0
];

$result = $conn->query("SELECT status, COUNT(*) as count FROM complaints GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'in-progress') {
            $stats['in_progress'] = $row['count'];
        } elseif ($row['status'] == 'pending') {
            $stats['pending'] = $row['count'];
        } elseif ($row['status'] == 'resolved') {
            $stats['resolved'] = $row['count'];
        }
    }
}

// Calculate total
$stats['total'] = $stats['pending'] + $stats['in_progress'] + $stats['resolved'];

// Fetch complaint types data for pie chart - CORRECTED
$complaint_types = [];
$type_result = $conn->query("
    SELECT ct.type_name, COUNT(c.id) as count 
    FROM complaints c 
    LEFT JOIN complaint_types ct ON c.type_id = ct.id 
    GROUP BY ct.type_name 
    ORDER BY count DESC
");
if ($type_result) {
    while ($row = $type_result->fetch_assoc()) {
        $complaint_types[] = [
            'type' => $row['type_name'],
            'count' => $row['count']
        ];
    }
}

// Fetch resolution status data for pie chart
$resolution_data = [
    ['status' => 'समाधान भएको', 'count' => $stats['resolved']],
    ['status' => 'समाधान नभएको', 'count' => $stats['pending'] + $stats['in_progress']]
];

// SEO Variables
$site_url = "https://gunaso.besisaharmun.gov.np";
$page_title = "गुनासो व्यवस्थापन प्रणाली - बेसीशहर नगरपालिका | Complaint Management System";
$page_description = "बेसीशहर नगरपालिकाको आधिकारिक गुनासो व्यवस्थापन प्रणाली। नागरिक सेवा, गुनासो दर्ता, र समस्या समाधान गर्नुहोस्। Besisahar Municipality Official Complaint Management System - Lamjung, Nepal.";
$page_keywords = "बेसीशहर नगरपालिका, गुनासो व्यवस्थापन, Besisahar Municipality, complaint system, नागरिक सेवा, लमजुङ, Nepal municipality, नगरपालिका सेवा, online complaint, गुनासो दर्ता";
$og_image = $site_url . "/assets/logo.png";
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Primary Meta Tags -->
    <title><?php echo $page_title; ?></title>
    <meta name="title" content="<?php echo $page_title; ?>">
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="<?php echo $page_keywords; ?>">
    <meta name="author" content="बेसीशहर नगरपालिका">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="language" content="Nepali">
    <meta name="revisit-after" content="7 days">
    <meta name="rating" content="General">
    <meta name="geo.region" content="NP-P3">
    <meta name="geo.placename" content="Besisahar, Lamjung">
    <meta name="geo.position" content="28.2266;84.4236">
    <meta name="ICBM" content="28.2266, 84.4236">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $site_url; ?>/">
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $page_description; ?>">
    <meta property="og:image" content="<?php echo $og_image; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="बेसीशहर नगरपालिका लोगो">
    <meta property="og:locale" content="ne_NP">
    <meta property="og:locale:alternate" content="en_US">
    <meta property="og:site_name" content="बेसीशहर नगरपालिका">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo $site_url; ?>/">
    <meta name="twitter:title" content="<?php echo $page_title; ?>">
    <meta name="twitter:description" content="<?php echo $page_description; ?>">
    <meta name="twitter:image" content="<?php echo $og_image; ?>">
    <meta name="twitter:image:alt" content="बेसीशहर नगरपालिका लोगो">
    
    <!-- Favicon & Icons -->
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="assets/favicon.png">
    <link rel="shortcut icon" href="assets/favicon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="बेसीशहर गुनासो">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo $site_url; ?>/">
    
    <!-- Alternate Languages -->
    <link rel="alternate" hreflang="ne" href="<?php echo $site_url; ?>/">
    <link rel="alternate" hreflang="en" href="<?php echo $site_url; ?>/en/">
    <link rel="alternate" hreflang="x-default" href="<?php echo $site_url; ?>/">
    
    <!-- Preconnect to external resources for performance -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css?v=<?php echo ASSET_VERSION; ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="assets/css/mobile-nav.css?v=<?php echo ASSET_VERSION; ?>">
    
    <!-- Chart.js - Deferred loading for better performance -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    
    <!-- Structured Data (JSON-LD) for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "GovernmentOrganization",
        "name": "बेसीशहर नगरपालिका",
        "alternateName": "Besisahar Municipality",
        "url": "<?php echo $site_url; ?>/",
        "logo": "<?php echo $og_image; ?>",
        "image": "<?php echo $og_image; ?>",
        "description": "बेसीशहर नगरपालिकाको आधिकारिक गुनासो व्यवस्थापन प्रणाली - नागरिक सेवा र समस्या समाधान",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "बेसीशहर",
            "addressLocality": "बेसीशहर",
            "addressRegion": "लमजुङ",
            "addressCountry": "NP"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": "28.2266",
            "longitude": "84.4236"
        },
        "areaServed": {
            "@type": "AdministrativeArea",
            "name": "लमजुङ जिल्ला"
        },
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+977-66-520150",
            "email": "besishaharmunicipality@gmail.com",
            "contactType": "Customer Service",
            "availableLanguage": ["Nepali", "English"],
            "hoursAvailable": {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday"],
                "opens": "10:00",
                "closes": "17:00"
            }
        },
        "sameAs": [
            "https://www.facebook.com/besisaharmun",
            "https://twitter.com/besisaharmun"
        ]
    }
    </script>
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "गुनासो व्यवस्थापन प्रणाली - बेसीशहर नगरपालिका",
        "alternateName": "Besisahar Complaint Management System",
        "url": "<?php echo $site_url; ?>/",
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "<?php echo $site_url; ?>/track.php?tracking_number={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        },
        "inLanguage": "ne"
    }
    </script>
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "गुनासो व्यवस्थापन प्रणाली",
        "alternateName": "Complaint Management System",
        "url": "<?php echo $site_url; ?>/",
        "applicationCategory": "GovernmentApplication",
        "operatingSystem": "Web Browser",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "NPR"
        },
        "featureList": "गुनासो दर्ता, गुनासो ट्र्याकिङ, समस्या समाधान, नागरिक सेवा, Online Complaint Registration, Complaint Tracking",
        "screenshot": "<?php echo $site_url; ?>/assets/mockup.png",
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.5",
            "ratingCount": "<?php echo $stats['total']; ?>"
        }
    }
    </script>
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [{
            "@type": "ListItem",
            "position": 1,
            "name": "गृहपृष्ठ",
            "item": "<?php echo $site_url; ?>/"
        }]
    }
    </script>
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Service",
        "serviceType": "Complaint Management",
        "provider": {
            "@type": "GovernmentOrganization",
            "name": "बेसीशहर नगरपालिका"
        },
        "areaServed": {
            "@type": "City",
            "name": "बेसीशहर"
        },
        "availableChannel": {
            "@type": "ServiceChannel",
            "serviceUrl": "<?php echo $site_url; ?>/submit.php",
            "servicePhone": "+977-66-520150",
            "serviceType": "Online Complaint Registration"
        }
    }
    </script>
</head>
<body>
    <!-- Header -->
    <div class="header-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <div class="header-logo">
                        <img src="assets/logo.png" alt="Logo" onerror="this.style.display='none'">
                        <div class="header-title">
                            <h1>बेसीशहर नगरपालिका</h1>
                            <p>गुनासो व्यवस्थापन प्रणाली</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4 text-md-end">
                    <div class="contact-info">
                        <div><i class="bi bi-telephone-fill"></i> ०६६-५२०१५०</div>
                        <div><i class="bi bi-envelope-fill"></i> besishaharmunicipality@gmail.com</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><i class="bi bi-house-door"></i> गृहपृष्ठ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="submit.php"><i class="bi bi-file-earmark-text"></i> गुनासो दर्ता गर्नुहोस्</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="track.php"><i class="bi bi-search"></i> गुनासो ट्र्याक गर्नुहोस्</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq.php"><i class="bi bi-question-circle"></i> प्रश्नहरू (FAQ)</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link admin-login" href="admin/login.php"><i class="bi bi-box-arrow-in-right"></i> प्रवेश (Admin)</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav">
        <div class="nav-container">
            <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-house-door-fill"></i>
                <span>गृहपृष्ठ</span>
            </a>
            <a href="submit.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'submit.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-plus-fill"></i>
                <span>दर्ता</span>
            </a>
            <a href="track.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'track.php' ? 'active' : ''; ?>">
                <i class="bi bi-search"></i>
                <span>ट्र्याक</span>
            </a>
            <a href="faq.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'faq.php' ? 'active' : ''; ?>">
                <i class="bi bi-question-circle-fill"></i>
                <span>प्रश्नहरू</span>
            </a>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-7">
                    <h2>नमस्कार, बेसीशहर नगरपालिकाको गुनासो व्यवस्थापन प्रणालीमा यहाँहरुलाई स्वागत छ ।</h2>
                    <p class="lead" style="text-align: justify;">
                  <strong>हामीलाई सेवा प्रदान गर्ने अवसर दिनुभएकोमा  खुसी व्यक्त गर्दै यहाँको निरन्तर सल्लाह, सुझाव तथा  रचनात्मक प्रतिक्रियाको अपेक्षा गर्दछौँ। </strong> <br <br> <br>
                        बेसीशहर नगरपालिकाबाट प्रवाह हुने सेवा प्रवाहको प्रभावकारिता, विकास र यहाँहरुको सन्तुष्टिमा हामी सदैव प्रतिवद्ध छौँ। यदि तपाईंसँग नगरपालिका वा अन्तरगतका निकायबाट प्रवाह हुने सेवा, कार्यक्रम वा विकास निर्माण सम्बन्धी कुनै गुनासो, सुझाव वा प्रतिक्रिया छ भने यस गुनासो व्यवस्थापन प्रणालीमार्फत हामीलाई जानकारी गराउनुहोस्। 
तपाईंको हरेक गुनासो र सुझावलाई हामी गम्भीरतापूर्वक लिई छिटो भन्दा छिटो समाधान, एवं सुधार गर्ने प्रयास गर्ने छौं ।<br><br>
                        <strong>धन्यवाद,<br>
                        बेसीशहर नगरपालिका</strong>
                    </p>
                    <div class="mt-4 btn-group-responsive">
                        <a href="submit.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-circle"></i> गुनासो दर्ता गर्नुहोस्
                        </a>
                        <a href="track.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-search"></i> गुनासो ट्र्याक गर्नुहोस्
                        </a>
                    </div>
                </div>
                <div class="col-12 col-md-5 text-center">
                    <img src="assets/mockup.png" alt="गुनासो फर्म" class="img-fluid hero-mockup">
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="container mb-5">
        <h3 class="text-center mb-4">गुनासो तथ्याङ्क</h3>
        <div class="row g-3 g-md-4">
            <div class="col-6 col-md-3">
                <div class="stat-card total">
                    <i class="bi bi-file-earmark-text"></i>
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>कुल प्राप्त गुनासो</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card pending">
                    <i class="bi bi-hourglass-split"></i>
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>विचाराधीन</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card progress">
                    <i class="bi bi-arrow-repeat"></i>
                    <h3><?php echo $stats['in_progress']; ?></h3>
                    <p>प्रकृयामा</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card resolved">
                    <i class="bi bi-check-circle"></i>
                    <h3><?php echo $stats['resolved']; ?></h3>
                    <p>समाधान भएको</p>
                </div>
            </div>
        </div>

        <!-- Pie Charts Section -->
        <div class="row mt-4">
            <div class="col-12 col-md-6">
                <div class="chart-container">
                    <h5 class="chart-title">गुनासोको प्रकार अनुसार वितरण</h5>
                    <div class="chart-wrapper">
                        <canvas id="complaintTypeChart" class="chart-canvas"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="chart-container">
                    <h5 class="chart-title">समाधान स्थिति</h5>
                    <div class="chart-wrapper">
                        <canvas id="resolutionChart" class="chart-canvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Cards -->
    <div class="container mb-5">
        <h3 class="text-center mb-4">कसरी प्रयोग गर्ने?</h3>
        <div class="row g-3 g-md-4">
            <div class="col-12 col-md-4">
                <div class="action-card text-center">
                    <i class="bi bi-1-circle-fill"></i>
                    <h5>गुनासो दर्ता गर्नुहोस्</h5>
                    <p>तपाईंको गुनासो विवरण र सम्बन्धित कागजात सहित दर्ता गर्नुहोस्। दर्ता पछि तपाईंलाई ट्र्याकिङ नम्बर प्रदान गरिनेछ।</p>
                    <a href="submit.php" class="btn btn-primary">दर्ता गर्नुहोस्</a>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="action-card text-center">
                    <i class="bi bi-2-circle-fill"></i>
                    <h5>ट्र्याकिङ नम्बर सुरक्षित राख्नुहोस्</h5>
                    <p>तपाईंलाई प्रदान गरिएको ट्र्याकिङ नम्बर सुरक्षित राख्नुहोस्। यो नम्बर प्रयोग गरी तपाईं आफ्नो गुनासोको स्थिति हेर्न सक्नुहुन्छ।</p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="action-card text-center">
                    <i class="bi bi-3-circle-fill"></i>
                    <h5>स्थिति जाँच गर्नुहोस्</h5>
                    <p>आफ्नो ट्र्याकिङ नम्बर प्रयोग गरी कुनै पनि समय गुनासोको स्थिति र प्रगति अनलाइन जाँच गर्नुहोस्।</p>
                    <a href="track.php" class="btn btn-primary">ट्र्याक गर्नुहोस्</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Information Section -->
    <div class="container mb-5">
        <div class="row g-3 g-md-4">
            <div class="col-12 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <i class="bi bi-info-circle"></i> महत्त्वपूर्ण जानकारी
                        </h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success"></i> सबै गुनासो गोप्य राखिनेछ</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success"></i> गुनासो दर्ता गर्न कुनै शुल्क लाग्दैन</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success"></i> २४ घण्टा भित्र प्रतिक्रिया दिइनेछ</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success"></i> कार्य दिनमा तत्काल कारबाही गरिनेछ</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success"></i> स्थिति परिवर्तनमा इमेल सूचना पठाइनेछ</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <i class="bi bi-telephone"></i> सम्पर्क जानकारी
                        </h5>
                        <div class="mb-3">
                            <strong>कार्यालय:</strong> बेसीशहर नगरपालिका<br>
                            <strong>ठेगाना:</strong> बेसीशहर, लमजुङ<br>
                            <strong>फोन:</strong> ०६६-५२०१५०<br>
                            <strong>इमेल:</strong> besishaharmunicipality@gmail.com<br>
                            <strong>कार्यालय समय:</strong> आइतबार - बिहिबार (१०:०० - ५:००)</br>
                            शुक्रबार (१०ः०० - ३ः००)
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>नोट:</strong> जरुरी अवस्थामा कृपया कार्यालयमा सीधा सम्पर्क गर्नुहोस्।
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-content">
            <div class="row">
                <!-- About Section -->
                <div class="col-12 col-md-4 footer-section">
                    <div class="footer-brand">
                        <h4><i class="bi bi-building"></i> बेसीशहर नगरपालिका</h4>
                        <p>नागरिक केन्द्रित, पारदर्शी र जवाफदेही सेवा प्रदान गर्दै समृद्ध बेसीशहरको निर्माण।</p>
                    </div>
                    <div class="social-links">
                        <a href="#" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" title="Twitter"><i class="bi bi-twitter"></i></a>
                        <a href="#" title="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" title="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-12 col-md-4 footer-section">
                    <h5>द्रुत लिङ्कहरू</h5>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="bi bi-chevron-right"></i> गृहपृष्ठ</a></li>
                        <li><a href="submit.php"><i class="bi bi-chevron-right"></i> गुनासो दर्ता गर्नुहोस्</a></li>
                        <li><a href="track.php"><i class="bi bi-chevron-right"></i> गुनासो ट्र्याक गर्नुहोस्</a></li>
                        <li><a href="faq.php"><i class="bi bi-chevron-right"></i> प्रश्नहरू (FAQ)</a></li>
                        <li><a href="admin/login.php"><i class="bi bi-chevron-right"></i> प्रशासक प्रवेश</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div class="col-12 col-md-4 footer-section">
                    <h5>सम्पर्क जानकारी</h5>
                    <ul class="footer-contact">
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>बेसीशहर, लमजुङ, नेपाल</span>
                        </li>
                        <li>
                            <i class="bi bi-telephone-fill"></i>
                            <a href="tel:066520150">०६६-५२०१५०</a>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill"></i>
                            <a href="mailto:besishaharmunicipality@gmail.com">besishaharmunicipality@gmail.com</a>
                        </li>
                        <li>
                            <i class="bi bi-clock-fill"></i>
                            <span>आइतबार - बिहिबार<br>१०:०० - ५:०० बजे</br>
                            शुक्रबार १०ः०० - ३ः०० बजे</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-12 col-md-6 text-center text-md-start mb-2 mb-md-0">
                        <p>&copy; <?php echo date('Y'); ?> बेसीशहर नगरपालिका । सर्वाधिकार सुरक्षित</p>
                    </div>
                    <div class="col-12 col-md-6 text-center text-md-end">
                        <p class="developer-credit">
                            Designed & Developed by 
                            <a href="https://sanjeevaniitsolution.com.np" target="_blank">
                                <i class="bi bi-code-slash"></i> Sanjeevani IT Solution
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Pass PHP data to JavaScript -->
    <script>
        // Chart data from PHP
        window.complaintTypesLabels = [<?php 
            if(!empty($complaint_types)) {
                $labels = [];
                foreach($complaint_types as $type) {
                    $labels[] = "'" . addslashes($type['type']) . "'";
                }
                echo implode(', ', $labels);
            } else {
                echo "'कुनै डाटा उपलब्ध छैन'";
            }
        ?>];
        
        window.complaintTypesData = [<?php 
            if(!empty($complaint_types)) {
                $data = [];
                foreach($complaint_types as $type) {
                    $data[] = $type['count'];
                }
                echo implode(', ', $data);
            } else {
                echo "1";
            }
        ?>];
        
        window.resolutionData = [<?php echo $resolution_data[0]['count']; ?>, <?php echo $resolution_data[1]['count']; ?>];
    </script>
    
    <!-- Load external JavaScript files -->
    <script src="assets/js/main.js?v=<?php echo ASSET_VERSION; ?>"></script>
    <script src="assets/js/charts.js?v=<?php echo ASSET_VERSION; ?>"></script>
    <script src="assets/js/pwa.js?v=<?php echo ASSET_VERSION; ?>"></script>
    
    <!-- Initialize charts after page load -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initializeCharts === 'function') {
                initializeCharts();
            }
        });
    </script>
    
    
    <script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').then(reg => {
    reg.onupdatefound = () => {
      const newWorker = reg.installing;
      newWorker.onstatechange = () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          newWorker.postMessage({ type: 'SKIP_WAITING' });
          window.location.reload(); // auto reload with latest files
        }
      };
    };
  });
}
</script>

    
    
</body>
</html>