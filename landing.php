<?php
session_start();
require_once 'db.php';

// Get counts from pre-registrations + base numbers
$baseStudents = 154;
$baseTeachers = 201;
$extraStudents = 0;
$extraTeachers = 0;
if (getDB()) {
    $rows = dbAll("SELECT rol, COUNT(*) AS cnt FROM landing_preregistros GROUP BY rol");
    foreach ($rows as $r) {
        if ($r['rol'] === 'estudiante') $extraStudents = (int)$r['cnt'];
        if ($r['rol'] === 'instructor') $extraTeachers = (int)$r['cnt'];
    }
}
$totalStudents = $baseStudents + $extraStudents;
$totalTeachers = $baseTeachers + $extraTeachers;
$totalRegistered = $totalStudents + $totalTeachers;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="favicon.ico?v=4">
    <link rel="icon" type="image/png" href="favicon.png?v=4">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <title>ClassExpress — Live Online Private Tutoring by Video Conference</title>
    <meta name="description" content="ClassExpress: live private tutoring platform. Connect with users who speak your language, from anywhere in the world. Search and join classes in real time.">
    <meta name="keywords" content="private tutoring, online tutoring, live classes, video conference, private teacher, online learning, math, science, languages, education, e-learning, live lessons">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://classexpress.online/">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ClassExpress — Live Online Private Tutoring">
    <meta property="og:description" content="Live private tutoring platform. Connect with users who speak your language from anywhere in the world. Search and join classes in real time.">
    <meta property="og:url" content="https://classexpress.online/">
    <meta property="og:site_name" content="ClassExpress">
    <meta property="og:locale" content="en_US">
    <meta property="og:image" content="https://classexpress.online/favicon.png?v=4">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="ClassExpress - Live Online Private Tutoring">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ClassExpress — Live Online Private Tutoring">
    <meta name="twitter:description" content="Live private tutoring platform. Connect with users who speak your language from anywhere in the world. Search and join classes in real time.">
    <meta name="twitter:image" content="https://classexpress.online/favicon.png?v=4">
    <link rel="icon" href="favico.svg?v=4" type="image/svg+xml">

    <link rel="alternate" hreflang="es" href="https://classexpress.online/?lang=es">
    <link rel="alternate" hreflang="en" href="https://classexpress.online/?lang=en">
    <link rel="alternate" hreflang="fr" href="https://classexpress.online/?lang=fr">
    <link rel="alternate" hreflang="de" href="https://classexpress.online/?lang=de">
    <link rel="alternate" hreflang="pt" href="https://classexpress.online/?lang=pt">
    <link rel="alternate" hreflang="it" href="https://classexpress.online/?lang=it">
    <link rel="alternate" hreflang="zh" href="https://classexpress.online/?lang=zh">
    <link rel="alternate" hreflang="ja" href="https://classexpress.online/?lang=ja">
    <link rel="alternate" hreflang="ru" href="https://classexpress.online/?lang=ru">
    <link rel="alternate" hreflang="ar" href="https://classexpress.online/?lang=ar">
    <link rel="alternate" hreflang="hi" href="https://classexpress.online/?lang=hi">
    <link rel="alternate" hreflang="ko" href="https://classexpress.online/?lang=ko">
    <link rel="alternate" hreflang="x-default" href="https://classexpress.online/">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "ClassExpress",
        "description": "Live private tutoring platform by video conference. Connect with users who speak your language from around the world.",
        "url": "https://classexpress.online",
        "logo": "https://classexpress.online/favicon.png?v=4",
        "sameAs": [],
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "customer support",
            "availableLanguage": ["Spanish","English","French","German","Portuguese","Italian","Chinese","Japanese","Russian","Arabic","Hindi","Korean"]
        },
        "areaServed": {
            "@type": "Place",
            "name": "Worldwide"
        },
        "knowsLanguage": ["es","en","fr","de","pt","it","zh","ja","ru","ar","hi","ko"]
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "ClassExpress",
        "url": "https://classexpress.online",
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "https://classexpress.online/CCC/api.php?action=search&q={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Course",
        "name": "Live Online Private Tutoring",
        "description": "Learn math, science, languages and more with expert teachers by live video conference.",
        "provider": {
            "@type": "EducationalOrganization",
            "name": "ClassExpress",
            "url": "https://classexpress.online"
        },
        "educationalLevel": "All levels",
        "inLanguage": ["es","en","fr","de","pt","it","zh","ja","ru","ar","hi","ko"],
        "isAccessibleForFree": true,
        "hasCourseInstance": {
            "@type": "CourseInstance",
            "courseMode": "online",
            "courseWorkload": "PT1H"
        }
    }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #66ddbd;
            --primary-dark: #4CBFA3;
            --bg-dark: #f4f6fb;
            --bg-card: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #dbe2ee;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-orange: #f59e0b;
            --accent-green: #16a34a;
            --accent-pink: #ec4899;
            --accent-red: #dc2626;
            --gradient-1: linear-gradient(135deg, #66ddbd 0%, #3b82f6 100%);
            --gradient-2: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg-dark); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; overflow-x: hidden; }
        a { color: var(--primary); text-decoration: none; }

        /* NAV */
        .nav-landing { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: rgba(255,255,255,0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); padding: 14px 0; }
        .nav-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { font-size: 22px; font-weight: 800; color: var(--primary); }

        /* HERO */
        .hero { min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 120px 24px 60px; background: radial-gradient(ellipse at 50% 0%, rgba(102,221,189,0.08) 0%, transparent 60%), radial-gradient(ellipse at 80% 80%, rgba(59,130,246,0.05) 0%, transparent 50%); }
        .hero-content { max-width: 820px; }
        .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(102,221,189,0.08); border: 1px solid rgba(102,221,189,0.3); color: var(--primary); font-size: 13px; font-weight: 600; padding: 8px 18px; border-radius: 30px; margin-bottom: 24px; }
        .hero-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
        .hero h1 { font-size: clamp(34px, 5.5vw, 62px); font-weight: 900; line-height: 1.1; margin-bottom: 20px; letter-spacing: -1px; }
        .hero h1 span { background: var(--gradient-1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero-sub { font-size: clamp(16px, 2vw, 19px); color: var(--text-muted); max-width: 620px; margin: 0 auto 28px; line-height: 1.6; }

        /* COUNTERS */
        .counters { display: flex; justify-content: center; gap: 24px; margin-bottom: 28px; flex-wrap: wrap; }
        .counter-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 18px 32px; text-align: center; transition: transform 0.3s, border-color 0.3s; }
        .counter-box:hover { transform: translateY(-3px); border-color: var(--primary); }
        .counter-number { font-size: 36px; font-weight: 900; background: var(--gradient-1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .counter-label { font-size: 13px; color: var(--text-muted); margin-top: 2px; font-weight: 500; }
        .social-proof { font-size: 13px; color: var(--text-muted); margin-bottom: 28px; }
        .social-proof strong { color: var(--text); }

        /* FORM */
        .signup-form { background: var(--bg-card); border: 2px solid var(--primary); border-radius: 20px; padding: 32px; max-width: 480px; margin: 0 auto; position: relative; }
        .signup-form::before { content: ''; position: absolute; inset: -2px; border-radius: 22px; background: var(--gradient-1); z-index: -1; opacity: 0.15; }
        .form-title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .form-subtitle { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control-landing { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border); background: #fff; color: var(--text); font-size: 16px; outline: none; transition: border-color 0.3s; }
        .form-control-landing:focus { border-color: var(--primary); }
        .form-control-landing::placeholder { color: #94a3b8; }
        .role-options { display: flex; gap: 12px; }
        .role-option { flex: 1; padding: 14px; border-radius: 12px; border: 2px solid var(--border); background: #fff; cursor: pointer; text-align: center; transition: all 0.3s; font-weight: 600; font-size: 14px; }
        .role-option:hover { border-color: var(--text-muted); }
        .role-option.active { border-color: var(--primary); background: rgba(102,221,189,0.08); color: var(--primary); }
        .role-option i { display: block; font-size: 24px; margin-bottom: 6px; }
        .btn-landing { width: 100%; padding: 16px; border-radius: 12px; border: none; font-size: 16px; font-weight: 700; color: #fff; cursor: pointer; transition: all 0.3s; background: var(--gradient-1); }
        .btn-landing:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(102,221,189,0.35); }
        .btn-landing:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .form-msg { margin-top: 12px; font-size: 14px; text-align: center; min-height: 20px; }
        .form-msg.success { color: var(--accent-green); }
        .form-msg.error { color: var(--accent-red); }
        .form-footnote { font-size: 12px; color: var(--text-muted); text-align: center; margin-top: 12px; }

        /* SECTIONS */
        section { padding: 100px 24px; }
        .section-inner { max-width: 1100px; margin: 0 auto; }
        .section-title { font-size: clamp(28px, 4vw, 42px); font-weight: 900; text-align: center; margin-bottom: 12px; letter-spacing: -0.5px; }
        .section-sub { font-size: 17px; color: var(--text-muted); text-align: center; max-width: 620px; margin: 0 auto 56px; line-height: 1.6; }

        /* CONNECT */
        .connect-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .connect-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 32px; position: relative; overflow: hidden; transition: all 0.3s; }
        .connect-card:hover { border-color: var(--primary); transform: translateY(-4px); }
        .connect-icon { font-size: 40px; margin-bottom: 16px; }
        .connect-card h3 { font-size: 20px; font-weight: 800; margin-bottom: 10px; }
        .connect-card p { font-size: 15px; color: var(--text-muted); line-height: 1.6; }
        .connect-detail { display: flex; align-items: center; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); font-size: 13px; color: var(--text-muted); }
        .connect-detail i { color: var(--accent-green); }

        /* HOW IT WORKS */
        .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; }
        .step { background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 28px 20px; text-align: center; transition: all 0.3s; }
        .step:hover { border-color: var(--primary); transform: translateY(-4px); }
        .step-number { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 900; margin: 0 auto 14px; background: var(--gradient-1); color: #fff; }
        .step h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
        .step p { font-size: 13px; color: var(--text-muted); line-height: 1.5; }

        /* FEATURES */
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .feature { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; display: flex; gap: 14px; align-items: flex-start; transition: all 0.3s; }
        .feature:hover { border-color: var(--primary); }
        .feature-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .feature-icon.green { background: rgba(63,185,80,0.12); color: var(--accent-green); }
        .feature-icon.blue { background: rgba(88,166,255,0.12); color: var(--accent-blue); }
        .feature-icon.purple { background: rgba(188,140,255,0.12); color: var(--accent-purple); }
        .feature-icon.orange { background: rgba(240,136,62,0.12); color: var(--accent-orange); }
        .feature-icon.pink { background: rgba(247,120,186,0.12); color: var(--accent-pink); }
        .feature-icon.teal { background: rgba(32,201,151,0.12); color: var(--primary); }
        .feature h4 { font-size: 14px; font-weight: 700; margin-bottom: 3px; }
        .feature p { font-size: 13px; color: var(--text-muted); line-height: 1.5; }

        /* FINAL CTA */
        .final-cta { text-align: center; padding: 80px 24px; background: radial-gradient(ellipse at 50% 50%, rgba(102,221,189,0.06) 0%, transparent 60%); }

        /* FOOTER */
        .footer-landing { border-top: 1px solid var(--border); padding: 40px 24px; text-align: center; color: var(--text-muted); font-size: 14px; }
        .section-divider { height: 1px; background: var(--border); max-width: 200px; margin: 0 auto; }

        @media (max-width: 768px) {
            .counters { gap: 12px; }
            .counter-box { padding: 14px 22px; }
            .counter-number { font-size: 28px; }
            .role-options { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="nav-landing">
    <div class="nav-inner">
        <div class="nav-brand">ClassExpress</div>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <span class="dot"></span>
            Classes in your language, from anywhere in the world
        </div>
        <h1>Speak your language, <span>learn in real time</span></h1>
        <p class="hero-sub">ClassExpress connects you with users who speak your language, from any corner of the world. Search for live classes and join them in real time by video conference.</p>

        <!-- Counters -->
        <div class="counters">
            <div class="counter-box">
                <div class="counter-number" id="counter-students"><?= $totalStudents ?></div>
                <div class="counter-label"><i class="bi bi-mortarboard-fill"></i> Students</div>
            </div>
            <div class="counter-box">
                <div class="counter-number" id="counter-teachers"><?= $totalTeachers ?></div>
                <div class="counter-label"><i class="bi bi-person-workspace"></i> Teachers</div>
            </div>
        </div>
        <div class="social-proof">Already <strong><?= $totalRegistered ?> people</strong> interested in learning without language barriers.</div>

        <!-- Signup Form -->
        <div class="signup-form">
            <div class="form-title"><i class="bi bi-globe2" style="color:var(--primary)"></i> Join ClassExpress</div>
            <div class="form-subtitle">Sign up for free to be notified when you can start taking classes</div>
            <form id="landingForm" onsubmit="return submitLanding(event)">
                <div class="form-group">
                    <label>Email address</label>
                    <input type="email" class="form-control-landing" id="landingEmail" placeholder="you@email.com" required>
                </div>
                <div class="form-group">
                    <label>I want to be</label>
                    <div class="role-options">
                        <div class="role-option active" data-role="estudiante" onclick="selectRole(this)">
                            <i class="bi bi-person-fill"></i>
                            Student
                        </div>
                        <div class="role-option" data-role="instructor" onclick="selectRole(this)">
                            <i class="bi bi-briefcase-fill"></i>
                            Teacher
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-landing" id="landingBtn">
                    <i class="bi bi-rocket-takeoff-fill"></i> Join now
                </button>
            </form>
            <div class="form-msg" id="landingMsg"></div>
            <div class="form-footnote"><i class="bi bi-shield-check"></i> No spam. We'll only notify you when everything is ready.</div>
        </div>
    </div>
</section>

<!-- Connect -->
<section style="background: rgba(102,221,189,0.03);">
    <div class="section-inner">
        <h2 class="section-title">A world connected in your language</h2>
        <p class="section-sub">No matter where you are or what language you speak, there's always someone ready to teach you and learn with you.</p>
        <div class="connect-grid">
            <div class="connect-card">
                <div class="connect-icon">🌍</div>
                <h3>From anywhere in the world</h3>
                <p>Connect with students and teachers across the globe. Distance is never a barrier to learning.</p>
                <div class="connect-detail"><i class="bi bi-globe2"></i> Worldwide community</div>
            </div>
            <div class="connect-card">
                <div class="connect-icon">🗣️</div>
                <h3>Speak your language</h3>
                <p>Every class happens in the language you feel most comfortable with. Communicate naturally, learn better.</p>
                <div class="connect-detail"><i class="bi bi-translate"></i> 12 languages available</div>
            </div>
            <div class="connect-card">
                <div class="connect-icon">⚡</div>
                <h3>Classes in real time</h3>
                <p>Search for live classes and join them instantly by HD video conference. No waiting, no downloads.</p>
                <div class="connect-detail"><i class="bi bi-camera-video-fill"></i> Live video conference</div>
            </div>
        </div>
    </div>
</section>

<!-- How it Works -->
<div class="section-divider"></div>
<section>
    <div class="section-inner">
        <h2 class="section-title">How it works</h2>
        <p class="section-sub">In 5 simple steps, start learning with the best online teachers in your language.</p>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Sign up for free</h3>
                <p>Create your account in 30 seconds. Choose if you're a student or teacher. Select your languages.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Search in real time</h3>
                <p>Search for live classes by subject, language or rating. See who is teaching right now.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>Join class</h3>
                <p>Connect via HD video conference with live chat. No downloads needed. Straight from the browser or app.</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h3>Learn for real</h3>
                <p>Screen sharing, digital whiteboard, instant questions. The closest experience to a real classroom.</p>
            </div>
            <div class="step">
                <div class="step-number">5</div>
                <h3>Rate and repeat</h3>
                <p>Rate the teacher and help the community. Save your favorites for next class.</p>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section style="background: rgba(59,130,246,0.03);">
    <div class="section-inner">
        <h2 class="section-title">Everything you need</h2>
        <p class="section-sub">Cutting-edge technology so online education is as effective as in-person.</p>
        <div class="features-grid">
            <div class="feature">
                <div class="feature-icon green"><i class="bi bi-camera-video-fill"></i></div>
                <div><h4>Real-time HD Video</h4><p>Direct WebRTC connection. No buffering, no delays. Professional quality.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon blue"><i class="bi bi-chat-dots-fill"></i></div>
                <div><h4>Live Chat</h4><p>Instant messaging throughout the class. Ask questions in real time.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon purple"><i class="bi bi-easel-fill"></i></div>
                <div><h4>Shared Whiteboard</h4><p>Share screen and digital whiteboard. Ideal for math and science.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon orange"><i class="bi bi-star-fill"></i></div>
                <div><h4>Real Ratings</h4><p>Verified reputation system. Choose the best teacher with confidence.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon pink"><i class="bi bi-credit-card-fill"></i></div>
                <div><h4>Flexible Payments</h4><p>Top up with MercadoPago. Pay per class or by time. No subscriptions.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon teal"><i class="bi bi-globe2"></i></div>
                <div><h4>12 Languages</h4><p>Full interface in 12 languages. Teachers and students from around the world.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon green"><i class="bi bi-search"></i></div>
                <div><h4>Real-time Class Search</h4><p>Find live classes by subject and language. Join with one click.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon blue"><i class="bi bi-phone-fill"></i></div>
                <div><h4>Mobile App</h4><p>Android and iOS. Study from anywhere, anytime.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon purple"><i class="bi bi-shield-lock-fill"></i></div>
                <div><h4>100% Secure</h4><p>Verified email, encrypted payments, protected data. Trusted community.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon orange"><i class="bi bi-graph-up-arrow"></i></div>
                <div><h4>Teacher Dashboard</h4><p>Manage classes, review earnings and track your professional progress.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon pink"><i class="bi bi-people-fill"></i></div>
                <div><h4>Language Matching</h4><p>We match you with teachers who speak your language so you never feel lost.</p></div>
            </div>
            <div class="feature">
                <div class="feature-icon teal"><i class="bi bi-headset"></i></div>
                <div><h4>24/7 Support</h4><p>Support team available to resolve any technical issue.</p></div>
            </div>
        </div>
    </div>
</section>

<!-- Final CTA -->
<section class="final-cta">
    <div class="section-inner">
        <h2 class="section-title">Your teacher is waiting, in your language</h2>
        <p class="section-sub" style="margin-bottom:32px;">Join ClassExpress and connect with people who speak your language from anywhere in the world.</p>
        <a href="#top" class="btn-landing" style="display:inline-block; width:auto; padding:18px 52px; font-size:18px; background:var(--gradient-1);" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">
            <i class="bi bi-rocket-takeoff-fill"></i> Join now
        </a>
    </div>
</section>

<!-- Footer -->
<footer class="footer-landing">
    <p>&copy; <?= date('Y') ?> ClassExpress — Bunny Software E.I.R.L. All rights reserved.</p>
    <p style="margin-top:6px;font-size:12px;">Made with <span style="color:var(--primary);">♥</span> in Chile for the world</p>
</footer>

<script>
let selectedRole = 'estudiante';
function selectRole(el) {
    document.querySelectorAll('.role-option').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    selectedRole = el.dataset.role;
}

function submitLanding(e) {
    e.preventDefault();
    const email = document.getElementById('landingEmail').value.trim();
    const btn = document.getElementById('landingBtn');
    const msg = document.getElementById('landingMsg');
    if (!email) { msg.className='form-msg error'; msg.textContent='Enter your email.'; return false; }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    msg.textContent = '';
    fetch('landing_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({email: email, rol: selectedRole})
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-rocket-takeoff-fill"></i> Join now';
        if (data.redirect) { window.location.href = data.redirect; return; }
        if (data.ok) {
            msg.className = 'form-msg success';
            msg.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + data.message;
            document.getElementById('landingEmail').value = '';
            if (data.students !== undefined) document.getElementById('counter-students').textContent = data.students;
            if (data.teachers !== undefined) document.getElementById('counter-teachers').textContent = data.teachers;
        } else {
            msg.className = 'form-msg error';
            msg.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.error || 'Error.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-rocket-takeoff-fill"></i> Join now';
        msg.className = 'form-msg error';
        msg.textContent = 'Connection error. Please try again.';
    });
    return false;
}
</script>
</body>
</html>
