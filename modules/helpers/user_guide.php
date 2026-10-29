<?php
$pageTitle = 'ব্যবহার নির্দেশিকা ও ট্রেনিং ম্যানুয়াল';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* ==========================================================================
       Master Design System for User Guide & Training Manual
       Clean Slate & Indigo Architecture (Unified, Non-Generic, Bulletproof Layout)
       ========================================================================== */
    .guide-container {
        font-family: 'Hind Siliguri', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #1e293b;
        background-color: #f8fafc;
        line-height: 1.7;
    }

    /* Hero Banner */
    .guide-hero-banner {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #312e81 100%);
        border-radius: 20px;
        padding: 40px 36px;
        color: #ffffff;
        margin-bottom: 28px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
        overflow: hidden;
    }
    .guide-hero-banner::after {
        content: "";
        position: absolute;
        top: -60px;
        right: -60px;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(99, 102, 241, 0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .guide-hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 255, 255, 0.12);
        color: #e2e8f0;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 50px;
        backdrop-filter: blur(8px);
        margin-bottom: 12px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    /* Main Section Card */
    .manual-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 32px 30px;
        margin-bottom: 28px;
        box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
        box-sizing: border-box;
        overflow: hidden;
    }
    .manual-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 10px 26px -4px rgba(15, 23, 42, 0.08);
    }

    /* Bulletproof Card Header */
    .manual-card-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 18px;
        margin-bottom: 24px;
        border-bottom: 1px solid #f1f5f9;
        scroll-margin-top: 100px;
    }
    .manual-title-wrapper {
        display: flex;
        align-items: center;
        gap: 14px;
        flex: 1 1 320px;
        min-width: 0;
    }
    .manual-icon-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 46px;
        height: 46px;
        border-radius: 14px;
        font-size: 1.35rem;
        background: #f1f5f9;
        color: #4f46e5;
        flex-shrink: 0;
        border: 1px solid #e2e8f0;
    }
    .manual-title {
        margin: 0;
        font-size: 1.28rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.4;
        word-break: break-word;
    }
    .manual-subtitle {
        display: block;
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 500;
        margin-top: 3px;
    }
    .manual-header-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    /* Action Buttons (Unified Slate-Indigo) */
    .guide-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 7px 15px;
        border-radius: 9px;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .guide-action-btn:hover {
        background: #f8fafc;
        color: #4f46e5;
        border-color: #a5b4fc;
        transform: translateY(-1px);
    }
    .guide-action-btn.btn-primary-style {
        background: #4f46e5;
        color: #ffffff;
        border-color: #4338ca;
    }
    .guide-action-btn.btn-primary-style:hover {
        background: #4338ca;
        color: #ffffff;
        border-color: #3730a3;
    }

    /* Sub-Feature Cards (Spacious, Zero-Spillover Flexbox) */
    .guide-feature-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 22px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-sizing: border-box;
        overflow: hidden;
        word-wrap: break-word;
        overflow-wrap: break-word;
        min-width: 0;
        transition: all 0.2s ease;
    }
    .guide-feature-card:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 6px 18px -4px rgba(15, 23, 42, 0.06);
        transform: translateY(-2px);
    }
    .feature-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 12px;
    }
    .feature-card-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }
    .feature-card-desc {
        font-size: 0.92rem;
        color: #475569;
        line-height: 1.65;
        margin-bottom: 16px;
    }
    .feature-card-footer {
        padding-top: 14px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    /* Role Chips */
    .guide-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }
    .chip-admin {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
    }
    .chip-manager {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .chip-cashier {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    /* Circular Step Indicator */
    .step-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #4f46e5;
        color: #ffffff;
        font-weight: 700;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        box-shadow: 0 4px 10px -2px rgba(79, 70, 229, 0.4);
    }

    /* Informational Callout (Harmonious Tint) */
    .guide-callout {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #4f46e5;
        border-radius: 12px;
        padding: 20px 24px;
        margin: 22px 0;
        font-size: 0.96rem;
        line-height: 1.7;
        color: #334155;
    }
    .guide-callout-title {
        font-weight: 700;
        color: #0f172a;
        font-size: 1.02rem;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .guide-callout-warning {
        background: #fffbeb;
        border-color: #fde68a;
        border-left-color: #f59e0b;
        color: #78350f;
    }
    .guide-callout-warning .guide-callout-title {
        color: #92400e;
    }

    /* Clean Bullet List */
    .guide-bullet-list {
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
    }
    .guide-bullet-list li {
        position: relative;
        padding-left: 28px;
        margin-bottom: 12px;
        line-height: 1.68;
        font-size: 0.96rem;
        color: #334155;
    }
    .guide-bullet-list li::before {
        content: "\F287";
        font-family: "bootstrap-icons";
        position: absolute;
        left: 0;
        top: 3px;
        color: #4f46e5;
        font-size: 1.05rem;
    }

    /* Sticky Sidebar Navigation (TOC) */
    .guide-sticky-toc {
        position: sticky;
        top: 90px;
        max-height: calc(100vh - 110px);
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 22px 16px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
    }
    .toc-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
    }
    .toc-header h6 {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.88rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
    }
    .toc-link-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        color: #475569;
        font-weight: 500;
        font-size: 0.94rem;
        text-decoration: none;
        border-radius: 10px;
        margin-bottom: 4px;
        transition: all 0.2s ease;
    }
    .toc-link-item:hover {
        background-color: #f8fafc;
        color: #4f46e5;
        transform: translateX(3px);
    }
    .toc-link-item.active {
        background-color: #eef2ff;
        color: #4338ca;
        font-weight: 700;
    }

    /* FAQ Accordion */
    .guide-faq-accordion .accordion-item {
        border: 1px solid #e2e8f0;
        border-radius: 14px !important;
        margin-bottom: 14px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
    }
    .guide-faq-accordion .accordion-button {
        background: #ffffff;
        color: #0f172a;
        font-weight: 600;
        font-size: 1rem;
        padding: 18px 22px;
        box-shadow: none;
        gap: 12px;
    }
    .guide-faq-accordion .accordion-button:not(.collapsed) {
        background: #f8fafc;
        color: #4f46e5;
        border-bottom: 1px solid #e2e8f0;
    }
    .guide-faq-accordion .accordion-body {
        padding: 22px 24px;
        color: #334155;
        font-size: 0.96rem;
        line-height: 1.75;
    }
    .faq-q-number {
        font-size: 0.8rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #475569;
        flex-shrink: 0;
    }
    .guide-faq-accordion .accordion-button:not(.collapsed) .faq-q-number {
        background: #eef2ff;
        color: #4338ca;
    }

    /* Print Styles */
    @media print {
        .guide-sticky-toc, .btn-print-guide, .guide-search-wrapper, .manual-header-actions, .guide-action-btn { display: none !important; }
        .main-guide-column { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
        .manual-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; page-break-inside: avoid; }
        .guide-hero-banner { background: #0f172a !important; color: #fff !important; }
    }
</style>

<div class="container-fluid py-4 guide-container">
    
    <!-- Hero Banner -->
    <div class="guide-hero-banner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="guide-hero-pill">
                    <i class="bi bi-shield-check"></i> প্রাতিষ্ঠানিক ট্রেনিং ম্যানুয়াল ও রেফারেন্স গাইড
                </div>
                <h1 class="fw-bold mb-2 fs-2 text-white">
                    <i class="bi bi-journal-bookmark-fill me-2 text-indigo-400"></i> ব্যবহার নির্দেশিকা ও ট্রেনিং ম্যানুয়াল
                </h1>
                <p class="mb-0 fs-6 text-white-50" style="max-width: 800px;">
                    সফটওয়্যারটির প্রতিটি ফিচার, হিসাবরক্ষণের আন্তর্জাতিক নিয়ম এবং ব্যবসায়িক লেনদেন নিখুঁতভাবে পরিচালনার পূর্ণাঙ্গ সহায়িকা।
                </p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light btn-print-guide fw-bold px-4 py-2 text-dark shadow-sm d-flex align-items-center gap-2" onclick="window.print()">
                    <i class="bi bi-printer-fill"></i> প্রিন্ট ম্যানুয়াল
                </button>
            </div>
        </div>
    </div>

    <!-- Main Grid Layout -->
    <div class="row">
        
        <!-- Sticky Sidebar Navigation (TOC) -->
        <div class="col-xl-3 col-lg-4 mb-4 mb-lg-0">
            <div class="guide-sticky-toc" id="sidebarTOC">
                <div class="toc-header">
                    <h6><i class="bi bi-list-nested me-2 text-primary"></i> বিষয় সূচিপত্র</h6>
                    <span class="guide-chip chip-admin">৯টি পর্ব</span>
                </div>
                
                <nav class="nav flex-column" id="guide-nav">
                    <a class="toc-link-item" href="#sec-roles"><i class="bi bi-person-badge"></i> ১. ইউজার রোল ও দায়িত্ব</a>
                    <a class="toc-link-item" href="#sec-setup"><i class="bi bi-sliders"></i> ২. প্রাথমিক সেটআপ</a>
                    <a class="toc-link-item" href="#sec-inventory"><i class="bi bi-box-seam"></i> ৩. পণ্য ও ইনভেন্টরি</a>
                    <a class="toc-link-item" href="#sec-purchases"><i class="bi bi-truck"></i> ৪. সাপ্লায়ার ও ক্রয়</a>
                    <a class="toc-link-item" href="#sec-pos"><i class="bi bi-cart-check"></i> ৫. সেলস ও POS কাউন্টার</a>
                    <a class="toc-link-item" href="#sec-customers"><i class="bi bi-people"></i> ৬. কাস্টমার ও বকেয়া খতিয়ান</a>
                    <a class="toc-link-item" href="#sec-finance"><i class="bi bi-wallet2"></i> ৭. ব্যয়, ব্যাংকিং ও মূলধন</a>
                    <a class="toc-link-item" href="#sec-accounting"><i class="bi bi-diagram-3"></i> ৮. আন্তর্জাতিক ডাবল-এন্ট্রি</a>
                    <a class="toc-link-item" href="#sec-faq"><i class="bi bi-patch-question"></i> ৯. বিস্তারিত প্রশ্নোত্তর (FAQ)</a>
                </nav>
            </div>
        </div>

        <!-- Main Content Column -->
        <div class="col-xl-9 col-lg-8 main-guide-column">
            
            <!-- Real-time Filter Search -->
            <div class="guide-search-wrapper mb-4">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-primary ps-3">
                        <i class="bi bi-search fs-5"></i>
                    </span>
                    <input type="text" id="guideSearch" class="form-control border-start-0 ps-1 fs-6" placeholder="যেকোনো বিষয় লিখে সার্চ করুন (যেমন: বাকি, ডিসকাউন্ট, রিটার্ন, জাবেদা, ব্যালেন্স শিট, ট্রান্সফার)...">
                    <button class="btn btn-outline-secondary border-start-0 bg-white text-muted pe-3" type="button" id="clearSearch" style="display: none;">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div id="searchMatchCount" class="small text-muted mt-2 ps-1" style="display: none;"></div>
            </div>

            <div id="guide-cards-container">
            
                <!-- ==========================================
                     Section 1: User Roles & Access Matrix
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-roles">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-person-badge"></i></span>
                            <div>
                                <h2 class="manual-title">১. ইউজার প্যানেল ও ভূমিকা বিভাজন (Role Matrix)</h2>
                                <span class="manual-subtitle">অ্যাডমিন, ম্যানেজার ও ক্যাশিয়ারের দায়িত্ব এবং এক্সেস নিয়ন্ত্রণ</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/users/index.php') ?>" class="guide-action-btn">
                                <i class="bi bi-people me-1"></i> কর্মী পরিচালনা
                            </a>
                        </div>
                    </div>
                    
                    <p class="text-secondary mb-4">
                        প্রতিষ্ঠানের আর্থিক নিরাপত্তা, ব্যবসায়িক গোপনীয়তা এবং দৈনন্দিন কাজের গতি বজায় রাখতে সিস্টেমে দায়িত্বভিত্তিক ৩টি সুস্পষ্ট ইউজার প্যানেল রয়েছে। প্রতিটি কর্মীর অ্যাকাউন্টে নিজস্ব ইউজারনেম, পাসওয়ার্ড এবং ঐচ্ছিক 2FA সিকিউরিটি থাকে।
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title">অ্যাডমিন (Admin)</h3>
                                        <span class="guide-chip chip-admin">মালিক / সিইও</span>
                                    </div>
                                    <p class="feature-card-desc">
                                        প্রতিষ্ঠানের শীর্ষ নিয়ন্ত্রক। ব্যাংক স্থিতি, ইনভেস্টর ক্যাপিটাল, নিট লাভ-ক্ষতি (P&L), ব্যালেন্স শিট, কর্মী নিয়োগ ও পারমিশন এবং সিস্টেম সেটিংস দেখার সম্পূর্ণ অধিকার থাকে।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <span class="text-primary fw-medium small"><i class="bi bi-shield-check me-1"></i> ১০০% আনরেস্ট্রিক্টেড অ্যাক্সেস</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title">ম্যানেজার (Manager)</h3>
                                        <span class="guide-chip chip-manager">শাখা তদারককারী</span>
                                    </div>
                                    <p class="feature-card-desc">
                                        শাখার ইনভেন্টরি, পারচেজ অর্ডার রিসিভ, পণ্যের দাম ও স্টক নিরীক্ষা, এবং কাস্টমারদের বকেয়া কালেকশন পরিচালনা করে। চূড়ান্ত ব্যাংক ব্যালেন্স বা মালিকের ইকুইটি দেখতে পারে না।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <span class="text-success fw-medium small"><i class="bi bi-shop me-1"></i> শাখাভিত্তিক ইনভেন্টরি ও সেলস</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title">ক্যাশিয়ার (Cashier)</h3>
                                        <span class="guide-chip chip-cashier">কাউন্টার সেলস</span>
                                    </div>
                                    <p class="feature-card-desc">
                                        সেলস কাউন্টারে বসে বারকোড স্ক্যান করে বিক্রি, ক্যাশ বা ডিজিটাল পেমেন্ট গ্রহণ এবং রিসিট প্রিন্ট করে। পণ্যের মূল ক্রয়মূল্য (Cost Price) বা ব্যবসায়িক কোনো লাভ দেখতে পারে না।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <span class="text-muted fw-medium small"><i class="bi bi-lock me-1"></i> ক্রয়মূল্য ও লাভ সম্পূর্ণ গোপন</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     Section 2: Initial Setup
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-setup">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-sliders"></i></span>
                            <div>
                                <h2 class="manual-title">২. প্রাথমিক সেটআপ ও প্রস্তুতি (প্রথম দিন যা যা করবেন)</h2>
                                <span class="manual-subtitle">সফটওয়্যারে নিয়মিত বিলিং শুরুর আগে ৩টি আবশ্যিক কনফিগারেশন</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/settings/company.php') ?>" class="guide-action-btn">
                                <i class="bi bi-gear-fill me-1"></i> কোম্পানি সেটিংস
                            </a>
                        </div>
                    </div>
                    
                    <p class="text-secondary mb-3">
                        সফটওয়্যারটিতে নিয়মিত লেনদেন শুরু করার পূর্বে নিচের ৩টি প্রাথমিক ধাপ সম্পন্ন করে নিলে আপনার ইনভয়েস, স্টক এবং অ্যাকাউন্টিং রিপোর্ট প্রথম দিন থেকেই নিখুঁতভাবে চলবে:
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="step-circle">১</div>
                                    <h3 class="feature-card-title mb-2">কোম্পানি প্রোফাইল সেটআপ</h3>
                                    <p class="feature-card-desc">
                                        দোকানের নাম, ঠিকানা, অফিসিয়াল ফোন নম্বর এবং চালানের নিচে প্রিন্ট হওয়া "ধন্যবাদ" মেসেজ সেট করুন।
                                    </p>
                                </div>
                                <a href="<?= url('/modules/settings/company.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                    <i class="bi bi-pencil-square me-1"></i> কোম্পানি তথ্য দিন
                                </a>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="step-circle">২</div>
                                    <h3 class="feature-card-title mb-2">শাখা ও আউটলেট রেজিস্ট্রি</h3>
                                    <p class="feature-card-desc">
                                        ডিফল্টভাবে 'Head Office' শাখাটি চালু থাকে। আপনার যদি একাধিক শোরুম বা গুদাম থাকে, তবে তা যুক্ত করে নিন।
                                    </p>
                                </div>
                                <a href="<?= url('/modules/branches/list.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                    <i class="bi bi-shop me-1"></i> শাখা তালিকা দেখুন
                                </a>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="step-circle">৩</div>
                                    <h3 class="feature-card-title mb-2">আর্থিক অ্যাকাউন্ট স্থাপন</h3>
                                    <p class="feature-card-desc">
                                        আপনার দোকানের মূল ক্যাশ বাক্স (Cash in Hand) এবং ব্যাংক/বিকাশ মার্চেন্ট অ্যাকাউন্টগুলো সেট করে নিন।
                                    </p>
                                </div>
                                <a href="<?= url('/modules/financial_accounts/list.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                    <i class="bi bi-bank me-1"></i> অ্যাকাউন্ট সেটআপ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     Section 3: Inventory & Products
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-inventory">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-box-seam"></i></span>
                            <div>
                                <h2 class="manual-title">৩. পণ্য ও মজুত নিয়ন্ত্রণ (Dual-Stock Inventory Engine)</h2>
                                <span class="manual-subtitle">ক্যাটাগরি, বারকোড, এলার্ট কোয়ান্টিটি ও লাইভ গুদাম ব্যবস্থাপনা</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/categories/list.php') ?>" class="guide-action-btn">
                                <i class="bi bi-tags me-1"></i> ক্যাটাগরি
                            </a>
                            <a href="<?= url('/modules/products/create.php') ?>" class="guide-action-btn btn-primary-style">
                                <i class="bi bi-plus-circle me-1"></i> নতুন পণ্য
                            </a>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <h3 class="h6 fw-bold text-dark mb-3">নতুন পণ্য এন্ট্রি ও নিয়মাবলী:</h3>
                            <ul class="guide-bullet-list">
                                <li><strong>ক্যাটাগরি ও ইউনিট:</strong> পণ্য তৈরির পূর্বে <code>Categories</code> থেকে পণ্যের শ্রেণি এবং <code>Units</code> থেকে পরিমাপের একক (যেমন: Pieces, Box, Kg) তৈরি করে নিন।</li>
                                <li><strong>বারকোড ও এসকেইউ (SKU):</strong> নিজস্ব প্রস্তুতকৃত কোড লিখতে পারেন, অথবা খালি রাখলে সফটওয়্যার স্বয়ংক্রিয়ভাবে একটি ইউনিক বারকোড বানিয়ে দেবে যা স্টিকার প্রিন্টারে প্রিন্ট করা যায়।</li>
                                <li><strong>সতর্কতা মজুত (Alert Quantity):</strong> শোরুমে পণ্য কত পিসে নেমে আসলে ড্যাশবোর্ডে লাল সতর্কতা ফ্ল্যাগ উঠবে তা নির্দিষ্ট করে দেওয়া যায়।</li>
                                <li><strong>ব্রাঞ্চ অনুযায়ী মজুত:</strong> নতুন পণ্য এন্ট্রি করলে তা নির্বাচিত শাখায় স্বয়ংক্রিয় মজুতভুক্ত হয়।</li>
                            </ul>
                        </div>

                        <div class="col-12 col-lg-5">
                            <div class="guide-callout mt-0">
                                <div class="guide-callout-title">
                                    <i class="bi bi-cpu-fill text-primary"></i> ডুয়াল ইনভেন্টরি অটো-সিঙ্ক কীভাবে কাজ করে?
                                </div>
                                <p class="small text-muted mb-0">
                                    আমাদের সিস্টেমে কেন্দ্রীয় ডাটাবেসের <strong>মাস্টার স্টক</strong> এবং নির্দিষ্ট শোরুমের <strong>শাখা স্টক</strong> একযোগে কাজ করে। পিওএস কাউন্টারে যখনই কোনো সেল সম্পন্ন হয়, সিস্টেম তাৎক্ষণিকভাবে ঐ শাখার স্টক বিয়োগ করে দেয়। ফলে অন্য কোনো শাখায় বা গুদামে কোনো গরমিল তৈরি হয় না।
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-3 pt-3 border-top">
                        <a href="<?= url('/modules/products/list.php') ?>" class="guide-action-btn">
                            <i class="bi bi-box-seam me-1"></i> পণ্য তালিকা দেখুন
                        </a>
                        <a href="<?= url('/modules/products/barcode.php') ?>" class="guide-action-btn">
                            <i class="bi bi-upc-scan me-1"></i> বারকোড প্রিন্টার
                        </a>
                    </div>
                </div>

                <!-- ==========================================
                     Section 4: Purchases & Suppliers
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-purchases">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-truck"></i></span>
                            <div>
                                <h2 class="manual-title">৪. সাপ্লায়ার ও ক্রয় চালান (Procurement & Supplier Accounting)</h2>
                                <span class="manual-subtitle">অফিশিয়াল ক্রয় চালান, স্বয়ংক্রিয় স্টক বৃদ্ধি ও বাকি খতিয়ান</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/suppliers/list.php') ?>" class="guide-action-btn">
                                <i class="bi bi-people me-1"></i> সাপ্লায়ার তালিকা
                            </a>
                            <a href="<?= url('/modules/purchases/create.php') ?>" class="guide-action-btn btn-primary-style">
                                <i class="bi bi-cart-plus me-1"></i> নতুন ক্রয় চালান
                            </a>
                        </div>
                    </div>

                    <p class="text-secondary leading-relaxed mb-4">
                        ব্যবসার স্টক বাড়ানোর একমাত্র অফিশিয়াল এবং অডিট-প্রুফ পদ্ধতি হলো <strong>Purchases (ক্রয়)</strong> মডিউল। সরাসরি পণ্য এডিট করে স্টক বাড়ালে সাপ্লায়ারের বকেয়া হিসাব এবং ক্রয়মূল্য খতিয়ান নষ্ট হয়ে যায়।
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title">১. ক্রয় চালান এন্ট্রি</h3>
                                        <i class="bi bi-receipt text-primary"></i>
                                    </div>
                                    <p class="feature-card-desc mb-0">
                                        সাপ্লায়ার নির্বাচন করে প্রতিটি পণ্যের ক্রয়মূল্য ও মোট সংখ্যা দিয়ে চালান সংরক্ষণ করুন।
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title">২. স্বয়ংক্রিয় স্টক বৃদ্ধি</h3>
                                        <i class="bi bi-boxes text-success"></i>
                                    </div>
                                    <p class="feature-card-desc mb-0">
                                        চালানের স্ট্যাটাস 'Received' হওয়ামাত্রই নির্ধারিত শাখার গুদামে পণ্য স্বয়ংক্রিয়ভাবে যুক্ত হয়ে যায়।
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title">৩. লেজারে দেনা সংরক্ষণ</h3>
                                        <i class="bi bi-journal-check text-secondary"></i>
                                    </div>
                                    <p class="feature-card-desc mb-0">
                                        যদি নগদ পরিশোধ না করেন, তবে সম্পূর্ণ বিলটি সাপ্লায়ারের খতিয়ানে বকেয়া (Accounts Payable) হিসেবে জমা থাকে।
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-callout mb-0">
                        <div class="guide-callout-title">
                            <i class="bi bi-cash-stack text-primary"></i> সাপ্লায়ারের বকেয়া পরিশোধের নিয়ম:
                        </div>
                        <p class="mb-0">
                            পরবর্তীতে সাপ্লায়ারকে নগদ, ব্যাংক বা বিকাশের মাধ্যমে বিল পরিশোধ করতে চাইলে 
                            <code>Suppliers > Pay Supplier</code> অথবা <code>Quick Transaction</code> বাটনে যান। কোন ব্যাংক বা ক্যাশ থেকে কত টাকা দিলেন তা সিলেক্ট করলেই আপনার ব্যাংক ব্যালেন্স থেকে টাকা কর্তন হবে এবং সাপ্লায়ারের লেজারে বাকি কমে যাবে।
                        </p>
                    </div>
                </div>

                <!-- ==========================================
                     Section 5: POS & Sales Terminal
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-pos">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-cart-check"></i></span>
                            <div>
                                <h2 class="manual-title">৫. সেলস কাউন্টার ও দ্রুত বিলিং (POS Terminal Billing)</h2>
                                <span class="manual-subtitle">বারকোড স্ক্যানিং, ডিসকাউন্ট, স্প্লিট পেমেন্ট ও সেলস রিটার্ন</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/sales/pos.php') ?>" class="guide-action-btn btn-primary-style">
                                <i class="bi bi-cart-check-fill me-1"></i> POS কাউন্টার খুলুন
                            </a>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <h3 class="h6 fw-bold text-dark mb-3">পিওএস কাউন্টারের শক্তিশালী বৈশিষ্ট্যসমূহ:</h3>
                            <ul class="guide-bullet-list">
                                <li><strong>লাইভ বারকোড স্ক্যানিং:</strong> কাউন্টারে বারকোড রিডার দিয়ে প্রোডাক্টে স্ক্যান করামাত্রই তা নিমেষে কার্টে জমা হয়।</li>
                                <li><strong>ফ্ল্যাট ও শতাংশ ডিসকাউন্ট:</strong> নির্দিষ্ট কোনো আইটেমের উপর অথবা সম্পূর্ণ চালানের উপর নগদ টাকা বা শতকরা হারে ছাড় দেওয়া যায়।</li>
                                <li><strong>স্প্লিট পেমেন্ট (Split Payment):</strong> কোনো ক্রেতা যদি ১০০০ টাকার বিলে ৫০০ টাকা ক্যাশ এবং ৫০০ টাকা বিকাশ বা কার্ডে দিতে চান, একই সাথে একাধিক পেমেন্ট মাধ্যমে গ্রহণ করার ব্যবস্থা।</li>
                                <li><strong>স্বয়ংক্রিয় বকেয়া পোস্টিং (Auto Due):</strong> ক্রেতা আংশিক টাকা পরিশোধ করলে (যেমন ১০০০ টাকার মধ্যে ৭০০ টাকা দিল), Paid বক্সে শুধু ৭০০ লিখবেন। সিস্টেম নিজে থেকেই বাকি ৩০০ টাকা ক্রেতার নামে বকেয়া লেজারে এন্ট্রি করে দেবে।</li>
                                <li><strong>ড্রাফট ও হোল্ড সেল:</strong> এক কাস্টমারের বিল করার মাঝেই অন্য কাস্টমারের ভিড় আসলে চলমান বিলটি 'Hold' করে রেখে পরবর্তীতে রিস্টোর করা যায়।</li>
                            </ul>
                        </div>

                        <div class="col-12 col-lg-5">
                            <div class="guide-feature-card bg-white">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-arrow-return-left text-primary me-2"></i>বিক্রি ফেরত (Sales Return)</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        কাস্টমার মাল ফেরত দিতে আসলে <code>Sales > Sales Returns</code> পেজে যান। ইনভয়েস নম্বর লিখে ফেরত দেওয়া পণ্যের সংখ্যা সাবমিট করলেই স্টক স্বয়ংক্রিয় গুদামে ফেরত চলে আসবে এবং কাস্টমারের টাকা/লেজার নিখুঁতভাবে সমন্বয় হয়ে যাবে।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <a href="<?= url('/modules/sales/list.php') ?>" class="guide-action-btn w-50 justify-content-center">
                                        <i class="bi bi-receipt me-1"></i> চালান তালিকা
                                    </a>
                                    <a href="<?= url('/modules/sales/returns/index.php') ?>" class="guide-action-btn btn-primary-style w-50 justify-content-center">
                                        <i class="bi bi-arrow-return-left me-1"></i> সেলস রিটার্ন
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     Section 6: Customers & Ledgers
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-customers">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-people"></i></span>
                            <div>
                                <h2 class="manual-title">৬. কাস্টমার ও দেনা-পাওনা খতিয়ান (Accounts Receivable)</h2>
                                <span class="manual-subtitle">ওয়াক-ইন বনাম নিবন্ধিত খদ্দের, বকেয়া আদায় ও ডিজিটাল স্টেটমেন্ট</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/customers/list.php') ?>" class="guide-action-btn">
                                <i class="bi bi-person-lines-fill me-1"></i> কাস্টমার তালিকা
                            </a>
                            <a href="<?= url('/modules/reports/customer_ledger.php') ?>" class="guide-action-btn">
                                <i class="bi bi-journal-text me-1"></i> লেজার খতিয়ান
                            </a>
                        </div>
                    </div>

                    <p class="text-secondary leading-relaxed mb-4">
                        সিস্টেমে দুই ধরনের ক্রেতা ব্যবস্থাপনা রয়েছে: <strong>Walk-in Customer</strong> (যারা সাথে সাথে ক্যাশ টাকা দিয়ে পণ্য নিয়ে যায়, আলাদা লেজার রাখার প্রয়োজন পড়ে না) এবং <strong>Registered Customer</strong> (যাদের নাম, ঠিকানা, বকেয়া এবং লেনদেনের সম্পূর্ণ ডিজিটাল লেজার খতিয়ান ট্র্যাক করা হয়)।
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>বকেয়া কালেকশন (Receive Payment)</h3>
                                    </div>
                                    <p class="feature-card-desc mb-0">
                                        বাকি থাকা কাস্টমার যখন দোকানে এসে টাকা পরিশোধ করবে, তখন <code>Quick Transaction</code> অথবা কাস্টমার প্রোফাইলে গিয়ে কাস্টমারের নাম, টাকার অঙ্ক এবং কোন অ্যাকাউন্টে (ক্যাশ ড্রয়ার নাকি ব্যাংক) জমা হলো তা নির্বাচন করুন। তাৎক্ষণিক ডিজিটাল রিসিট জেনারেট হবে।
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-palette text-secondary me-2"></i>লেজারের কালার কোডের অর্থ</h3>
                                    </div>
                                    <p class="feature-card-desc mb-0">
                                        কাস্টমার বা সাপ্লায়ারের তালিকায় <strong>লাল রঙ (Due)</strong> থাকা মানে পাওনা টাকা বা দেনাদার অবস্থা। আর <strong>সবুজ রঙ (Advance)</strong> থাকা মানে গ্রাহক অগ্রিম জমা দিয়ে রেখেছে বা আপনি সাপ্লায়ারকে অগ্রিম দিয়ে রেখেছেন।
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     Section 7: Finance, Capital & Expenses
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-finance">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-wallet2"></i></span>
                            <div>
                                <h2 class="manual-title">৭. ব্যয়, ব্যাংকিং ও মূলধন ব্যবস্থাপনা (Finance & Capital)</h2>
                                <span class="manual-subtitle">দোকান খরচ, অভ্যন্তরীণ ফান্ড স্থানান্তর ও পার্টনারদের মূলধন হিসাব</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/expenses/create.php') ?>" class="guide-action-btn">
                                <i class="bi bi-dash-circle me-1"></i> খরচ এন্ট্রি
                            </a>
                            <a href="<?= url('/modules/capital/index.php') ?>" class="guide-action-btn">
                                <i class="bi bi-bank me-1"></i> ক্যাপিটাল লেজার
                            </a>
                        </div>
                    </div>

                    <p class="text-secondary leading-relaxed mb-4">
                        একটি ব্যবসার প্রতিদিনের ক্ষুদ্র ব্যয় থেকে শুরু করে শোরুমের ক্যাশ ব্যাংকে পাঠানো এবং মালিকদের ব্যক্তিগত মূলধন উত্তোলন—প্রতিটি বিষয় সিস্টেমে আলাদা মডিউল দ্বারা পরিচালিত হয়:
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-receipt text-danger me-2"></i>দোকান ও পরিচালন ব্যয়</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        চা-নাস্তা, কর্মচারীর বেতন, বিদ্যুৎ বিল, দোকান ভাড়া বা অন্যান্য দৈনন্দিন খরচ এন্ট্রি দিন। কোন অ্যাকাউন্ট (ক্যাশ বা ব্যাংক) থেকে টাকা গেল তা নির্ধারণ করলে স্বয়ংক্রিয় টাকা কর্তন হবে।
                                    </p>
                                </div>
                                <a href="<?= url('/modules/expenses/list.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                    <i class="bi bi-receipt me-1"></i> খরচ তালিকা দেখুন
                                </a>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-arrow-left-right text-primary me-2"></i>অভ্যন্তরীণ ফান্ড স্থানান্তর</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        শোরুমের ক্যাশ ড্রয়ার থেকে টাকা নিয়ে ব্যাংকে জমা দেওয়া, অথবা ব্যাংক থেকে টাকা তুলে ক্যাশে আনা। এতে কোনো লাভ-ক্ষতি হয় না, কেবল ব্যালেন্স স্থানান্তরিত হয়।
                                    </p>
                                </div>
                                <a href="<?= url('/modules/finance/quick_adjustment.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                    <i class="bi bi-arrow-left-right me-1"></i> দ্রুত লেনদেন করুন
                                </a>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="guide-feature-card">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-cash-coin text-success me-2"></i>মালিকানা ও মূলধন (Equity)</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        ব্যবসায় পার্টনারের নতুন বিনিয়োগ (Capital Injection) গ্রহণ করা কিংবা ব্যক্তিগত প্রয়োজনে মুনাফা উত্তোলন (Drawings)। পার্টনারভিত্তিক ব্যক্তিগত খতিয়ান সংরক্ষিত থাকে।
                                    </p>
                                </div>
                                <a href="<?= url('/modules/capital/index.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                    <i class="bi bi-bank me-1"></i> মূলধন লেজার দেখুন
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     Section 8: Double-Entry Accounting Core
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-accounting">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-diagram-3"></i></span>
                            <div>
                                <h2 class="manual-title">৮. আন্তর্জাতিক ডাবল-এন্ট্রি অ্যাকাউন্টিং কোর (Double-Entry Engine)</h2>
                                <span class="manual-subtitle">ব্যাকগ্রাউন্ড জাবেদা, স্বয়ংক্রিয় COGS ও সিএ-লেভেল ব্যালেন্স শিট</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <a href="<?= url('/modules/accounting/chart_of_accounts.php') ?>" class="guide-action-btn">
                                <i class="bi bi-diagram-3 me-1"></i> চার্ট অব অ্যাকাউন্টস
                            </a>
                            <a href="<?= url('/modules/reports/profit_loss.php') ?>" class="guide-action-btn btn-primary-style">
                                <i class="bi bi-graph-up me-1"></i> লাভ-ক্ষতি রিপোর্ট
                            </a>
                        </div>
                    </div>

                    <div class="guide-callout mt-0 mb-4">
                        <div class="guide-callout-title">
                            <i class="bi bi-lightbulb text-primary"></i> ব্যাকগ্রাউন্ড জাবেদা কীভাবে কাজ করে এবং ব্যবসায়ীর কী লাভ?
                        </div>
                        <p class="mb-2">
                            প্রচলিত সাধারণ সফটওয়্যারে কেবল একটি কাঁচা খাতা বা ক্যাশ হিসাব থাকে। কিন্তু আমাদের সফটওয়্যারটি আন্তর্জাতিক <strong>Double-Entry Bookkeeping (দ্বৈত দাখিলা পদ্ধতি)</strong> নীতিতে তৈরি।
                        </p>
                        <p class="mb-0 text-muted">
                            <strong>বাস্তব উদাহরণ:</strong> যখন কাউন্টারে ক্যাশিয়ার ১,০০০ টাকার নগদ পণ্য বিক্রি করে, আপনাকে আলাদা করে কোনো হিসাব লিখতে হয় না। সিস্টেম স্বয়ংক্রিয়ভাবে ব্যাকগ্রাউন্ডে:
                            <br>• <code>Cash in Hand (Asset)</code>-কে <strong>১,০০০ টাকা ডেবিট</strong> (নগদ সম্পদ বৃদ্ধি) করে, এবং
                            <br>• <code>Sales Revenue (Income)</code>-কে <strong>১,০০০ টাকা ক্রেডিট</strong> (প্রতিষ্ঠানের আয় বৃদ্ধি) করে।
                            <br>একই সাথে বিক্রি হওয়া পণ্যের ক্রয়মূল্য স্বয়ংক্রিয়ভাবে <code>Cost of Goods Sold (COGS)</code> হিসেবে ডেবিট হয়ে মোট বিক্রি থেকে বাদ যায়। ফলে দিন বা মাস শেষে কোনো হিসাবরক্ষক ছাড়াই ১ ক্লিকে <strong>লাভ-ক্ষতি বিবরণী (P&L)</strong> ও <strong>ব্যালেন্স শিট</strong> স্বয়ংক্রিয় নিখুঁতভাবে তৈরি হয়ে যায়!
                        </p>
                    </div>

                    <!-- Spacious 2x2 Grid (Immune to Overflows) -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="guide-feature-card bg-white">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-list-columns text-primary me-2"></i>চার্ট অব অ্যাকাউন্টস (COA)</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        Assets (সম্পদ), Liabilities (দায়), Equity (মূলধন), Revenue (আয়) এবং Expenses (ব্যয়)—এই ৫টি আন্তর্জাতিক গ্রুপে সাজানো সুবিন্যস্ত লেজার কোড।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <a href="<?= url('/modules/accounting/chart_of_accounts.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                        <i class="bi bi-diagram-3 me-1"></i> চার্ট অব অ্যাকাউন্টস দেখুন
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="guide-feature-card bg-white">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-journal-plus text-primary me-2"></i>ম্যানুয়াল জার্নাল (Manual Journal)</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        বিশেষ আর্থিক সমন্বয়ের জন্য সরাসরি ডেবিট-ক্রেডিট এন্ট্রি। অ্যাকাউন্টিং সমীকরণের নিয়ম অনুযায়ী মোট ডেবিট ও ক্রেডিট সমান না হলে এন্ট্রি সেভ হবে না।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <a href="<?= url('/modules/accounting/manual_journal.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                        <i class="bi bi-journal-plus me-1"></i> জার্নাল এন্ট্রি করুন
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="guide-feature-card bg-white">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>ট্রায়াল ব্যালেন্স (Trial Balance)</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        প্রতিষ্ঠানের সকল খতিয়ানের ডেবিট ও ক্রেডিট ব্যালেন্স সমান আছে কিনা তা যাচাই করার গাণিতিক শিট। এটি আর্থিক গরমিল শূন্যে নামিয়ে আনে।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <a href="<?= url('/modules/accounting/trial_balance.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> ট্রায়াল ব্যালেন্স দেখুন
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="guide-feature-card bg-white">
                                <div>
                                    <div class="feature-card-header">
                                        <h3 class="feature-card-title"><i class="bi bi-bank2 text-primary me-2"></i>ব্যালেন্স শিট ও পিরিয়ড লকিং</h3>
                                    </div>
                                    <p class="feature-card-desc">
                                        নির্দিষ্ট তারিখে প্রতিষ্ঠানের মোট সম্পদ বনাম মোট দায় ও মূলধনের চূড়ান্ত দলিল। বছর বা মাস শেষে পূর্বের হিসাব লক করে টেম্পারিং রোধ করা যায়।
                                    </p>
                                </div>
                                <div class="feature-card-footer">
                                    <a href="<?= url('/modules/accounting/balance_sheet.php') ?>" class="guide-action-btn w-100 justify-content-center">
                                        <i class="bi bi-bank2 me-1"></i> ব্যালেন্স শিট খুলুন
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     Section 9: Practical FAQs (12 Questions)
                     ========================================== -->
                <div class="manual-card searchable-card" id="sec-faq">
                    <div class="manual-card-header">
                        <div class="manual-title-wrapper">
                            <span class="manual-icon-box"><i class="bi bi-patch-question"></i></span>
                            <div>
                                <h2 class="manual-title">৯. বিস্তারিত প্রশ্নোত্তর ও সমস্যা সমাধান (Practical FAQ)</h2>
                                <span class="manual-subtitle">দোকান ও ব্যবসা পরিচালনার বাস্তব ১২টি সমস্যার সুস্পষ্ট সমাধান</span>
                            </div>
                        </div>
                        <div class="manual-header-actions">
                            <span class="guide-chip chip-admin">১২টি ব্যবহারিক সমাধান</span>
                        </div>
                    </div>

                    <p class="text-secondary leading-relaxed mb-4">
                        দোকান ও ব্যবসা পরিচালনার সময় যেসব বাস্তব প্রশ্ন বা পরিস্থিতির মুখোমুখি হতে হয়, তার সহজ ও সঠিক সমাধান নিচে দেওয়া হলো:
                    </p>

                    <div class="accordion guide-faq-accordion" id="comprehensiveFaqAccordion">
                        
                        <!-- FAQ 1 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                    <span class="faq-q-number">প্রশ্ন ০১</span>
                                    <span>ভুলে কোনো সেলস ইনভয়েস বা চালান ভুল হয়ে গেলে বা কাস্টমার না নিলে কী করব?</span>
                                </button>
                            </h3>
                            <div id="q1" class="accordion-collapse collapse show" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>সহজ সমাধান:</strong> <code>Sales > Orders & Invoices</code> পেজে যান। যে চালানটি বাতিল করতে চান তার ডান পাশে থাকা মেনু থেকে <strong>Cancel</strong> বাটনে চাপ দিন। চালান বাতিল করার সাথে সাথে সফটওয়্যারটি স্বয়ংক্রিয়ভাবে বিক্রি হওয়া পণ্যগুলো গুদামের স্টকে ফেরত যোগ করে দেবে এবং কাস্টমারের হিসাব থেকে টাকার দাবি বাতিল করে দেবে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 2 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                    <span class="faq-q-number">প্রশ্ন ০২</span>
                                    <span>সফটওয়্যার শুরুর আগের পুরানো বকেয়া (Old Due) কীভাবে এন্ট্রি দেব?</span>
                                </button>
                            </h3>
                            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>নিয়ম:</strong> নতুন কোনো কাস্টমার বা সাপ্লায়ার তৈরি করার সময় ফর্মে একটি <strong>Opening Balance</strong> ফিল্ড থাকে। সেখানে সফটওয়্যার চালু করার পূর্বের বকেয়া টাকার অঙ্ক লিখে সেভ করুন। কিন্তু সফটওয়্যার চালু করার পর কোনো চলমান লেনদেনের টাকা ওপেনিং ব্যালেন্সে লিখবেন না; সেগুলো যথারীতি <code>Receive Payment</code> বা <code>Quick Transaction</code>-এর মাধ্যমে এন্ট্রি দেবেন।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 3 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                                    <span class="faq-q-number">প্রশ্ন ০৩</span>
                                    <span>একই বিলে কাস্টমার কিছু টাকা নগদ এবং কিছু টাকা বিকাশ বা কার্ডে দিলে কীভাবে বিল করব?</span>
                                </button>
                            </h3>
                            <div id="q3" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>পদ্ধতি:</strong> পিওএস টার্মিনালে চেকআউট করার সময় পেমেন্ট অপশনে আমাদের <strong>Split Payment</strong> সুবিধা রয়েছে। সেখানে আপনি Cash ড্রপডাউনে নগদ দেওয়া টাকার পরিমাণ এবং Mobile Banking ড্রপডাউনে বিকাশের টাকা আলাদাভাবে লিখে সাবমিট করতে পারবেন। এতে আপনার ক্যাশ বাক্সে এবং বিকাশ মার্চেন্ট অ্যাকাউন্টে যার যার ভাগ অনুযায়ী ব্যালেন্স জমা হবে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 4 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4">
                                    <span class="faq-q-number">প্রশ্ন ০৪</span>
                                    <span>কাস্টমার যদি পণ্য ফেরত (Sales Return) দিতে আসে, তবে কীভাবে অ্যাডজাস্ট করব?</span>
                                </button>
                            </h3>
                            <div id="q4" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>পদ্ধতি:</strong> <code>Sales > Sales Returns > Create Return</code> পেজে যান। আগের সেলস চালানের রেফারেন্স বা কাস্টমার সিলেক্ট করে কোন পণ্যটি কয় পিস ফেরত এসেছে তা দিন। কাস্টমার যদি বাকি কাস্টমার হয়, তবে তার খতিয়ানে বকেয়া কমে যাবে; আর নগদ কাস্টমার হলে ক্যাশ থেকে রিফান্ড দেওয়ার অপশন সিলেক্ট করবেন। পণ্যটি আবার শোরুমের লাইভ স্টকে যোগ হয়ে যাবে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 5 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5">
                                    <span class="faq-q-number">প্রশ্ন ০৫</span>
                                    <span>শোরুমের ক্যাশ ড্রয়ার থেকে টাকা ব্যাংকে জমা দিলে বা এক অ্যাকাউন্ট থেকে অন্য অ্যাকাউন্টে সরালে কী করব?</span>
                                </button>
                            </h3>
                            <div id="q5" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>পদ্ধতি:</strong> এটি কোনো ব্যবসায়িক খরচ (Expense) নয়; এটি একটি অভ্যন্তরীণ স্থানান্তর। এর জন্য <code>Quick Transaction</code> অথবা অ্যাকাউন্টিং জার্নাল মেনুতে যান। <strong>From Account</strong>-এ 'Cash in Hand' এবং <strong>To Account</strong>-এ আপনার 'Bank Account' নির্বাচন করে টাকার পরিমাণ লিখুন। ড্রয়ারের ক্যাশ কমে যাবে এবং ব্যাংক ব্যালেন্স বৃদ্ধি পাবে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 6 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q6">
                                    <span class="faq-q-number">প্রশ্ন ০৬</span>
                                    <span>ব্যবসার মালিক বা পার্টনার ব্যক্তিগত প্রয়োজনে ক্যাশ বা ব্যাংক থেকে টাকা তুললে কীভাবে তুলব?</span>
                                </button>
                            </h3>
                            <div id="q6" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>পদ্ধতি:</strong> মালিকের ব্যক্তিগত খরচ কখনো দোকানের দৈনন্দিন খরচে (Expense) ফেলা উচিত নয়, কারণ এতে প্রকৃত ব্যবসায়িক লাভ কমে দেখায়। এর জন্য <code>Capital & Loans</code> মেনুতে যান। সেখানে নির্দিষ্ট পার্টনার বা মালিকের প্রোফাইলে গিয়ে <strong>Withdrawal (উত্তোলন)</strong> নির্বাচন করে এন্ট্রি দিন। এতে মালিকের মূলধন খতিয়ান থেকে টাকা কমবে এবং ব্যাংকিং ব্যালেন্স কমবে, কিন্তু দোকানের পিএন্ডএল লাভ অক্ষত থাকবে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 7 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q7">
                                    <span class="faq-q-number">প্রশ্ন ০৭</span>
                                    <span>দোকানের প্রাত্যহিক খরচ (চা-নাস্তা, বিল, বেতন) দিলে ক্যাশ ব্যালেন্সের কী হয়?</span>
                                </button>
                            </h3>
                            <div id="q7" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>পদ্ধতি:</strong> <code>Expenses > Add Expense</code>-এ গিয়ে খরচের খাত (Expense Category) এবং কোন ব্যাংক বা ক্যাশ থেকে টাকা গেল তা নির্বাচন করে এন্ট্রি দিন। এর সাথে সাথে সংশ্লিষ্ট অ্যাকাউন্টের ব্যালেন্স কমে যাবে এবং এটি সরাসরি মাসের লাভ-ক্ষতি বিবরণীতে পরিচালন ব্যয় হিসেবে অন্তর্ভুক্ত হবে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 8 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q8">
                                    <span class="faq-q-number">প্রশ্ন ০৮</span>
                                    <span>ক্যাশিয়ার কি পণ্যের আসল কেনা দাম বা লাভ দেখতে পারে?</span>
                                </button>
                            </h3>
                            <div id="q8" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>না, একদমই নয়!</strong> ক্যাশিয়ারের প্যানেলে বিক্রয়মূল্য ছাড়া পণ্যের মূল ক্রয়মূল্য, লাভ বা ব্যাংক ব্যালেন্সের কোনো মেনুই থাকে না। এটি সম্পূর্ণভাবে অ্যাডমিন এবং অনুমতিপ্রাপ্ত ম্যানেজারদের জন্য সুরক্ষিত থাকে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 9 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q9">
                                    <span class="faq-q-number">প্রশ্ন ০৯</span>
                                    <span>সাময়িক সময়ের জন্য ইন্টারনেট না থাকলে কি পিওএস কাউন্টারে বিক্রি চালু রাখা যায়?</span>
                                </button>
                            </h3>
                            <div id="q9" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>হ্যাঁ!</strong> সফটওয়্যারটি একটি সর্বাধুনিক <strong>PWA (Progressive Web App)</strong> প্রযুক্তি সমর্থন করে। এটি যেকোনো কম্পিউটার, ট্যাবলেট বা স্মার্টফোনে 'Install App' হিসেবে ইনস্টল করা যায় এবং নেটওয়ার্ক সাময়িক ড্রপ করলেও পিওএস স্ক্রিন লোড থাকে।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 10 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q10">
                                    <span class="faq-q-number">প্রশ্ন ১০</span>
                                    <span>লাভ-ক্ষতি রিপোর্টে মোট লাভ (Gross Profit) এবং নিট লাভের (Net Profit) পার্থক্য কী?</span>
                                </button>
                            </h3>
                            <div id="q10" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>সহজ ব্যাখ্যা:</strong> 
                                    <br>• <strong>মোট লাভ (Gross Profit):</strong> মোট পণ্য বিক্রি থেকে বিক্রিত পণ্যের মূল ক্রয়মূল্য (COGS) বাদ দিলে যা থাকে তা হলো মোট লাভ।
                                    <br>• <strong>নিট লাভ (Net Profit):</strong> মোট লাভ থেকে দোকানের সমস্ত পরিচালন খরচ (দোকান ভাড়া, স্টাফ বেতন, বিদ্যুৎ বিল, যাতায়াত ইত্যাদি) বাদ দেওয়ার পর দিন বা মাস শেষে মালিকের পকেটে যে আসল টাকা থাকে, তাই হলো প্রকৃত নিট লাভ। সফটওয়্যারটি রিয়েল-টাইমে এই দুটিই হিসাব করে দেখায়।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 11 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q11">
                                    <span class="faq-q-number">প্রশ্ন ১১</span>
                                    <span>কোনো স্টাফকে সাময়িক বরখাস্ত করতে চাইলে বা পাসওয়ার্ড ভুলে গেলে করণীয় কী?</span>
                                </button>
                            </h3>
                            <div id="q11" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>পদ্ধতি:</strong> <code>Users</code> পেজে যান। কর্মীর নামের পাশে এডিট বাটনে ক্লিক করে 'Status' কে 'Inactive' করে দিন। এর সাথে সাথে ঐ কর্মীর চলতি সেশন স্বয়ংক্রিয় টার্মিনেট হয়ে যাবে এবং সে আর লগইন করতে পারবে না। পাসওয়ার্ড রিসেট করতে চাইলে একই পেজ থেকে নতুন পাসওয়ার্ড লিখে সেভ করতে পারবেন।
                                </div>
                            </div>
                        </div>

                        <!-- FAQ 12 -->
                        <div class="accordion-item faq-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q12">
                                    <span class="faq-q-number">প্রশ্ন ১২</span>
                                    <span>মাস বা অর্থবছর শেষে পিরিয়ড লকিং (Period Locking) করা কেন জরুরি?</span>
                                </button>
                            </h3>
                            <div id="q12" class="accordion-collapse collapse" data-bs-parent="#comprehensiveFaqAccordion">
                                <div class="accordion-body">
                                    <strong>পদ্ধতি ও নিরাপত্তা:</strong> এক মাসের বা এক বছরের হিসাব যখন চূড়ান্তভাবে অডিট শেষ হয়ে যায়, তখন <code>Accounting > Period Locking</code>-এ গিয়ে ঐ মাস বা অর্থবছরটি লক করে দেওয়া যায়। লক করে দিলে অসদুপায়ে বা ভুলবশত কেউ আর পুরনো তারিখে কোনো বিল কাটতে, এডিট করতে বা টাকা বদলাতে পারবে না।
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Live Instant Search Filter (Filters both Cards and FAQs seamlessly)
    const searchInput = document.getElementById('guideSearch');
    const clearBtn = document.getElementById('clearSearch');
    const matchCountEl = document.getElementById('searchMatchCount');
    const cards = document.querySelectorAll('.searchable-card');
    const faqItems = document.querySelectorAll('.faq-item');

    function executeSearch(query) {
        query = query.toLowerCase().trim();

        if (clearBtn) {
            clearBtn.style.display = query.length > 0 ? 'block' : 'none';
        }

        if (query === '') {
            cards.forEach(card => card.style.display = 'block');
            faqItems.forEach(item => {
                item.style.display = 'block';
                const collapse = item.querySelector('.accordion-collapse');
                // Keep default state
            });
            if (matchCountEl) matchCountEl.style.display = 'none';
            return;
        }

        let totalMatches = 0;

        // Filter Section Cards
        cards.forEach(card => {
            const cardText = card.textContent.toLowerCase();
            if (cardText.includes(query)) {
                card.style.display = 'block';
                totalMatches++;
            } else {
                card.style.display = 'none';
            }
        });

        // Filter individual FAQs inside section 9
        let faqMatches = 0;
        faqItems.forEach(item => {
            const itemText = item.textContent.toLowerCase();
            const collapse = item.querySelector('.accordion-collapse');
            const button = item.querySelector('.accordion-button');

            if (itemText.includes(query)) {
                item.style.display = 'block';
                faqMatches++;
                if (collapse && !collapse.classList.contains('show')) {
                    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapse);
                    bsCollapse.show();
                }
            } else {
                item.style.display = 'none';
            }
        });

        // If any FAQ matched, make sure FAQ card itself is visible
        const faqCard = document.getElementById('sec-faq');
        if (faqMatches > 0 && faqCard) {
            faqCard.style.display = 'block';
        }

        if (matchCountEl) {
            matchCountEl.style.display = 'block';
            matchCountEl.innerHTML = `<i class="bi bi-info-circle me-1"></i> "${query}" সম্পর্কিত <strong>${totalMatches}</strong> টি সেকশন ও <strong>${faqMatches}</strong> টি সমাধান পাওয়া গেছে।`;
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            executeSearch(this.value);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            executeSearch('');
        });
    }

    // 2. Smooth Scroll & Active TOC State Indicator
    const tocLinks = document.querySelectorAll('.toc-link-item');
    window.addEventListener('scroll', () => {
        let current = '';
        cards.forEach(card => {
            const cardTop = card.offsetTop - 130;
            if (window.pageYOffset >= cardTop) {
                current = card.getAttribute('id');
            }
        });
        
        tocLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
