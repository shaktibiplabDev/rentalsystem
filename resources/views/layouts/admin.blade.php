<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EKiraya – Admin Panel</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js"></script>
    <!-- Vite removed – using static CSS inline -->
    <style>
        /* ─── TOKENS ─────────────────────────────────────────── */
        :root {
            --bg: #050913;
            --bg2: #0a1222;
            --surface: rgba(255, 255, 255, 0.035);
            --surface-hover: rgba(255, 255, 255, 0.065);
            --surface-active: rgba(68, 135, 245, 0.15);
            --border: rgba(181, 196, 226, 0.11);
            --border-hi: rgba(198, 211, 239, 0.24);
            --text: #e9efff;
            --text-2: #a1aec8;
            --text-3: #62708c;
            --accent: #4487f5;
            --accent-lo: rgba(68, 135, 245, 0.15);
            --green: #2bcf9f;
            --green-lo: rgba(43, 207, 159, 0.14);
            --amber: #f4b73f;
            --amber-lo: rgba(244, 183, 63, 0.14);
            --red: #f35b6f;
            --red-lo: rgba(243, 91, 111, 0.13);
            --purple: #8e7df3;
            --font: 'Syne', sans-serif;
            --mono: 'DM Mono', monospace;
            --r: 18px;
            --r-sm: 12px;
            --r-xs: 9px;
            --nav-w: 60px;
            --shadow-soft: 0 10px 30px rgba(8, 20, 45, 0.35);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: var(--font);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* subtle grain */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: .28;
            background:
                radial-gradient(800px 260px at 12% -2%, rgba(68, 135, 245, 0.25), transparent 70%),
                radial-gradient(700px 280px at 95% 0%, rgba(142, 125, 243, 0.22), transparent 68%),
                url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.05'/%3E%3C/svg%3E");
        }

        /* grid */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: linear-gradient(rgba(255, 255, 255, 0.01) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.01) 1px, transparent 1px);
            background-size: 46px 46px;
        }

        /* ─── LAYOUT ─────────────────────────────────────────── */
        .shell {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: var(--nav-w) 1fr;
            height: 100vh;
        }

        .main {
            display: grid;
            grid-template-rows: 56px 1fr;
            overflow: hidden;
        }

        /* ─── SIDE NAV ───────────────────────────────────────── */
        .sidenav {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 14px 0 12px;
            gap: 4px;
            border-right: 1px solid var(--border);
            background: rgba(5, 9, 19, 0.88);
            backdrop-filter: blur(20px);
        }

        .nav-logo {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(140deg, #4f6ef7 0%, #9b72f7 100%);
            font-size: 13px;
            color: #fff;
            margin-bottom: 10px;
            box-shadow: 0 0 20px rgba(79, 110, 247, .45);
        }

        .nav-sep {
            width: 24px;
            height: 1px;
            background: var(--border);
            margin: 6px 0;
        }

        .nav-item {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: var(--text-3);
            cursor: pointer;
            transition: all .18s;
            position: relative;
            border: 1px solid transparent;
        }

        .nav-item:hover {
            background: var(--surface-hover);
            color: var(--text-2);
            border-color: var(--border);
        }

        .nav-item.active {
            background: var(--surface-active);
            color: var(--accent);
            border-color: rgba(68, 135, 245, 0.24);
            box-shadow: inset 0 0 0 1px rgba(68, 135, 245, 0.08);
        }

        .nav-item .tip {
            position: absolute;
            left: 46px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(7, 11, 24, 0.97);
            border: 1px solid var(--border-hi);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-family: var(--mono);
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s;
            z-index: 200;
        }

        .nav-item:hover .tip {
            opacity: 1;
        }

        .nav-spacer {
            flex: 1;
        }

        .nav-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f6ef7, #9b72f7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            margin-top: 6px;
            border: 1.5px solid rgba(79, 110, 247, .35);
        }

        /* ─── TOPBAR ─────────────────────────────────────────── */
        .topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px;
            border-bottom: 1px solid var(--border);
            background: rgba(5, 9, 19, 0.88);
            backdrop-filter: blur(24px);
            position: relative;
            z-index: 3000;
            overflow: visible;
        }

        .brand {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            background: linear-gradient(90deg, #c8cfff, #9b72f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .tb-div {
            width: 1px;
            height: 18px;
            background: var(--border);
        }

        .page-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-2);
        }

        .tb-spacer {
            flex: 1;
        }

        .search-box {
            position: relative;
            width: 240px;
        }

        .search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: var(--text-3);
        }

        .search-box input {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-xs);
            padding: 6px 10px 6px 28px;
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text);
            outline: none;
            transition: border-color .15s;
        }

        .search-box input::placeholder {
            color: var(--text-3);
        }

        .search-box input:focus {
            border-color: var(--accent);
        }

        .tb-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-3);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 4px 10px;
        }

        .pulse {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: .25; }
        }

        .notif-btn {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: var(--text-2);
            cursor: pointer;
            position: relative;
            transition: all .15s;
        }

        .notif-btn:hover {
            background: var(--surface-hover);
        }

        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--red);
        }

        /* ─── PAGES ──────────────────────────────────────────── */
        .page {
            display: none;
            height: 100%;
            overflow: hidden;
            animation: fadeSlide .22s ease-out;
        }

        .page.active {
            display: flex;
            flex-direction: column;
        }

        /* ─── SHARED COMPONENTS ─────────────────────────────── */
        .panel {
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-soft);
            transition: border-color .2s ease, transform .2s ease;
        }

        .panel:hover {
            border-color: var(--border-hi);
        }

        .ph {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 16px 11px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .ph-title {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-3);
        }

        .pb {
            flex: 1;
            overflow-y: auto;
            padding: 10px 14px;
        }

        .pb::-webkit-scrollbar {
            width: 2px;
        }

        .pb::-webkit-scrollbar-thumb {
            background: var(--border-hi);
            border-radius: 2px;
        }

        .section-label {
            font-size: 9px;
            font-family: var(--mono);
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-3);
            margin-bottom: 8px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 3px 8px;
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-2);
            margin: 0 4px 5px 0;
        }

        .chip i {
            font-size: 8px;
            color: var(--accent);
        }

        /* badges */
        .badge {
            font-size: 9px;
            font-family: var(--mono);
            padding: 2px 7px;
            border-radius: 20px;
            border: 1px solid;
            font-weight: 400;
        }

        .badge-green {
            background: var(--green-lo);
            color: var(--green);
            border-color: rgba(31, 207, 170, .22);
        }

        .badge-red {
            background: var(--red-lo);
            color: var(--red);
            border-color: rgba(240, 68, 90, .22);
        }

        .badge-amber {
            background: var(--amber-lo);
            color: var(--amber);
            border-color: rgba(240, 180, 41, .22);
        }

        .badge-accent {
            background: var(--accent-lo);
            color: var(--accent);
            border-color: rgba(79, 110, 247, .22);
        }

        .badge-purple {
            background: rgba(155, 114, 247, .12);
            color: var(--purple);
            border-color: rgba(155, 114, 247, .22);
        }

        /* status tags */
        .st-tag {
            font-size: 9px;
            font-family: var(--mono);
            padding: 3px 9px;
            border-radius: 20px;
            border: 1px solid;
            white-space: nowrap;
        }

        .st-active {
            color: var(--green);
            border-color: rgba(31, 207, 170, .3);
            background: var(--green-lo);
        }

        .st-banned {
            color: var(--red);
            border-color: rgba(240, 68, 90, .3);
            background: var(--red-lo);
        }

        .st-pending {
            color: var(--amber);
            border-color: rgba(240, 180, 41, .3);
            background: var(--amber-lo);
        }

        /* buttons */
        .btn {
            padding: 8px 14px;
            border-radius: var(--r-xs);
            font-family: var(--font);
            font-size: 11px;
            font-weight: 600;
            border: 1px solid;
            cursor: pointer;
            transition: all .15s;
            letter-spacing: .03em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .btn i {
            font-size: 10px;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: var(--surface);
            border-color: var(--border-hi);
            color: var(--text-2);
        }

        .btn-ghost:hover {
            background: var(--surface-hover);
            color: var(--text);
        }

        .btn-accent {
            background: var(--accent-lo);
            border-color: rgba(79, 110, 247, .35);
            color: var(--accent);
        }

        .btn-accent:hover {
            background: rgba(79, 110, 247, .22);
        }

        .btn-green {
            background: var(--green-lo);
            border-color: rgba(31, 207, 170, .35);
            color: var(--green);
        }

        .btn-green:hover {
            background: rgba(31, 207, 170, .2);
        }

        .btn-red {
            background: var(--red-lo);
            border-color: rgba(240, 68, 90, .35);
            color: var(--red);
        }

        .btn-red:hover {
            background: rgba(240, 68, 90, .2);
        }

        .btn-solid {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 8px 20px rgba(68, 135, 245, 0.28);
        }

        .btn-solid:hover {
            background: #3d5ce8;
        }

        /* metric card */
        .mcard {
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            padding: 14px 16px;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: transform .2s ease, border-color .2s ease;
        }

        .mcard:hover {
            transform: translateY(-2px);
            border-color: var(--border-hi);
        }

        .mcard::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0;
            transition: .3s;
        }

        .mcard:hover::before {
            opacity: .7;
        }

        .ml {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 7px;
        }

        .mv {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }

        .ms {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-top: 3px;
        }

        .mv-accent { color: var(--accent); }
        .mv-green { color: var(--green); }
        .mv-amber { color: var(--amber); }
        .mv-red { color: var(--red); }
        .mv-purple { color: var(--purple); }

        /* table */
        .tbl {
            width: 100%;
            border-collapse: collapse;
        }

        .tbl th {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .tbl td {
            font-size: 11px;
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            vertical-align: middle;
        }

        .tbl tr:last-child td {
            border-bottom: none;
        }

        .tbl tr:hover td {
            background: var(--surface);
        }

        .tbl-mono {
            font-family: var(--mono);
            font-size: 10px;
            color: var(--text-2);
        }

        /* row action buttons small */
        .row-btn {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--surface);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: var(--text-2);
            cursor: pointer;
            transition: all .15s;
        }

        .row-btn:hover {
            background: var(--surface-hover);
            color: var(--text);
        }

        /* avatar circle */
        .av {
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .av-sm {
            width: 30px;
            height: 30px;
            font-size: 10px;
        }

        .av-md {
            width: 36px;
            height: 36px;
            font-size: 12px;
        }

        .av-lg {
            width: 52px;
            height: 52px;
            font-size: 16px;
        }

        /* icon box */
        .ibox {
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ibox-sm {
            width: 32px;
            height: 32px;
            font-size: 12px;
        }

        .ibox-md {
            width: 38px;
            height: 38px;
            font-size: 14px;
        }

        .ibox-accent {
            background: var(--accent-lo);
            border: 1px solid rgba(79, 110, 247, .2);
            color: var(--accent);
        }

        .ibox-green {
            background: var(--green-lo);
            border: 1px solid rgba(31, 207, 170, .2);
            color: var(--green);
        }

        .ibox-amber {
            background: var(--amber-lo);
            border: 1px solid rgba(240, 180, 41, .2);
            color: var(--amber);
        }

        .ibox-red {
            background: var(--red-lo);
            border: 1px solid rgba(240, 68, 90, .2);
            color: var(--red);
        }

        .ibox-purple {
            background: rgba(155, 114, 247, .1);
            border: 1px solid rgba(155, 114, 247, .2);
            color: var(--purple);
        }

        /* progress bar */
        .prog-track {
            height: 3px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 5px;
        }

        .prog-fill {
            height: 100%;
            border-radius: 2px;
            transition: width .5s cubic-bezier(.4, 0, .2, 1);
        }

        /* divider */
        .divider {
            width: 100%;
            height: 1px;
            background: var(--border);
            margin: 8px 0;
        }

        /* toggle switch */
        .tog {
            width: 32px;
            height: 18px;
            background: var(--border-hi);
            border-radius: 10px;
            position: relative;
            cursor: pointer;
            transition: background .2s;
            flex-shrink: 0;
        }

        .tog.on {
            background: var(--accent);
        }

        .tog::after {
            content: '';
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #fff;
            top: 2px;
            left: 2px;
            transition: left .2s;
        }

        .tog.on::after {
            left: 16px;
        }

        /* filter strip */
        .fstrip {
            display: flex;
            gap: 2px;
            background: var(--surface);
            border-radius: 7px;
            padding: 2px;
        }

        .fbtn {
            padding: 4px 10px;
            border-radius: 5px;
            font-family: var(--mono);
            font-size: 10px;
            color: var(--text-3);
            cursor: pointer;
            transition: all .15s;
            border: none;
            background: none;
        }

        .fbtn.active {
            background: var(--surface-active);
            color: var(--accent);
        }

        /* scrollable area */
        .scroll-y {
            overflow-y: auto;
        }

        .scroll-y::-webkit-scrollbar {
            width: 2px;
        }

        .scroll-y::-webkit-scrollbar-thumb {
            background: var(--border-hi);
        }

        /* fade-up animation */
        @keyframes fu {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fu { animation: fu .3s ease both; }
        .fu-1 { animation-delay: .05s; }
        .fu-2 { animation-delay: .1s; }
        .fu-3 { animation-delay: .15s; }
        .fu-4 { animation-delay: .2s; }

        /* ─── DASHBOARD ──────────────────────────────────────── */
        .db-grid {
            display: grid;
            grid-template-columns: 292px 1fr 276px;
            gap: 14px;
            padding: 16px;
            height: 100%;
            overflow: hidden;
        }

        .db-mid {
            display: grid;
            grid-template-rows: auto 1fr;
            gap: 14px;
            overflow: hidden;
        }

        .db-metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .db-detail {
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r);
            overflow: hidden;
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
        }

        .dd-hero {
            padding: 16px 18px 13px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(79, 110, 247, .05), transparent);
        }

        .dd-name {
            font-size: 17px;
            font-weight: 700;
        }

        .dd-meta {
            font-size: 10px;
            color: var(--text-3);
            font-family: var(--mono);
            margin-top: 3px;
        }

        .dd-info {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-top: 5px;
            flex-wrap: wrap;
        }

        .dd-info i {
            font-size: 9px;
            color: var(--text-3);
        }

        .dd-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid var(--border);
        }

        .dd-stat {
            padding: 11px 14px;
            text-align: center;
            border-right: 1px solid var(--border);
        }

        .dd-stat:last-child {
            border-right: none;
        }

        .dd-stat-v {
            font-size: 15px;
            font-weight: 700;
        }

        .dd-stat-l {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-top: 2px;
        }

        .dd-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            flex: 1;
            overflow: hidden;
        }

        .dd-section {
            padding: 12px 16px;
            overflow-y: auto;
        }

        .dd-section:first-child {
            border-right: 1px solid var(--border);
        }

        .dd-section::-webkit-scrollbar {
            width: 2px;
        }

        .dd-section::-webkit-scrollbar-thumb {
            background: var(--border);
        }

        .dd-actions {
            display: flex;
            gap: 7px;
            padding: 11px 16px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .dd-actions .btn {
            flex: 1;
            justify-content: center;
            padding: 8px 10px;
        }

        /* shop list item */
        .sli {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 9px;
            border-radius: 9px;
            cursor: pointer;
            transition: all .15s;
            margin-bottom: 4px;
            border: 1px solid transparent;
        }

        .sli:hover {
            background: var(--surface-hover);
        }

        .sli.active {
            background: var(--surface-active);
            border-color: rgba(79, 110, 247, .18);
        }

        .sli-info {
            flex: 1;
            min-width: 0;
        }

        .sli-name {
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sli-sub {
            font-size: 9px;
            color: var(--text-3);
            font-family: var(--mono);
            margin-top: 1px;
        }

        /* customer list item right */
        .cli {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 9px;
            border-radius: 9px;
            cursor: pointer;
            transition: all .15s;
            margin-bottom: 4px;
        }

        .cli:hover {
            background: var(--surface-hover);
        }

        .cli-info {
            flex: 1;
            min-width: 0;
        }

        .cli-name {
            font-size: 11px;
            font-weight: 600;
        }

        .cli-sub {
            font-size: 9px;
            color: var(--text-3);
            font-family: var(--mono);
            margin-top: 1px;
        }

        /* db right col */
        .db-right {
            display: flex;
            flex-direction: column;
            gap: 14px;
            overflow: hidden;
        }

        /* ─── MAP ────────────────────────────────────────────── */
        .map-shell {
            display: grid;
            grid-template-columns: 300px 1fr;
            height: 100%;
            overflow: hidden;
        }

        .map-bar {
            background: rgba(4, 6, 15, 0.92);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            backdrop-filter: blur(24px);
        }

        .map-bar-header {
            padding: 14px 16px 12px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .map-bar-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px 14px;
        }

        .map-bar-body::-webkit-scrollbar {
            width: 2px;
        }

        .map-bar-body::-webkit-scrollbar-thumb {
            background: var(--border-hi);
        }

        .map-wrap {
            position: relative;
            flex: 1;
            z-index: 1;
        }

        #leafletMap {
            width: 100%;
            height: 100%;
        }

        .leaflet-container {
            background: #060a18 !important;
        }

        .map-float {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 500;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .map-float-btn {
            background: rgba(7, 11, 24, 0.92);
            border: 1px solid var(--border-hi);
            border-radius: 8px;
            padding: 7px 12px;
            font-family: var(--mono);
            font-size: 10px;
            color: var(--text);
            cursor: pointer;
            transition: all .15s;
            backdrop-filter: blur(16px);
        }

        .map-float-btn:hover {
            border-color: rgba(79, 110, 247, .5);
            color: var(--accent);
        }

        .tog-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px;
            cursor: pointer;
            transition: background .15s;
            margin-bottom: 5px;
        }

        .tog-row:hover {
            background: var(--surface-hover);
        }

        .tog-label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-2);
        }

        .tog-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .map-stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
            margin-bottom: 12px;
        }

        .mstat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 9px 11px;
        }

        .mstat-v {
            font-size: 14px;
            font-weight: 700;
            font-family: var(--mono);
        }

        .mstat-l {
            font-size: 9px;
            color: var(--text-3);
            font-family: var(--mono);
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .income-table {
            width: 100%;
            margin-bottom: 10px;
        }

        .income-table td {
            font-size: 10px;
            font-family: var(--mono);
            padding: 5px 0;
            border-bottom: 1px solid var(--border);
        }

        .income-table tr:last-child td {
            border: none;
        }

        .income-table .iv {
            text-align: right;
            font-weight: 500;
        }

        .map-shop-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 9px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: all .15s;
        }

        .map-shop-row:hover {
            background: var(--surface-hover);
        }

        .msr-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .msr-info {
            flex: 1;
            min-width: 0;
        }

        .msr-name {
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .msr-sub {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-top: 1px;
        }

        /* leaflet overrides */
        .leaflet-popup-content-wrapper {
            background: rgba(7, 11, 24, .96) !important;
            backdrop-filter: blur(20px);
            color: #fff !important;
            border-radius: 12px !important;
            border: 1px solid rgba(79, 110, 247, .3) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .8) !important;
        }

        .leaflet-popup-tip {
            background: rgba(7, 11, 24, .96) !important;
        }

        .leaflet-popup-content {
            margin: 10px 14px !important;
            font-family: 'DM Mono', monospace;
            font-size: 11px;
            min-width: 190px;
        }

        .leaflet-control-zoom a {
            background: rgba(7, 11, 24, .9) !important;
            color: #fff !important;
            border-color: rgba(255, 255, 255, .1) !important;
        }

        .leaflet-control-zoom a:hover {
            background: rgba(79, 110, 247, .3) !important;
        }

        .l-tip {
            background: rgba(7, 11, 24, .94) !important;
            border: 1px solid rgba(79, 110, 247, .25) !important;
            color: #e2e5f0 !important;
            border-radius: 20px !important;
            font-family: 'DM Mono', monospace !important;
            font-size: 10px !important;
            padding: 4px 11px !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .5) !important;
        }

        .leaflet-tooltip-top::before {
            border-top-color: rgba(7, 11, 24, .94) !important;
        }

        /* ─── FLEET PAGE ─────────────────────────────────────── */
        .fleet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            padding: 16px;
            height: 100%;
            overflow-y: auto;
            align-content: start;
        }

        .fleet-grid::-webkit-scrollbar {
            width: 2px;
        }

        .fleet-grid::-webkit-scrollbar-thumb {
            background: var(--border-hi);
        }

        .fleet-card {
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 16px;
            backdrop-filter: blur(20px);
            transition: border-color .2s;
            box-shadow: var(--shadow-soft);
        }

        .fleet-card:hover {
            border-color: var(--border-hi);
            transform: translateY(-2px);
        }

        .fc-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .fc-model {
            font-size: 14px;
            font-weight: 700;
        }

        .fc-plate {
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-top: 2px;
        }

        .fc-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 10px;
        }

        .fc-meta-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 9px;
        }

        .fc-meta-l {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .fc-meta-v {
            font-size: 12px;
            font-weight: 600;
            margin-top: 1px;
        }

        .fc-shop {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .fc-shop-name {
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-2);
        }

        /* ─── CUSTOMERS PAGE ─────────────────────────────────── */
        .cust-page-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 14px;
            padding: 16px;
            height: 100%;
            overflow: hidden;
        }

        .cust-table-wrap {
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .cust-filters {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .cust-panel {
            overflow: hidden;
            flex: 1;
        }

        .cust-side {
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow: hidden;
        }

        .cust-detail-card {
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 18px;
            backdrop-filter: blur(20px);
        }

        .cust-verif-log {
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r);
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(20px);
        }

        .verif-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
        }

        .verif-item:last-child {
            border-bottom: none;
        }

        .verif-icon {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
        }

        .vi-green {
            background: var(--green-lo);
            color: var(--green);
        }

        .vi-amber {
            background: var(--amber-lo);
            color: var(--amber);
        }

        .vi-accent {
            background: var(--accent-lo);
            color: var(--accent);
        }

        .verif-info {
            flex: 1;
        }

        .verif-desc {
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-2);
        }

        .verif-time {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-top: 1px;
        }

        .verif-amt {
            font-size: 11px;
            font-weight: 700;
            font-family: var(--mono);
        }

        /* ─── WALLET LOGS PAGE ───────────────────────────────── */
        .wallet-page {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 14px;
            padding: 16px;
            height: 100%;
            overflow: hidden;
        }

        .wallet-left {
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow: hidden;
        }

        .wallet-right {
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow: hidden;
        }

        .wallet-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            flex-shrink: 0;
        }

        .wlog-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-bottom: 1px solid var(--border);
        }

        .wlog-item:last-child {
            border-bottom: none;
        }

        .wlog-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
        }

        .wl-credit {
            background: var(--green-lo);
            color: var(--green);
        }

        .wl-debit {
            background: var(--amber-lo);
            color: var(--amber);
        }

        .wl-verif {
            background: var(--accent-lo);
            color: var(--accent);
        }

        .wl-ban {
            background: var(--red-lo);
            color: var(--red);
        }

        .wlog-info {
            flex: 1;
            min-width: 0;
        }

        .wlog-desc {
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wlog-meta {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-top: 1px;
        }

        .wlog-amt {
            font-size: 12px;
            font-weight: 700;
            font-family: var(--mono);
        }

        /* ─── SETTINGS PAGE ─────────────────────────────────── */
        .settings-shell {
            display: grid;
            grid-template-columns: 220px 1fr;
            height: 100%;
            overflow: hidden;
        }

        .settings-nav {
            border-right: 1px solid var(--border);
            padding: 16px 12px;
            background: rgba(4, 6, 15, .5);
            overflow-y: auto;
        }

        .settings-nav::-webkit-scrollbar {
            width: 2px;
        }

        .snav-group {
            margin-bottom: 20px;
        }

        .snav-group-title {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: .1em;
            padding: 0 8px;
            margin-bottom: 6px;
        }

        .snav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 11px;
            color: var(--text-2);
            transition: all .15s;
            margin-bottom: 2px;
        }

        .snav-item:hover {
            background: var(--surface-hover);
            color: var(--text);
        }

        .snav-item.active {
            background: var(--surface-active);
            color: var(--accent);
        }

        .snav-item i {
            font-size: 11px;
            width: 14px;
            text-align: center;
        }

        .settings-content {
            padding: 24px 28px;
            overflow-y: auto;
            display: grid;
            gap: 16px;
            align-content: start;
        }

        .settings-content::-webkit-scrollbar {
            width: 2px;
        }

        .settings-content::-webkit-scrollbar-thumb {
            background: var(--border-hi);
        }

        .settings-section {
            max-width: none;
            width: 100%;
            margin-bottom: 0;
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 18px;
            box-shadow: var(--shadow-soft);
        }

        .ss-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .ss-desc {
            font-size: 11px;
            color: var(--text-3);
            font-family: var(--mono);
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 6px;
            display: block;
        }

        .form-input {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-xs);
            padding: 9px 12px;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--text);
            outline: none;
            transition: border-color .15s;
        }

        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(68, 135, 245, 0.16);
        }

        .form-input::placeholder {
            color: var(--text-3);
        }

        .form-select {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-xs);
            padding: 9px 12px;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--text);
            outline: none;
            cursor: pointer;
        }

        .logout-btn {
            background: var(--red-lo);
            border: 1px solid rgba(243, 91, 111, 0.34);
            border-radius: 9px;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--red);
            cursor: pointer;
            transition: all .15s ease;
            margin-left: 8px;
        }

        .logout-btn:hover {
            background: rgba(243, 91, 111, 0.22);
            border-color: rgba(243, 91, 111, 0.5);
            transform: scale(1.03);
        }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(3px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1280px) {
            .db-grid {
                grid-template-columns: 260px 1fr 250px;
                gap: 10px;
                padding: 12px;
            }

            .db-metrics {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .shell {
                grid-template-columns: 52px 1fr;
            }

            .db-grid,
            .cust-page-grid,
            .wallet-page,
            .settings-shell,
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .map-shell {
                grid-template-columns: 260px 1fr;
            }

            .topbar {
                padding: 0 12px;
            }

            .search-box {
                width: 190px;
            }
        }

        @media (max-width: 760px) {
            .main {
                grid-template-rows: 52px 1fr;
            }

            .search-box,
            .tb-pill {
                display: none;
            }

            .map-shell {
                grid-template-columns: 1fr;
            }

            .map-bar {
                display: none;
            }

            .db-grid,
            .cust-page-grid,
            .wallet-page {
                padding: 10px;
            }
        }

        .form-select option {
            background: #0a0e1c;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 9px;
            margin-bottom: 8px;
        }

        .setting-row-info .sr-title {
            font-size: 12px;
            font-weight: 600;
        }

        .setting-row-info .sr-desc {
            font-size: 10px;
            color: var(--text-3);
            font-family: var(--mono);
            margin-top: 2px;
        }

        .save-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 9px;
            margin-top: 8px;
        }

        /* ─── PROFILE PAGE ───────────────────────────────────── */
        .profile-shell {
            padding: 24px;
            height: 100%;
            overflow-y: auto;
        }

        .profile-shell::-webkit-scrollbar {
            width: 2px;
        }

        .profile-shell::-webkit-scrollbar-thumb {
            background: var(--border-hi);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 16px;
            max-width: none;
            width: 100%;
            align-items: start;
        }

        .profile-card {
            background: rgba(7, 11, 24, 0.72);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 24px;
            backdrop-filter: blur(20px);
            text-align: center;
        }

        .profile-av-wrap {
            position: relative;
            display: inline-flex;
            margin-bottom: 14px;
        }

        .profile-av {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f6ef7, #9b72f7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            border: 2px solid rgba(79, 110, 247, .4);
        }

        .profile-av-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--green);
            border: 2px solid var(--bg2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .profile-role {
            font-size: 10px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-bottom: 14px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 9px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .ps-item {
            background: var(--bg2);
            padding: 10px;
            text-align: center;
        }

        .ps-v {
            font-size: 15px;
            font-weight: 700;
        }

        .ps-l {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            text-transform: uppercase;
        }

        .profile-right {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .act-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .act-text {
            font-size: 11px;
            color: var(--text-2);
            line-height: 1.5;
        }

        .act-time {
            font-size: 9px;
            font-family: var(--mono);
            color: var(--text-3);
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="shell">
        @include('admin.partials.sidebar')
        <div class="main">
            @include('admin.partials.topbar')
            @yield('content')
        </div>
    </div>
    <script>
        window.Laravel = {
            csrfToken: '{{ csrf_token() }}',
            apiBase: '{{ url("/api") }}',
            user: @json(auth()->user())
        };
    </script>
    <script src="{{ asset('js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
