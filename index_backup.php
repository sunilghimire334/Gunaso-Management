<?php
require_once 'config.php';

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
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>गुनासो व्यवस्थापन प्रणाली - बेसीशहर नगरपालिका</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #dc2626;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
        }
        
        .header-top {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            padding: 1rem 0;
        }
        
        .header-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0rem;
        }
        
        .header-logo img {
            width: 60px;
            height: 50px;
            background: white;
            border-radius: 50%;
            padding: 5px;
            flex-shrink: 0;
        }
        
        .header-title h1 {
            font-size: 1.3rem;
            margin: 0;
            font-weight: 700;
        }
        
        .header-title p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .contact-info {
            font-size: 0.85rem;
        }
        
        .contact-info div {
            margin-bottom: 0.25rem;
            word-break: break-word;
        }
        
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.75rem 0;
        }
        
        .navbar-nav .nav-link {
            padding: 0.75rem 1.25rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }
        
        .navbar-nav .nav-link:hover {
            background-color: #f3f4f6;
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
        }
        
        .navbar-nav .nav-link.active:hover {
            background: linear-gradient(135deg, #1e3a8a, var(--primary-color));
            color: white;
        }
        
        .navbar-nav .nav-link i {
            font-size: 1.1rem;
        }
        
        .navbar-nav .nav-link.admin-login {
            border: 1px solid var(--primary-color);
            font-weight: 600;
            background: transparent;
        }
        
        .navbar-nav .nav-link.admin-login:hover {
            background: #f3f4f6;
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.2);
            transform: translateY(-2px);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .hero-section h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.75rem;
        }
        
        .hero-section .lead {
            font-size: 1rem;
        }
        
        .hero-section .hero-mockup {
            max-width: 220px;
            height: auto;
            animation: float 3s ease-in-out infinite;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        .hero-section .bi-people {
            font-size: 8rem;
            color: var(--primary-color);
            opacity: 0.2;
        }
        
        .stat-card {
            border-radius: 10px;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            height: 100%;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .stat-card p {
            margin: 0;
            font-size: 0.95rem;
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .stat-card.pending {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }
        
        .stat-card.progress {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
        }
        
        .stat-card.resolved {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            height: 350px;
            display: flex;
            flex-direction: column;
        }
        
        .chart-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .chart-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 0;
        }
        
        .chart-canvas {
            max-width: 250px;
            max-height: 250px;
            margin: 0 auto;
        }
        
        .action-card {
            border-radius: 15px;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s;
            border: 2px solid #e5e7eb;
            margin-bottom: 1rem;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .action-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .action-card h5 {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }
        
        .action-card p {
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .btn-primary:hover {
            background: #1e3a8a;
        }
        
        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }
        
        .footer {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 3rem 0 1.5rem;
            margin-top: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #3b82f6, #06b6d4);
        }
        
        .footer-content {
            position: relative;
            z-index: 1;
        }
        
        .footer-brand {
            margin-bottom: 1.5rem;
        }
        
        .footer-brand h4 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .footer-brand p {
            font-size: 0.9rem;
            color: #e2e8f0;
            margin: 0;
            line-height: 1.6;
        }
        
        .footer-section {
            margin-bottom: 2rem;
        }
        
        .footer-section h5 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: white;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .footer-section h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), #3b82f6);
            border-radius: 2px;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.7rem;
        }
        
        .footer-links a {
            color: #e2e8f0;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer-links a:hover {
            color: #3b82f6;
            transform: translateX(5px);
        }
        
        .footer-links a i {
            font-size: 0.8rem;
        }
        
        .footer-contact {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-contact li {
            margin-bottom: 0.8rem;
            color: #e2e8f0;
            font-size: 0.9rem;
            display: flex;
            align-items: start;
            gap: 0.7rem;
        }
        
        .footer-contact li i {
            color: #3b82f6;
            font-size: 1.1rem;
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        
        .footer-contact a {
            color: #e2e8f0;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-contact a:hover {
            color: #3b82f6;
        }
        
        .social-links {
            display: flex;
            gap: 0.8rem;
            margin-top: 1rem;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            border: 2px solid rgba(59, 130, 246, 0.2);
        }
        
        .social-links a:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.5rem;
            margin-top: 2rem;
        }
        
        .footer-bottom p {
            margin: 0;
            font-size: 0.85rem;
            color: #cbd5e1;
        }
        
        .footer-bottom a {
            color: #3b82f6;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-bottom a:hover {
            color: #60a5fa;
        }
        
        .developer-credit {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        h3 {
            font-size: 1.5rem;
        }
        
        .card-title {
            font-size: 1.1rem;
        }
        
        .list-unstyled li {
            font-size: 0.9rem;
        }
        
        /* Mobile optimizations */
        @media (max-width: 767.98px) {
            .header-top {
                padding: 0.75rem 0;
            }
            
            .header-logo {
                flex-direction: row;
                gap: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .header-logo img {
                width: 50px;
                height: 45px;
            }
            
            .header-title h1 {
                font-size: 1.1rem;
            }
            
            .header-title p {
                font-size: 0.8rem;
            }
            
            .contact-info {
                font-size: 0.75rem;
                margin-top: 0.5rem;
            }
            
            .contact-info i {
                display: none;
            }
            
            .hero-section {
                padding: 1.5rem 0;
            }
            
            .hero-section h2 {
                font-size: 1.4rem;
                text-align: center;
            }
            
            .hero-section .lead {
                font-size: 0.9rem;
                text-align: center;
            }
            
            .hero-section .mt-4 {
                text-align: center;
            }
            
            .hero-section .btn {
                display: block;
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .hero-section .btn.me-2 {
                margin-right: 0 !important;
            }
            
            .hero-section .hero-mockup {
                display: none;
            }
            
            .hero-section .bi-people {
                font-size: 5rem;
                margin-top: 1rem;
            }
            
            .stat-card {
                min-height: 160px;
                padding: 1rem 0.75rem;
            }
            
            .stat-card i {
                font-size: 1.75rem;
            }
            
            .stat-card h3 {
                font-size: 1.75rem;
            }
            
            .stat-card p {
                font-size: 0.85rem;
            }
            
            .chart-container {
                padding: 1rem;
                margin-bottom: 1.5rem;
                height: 300px;
            }
            
            .chart-title {
                font-size: 1rem;
            }
            
            .chart-canvas {
                max-width: 200px;
                max-height: 200px;
            }
            
            .action-card {
                padding: 1.25rem;
            }
            
            .action-card i {
                font-size: 2rem;
            }
            
            h3 {
                font-size: 1.3rem;
            }
            
            .navbar {
                padding: 0.5rem 0;
            }
            
            .navbar-nav .nav-link {
                font-size: 0.95rem;
                padding: 0.65rem 1rem;
                margin: 0.25rem 0;
            }
            
            .navbar-collapse {
                margin-top: 0.5rem;
            }
            
            .footer {
                text-align: center;
                margin-top: 2rem;
            }
            
            .footer .text-md-end {
                text-align: center !important;
                margin-top: 1rem;
            }
            
            .footer-section h5::after {
                left: 50%;
                transform: translateX(-50%);
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 575.98px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .header-title h1 {
                font-size: 1rem;
            }
            
            .header-title p {
                font-size: 0.75rem;
            }
            
            .hero-section h2 {
                font-size: 1.25rem;
            }
            
            .stat-card {
                min-height: 150px;
            }
            
            .btn-lg {
                padding: 0.65rem 1.25rem;
                font-size: 0.95rem;
            }
            
            .chart-container {
                padding: 0.75rem;
                height: 280px;
            }
            
            .chart-canvas {
                max-width: 180px;
                max-height: 180px;
            }
        }
        
        /* Tablet optimizations */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .header-logo img {
                width: 70px;
                height: 65px;
            }
            
            .header-title h1 {
                font-size: 1.5rem;
            }
            
            .hero-section h2 {
                font-size: 1.6rem;
            }
            
            .stat-card {
                min-height: 160px;
            }
            
            .chart-container {
                height: 320px;
            }
        }
        
        /* Large screens */
        @media (min-width: 1200px) {
            .container {
                max-width: 1140px;
            }
            
            .header-logo img {
                width: 80px;
                height: 75px;
            }
            
            .header-title h1 {
                font-size: 1.8rem;
            }
            
            .hero-section h2 {
                font-size: 2rem;
            }
            
            .stat-card {
                min-height: 180px;
            }
        }
        
        /* Ensure buttons stack nicely on all screen sizes */
        .btn-group-responsive {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        @media (max-width: 767.98px) {
            .btn-group-responsive {
                flex-direction: column;
            }
            
            .btn-group-responsive .btn {
                width: 100%;
            }
        }
    </style>
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

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-7">
                    <h2>नमस्ते मेयर</h2>
                    <!-- <h3>तपाईंको आवाज, हाम्रो जिम्मेवारी</h3>-->
                    <p class="lead" style="text-align: justify;">
                        आदरणीय नगरवासी तथा सर्वसाधारण,<br><br>
                        बेसीशहर नगरपालिकाको विकास र प्रगति तपाईंहरूको सक्रिय सहभागिता र सुझावमा आधारित छ। नगरको समग्र विकासमा प्रत्येक नागरिकको विचार र योगदान अत्यन्त महत्त्वपूर्ण रहन्छ।<br>
                        यदि तपाईंसँग नगरपालिकाका सेवा, कार्यक्रम वा विकास सम्बन्धी कुनै गुनासो, सुझाव वा समस्या छ भने यस गुनासो व्यवस्थापन प्रणालीमार्फत हामीलाई जानकारी गराउनुहोस्। तपाईंको हरेक गुनासो र सुझावलाई हामी गम्भीरतापूर्वक लिनेछौं र छिटो समाधान गर्न प्रतिबद्ध छौं।<br><br>
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
                            <strong>कार्यालय समय:</strong> आइतबार - शुक्रबार (१०:०० - १७:००)
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
                            <span>आइतबार - शुक्रबार<br>१०:०० - १७:०० बजे</span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Colors for charts
        const chartColors = [
            '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', 
            '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#ec4899',
            '#14b8a6', '#f43f5e'
        ];

        // Complaint Type Chart
        const complaintTypeCtx = document.getElementById('complaintTypeChart').getContext('2d');
        const complaintTypeChart = new Chart(complaintTypeCtx, {
            type: 'pie',
            data: {
                labels: [<?php 
                    if(!empty($complaint_types)) {
                        $labels = [];
                        foreach($complaint_types as $type) {
                            $labels[] = "'" . $type['type'] . "'";
                        }
                        echo implode(', ', $labels);
                    } else {
                        echo "'कुनै डाटा उपलब्ध छैन'";
                    }
                ?>],
                datasets: [{
                    data: [<?php 
                        if(!empty($complaint_types)) {
                            $data = [];
                            foreach($complaint_types as $type) {
                                $data[] = $type['count'];
                            }
                            echo implode(', ', $data);
                        } else {
                            echo "1";
                        }
                    ?>],
                    backgroundColor: chartColors,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: window.innerWidth < 768 ? 9 : 11
                            },
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });

        // Resolution Status Chart
        const resolutionCtx = document.getElementById('resolutionChart').getContext('2d');
        const resolutionChart = new Chart(resolutionCtx, {
            type: 'pie',
            data: {
                labels: ['समाधान भएको', 'समाधान नभएको'],
                datasets: [{
                    data: [<?php echo $resolution_data[0]['count']; ?>, <?php echo $resolution_data[1]['count']; ?>],
                    backgroundColor: ['#10b981', '#f59e0b'],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: window.innerWidth < 768 ? 9 : 11
                            },
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });

        // Update charts on window resize
        window.addEventListener('resize', function() {
            complaintTypeChart.update();
            resolutionChart.update();
        });
        
        
        // Check if Service Workers are supported
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js');
        });
    }
</script>
        
</body>
</html>