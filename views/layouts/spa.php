<?php
/**
 * React SPA Layout
 * Production-optimized with combined JS and minified React
 */

// Determine if we should usedevelopment or production React
$isDevelopment = false; // Set to true for local development
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="format-detection" content="telephone=no">
<title>Sri Panchami Spiritual – Buy Rudraksha, Pooja Items & Astrology Consultation in Chennai</title>
<meta name="description" content="Buy original rudraksha, pooja items, spiritual jewellery & accessories online in Chennai. Book expert Vedic astrology consultation. Free shipping across India. Trusted by 500+ devotees.">
<meta name="keywords" content="buy rudraksha online Chennai, pooja items online India, spiritual products online, Vedic astrology consultation Chennai">
<meta name="robots" content="index, follow">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sri Panchami Spiritual">
<meta property="og:title" content="Sri Panchami Spiritual – Buy Rudraksha, Pooja Items & Astrology">
<meta property="og:description" content="Buy original rudraksha, pooja items, spiritual jewellery online. Expert Vedic astrology consultation in Chennai.">

<!-- Preconnect -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Styles -->
<link rel="stylesheet" href="/assets/css/band.css">

<?php if ($isDevelopment): ?>
<!-- Development: React with warnings -->
<script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
<?php else: ?>
<!-- Production: React minified -->
<script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<?php endif; ?>
</head>
<body>
    <!-- React mounts here -->
    <div id="root">
        <!-- Fallback content while React loads -->
        <div style="display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column;">
            <img src="/assets/images/logo-square.jpeg" alt="Sri Panchami Spiritual" style="width: 80px; height: 80px; border-radius: 50%; margin-bottom: 1rem;">
            <p style="color: var(--color-text-muted);">Loading...</p>
        </div>
    </div>

    <!-- App Scripts - Combined for production -->
    <script src="/assets/js/app.min.js"></script>
</body>
</html>
