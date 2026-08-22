<!DOCTYPE html>
<html lang="en">
<!--// ── This script is made by Siva Balaji sms ────────────────────── -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>404 — Page Not Found</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{height:100%}
        body{font-family:'DM Sans',sans-serif;background:#0d0b08;color:#f2eee8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;overflow:hidden;position:relative}
        body::before{content:"";position:fixed;inset:0;background:radial-gradient(circle at 25% 25%,rgba(200,169,126,.10),transparent 34%),radial-gradient(circle at 80% 80%,rgba(139,100,40,.08),transparent 32%);pointer-events:none}
        body::after{content:"";position:fixed;width:480px;height:480px;border:1px solid rgba(255,255,255,.025);border-radius:50%;top:-240px;right:-180px;pointer-events:none}
        .wrap{width:100%;max-width:560px;text-align:center;position:relative;z-index:1}
        .error-label{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border:1px solid rgba(255,255,255,.08);border-radius:100px;background:rgba(255,255,255,.025);font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:rgba(242,238,232,.55);margin-bottom:28px;backdrop-filter:blur(10px)}
        .error-dot{width:7px;height:7px;border-radius:50%;background:#c8a97e;box-shadow:0 0 0 5px rgba(200,169,126,.09)}
        .code{font-family:'Syne',sans-serif;font-size:clamp(88px,19vw,150px);font-weight:800;line-height:.9;letter-spacing:-7px;background:linear-gradient(135deg,#e3c69f 0%,#c8a97e 45%,#765323 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-shadow:0 0 60px rgba(200,169,126,.08);margin-bottom:22px}
        .divider{width:48px;height:2px;border-radius:10px;background:linear-gradient(90deg,transparent,#c8a97e,transparent);margin:0 auto 24px}
        h1{font-family:'Syne',sans-serif;font-size:clamp(24px,5vw,30px);font-weight:700;letter-spacing:-.7px;margin-bottom:13px}
        .message{max-width:430px;margin:0 auto;color:rgba(242,238,232,.48);font-size:14.5px;line-height:1.75}
        .actions{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:34px}
        .btn{min-height:46px;padding:0 21px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;gap:9px;text-decoration:none;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:600;transition:transform .2s ease,background .2s ease,border-color .2s ease,box-shadow .2s ease}
        .btn svg{width:17px;height:17px}
        .btn-home{background:linear-gradient(135deg,#d2b287,#9b7136);color:#110e0a;box-shadow:0 10px 30px rgba(200,169,126,.15)}
        .btn-home:hover{transform:translateY(-2px);box-shadow:0 14px 35px rgba(200,169,126,.22)}
        .btn-back{background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.09);color:rgba(242,238,232,.64)}
        .btn-back:hover{transform:translateY(-2px);background:rgba(255,255,255,.055);border-color:rgba(255,255,255,.14);color:#f2eee8}
        .footer{margin-top:30px;font-size:11px;letter-spacing:.3px;color:rgba(242,238,232,.24)}
        @media(max-width:520px){
            body{padding:20px}
            .error-label{margin-bottom:24px}
            .code{font-size:96px;letter-spacing:-5px}
            h1{font-size:25px}
            .message{font-size:14px}
            .actions{flex-direction:column;width:100%;margin-top:30px}
            .btn{width:100%;max-width:280px}
        }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="error-label">
            <span class="error-dot"></span>
            Error 404
        </div>
        <div class="code">404</div>
        <div class="divider"></div>
        <h1>Page not found</h1>
        <p class="message">The page you're looking for doesn't exist, may have been moved, or the address may be incorrect.</p>
        <div class="actions">
            <a href="index.php" class="btn btn-home">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 11.5 12 4l9 7.5"></path>
                    <path d="M5.5 10v9h13v-9"></path>
                    <path d="M9.5 19v-5h5v5"></path>
                </svg>
                Go to homepage
            </a>
            <a href="javascript:history.back()" class="btn btn-back">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"></path>
                    <path d="M11 6l-6 6 6 6"></path>
                </svg>
                Go back
            </a>
        </div>
        <div class="footer">The requested page could not be found.</div>
    </main>
</body>
</html>