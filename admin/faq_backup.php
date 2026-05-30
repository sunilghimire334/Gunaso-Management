<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <title>बारम्बार सोधिने प्रश्नहरू - बेसीशहर नगरपालिका</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        
        .page-header {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            padding: 3rem 0;
            margin-bottom: 3rem;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            font-size: 1.1rem;
            color: #64748b;
            margin: 0;
        }
        
        .faq-section {
            margin-bottom: 3rem;
        }
        
        .faq-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            transition: background 0.3s ease;
        }
        
        .faq-question:hover {
            background: #f9fafb;
        }
        
        .faq-question h5 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.05rem;
            flex: 1;
            padding-right: 1rem;
        }
        
        .faq-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }
        
        .faq-card.active .faq-icon {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            background: #f9fafb;
        }
        
        .faq-card.active .faq-answer {
            max-height: 1000px;
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .faq-answer p {
            margin: 0;
            color: #475569;
            line-height: 1.7;
            font-size: 0.95rem;
        }
        
        .faq-category {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        
        .help-box {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-top: 3rem;
            border: 2px solid var(--primary-color);
        }
        
        .help-box h4 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .help-box p {
            color: #475569;
            margin-bottom: 1.5rem;
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
        
        .contact-info i {
            color: var(--primary-color);
            margin-right: 0.5rem;
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
            
            .page-header {
                padding: 2rem 0;
                margin-bottom: 2rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .page-header p {
                font-size: 0.95rem;
            }
            
            .faq-question {
                padding: 1rem;
            }
            
            .faq-question h5 {
                font-size: 0.95rem;
            }
            
            .faq-icon {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .faq-card.active .faq-answer {
                padding: 1rem;
            }
            
            .faq-answer p {
                font-size: 0.9rem;
            }
            
            .faq-category {
                font-size: 1rem;
                padding: 0.6rem 1.25rem;
            }
            
            .help-box {
                padding: 1.5rem;
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
            
            .page-header h2 {
                font-size: 1.3rem;
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
                        <a class="nav-link" href="index.php"><i class="bi bi-house-door"></i> गृहपृष्ठ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="submit.php"><i class="bi bi-file-earmark-text"></i> गुनासो दर्ता गर्नुहोस्</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="track.php"><i class="bi bi-search"></i> गुनासो ट्र्याक गर्नुहोस्</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faq.php"><i class="bi bi-question-circle"></i> प्रश्नहरू (FAQ)</a>
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

    <!-- Page Header -->
    <div class="page-header">
        <div class="container text-center">
            <h2><i class="bi bi-question-circle"></i> बारम्बार सोधिने प्रश्नहरू</h2>
            <p>तपाईंको प्रश्नको उत्तर यहाँ खोज्नुहोस्</p>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="container faq-section">
        <!-- General Questions -->
        <div class="faq-category">
            <i class="bi bi-info-circle"></i> सामान्य प्रश्नहरू
        </div>
        
        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-1-circle-fill me-2"></i> गुनासो व्यवस्थापन प्रणाली के हो?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>गुनासो व्यवस्थापन प्रणाली एक अनलाइन प्लेटफर्म हो जसले नागरिकहरूलाई नगरपालिकासँग सम्बन्धित कुनै पनि समस्या वा गुनासो दर्ता गर्न र ट्र्याक गर्न सजिलो बनाउँछ। यस प्रणालीमार्फत तपाईं आफ्नो गुनासो अनलाइन दर्ता गर्न सक्नुहुन्छ र त्यसको प्रगति नियमित रूपमा हेर्न सक्नुहुन्छ।</p>
            </div>
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-2-circle-fill me-2"></i> गुनासो दर्ता गर्न कुनै शुल्क लाग्छ?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>होइन, गुनासो दर्ता गर्न कुनै पनि प्रकारको शुल्क लाग्दैन। यो सेवा पूर्ण रूपमा निःशुल्क छ र सबै नागरिकहरूको लागि उपलब्ध छ।</p>
            </div>
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-3-circle-fill me-2"></i> के मेरो गुनासो गोप्य राखिन्छ?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>हो, तपाईंको सबै जानकारी र गुनासो पूर्ण रूपमा गोप्य राखिन्छ। तपाईंको व्यक्तिगत विवरण मात्र सम्बन्धित अधिकारीहरूले मात्र देख्न सक्नेछन् र यसलाई कुनै पनि तेस्रो पक्षसँग साझा गरिने छैन।</p>
            </div>
        </div>

        <!-- Registration Process -->
        <div class="faq-category mt-5">
            <i class="bi bi-file-earmark-text"></i> दर्ता प्रक्रिया
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-4-circle-fill me-2"></i> गुनासो दर्ता गर्न के के आवश्यक पर्छ?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>गुनासो दर्ता गर्न तपाईंलाई निम्न जानकारीहरू चाहिन्छ: तपाईंको पूरा नाम, सम्पर्क नम्बर, इमेल ठेगाना, गुनासोको प्रकार, विस्तृत विवरण र यदि सम्भव भए सम्बन्धित कागजात वा फोटोहरू। सबै जानकारी सही र पूर्ण भएमा दर्ता प्रक्रिया सजिलो हुन्छ।</p>
            </div>
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-5-circle-fill me-2"></i> के म एकै पटक धेरै गुनासो दर्ता गर्न सक्छु?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>हो, तपाईं एकै पटक धेरै गुनासो दर्ता गर्न सक्नुहुन्छ। प्रत्येक गुनासोको लागि छुट्टै ट्र्याकिङ नम्बर प्रदान गरिनेछ। तर, प्रत्येक गुनासो छुट्टाछुट्टै र विस्तृत रूपमा दर्ता गर्नु राम्रो हुन्छ ताकि छिटो समाधान हुन सकोस्।</p>
            </div>
        </div>

        <!-- Tracking & Status -->
        <div class="faq-category mt-5">
            <i class="bi bi-search"></i> ट्र्याकिङ र स्थिति
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-6-circle-fill me-2"></i> ट्र्याकिङ नम्बर के हो र यो कसरी प्राप्त गर्ने?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>ट्र्याकिङ नम्बर एक विशेष पहिचान नम्बर हो जुन तपाईंको गुनासो दर्ता भएपछि स्वचालित रूपमा उत्पन्न हुन्छ। यो नम्बर तपाईंको स्क्रिनमा देखिनेछ र तपाईंको इमेलमा पनि पठाइनेछ। यो नम्बर सुरक्षित राख्नुहोस् किनभने यही प्रयोग गरी तपाईं आफ्नो गुनासोको स्थिति हेर्न सक्नुहुन्छ।</p>
            </div>
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-7-circle-fill me-2"></i> मेरो गुनासो समाधान हुन कति समय लाग्छ?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>गुनासो समाधान हुन लाग्ने समय गुनासोको प्रकार र जटिलतामा निर्भर गर्दछ। साधारण गुनासोहरू ७-१५ दिन भित्र समाधान हुन्छन् भने जटिल समस्याहरूमा बढी समय लाग्न सक्छ। हामी २४ घण्टा भित्र प्रतिक्रिया दिने र नियमित रूपमा तपाईंलाई अपडेट गरिरहने छौं।</p>
            </div>
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-8-circle-fill me-2"></i> यदि म मेरो ट्र्याकिङ नम्बर बिर्सें भने के गर्ने?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>यदि तपाईंले ट्र्याकिङ नम्बर बिर्सनुभयो भने, तपाईंले दर्ता गर्दा प्रयोग गर्नुभएको इमेलमा ट्र्याकिङ नम्बर पठाइएको थियो। त्यहाँ हेर्नुहोस्। यदि इमेल पनि फेला परेन भने नगरपालिकाको हेल्पलाइन नम्बर ०६६-५२०१५० मा सम्पर्क गर्नुहोस्। तपाईंको नाम र सम्पर्क नम्बर दिएर ट्र्याकिङ नम्बर पुनः प्राप्त गर्न सक्नुहुन्छ।</p>
            </div>
        </div>

        <!-- Contact & Support -->
        <div class="faq-category mt-5">
            <i class="bi bi-telephone"></i> सम्पर्क र सहयोग
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-9-circle-fill me-2"></i> यदि मलाई थप सहयोग चाहियो भने कसरी सम्पर्क गर्ने?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>थप सहयोगको लागि तपाईं निम्न माध्यमबाट सम्पर्क गर्न सक्नुहुन्छ: <br>
                <strong>फोन:</strong> ०६६-५२०१५० (कार्यालय समयमा)<br>
                <strong>इमेल:</strong> besishaharmunicipality@gmail.com<br>
                <strong>कार्यालय:</strong> बेसीशहर, लमजुङ<br>
                <strong>समय:</strong> आइतबार - शुक्रबार (१०:०० - १७:००)</p>
            </div>
        </div>

        <div class="faq-card">
            <div class="faq-question" onclick="toggleFAQ(this)">
                <h5><i class="bi bi-10-circle-fill me-2"></i> के म बिदाको दिनमा पनि गुनासो दर्ता गर्न सक्छु?</h5>
                <div class="faq-icon"><i class="bi bi-chevron-down"></i></div>
            </div>
            <div class="faq-answer">
                <p>हो, तपाईं २४/७ अनलाइन मार्फत गुनासो दर्ता गर्न सक्नुहुन्छ। तर, गुनासोको प्रशोधन र प्रतिक्रिया कार्यालय समयमा मात्र हुनेछ। शनिबार र सार्वजनिक बिदाको दिन दर्ता भएका गुनासोहरू अर्को कार्य दिनमा प्रशोधन सुरु हुनेछ।</p>
            </div>
        </div>

        <!-- Help Box -->
        <div class="help-box">
            <h4><i class="bi bi-question-circle-fill"></i> तपाईंको प्रश्नको उत्तर भेटिएन?</h4>
            <p>यदि तपाईंको प्रश्नको उत्तर माथि उल्लेख गरिएको छैन भने, कृपया हामीलाई सम्पर्क गर्नुहोस्। हामी तपाईंलाई सहयोग गर्न सधैं तयार छौं।</p>
            <a href="submit.php" class="btn btn-primary me-2"><i class="bi bi-file-earmark-text"></i> गुनासो दर्ता गर्नुहोस्</a>
            <a href="track.php" class="btn btn-outline-primary"><i class="bi bi-search"></i> गुनासो ट्र्याक गर्नुहोस्</a>
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
        function toggleFAQ(element) {
            const faqCard = element.parentElement;
            const allCards = document.querySelectorAll('.faq-card');
            
            // Close all other cards
            allCards.forEach(card => {
                if (card !== faqCard && card.classList.contains('active')) {
                    card.classList.remove('active');
                }
            });
            
            // Toggle current card
            faqCard.classList.toggle('active');
        }

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>