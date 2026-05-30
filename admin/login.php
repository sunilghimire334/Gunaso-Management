<?php
require_once '../config.php';

if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, name, password, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] === 'inactive') {
                $error = 'तपाईंको खाता निष्क्रिय छ। कृपया प्रशासकसँग सम्पर्क गर्नुहोस्।';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                redirect('admin/dashboard.php');
            } else {
                $error = 'गलत प्रयोगकर्ता नाम वा पासवर्ड।';
            }
        } else {
            $error = 'गलत प्रयोगकर्ता नाम वा पासवर्ड।';
        }
    } else {
        $error = 'कृपया सबै फिल्ड भर्नुहोस्।';
    }
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>प्रवेश - गुनासो व्यवस्थापन प्रणाली</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #066db3;
            --dark-green: #1d5a3a;
            --light-bg: #f0f5ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .auth-container {
            width: 100%;
            max-width: 1100px;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .auth-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.25);
        }

        .auth-form-section {
            padding: 2.5rem 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
            min-height: auto;
        }

        .auth-header h1 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 0;
            font-weight: 500;
        }

        .form-section {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            padding: 0.9rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-control::placeholder {
            color: #d1d5db;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .form-options a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .form-options a:hover {
            text-decoration: underline;
        }

        .checkbox-custom {
            display: flex;
            align-items: center;
        }

        .checkbox-custom input {
            margin-right: 0.5rem;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .checkbox-custom label {
            margin: 0;
            text-transform: none;
            font-weight: 500;
            letter-spacing: normal;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-signin {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            padding: 1rem;
            font-weight: 700;
            width: 100%;
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(30, 64, 175, 0.2);
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(30, 64, 175, 0.3);
        }

        .alert-danger {
            border: none;
            border-radius: 10px;
            padding: 0.9rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #7f1d1d;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
            animation: shake 0.3s ease-in-out;
            font-size: 0.9rem;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .footer-link {
            margin-top: 2rem;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
        }

        .footer-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .auth-feature-section {
            background: linear-gradient(135deg, var(--dark-green) 0%, #2d6b4a 100%);
            padding: 2.5rem 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: auto;
        }

        .auth-feature-section::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            top: -100px;
            right: -100px;
            z-index: 1;
        }

        .auth-feature-section::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
            z-index: 1;
        }

        .feature-header {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .feature-support {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.6rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .feature-content {
            position: relative;
            z-index: 2;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .features-list {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .feature-item {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .feature-item-icon {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.3rem;
        }

        .feature-item-content h4 {
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
            margin-top: 0;
        }

        .feature-item-content p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.4;
        }

        @media (max-width: 1024px) {
            .auth-form-section {
                padding: 3rem 2.5rem;
            }
            .auth-feature-section {
                padding: 3rem 2.5rem;
            }
            .auth-header h1 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 768px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
            }
            .auth-form-section {
                padding: 2.5rem 2rem;
            }
            .auth-feature-section {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                max-width: 100%;
            }
            .auth-form-section {
                padding: 2rem 1.5rem;
            }
            .auth-header h1 {
                font-size: 1.5rem;
            }
            .auth-header p {
                font-size: 0.9rem;
            }
            .form-control {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-wrapper">
            <!-- Left Side - Login Form -->
            <div class="auth-form-section">
                <div>
                    <div class="auth-header">
                        <center><h1>बेसीशहर नगरपालिका</h1>
                        <p>गुनासो व्यवस्थापन प्रणाली</p></center>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>त्रुटि:</strong> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <div class="form-section">
                            <div class="form-group">
                                <label for="username">प्रयोगकर्ता नाम</label>
                                <input 
                                    type="text" 
                                    name="username" 
                                    id="username" 
                                    class="form-control" 
                                    placeholder="तपाईंको प्रयोगकर्ता नाम" 
                                    required 
                                    autofocus
                                >
                            </div>

                            <div class="form-group">
                                <label for="password">पासवर्ड</label>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password" 
                                    class="form-control" 
                                    placeholder="तपाईंको पासवर्ड" 
                                    required
                                >
                            </div>

                            <div class="form-options">
                                <div class="checkbox-custom">
                                    <input type="checkbox" id="remember" name="remember">
                                    <label for="remember">मलाई याद राख्नुहोस्</label>
                                </div>
                                <a href="#">पासवर्ड बिर्सनुभयो?</a>
                            </div>

                            <button type="submit" class="btn-signin">
                                <i class="bi bi-box-arrow-in-right"></i> प्रवेश गर्नुहोस्
                            </button>
                        </div>
                    </form>
                </div>

                <div class="footer-link">
                    <a href="../index.php">
                        <i class="bi bi-house"></i> गृहपृष्ठमा फर्कनुहोस्
                    </a>
                </div>
            </div>

            <!-- Right Side - Feature Section -->
            <div class="auth-feature-section">
                <div class="feature-header">
                    <div class="feature-title">गुनासो व्यवस्थापन</div>
                    <div class="feature-support">
                        <i class="bi bi-headset"></i>
                        सहायता
                    </div>
                </div>

                <div class="feature-content">
    <div class="features-list">
        <div class="feature-item">
            <div class="feature-item-icon">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div class="feature-item-content">
                <h4>छिटो र प्रभावकारी सेवा</h4>
                <p>तपाईंको गुनासो दर्ता भएपछि तुरुन्तै सम्बन्धित शाखामा पठाइन्छ र समाधानको प्रगति रियल-टाइममा हेर्न सक्नुहुन्छ।</p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-item-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="feature-item-content">
                <h4>विश्वसनीय र गोपनीय</h4>
                <p>तपाईंको व्यक्तिगत विवरण पूर्ण रूपमा सुरक्षित राखिन्छ। हामीले उच्चस्तरीय डेटा सुरक्षा र गोपनीयता मापदण्डहरू अपनाएका छौँ।</p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-item-icon">
                <i class="bi bi-eye"></i>
            </div>
            <div class="feature-item-content">
                <h4>पारदर्शी गुनासो प्रक्रिया</h4>
                <p>गुनासोको प्रत्येक चरण पारदर्शी हुन्छ। तपाईंले आफ्नो गुनासोको स्थिति र अपडेटहरू कुनै पनि बेला ट्र्याक गर्न सक्नुहुन्छ।</p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-item-icon">
                <i class="bi bi-telephone-fill"></i>
            </div>
            <div class="feature-item-content">
                <h4>२४/७ सहायता</h4>
                <p>हाम्रो सहयोगी टिम सधैं तपाईंको गुनासो समाधानका लागि तत्पर छ। कुनै समस्या परे तुरुन्त सम्पर्क गर्नुहोस्।</p>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                alert('कृपया सबै फिल्ड भर्नुहोस्।');
                return false;
            }
        });

        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });

        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>