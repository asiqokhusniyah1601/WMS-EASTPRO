@extends('layouts.app')

@section('title', 'About Us | WMS Easy Go')

@section('styles')
<style>
    /* ===== KEYFRAME ANIMATIONS ===== */
    @keyframes heroFloat {
        0%, 100% { transform: translateY(0px); }
        50%       { transform: translateY(-8px); }
    }
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(32px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeSlideLeft {
        from { opacity: 0; transform: translateX(-24px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    @keyframes pulseRing {
        0%   { transform: scale(1);   opacity: 0.5; }
        70%  { transform: scale(1.4); opacity: 0; }
        100% { transform: scale(1.4); opacity: 0; }
    }
    @keyframes shimmer {
        0%   { background-position: -200% center; }
        100% { background-position:  200% center; }
    }
    @keyframes orb1 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33%       { transform: translate(30px, -20px) scale(1.1); }
        66%       { transform: translate(-20px, 15px) scale(0.95); }
    }
    @keyframes orb2 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33%       { transform: translate(-25px, 20px) scale(1.05); }
        66%       { transform: translate(20px, -15px) scale(1.1); }
    }
    @keyframes connectorPulse {
        0%, 100% { opacity: 0.4; }
        50%       { opacity: 1; }
    }
    @keyframes cardEntrance {
        from { opacity: 0; transform: translateY(40px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0)  scale(1); }
    }

    /* ===== HERO SECTION ===== */
    .about-hero {
        position: relative;
        padding: 70px 48px 60px;
        border-radius: 28px;
        overflow: hidden;
        margin-bottom: 36px;
        color: white;
        text-align: center;
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 45%, #6366f1 100%);
        box-shadow: 0 24px 64px rgba(59,130,246,0.30);
    }

    /* animated orbs inside hero */
    .about-hero .orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        pointer-events: none;
    }
    .about-hero .orb-1 {
        width: 320px; height: 320px;
        top: -80px; left: -80px;
        background: rgba(99,102,241,0.45);
        animation: orb1 8s ease-in-out infinite;
    }
    .about-hero .orb-2 {
        width: 260px; height: 260px;
        bottom: -60px; right: -60px;
        background: rgba(16,185,129,0.30);
        animation: orb2 10s ease-in-out infinite;
    }
    /* logo watermark inside hero */
    .hero-logo-bg {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        width: 380px;
        height: 380px;
        object-fit: contain;
        opacity: 0.22;
        filter: brightness(100) grayscale(1);
        pointer-events: none;
        z-index: 1;
        animation: heroFloat 8s ease-in-out infinite;
    }

    .about-hero .orb-3 {
        width: 180px; height: 180px;
        top: 50%; right: 15%;
        transform: translateY(-50%);
        background: rgba(59,130,246,0.25);
        animation: orb1 12s ease-in-out infinite reverse;
    }

    .about-hero-inner {
        position: relative;
        z-index: 2;
        animation: fadeSlideUp 0.7s ease both;
    }

    .about-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(10px);
        border-radius: 100px;
        padding: 5px 16px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.9);
        margin-bottom: 20px;
    }

    .about-hero h1 {
        font-size: 48px;
        font-weight: 800;
        margin-bottom: 22px;
        letter-spacing: -1.5px;
        line-height: 1.1;
        background: linear-gradient(135deg, #ffffff 30%, #a5f3fc 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: none;
    }

    .about-hero p {
        font-size: 16px;
        line-height: 1.85;
        max-width: 720px;
        margin: 0 auto 16px;
        color: rgba(255,255,255,0.85);
        font-weight: 400;
    }
    .about-hero p strong {
        color: #fff;
        font-weight: 700;
    }

    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin-top: 36px;
        flex-wrap: wrap;
    }
    .hero-stat {
        text-align: center;
    }
    .hero-stat-value {
        font-size: 30px;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        background: linear-gradient(135deg, #fff, #a5f3fc);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .hero-stat-label {
        font-size: 11px;
        color: rgba(255,255,255,0.65);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-top: 4px;
        font-weight: 500;
    }
    .hero-stat-divider {
        width: 1px;
        background: rgba(255,255,255,0.2);
        align-self: stretch;
    }

    /* ===== FEATURES GRID ===== */
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 48px;
    }

    .feature-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        padding: 28px 24px;
        border-radius: 20px;
        display: flex;
        align-items: flex-start;
        gap: 18px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        animation: fadeSlideUp 0.6s ease both;
    }
    .feature-card:nth-child(1) { animation-delay: 0.10s; }
    .feature-card:nth-child(2) { animation-delay: 0.20s; }
    .feature-card:nth-child(3) { animation-delay: 0.30s; }

    .feature-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(59,130,246,0.06), rgba(99,102,241,0.04));
        opacity: 0;
        transition: opacity 0.3s ease;
        border-radius: inherit;
    }
    .feature-card:hover {
        transform: translateY(-6px);
        border-color: rgba(59,130,246,0.4);
        box-shadow: 0 16px 40px rgba(59,130,246,0.12);
    }
    .feature-card:hover::after { opacity: 1; }

    .feature-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(99,102,241,0.10));
        border: 1px solid rgba(59,130,246,0.2);
        color: var(--accent-blue, #3b82f6);
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }
    .feature-card:hover .feature-icon {
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 8px 20px rgba(59,130,246,0.35);
        transform: scale(1.08) rotate(-4deg);
    }

    .feature-content h3 {
        font-size: 16px; font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 6px;
    }
    .feature-content p {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.65;
        margin: 0;
    }

    /* ===== SECTION HEADING ===== */
    .section-heading {
        text-align: center;
        margin-bottom: 40px;
        animation: fadeSlideUp 0.6s ease both;
    }
    .section-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--accent-blue, #3b82f6);
        margin-bottom: 10px;
    }
    .section-label::before,
    .section-label::after {
        content: '';
        width: 28px; height: 1px;
        background: var(--accent-blue, #3b82f6);
        opacity: 0.5;
    }
    .section-title {
        font-size: 36px;
        font-weight: 800;
        color: var(--text-primary);
        letter-spacing: -0.5px;
        line-height: 1.15;
        margin-bottom: 10px;
    }
    .section-title span {
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .section-desc {
        font-size: 14px;
        color: var(--text-secondary);
        max-width: 480px;
        margin: 0 auto;
        line-height: 1.7;
    }

    /* ===== TEAM LAYOUT ===== */
    .team-structure {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0;
        margin-bottom: 60px;
    }

    /* connector line between leader and members */
    .team-connector {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0;
        margin: 4px 0;
    }
    .team-connector-line {
        width: 2px;
        height: 30px;
        background: linear-gradient(to bottom, #3b82f6, #6366f1);
        animation: connectorPulse 2s ease-in-out infinite;
    }
    .team-connector-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.25);
        animation: connectorPulse 2s ease-in-out infinite;
    }

    .team-row-top {
        display: flex;
        justify-content: center;
        width: 100%;
    }

    .team-row-bottom {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 20px;
        width: 100%;
        margin-top: 8px;
        perspective: 1500px;
    }
    @media (max-width: 1100px) {
        .team-row-bottom { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .team-row-bottom { grid-template-columns: 1fr; }
        .about-hero h1 { font-size: 32px; }
        .hero-stat-divider { display: none; }
    }

    /* ===== TEAM CARDS ===== */
    .team-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 32px 20px 24px;
        text-align: center;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease, border-color 0.4s ease;
        position: relative;
        overflow: hidden;
        animation: cardEntrance 0.5s ease both;
        transform-style: preserve-3d;
    }
    .team-card:nth-child(1) { animation-delay: 0.15s; }
    .team-card:nth-child(2) { animation-delay: 0.25s; }
    .team-card:nth-child(3) { animation-delay: 0.35s; }
    .team-card:nth-child(4) { animation-delay: 0.45s; }

    /* top gradient bar */
    .team-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #3b82f6, #6366f1, #10b981);
        opacity: 0;
        transition: opacity 0.35s ease;
    }
    /* background glow on hover */
    .team-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 50% 30%, rgba(59,130,246,0.06) 0%, transparent 70%);
        opacity: 0;
        transition: opacity 0.35s ease;
    }
    .team-card:hover {
        transform: translateY(-12px) rotateX(10deg) rotateY(-5deg) scale3d(1.03, 1.03, 1.03);
        border-color: rgba(59,130,246,0.5);
        box-shadow: -15px 25px 40px rgba(59,130,246,0.2), 15px 25px 40px rgba(99,102,241,0.2);
        z-index: 10;
    }
    .team-card:hover::before { opacity: 1; }
    .team-card:hover::after  { opacity: 1; }

    /* leader card special style */
    .team-card.leader {
        border-color: rgba(59,130,246,0.3);
        background: linear-gradient(135deg, var(--bg-secondary) 60%, rgba(59,130,246,0.04));
        width: 300px;
    }
    .team-card.leader::before { opacity: 1; }

    /* avatar */
    .team-avatar-wrapper {
        position: relative;
        width: 110px; height: 110px;
        margin: 0 auto 20px;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .team-card:hover .team-avatar-wrapper {
        transform: translateZ(30px);
    }
    .team-card.leader .team-avatar-wrapper {
        width: 130px; height: 130px;
    }

    /* pulse ring for leader */
    .team-card.leader .team-avatar-wrapper::before {
        content: '';
        position: absolute;
        inset: -6px;
        border-radius: 50%;
        border: 2px solid rgba(59,130,246,0.5);
        animation: pulseRing 2.5s cubic-bezier(0.4,0,0.6,1) infinite;
    }

    .team-avatar {
        width: 100%; height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--bg-primary);
        box-shadow: 0 8px 28px rgba(0,0,0,0.12);
        transition: transform 0.35s ease;
        position: relative; z-index: 1;
    }
    .team-card.leader .team-avatar {
        border: 4px solid transparent;
        background-clip: padding-box;
        box-shadow: 0 0 0 3px #3b82f6, 0 8px 28px rgba(59,130,246,0.25);
    }
    .team-card:hover .team-avatar { transform: scale(1.07); }

    .team-name {
        font-size: 17px; font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 6px;
    }
    .team-card.leader .team-name { font-size: 20px; }

    .team-role {
        display: inline-block;
        font-size: 10px;
        color: #3b82f6;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        background: rgba(59,130,246,0.1);
        border: 1px solid rgba(59,130,246,0.2);
        border-radius: 100px;
        padding: 3px 12px;
    }
    .team-card.leader .team-role {
        background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(99,102,241,0.15));
        border-color: rgba(99,102,241,0.3);
        color: #818cf8;
    }

    /* ===== AURA COLORS ===== */
    .team-card.aura-red:hover {
        border-color: rgba(239,68,68,0.5);
        box-shadow: -15px 25px 40px rgba(239,68,68,0.25), 15px 25px 40px rgba(220,38,38,0.25);
    }
    .team-card.aura-red:hover .team-avatar {
        box-shadow: 0 0 0 3px #ef4444, 0 8px 28px rgba(239,68,68,0.35);
    }
    .team-card.aura-pink:hover {
        border-color: rgba(236,72,153,0.5);
        box-shadow: -15px 25px 40px rgba(236,72,153,0.25), 15px 25px 40px rgba(219,39,119,0.25);
    }
    .team-card.aura-pink:hover .team-avatar {
        box-shadow: 0 0 0 3px #ec4899, 0 8px 28px rgba(236,72,153,0.35);
    }
    .team-card.aura-black:hover {
        border-color: rgba(30,41,59,0.5);
        box-shadow: -15px 25px 40px rgba(15,23,42,0.3), 15px 25px 40px rgba(0,0,0,0.3);
    }
    .team-card.aura-black:hover .team-avatar {
        box-shadow: 0 0 0 3px #1e293b, 0 8px 28px rgba(15,23,42,0.4);
    }
    .team-card.aura-blue:hover {
        border-color: rgba(59,130,246,0.5);
        box-shadow: -15px 25px 40px rgba(59,130,246,0.25), 15px 25px 40px rgba(37,99,235,0.25);
    }
    .team-card.aura-blue:hover .team-avatar {
        box-shadow: 0 0 0 3px #3b82f6, 0 8px 28px rgba(59,130,246,0.35);
    }
    .team-card.aura-purple:hover {
        border-color: rgba(168,85,247,0.5);
        box-shadow: -15px 25px 40px rgba(168,85,247,0.25), 15px 25px 40px rgba(147,51,234,0.25);
    }
    .team-card.aura-purple:hover .team-avatar {
        box-shadow: 0 0 0 3px #a855f7, 0 8px 28px rgba(168,85,247,0.35);
    }
    .team-card.aura-yellow:hover {
        border-color: rgba(234,179,8,0.5);
        box-shadow: -15px 25px 40px rgba(234,179,8,0.25), 15px 25px 40px rgba(202,138,4,0.25);
    }
    .team-card.aura-yellow:hover .team-avatar {
        box-shadow: 0 0 0 3px #eab308, 0 8px 28px rgba(234,179,8,0.35);
    }
    .team-card.aura-cyan:hover {
        border-color: rgba(6,182,212,0.5);
        box-shadow: -15px 25px 40px rgba(6,182,212,0.25), 15px 25px 40px rgba(8,145,178,0.25);
    }
    .team-card.aura-cyan:hover .team-avatar {
        box-shadow: 0 0 0 3px #06b6d4, 0 8px 28px rgba(6,182,212,0.35);
    }

    /* ===== CROWN EFFECT ===== */
    .team-crown {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%) rotate(0deg);
        font-size: 28px;
        color: #fbbf24;
        z-index: 10;
        filter: drop-shadow(0 2px 5px rgba(251,191,36,0.5));
        transition: transform 0.4s ease, color 0.4s ease;
    }
    .team-card:hover .team-crown {
        transform: translateX(-50%) translateZ(40px) rotate(15deg) scale(1.2);
        color: #f59e0b;
    }

    /* ===== WAREHOUSE BG ANIMATION ===== */
    .warehouse-bg-animation {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        pointer-events: none;
        z-index: -1;
        overflow: hidden;
    }
    .warehouse-bg-animation .box {
        position: absolute;
        color: rgba(59, 130, 246, 0.04);
        bottom: -150px;
        animation: floatUp 15s linear infinite;
    }
    @keyframes floatUp {
        0% { transform: translateY(0) rotate(0deg); opacity: 1; }
        100% { transform: translateY(-110vh) rotate(360deg); opacity: 0; }
    }
    .box-1 { left: 10%; animation-duration: 20s; animation-delay: 0s; font-size: 80px; }
    .box-2 { left: 25%; animation-duration: 15s; animation-delay: 2s; font-size: 100px; }
    .box-3 { left: 50%; animation-duration: 25s; animation-delay: 4s; font-size: 70px; }
    .box-4 { left: 75%; animation-duration: 18s; animation-delay: 1s; font-size: 90px; }
    .box-5 { left: 90%; animation-duration: 22s; animation-delay: 5s; font-size: 60px; }
    .box-6 { left: 40%; animation-duration: 16s; animation-delay: 8s; font-size: 110px; }
</style>
@endsection

@section('content')
<div class="animate-fade-in">
    <!-- Warehouse Background Animation -->
    <div class="warehouse-bg-animation">
        <div class="box box-1"><i class="fa-solid fa-box"></i></div>
        <div class="box box-2"><i class="fa-solid fa-truck-ramp-box"></i></div>
        <div class="box box-3"><i class="fa-solid fa-pallet"></i></div>
        <div class="box box-4"><i class="fa-solid fa-box-open"></i></div>
        <div class="box box-5"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="box box-6"><i class="fa-solid fa-box"></i></div>
    </div>

    {{-- ===== HERO ===== --}}
    <div class="about-hero">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        @php
            $heroLogo = \App\Models\AppSetting::getValue('app_logo');
        @endphp
        @if($heroLogo)
            <img src="{{ asset($heroLogo) }}" alt="WMS Logo" class="hero-logo-bg">
        @else
            <img src="{{ asset('uploads/logo_1782107462.png') }}" alt="WMS Logo" class="hero-logo-bg">
        @endif

        <div class="about-hero-inner">
            <div class="about-hero-badge">
                <i class="fa-solid fa-circle-info fa-sm"></i>
                Warehouse Management System
            </div>

            <h1>WMS Easy Go</h1>

            <p>Merupakan <strong>Warehouse Management System</strong> dan solusi cerdas untuk manajemen asset GPS, Dashcam, SIM Card, Aksesoris, dll.</p>
            <p>WMS Easy Go dibangun untuk mengatasi kehilangan visibilitas dan kontrol terhadap asset yang bergerak secara dinamis antar gudang, teknisi, admin gudang, dan proses QC — sehingga operasional menjadi lebih <strong>terkontrol, akurat, dan efisien</strong>.</p>

            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-value">5+</div>
                    <div class="hero-stat-label">Gudang Terhubung</div>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <div class="hero-stat-value">Real‑time</div>
                    <div class="hero-stat-label">Tracking Aset</div>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <div class="hero-stat-value">100%</div>
                    <div class="hero-stat-label">Tercatat & Teraudit</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== FEATURES ===== --}}
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="feature-content">
                <h3>Manajemen Aset Terpadu</h3>
                <p>Melacak pergerakan GPS, Dashcam, dan Aksesoris dengan presisi tinggi dari barang masuk hingga instalasi.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-truck-fast"></i></div>
            <div class="feature-content">
                <h3>Visibilitas Dinamis</h3>
                <p>Mencegah kehilangan unit saat proses transfer antar gudang atau saat dipegang oleh teknisi di lapangan.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="feature-content">
                <h3>Kontrol Kualitas (QC)</h3>
                <p>Memastikan setiap perangkat berfungsi dengan baik melalui alur QC yang sistematis dan tercatat.</p>
            </div>
        </div>
    </div>

    {{-- ===== THE POWERHOUSE ===== --}}
    <div class="section-heading">
        <div class="section-label">Tim Kami</div>
        <div class="section-title">The <span>Powerhouse</span></div>
        <div class="section-desc">Tim IT Area Timur — para profesional di balik sistem yang mengelola ribuan aset setiap harinya.</div>
    </div>

    <div class="team-structure">

        {{-- Leader --}}
        <div class="team-row-top">
            <div class="team-card leader aura-red">
                <div class="team-avatar-wrapper">
                    <i class="fa-solid fa-crown team-crown"></i>
                    <img src="{{ asset('assets/team/SAMSU RIZAL.png') }}" alt="Samsu Rizal" class="team-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=Samsu+Rizal&background=3b82f6&color=fff&size=140'">
                </div>
                <div class="team-name">Samsu Rizal</div>
                <div class="team-role">Leader IT Support</div>
            </div>
        </div>

        {{-- Connector --}}
        <div class="team-connector">
            <div class="team-connector-line"></div>
            <div class="team-connector-dot"></div>
            <div class="team-connector-line"></div>
        </div>

        {{-- Members --}}
        <div class="team-row-bottom">
            <div class="team-card aura-purple">
                <div class="team-avatar-wrapper">
                    <img src="{{ asset('assets/team/ANDRE SAPUTRA.png') }}" alt="Andre Saputra" class="team-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=Andre+Saputra&background=3b82f6&color=fff&size=140'">
                </div>
                <div class="team-name">Andre Saputra</div>
                <div class="team-role">Technical Architect</div>
            </div>

            <div class="team-card aura-pink">
                <div class="team-avatar-wrapper">
                    <img src="{{ asset('assets/team/ASIQ KHUSNIYAH.png') }}" alt="Asiq Khusniyah" class="team-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=Asiq+Khusniyah&background=3b82f6&color=fff&size=140'">
                </div>
                <div class="team-name">Asiq Khusniyah</div>
                <div class="team-role">System Developer</div>
            </div>

            <div class="team-card aura-yellow">
                <div class="team-avatar-wrapper">
                    <img src="{{ asset('assets/team/HANDI FAJAR.png') }}" alt="Handi Fajar" class="team-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=Handi+Fajar&background=3b82f6&color=fff&size=140'">
                </div>
                <div class="team-name">Handi Fajar</div>
                <div class="team-role">Quality &amp; Solutions</div>
            </div>

            <div class="team-card aura-cyan">
                <div class="team-avatar-wrapper">
                    <img src="{{ asset('assets/team/ALVAN CAHYA.png') }}" alt="Alvan Cahya" class="team-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=Alvan+Cahya&background=3b82f6&color=fff&size=140'">
                </div>
                <div class="team-name">Alvan Cahya</div>
                <div class="team-role">Support &amp; Infrastructure</div>
            </div>
            
            <div class="team-card aura-blue">
                <div class="team-avatar-wrapper">
                    <img src="{{ asset('assets/team/HENDRA CYZAR.png') }}" alt="Hendra Cyzar" class="team-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=Hendra+Cyzar&background=3b82f6&color=fff&size=140'">
                </div>
                <div class="team-name">Hendra Cyzar</div>
                <div class="team-role">Trainer &amp; Tester</div>
            </div>
        </div>

    </div>

</div>
@endsection
