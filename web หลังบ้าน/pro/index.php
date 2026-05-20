<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คลังข้อมูลการฝึกงาน ✨📚</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --lib-bg: #fdfaf6; /* สีครีมกระดาษถนอมสายตา */
            --lib-primary: #a2d2ff; /* สีฟ้าพาสเทล */
            --lib-accent: #ffb5a7; /* สีพีช/ชมพูพาสเทล */
            --lib-text: #5e503f; /* สีน้ำตาลเข้มหมึกพิมพ์ */
        }

        body {
            margin: 0; padding: 0; height: 100vh;
            background-color: var(--lib-bg);
            /* ลายพื้นหลังจุดไข่ปลาจางๆ ให้ดูเหมือนสมุดจด */
            background-image: radial-gradient(#e5e0d8 1.5px, transparent 1.5px);
            background-size: 25px 25px;
            color: var(--lib-text);
            font-family: 'Prompt', 'Poppins', sans-serif;
            display: flex; flex-direction: column;
            overflow: hidden;
        }

        /* Navbar */
        .navbar {
            padding: 1.5rem 2rem;
            background: transparent;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--lib-text) !important;
            font-size: 1.5rem;
        }

        /* ส่วนกลางหน้าจอ */
        .main-content {
            flex: 1; display: flex; align-items: center; justify-content: center; text-align: center;
            position: relative; z-index: 10;
        }

        .hero-text h1 {
            font-size: 4.5rem;
            font-weight: 700;
            color: var(--lib-text);
            text-shadow: 2px 2px 0px white, 4px 4px 0px var(--lib-primary);
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        .hero-text p {
            font-size: 1.4rem;
            color: #8a7e71;
            margin-bottom: 40px;
        }

        /* ปุ่มกดเข้าคลังหนังสือ */
        .btn-enter {
            display: inline-block;
            background-color: var(--lib-accent);
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(255, 181, 167, 0.4);
            transition: all 0.3s ease;
            border: 3px solid white;
        }
        .btn-enter:hover {
            transform: translateY(-5px) scale(1.05);
            background-color: #ff9b8a;
            color: white;
            box-shadow: 0 12px 25px rgba(255, 181, 167, 0.6);
        }

        /* แอนิเมชันของตกแต่งลอยไปมา */
        .floating-icon {
            position: absolute;
            opacity: 0.6;
            animation: float 6s ease-in-out infinite;
            z-index: 0;
        }
        .icon-1 { top: 20%; left: 15%; font-size: 4rem; animation-delay: 0s; color: var(--lib-primary); }
        .icon-2 { bottom: 20%; right: 15%; font-size: 5rem; animation-delay: 2s; color: var(--lib-accent); }
        .icon-3 { top: 30%; right: 20%; font-size: 3rem; animation-delay: 1s; color: #ffd166; }
        .icon-4 { bottom: 30%; left: 20%; font-size: 4.5rem; animation-delay: 3s; color: #06d6a0; }

        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
    </style>
</head>
<body>

    <i class="fas fa-book-open floating-icon icon-1"></i>
    <i class="fas fa-book floating-icon icon-2"></i>
    <i class="fas fa-star floating-icon icon-3"></i>
    <i class="fas fa-bookmark floating-icon icon-4"></i>

    <nav class="navbar">
        <div class="navbar-brand"><i class="fas fa-book-reader me-2" style="color: var(--lib-accent);"></i> Internship Archive</div>
    </nav>

    <div class="main-content">
        <div class="hero-text">
            <h1>คลังข้อมูลการฝึกงาน</h1>
            <p>ระบบจัดการข้อมูลและสมุดบันทึกประสบการณ์ สำหรับนักศึกษา อาจารย์ และผู้ดูแลระบบ 🪴📖</p>
            <a href="login.php" class="btn-enter"><i class="fas fa-door-open me-2"></i> เข้าสู่ระบบ</a>
        </div>
    </div>

</body>
</html>