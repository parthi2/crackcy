<?php
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cracksy - AK Traders Quality Crackers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- <link href="assets/css/style.css" rel="stylesheet"> -->

    <style>
    /* ============================================================= */
    /* 🎨 UI/UX DESIGN SYSTEM: RESPONSIVE HEADER                     */
    /* ============================================================= */
    
    :root {
        --header-bg: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
        --accent-red: #E53935;
        --accent-gold: #FFB300;
    }

    /* Top Strip Bar for Mobile Quick Highlights */
    .top-announcement-strip {
        background: linear-gradient(90deg, #E53935 0%, #FF9800 100%);
        color: #FFFFFF;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 5px 0;
        text-align: center;
        letter-spacing: 0.03em;
    }

    /* Main Brand Header Container */
    .brand-header-wrapper {
        background: var(--header-bg);
        border-bottom: 3px solid var(--accent-red);
        padding: 12px 0;
        position: relative;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    /* Ambient Festive Glow Background */
    .brand-header-wrapper::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 70%;
        height: 100%;
        background: radial-gradient(circle, rgba(229, 57, 53, 0.18) 0%, rgba(255, 179, 0, 0) 70%);
        pointer-events: none;
    }

    .header-flex-layout {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        z-index: 2;
    }

    /* Logo Image Styling */
    .brand-logo-link {
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        transition: transform 0.25s ease;
    }

    .brand-logo-link:hover {
        transform: scale(1.02);
    }

    .brand-logo-img {
        max-height: 80px;
        width: auto;
        object-fit: contain;
        filter: drop-shadow(0px 4px 12px rgba(255, 255, 255, 0.15));
    }

    /* Desktop Trust Badges */
    .desktop-trust-pills {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .trust-pill {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.12);
        padding: 8px 14px;
        border-radius: 12px;
        backdrop-filter: blur(8px);
    }

    .trust-pill-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, #E53935 0%, #FF9800 100%);
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .trust-pill-title {
        font-size: 0.78rem;
        font-weight: 700;
        color: #F8FAFC;
        line-height: 1.1;
        margin-bottom: 2px;
    }

    .trust-pill-desc {
        font-size: 0.68rem;
        color: #94A3B8;
        margin: 0;
    }

    /* Mobile Features Strip (Below Logo on Small Screens) */
    .mobile-feature-badges {
        display: none;
        background: #0F172A;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 6px 0;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
    }

    .mobile-feature-badges::-webkit-scrollbar {
        display: none;
    }

    .mobile-badge-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        color: #E2E8F0;
        padding: 2px 12px;
        border-right: 1px solid rgba(255, 255, 255, 0.15);
    }

    .mobile-badge-item:last-child {
        border-right: none;
    }

    /* ============================================================= */
    /* 📱 MOBILE RESPONSIVE MEDIA QUERIES                            */
    /* ============================================================= */
    @media (max-width: 991px) {
        .desktop-trust-pills {
            display: none; /* Hide heavy cards on small screens */
        }

        .header-flex-layout {
            justify-content: center; /* Center logo cleanly */
        }

        .brand-logo-img {
            max-height: 65px; /* Compact mobile size */
        }

        .brand-header-wrapper {
            padding: 8px 0;
        }

        .mobile-feature-badges {
            display: flex;
            justify-content: space-around;
        }
    }

    @media (max-width: 480px) {
        .brand-logo-img {
            max-height: 58px;
        }
        
        .top-announcement-strip {
            font-size: 0.7rem;
        }
    }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">   

<!-- 1. Top Offer Strip Banner -->
<div class="top-announcement-strip">
    <div class="container">
        <i class="fa-solid fa-fire me-1 text-warning"></i> Sivakasi Factory Direct Crackers • Wholesale Prices
    </div>
</div>

<!-- 2. Responsive Main Brand Header -->
<header class="brand-header-wrapper">
    <div class="container">
        <div class="header-flex-layout">
            
            <!-- Brand Logo -->
            <a href="index.php" class="brand-logo-link">
                <img src="assets/image/logo.png" alt="AK Traders Quality Crackers" class="brand-logo-img">
            </a>

            <!-- Desktop E-Commerce Trust Badges -->
            <div class="desktop-trust-pills">
                <div class="trust-pill">
                    <div class="trust-pill-icon"><i class="fa-solid fa-certificate"></i></div>
                    <div>
                        <div class="trust-pill-title">100% Original</div>
                        <div class="trust-pill-desc">Sivakasi Direct</div>
                    </div>
                </div>

                <div class="trust-pill">
                    <div class="trust-pill-icon"><i class="fa-solid fa-tags"></i></div>
                    <div>
                        <div class="trust-pill-title">Factory Price</div>
                        <div class="trust-pill-desc">Wholesale Rates</div>
                    </div>
                </div>

                <div class="trust-pill">
                    <div class="trust-pill-icon"><i class="fa-solid fa-truck-fast"></i></div>
                    <div>
                        <div class="trust-pill-title">Fast Delivery</div>
                        <div class="trust-pill-desc">Safe Transport</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</header>

<!-- 3. Mobile Fast Feature Strip (Scrolls smoothly on mobile screens) -->
<div class="mobile-feature-badges">
    <span class="mobile-badge-item"><i class="fa-solid fa-shield-halved text-warning me-1"></i> 100% Sivakasi</span>
    <span class="mobile-badge-item"><i class="fa-solid fa-percent text-danger me-1"></i> Direct Factory Rate</span>
    <span class="mobile-badge-item"><i class="fa-solid fa-truck-fast text-success me-1"></i> Express Shipping</span>
</div>

<main class="container my-3 flex-grow-1">
<?php 
if (function_exists('displayFlash')) {
    displayFlash(); 
}
?>