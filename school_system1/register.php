 <?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// ── Бүртгэлийн нээлт / хаалт тохиргоо ─────────────────────────────────────
$regSetting = dbOne("SELECT setting_value FROM settings WHERE setting_key='registration_open'");
$isRegOpen  = ($regSetting && $regSetting['setting_value'] === '1');

// ── Хандалтын хяналт ────────────────────────────────────────────────────────
// Admin + Director: үргэлж нэвтрэх эрхтэй
if (isLoggedIn() && in_array($_SESSION['role'] ?? '', ['admin', 'director'], true)) {
    // ✅ Зөвшөөрөгдсөн — доош үргэлжлэх
}
// Нэвтэрсэн боловч admin/director биш → dashboard руу
elseif (isLoggedIn()) {
    header('Location: /school_system1/dashboard.php');
    exit;
}
// Нэвтрээгүй + бүртгэл хаалттай → login руу
elseif (!$isRegOpen) {
    setFlash('error', '🔒 Бүртгэл одоогоор хаалттай байна. Admin эсвэл Захирлаас асуугаарай.');
    header('Location: /school_system1/index.php');
    exit;
}
// ✅ Нэвтрээгүй + бүртгэл нээлттэй → зөвшөөрөгдсөн

// Нэвтэрсэн admin/director-г dashboard руу биш — бүртгэл хийх боломжтой
// (Доорх "logged in redirect" блокийг арилгав)

$pageTitle = 'Шинээр бүртгүүлэх';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $roleId = (int)$_POST['role_id']; // 4 = Student, 5 = Parent
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Student specific
    $registerNo = trim($_POST['register_no'] ?? '');
    $gender = $_POST['gender'] ?? null;
    $birthDate = $_POST['birth_date'] ?? null;
    $classId = null; // Class will be assigned by admin
    
    // Parent specific
    $studentRegisterNo = trim($_POST['student_register_no'] ?? '');

    if (!$username || !$password || !$firstName || !$lastName || !in_array($roleId, [4, 5])) {
        setFlash('error', 'Шаардлагатай талбаруудыг бүрэн бөглөнө үү.');
    } elseif ($roleId == 5 && empty($phone)) {
        setFlash('error', 'Эцэг эхийн утасны дугаарыг заавал оруулна уу.');
    } elseif ($pwErr = validatePassword($password)) {
        setFlash('error', $pwErr);
    } elseif ($email && !isValidEmail($email)) {
        setFlash('error', 'Зөв имэйл хаяг оруулна уу.');
    } else {
        // Check if username already exists in `users` or `pending_registrations`
        $existsUser = dbOne("SELECT user_id FROM users WHERE username=?", [$username]);
        $existsPending = dbOne("SELECT id FROM pending_registrations WHERE username=?", [$username]);
        
        // Check duplicate register_no for students
        $existsRegisterNo = false;
        if ($roleId == 4 && !empty($registerNo)) {
            $existsStudent = dbOne("SELECT student_id FROM students WHERE register_no=?", [$registerNo]);
            $existsPendingReg = dbOne("SELECT id FROM pending_registrations WHERE role_id=4 AND register_no=? AND status='pending'", [$registerNo]);
            if ($existsStudent || $existsPendingReg) {
                $existsRegisterNo = true;
            }
        }
        
        if ($existsUser || $existsPending) {
            setFlash('error', 'Энэ нэвтрэх нэр (username) аль хэдийн бүртгэгдсэн байна.');
        } elseif ($existsRegisterNo) {
            setFlash('error', 'Энэ регистрийн дугаартай сурагч аль хэдийн бүртгэгдсэн эсвэл хүсэлт илгээсэн байна.');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            try {
                $sql = "INSERT INTO pending_registrations 
                        (role_id, username, password_hash, first_name, last_name, register_no, gender, birth_date, class_id, student_register_no, phone, email, address) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                dbExec($sql, [
                    $roleId, $username, $hash, $firstName, $lastName, 
                    $roleId == 4 ? $registerNo : null, 
                    $roleId == 4 ? $gender : null, 
                    $roleId == 4 ? $birthDate : null, 
                    $roleId == 4 ? $classId : null, 
                    $roleId == 5 ? $studentRegisterNo : null, 
                    $phone, $email, $address
                ]);
                
                setFlash('success', 'Таны бүртгэлийн хүсэлт амжилттай илгээгдлээ. Сургуулийн захиргаа шалгаж баталгаажуулсны дараа та нэвтрэх боломжтой.');
                header('Location: /school_system1/index.php');
                exit;
            } catch (Exception $e) {
                setFlash('error', 'Бүртгүүлэх үед алдаа гарлаа: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mn" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="/school_system1/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        [data-theme="dark"] body {
            background: #0f172a;
        }

        .register-wrapper {
            display: flex;
            width: 100%;
            max-width: 1300px; /* Same as login */
            height: 100vh;
            background: #fff;
            box-shadow: 0 40px 120px rgba(0,0,0,0.12);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        @media (min-width: 1024px) {
            .register-wrapper {
                height: auto;
                min-height: 850px; /* Slightly taller */
                margin: 30px;
                border-radius: 40px;
                overflow: hidden;
                padding: 15px; /* Thicker frame */
                background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%); /* Vivid frame */
                box-shadow: 0 50px 100px rgba(0,0,0,0.15);
                border: 1px solid rgba(0,0,0,0.1);
                gap: 15px;
            }
        }

        [data-theme="dark"] .register-wrapper {
            background: #1e293b;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            padding: 0;
            border: none;
            gap: 0;
        }

        /* Left Side: Image / Branding */
        .register-banner {
            display: none;
            position: relative;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            overflow: hidden;
        }

        @media (min-width: 1024px) {
            .register-banner {
                display: flex;
                flex: 1;
                flex-direction: column;
                justify-content: space-between;
                padding: 50px;
                color: #fff;
                border-radius: 24px;
            }
        }

        .banner-bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: url('/school_system1/assets/img/dark_bg.png');
            background-size: cover;
            background-position: center;
            opacity: 1;
        }
        
        [data-theme="dark"] .banner-bg {
            background-image: url('/school_system1/assets/img/login_bg.png');
            opacity: 0.8;
        }

        .banner-content {
            position: relative;
            z-index: 10;
        }

        .banner-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .banner-logo img {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #fff;
            padding: 4px;
        }

        .banner-text {
            margin-top: auto;
            position: relative;
            z-index: 10;
        }

        .banner-text h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 42px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .banner-text p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 400px;
        }

        /* Right Side: Form */
        .register-form-container {
            flex: 1.4; /* More space for form */
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding: 50px;
            width: 100%;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 28px;
            scroll-behavior: smooth;
        }

        [data-theme="dark"] .register-form-container {
            background: #1e293b;
        }

        @media (min-width: 1024px) {
            .register-form-container {
                padding: 60px 80px;
            }
        }

        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .form-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #000000;
            margin: 0 0 5px 0;
            letter-spacing: -0.5px;
        }

        [data-theme="dark"] .form-header h1 { color: #f8fafc; }

        .form-header p {
            font-size: 14px;
            color: #475569;
            margin: 0;
        }
        [data-theme="dark"] .form-header p {
            color: #94a3b8;
        }

        /* Stepper UI */
        .stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
            position: relative;
            max-width: 100%; /* Spreading them out */
            margin-left: 0;
            margin-right: 0;
            padding: 0 10px;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }
        [data-theme="dark"] .stepper::before { background: #334155; }

        .step {
            position: relative;
            z-index: 2;
            background: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #e2e8f0;
            color: #94a3b8;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .step { background: #1e293b; border-color: #334155; }

        .step.active {
            border-color: #6366f1;
            background: #6366f1;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .step.completed {
            border-color: #10b981;
            background: #10b981;
            color: #fff;
        }
        .step-label {
            position: absolute;
            top: 45px;
            font-size: 12px;
            color: #94a3b8;
            white-space: nowrap;
            font-weight: 700;
            transition: all 0.3s;
        }
        .step.active .step-label {
            color: #6366f1;
            font-size: 13px;
        }
        .step.completed .step-label {
            color: #10b981;
        }
        
        /* Step Content */
        .step-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        .step-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Role Selection Cards */
        .role-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .role-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        [data-theme="dark"] .role-card { background: #0f172a; border-color: #334155; }

        .role-card:hover {
            border-color: #6366f1;
            transform: translateY(-2px);
        }
        .role-card.selected {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.05);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.05);
        }
        .role-card i {
            font-size: 40px;
            color: #94a3b8;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .role-card.selected i {
            color: #6366f1;
        }
        .role-card h3 {
            margin: 0;
            font-size: 18px;
            color: #334155;
            font-family: 'Poppins', sans-serif;
        }
        [data-theme="dark"] .role-card h3 { color: #f1f5f9; }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        [data-theme="dark"] .form-group label { color: #cbd5e1; }

        .modern-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        [data-theme="dark"] .modern-input { background: #0f172a; border-color: #334155; color: #f1f5f9; }

        .modern-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            color: #0f172a;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        [data-theme="dark"] .modern-input:focus {
            background: #1e293b;
            color: #f1f5f9;
        }

        /* Buttons */
        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        [data-theme="dark"] .actions { border-color: #334155; }

        .btn-prev {
            background: #f1f5f9;
            color: #475569;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        [data-theme="dark"] .btn-prev { background: #334155; color: #cbd5e1; }

        .btn-next {
            background: #6366f1;
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-next:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }
        .btn-next:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .form-section-title {
            font-size: 16px;
            color: #334155;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
        }
        [data-theme="dark"] .form-section-title { color: #f1f5f9; }
        .form-section-title i { color: #6366f1; }

        .mobile-logo {
            display: block;
            text-align: center;
            margin-bottom: 20px;
        }
        .mobile-logo img {
            width: 56px;
            height: 56px;
            border-radius: 12px;
        }
        @media (min-width: 1024px) {
            .mobile-logo { display: none; }
        }
        /* Stylish Theme Toggle Switch */
        .theme-switch-wrapper {
            position: absolute;
            top: 30px;
            right: 30px;
            display: flex;
            align-items: center;
            z-index: 100;
        }

        .theme-switch {
            display: inline-block;
            height: 34px;
            position: relative;
            width: 60px;
        }

        .theme-switch input {
            display: none;
        }

        .slider {
            background-color: #cbd5e1;
            bottom: 0;
            cursor: pointer;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            transition: .4s;
            border-radius: 34px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 8px;
        }

        .slider:before {
            background-color: #fff;
            bottom: 4px;
            content: "";
            height: 26px;
            left: 4px;
            position: absolute;
            transition: .4s;
            width: 26px;
            border-radius: 50%;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        input:checked + .slider {
            background-color: #6366f1;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .slider i {
            font-size: 14px;
            z-index: 1;
        }

        .slider .fa-sun { color: #f59e0b; }
        .slider .fa-moon { color: #f1f5f9; }

        [data-theme="dark"] .slider { background-color: #334155; }
        [data-theme="dark"] .slider:before { background-color: #1e293b; }
        [data-theme="dark"] .slider .fa-sun { color: #475569; }
        [data-theme="dark"] .slider .fa-moon { color: #f1c40f; }
    </style>
</head>
<body>

<div class="register-wrapper">
    <!-- Left Side Banner -->
    <div class="register-banner">
        <div class="banner-bg"></div>
        <div class="banner-content banner-logo">
            <img src="/school_system1/assets/img/logo.png" alt="Logo">
            Цахим Сургууль
        </div>
        
        <div class="banner-text">
            <h2>Шинэ бүртгэл<br>үүсгэх</h2>
            <p>Сургуулийн системд нэгдэж, сургалтын үйл ажиллагаагаа илүү хялбар, ухаалаг удирдах боломжийг аваарай.</p>
        </div>
    </div>

    <!-- Right Side Form -->
    <div class="register-form-container">
        
        <div class="theme-switch-wrapper">
            <label class="theme-switch" for="themeCheckbox" title="Горим солих">
                <input type="checkbox" id="themeCheckbox" onchange="toggleDarkMode()" />
                <div class="slider round">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                </div>
            </label>
        </div>
        
        <div class="mobile-logo">
            <img src="/school_system1/assets/img/logo.png" alt="Logo">
        </div>

        <div class="form-header">
            <h1>Бүртгүүлж эхлэх</h1>
            <p>Дараах алхмуудын дагуу мэдээллээ оруулна уу.</p>
        </div>

        <!-- Stepper -->
        <div class="stepper">
            <div class="step active" id="indicator-1">1<div class="step-label">Дүр</div></div>
            <div class="step" id="indicator-2">2<div class="step-label">Нэвтрэх</div></div>
            <div class="step" id="indicator-3">3<div class="step-label">Хувийн</div></div>
            <div class="step" id="indicator-4">4<div class="step-label">Холбоо</div></div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>" style="border-radius:12px; margin-bottom:20px;">
            <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
            <?= h($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:14px;color:#065f46;">
            <i class="fas fa-info-circle" style="color:#10b981;font-size:18px;"></i>
            <span>Бүртгэлийн хүсэлтийг <strong>захиргаа шалгаж батлах</strong> ба дараа нь та нэвтрэх боломжтой.</span>
        </div>

        <form method="POST" action="" id="registerForm">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="role_id" id="role_id" value="">

            <!-- STEP 1: Role Selection -->
            <div class="step-content active" id="step-1">
                <div class="form-section-title"><i class="fas fa-user-tag"></i> Та хэн бэ?</div>
                <div class="role-cards">
                    <div class="role-card" id="card-student" onclick="selectRole(4)">
                        <i class="fas fa-user-graduate"></i>
                        <h3>Сурагч</h3>
                        <p style="font-size:12px; color:#64748b; margin-top:8px;">Сургуульд суралцагч</p>
                    </div>
                    <div class="role-card" id="card-parent" onclick="selectRole(5)">
                        <i class="fas fa-user-friends"></i>
                        <h3>Эцэг эх</h3>
                        <p style="font-size:12px; color:#64748b; margin-top:8px;">Асран хамгаалагч</p>
                    </div>
                </div>
                <div class="actions" style="justify-content: flex-end;">
                    <button type="button" class="btn-next" onclick="nextStep(2)" id="btnNext1" disabled>
                        Дараах <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Login Info -->
            <div class="step-content" id="step-2">
                <div class="form-section-title"><i class="fas fa-key"></i> Нэвтрэх мэдээлэл</div>
                <div class="form-group">
                    <label>Нэвтрэх нэр (Username) *</label>
                    <input type="text" name="username" id="username" class="modern-input" placeholder="Хэрэглэгчийн нэр">
                </div>
                <div class="form-group">
                    <label>Нууц үг *</label>
                    <input type="password" name="password" id="password" class="modern-input" placeholder="••••••••" minlength="8" required>
                    <small id="pw-hint" style="color:#94a3b8; font-size:12px; margin-top:4px; display:block;">Дор хаяж 8 тэмдэгт, үсэг + тоо агуулсан</small>
                </div>
                <div class="actions">
                    <button type="button" class="btn-prev" onclick="prevStep(1)"><i class="fas fa-arrow-left"></i> Өмнөх</button>
                    <button type="button" class="btn-next" onclick="nextStep(3)">Дараах <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- STEP 3: Personal Info -->
            <div class="step-content" id="step-3">
                <div class="form-section-title"><i class="fas fa-id-card"></i> Хувийн мэдээлэл</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Овог *</label>
                        <input type="text" name="last_name" id="last_name" class="modern-input">
                    </div>
                    <div class="form-group">
                        <label>Өөрийн нэр *</label>
                        <input type="text" name="first_name" id="first_name" class="modern-input">
                    </div>
                </div>
                
                <div id="student_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Регистрийн дугаар</label>
                            <input type="text" name="register_no" class="modern-input" placeholder="АБ12345678">
                        </div>
                        <div class="form-group">
                            <label>Хүйс</label>
                            <select name="gender" class="modern-input">
                                <option value="">Сонгох</option>
                                <option value="male">Эрэгтэй</option>
                                <option value="female">Эмэгтэй</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Төрсөн огноо</label>
                        <input type="date" name="birth_date" class="modern-input">
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn-prev" onclick="prevStep(2)"><i class="fas fa-arrow-left"></i> Өмнөх</button>
                    <button type="button" class="btn-next" onclick="nextStep(4)">Дараах <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- STEP 4: Contact Info -->
            <div class="step-content" id="step-4">
                <div class="form-section-title"><i class="fas fa-address-book"></i> Холбоо барих</div>
                
                <div id="parent_fields" style="display:none; background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.1); padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="color: #6366f1;"><i class="fas fa-child"></i> Хүүхдийн регистрийн дугаар</label>
                        <input type="text" name="student_register_no" class="modern-input" placeholder="АБ12345678">
                        <small style="color: #64748b; display:block; margin-top:5px;">Системд бүртгэлтэй хүүхэдтэйгээ холбогдохын тулд.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Утасны дугаар</label>
                        <input type="text" name="phone" class="modern-input">
                    </div>
                    <div class="form-group">
                        <label>Имэйл хаяг</label>
                        <input type="email" name="email" class="modern-input">
                    </div>
                </div>
                <div class="form-group">
                    <label>Гэрийн хаяг</label>
                    <input type="text" name="address" class="modern-input">
                </div>

                <div class="actions">
                    <button type="button" class="btn-prev" onclick="prevStep(3)"><i class="fas fa-arrow-left"></i> Өмнөх</button>
                    <button type="submit" id="submitBtn" class="btn-next" style="background: #10b981;">
                        <i class="fas fa-paper-plane"></i> Бүртгэл илгээх
                    </button>
                </div>
            </div>
        </form>

        <div style="text-align:center; margin-top:30px;">
            <a href="/school_system1/index.php" style="color:#64748b; text-decoration:none; font-size: 14px; font-weight:500;"><i class="fas fa-sign-in-alt"></i> Аль хэдийн бүртгэлтэй юу? Нэвтрэх</a>
        </div>
    </div>
</div>

<script>
    function selectRole(roleId) {
        document.getElementById('role_id').value = roleId;
        
        document.getElementById('card-student').classList.remove('selected');
        document.getElementById('card-parent').classList.remove('selected');
        
        if (roleId === 4) {
            document.getElementById('card-student').classList.add('selected');
            document.getElementById('student_fields').style.display = 'block';
            document.getElementById('parent_fields').style.display = 'none';
        } else {
            document.getElementById('card-parent').classList.add('selected');
            document.getElementById('student_fields').style.display = 'none';
            document.getElementById('parent_fields').style.display = 'block';
        }
        
        document.getElementById('btnNext1').disabled = false;
    }

    function scrollFormToTop() {
        var container = document.querySelector('.register-form-container');
        if (container) {
            container.scrollTo({ top: 0, behavior: 'smooth' });
        }
        // Also scroll window to top for mobile
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function nextStep(step) {
        if (step === 3) {
            var u = document.getElementById('username').value;
            var p = document.getElementById('password').value;
            if(!u || !p) {
                alert("Нэвтрэх мэдээллээ оруулна уу."); return;
            }
            if(p.length < 8) {
                alert("Нууц үг дор хаяж 8 тэмдэгт байх ёстой."); return;
            }
            if(!/[A-Za-zА-Яа-яӨөҮү]/.test(p) || !/[0-9]/.test(p)) {
                alert("Нууц үг үсэг болон тоо агуулсан байх ёстой."); return;
            }
        }
        if (step === 4) {
            if(!document.getElementById('last_name').value || !document.getElementById('first_name').value) {
                alert("Овог нэрээ оруулна уу."); return;
            }
        }

        document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');
        
        for(let i=1; i<=4; i++) {
            let ind = document.getElementById('indicator-' + i);
            if (i < step) { ind.classList.add('completed'); ind.classList.remove('active'); }
            else if (i === step) { ind.classList.add('active'); ind.classList.remove('completed'); }
            else { ind.classList.remove('active', 'completed'); }
        }

        scrollFormToTop();
    }

    function prevStep(step) {
        document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');
        
        for(let i=1; i<=4; i++) {
            let ind = document.getElementById('indicator-' + i);
            if (i < step) { ind.classList.add('completed'); ind.classList.remove('active'); }
            else if (i === step) { ind.classList.add('active'); ind.classList.remove('completed'); }
            else { ind.classList.remove('active', 'completed'); }
        }

        scrollFormToTop();
    }

    document.getElementById('registerForm').addEventListener('submit', function() {
        var btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Түр хүлээнэ үү...';
        btn.disabled = true;
    });
</script>
<script src="/school_system1/assets/js/main.js"></script>
</body>
</html>

