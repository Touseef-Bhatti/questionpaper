<?php
require_once __DIR__ . '/ads.php';
include_once __DIR__ . '/db_connect.php';
if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();

$current_page = $_SERVER['SCRIPT_NAME'];
if (!function_exists('is_active')) {
    function is_active($page_name) {
        global $current_page;
        return (strpos($current_page, $page_name) !== false) ? 'alh-active' : '';
    }
}

$gen_paper_pages = ['select_class.php','select_book.php','select_chapters.php','topic-wise-mcqs-test','quiz_setup.php','online_quiz_host','online_quiz_lobby.php','quiz.php'];
$is_gen_paper_active = false;
foreach ($gen_paper_pages as $p) {
    if (strpos($current_page, $p) !== false) { $is_gen_paper_active = true; break; }
}

if (isset($only_navbar) && $only_navbar) goto alh_navbar_start;
?>
<?php if (!isset($skip_shell)): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php endif; ?>

<?php include_once __DIR__ . '/includes/favicons.php'; ?>
<?php include_once __DIR__ . '/includes/google_analytics.php'; ?>

<?= renderMonetagScripts() ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'AhmadLearningHub' ?></title>
<?php if (isset($metaDescription)): ?><meta name="description" content="<?= htmlspecialchars($metaDescription) ?>"><?php endif; ?>
<?php if (isset($metaKeywords)): ?><meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>"><?php endif; ?>

<script>
(function(){
    if(localStorage.getItem('darkMode')==='enabled') document.documentElement.classList.add('alh-dark');
    var t=localStorage.getItem('user_type_preference');
    if(t==='School'||t===null) document.documentElement.classList.add('alh-school','school-mode');
})();
</script>

<style>
/* ============================================================
   AhmadLearningHub — Navbar v3
   ALL selectors prefixed .ALH_ — zero collision with page CSS
   ============================================================ */

@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

.ALH_root {
    --ap: #4f46e5;
    --ap2: #7c3aed;
    --ask: #0ea5e9;
    --ag: #10b981;
    --aa: #f59e0b;
    --ar: #ef4444;
    --abg: rgba(255,255,255,0.82);
    --asurf: #ffffff;
    --asurf2: #f8fafc;
    --atxt: #0f172a;
    --atxt2: #475569;
    --atxt3: #94a3b8;
    --abdr: rgba(15,23,42,0.08);
    --ah: 66px;
    --afont: 'Plus Jakarta Sans','Segoe UI',sans-serif;
    --ar1: 10px;
    --ar2: 16px;
    --ar3: 22px;
}
html.alh-dark .ALH_root {
    --abg: rgba(8,14,36,0.90);
    --asurf: #1e293b;
    --asurf2: #0f172a;
    --atxt: #f1f5f9;
    --atxt2: #94a3b8;
    --atxt3: #64748b;
    --abdr: rgba(255,255,255,0.07);
}

.ALH_root *, .ALH_root *::before, .ALH_root *::after { box-sizing:border-box; margin:0; padding:0; }
.ALH_root a { text-decoration:none; color:inherit; }
.ALH_root ul { list-style:none; }
.ALH_root button { cursor:pointer; background:none; border:none; font-family:inherit; }

/* ── NAV SHELL ── */
.ALH_nav {
    position:fixed; inset:0 0 auto 0; z-index:10000;
    height:var(--ah);
    background:var(--abg);
    backdrop-filter:blur(24px) saturate(180%);
    -webkit-backdrop-filter:blur(24px) saturate(180%);
    border-bottom:1px solid var(--abdr);
    box-shadow:0 1px 2px rgba(0,0,0,0.04), 0 6px 22px rgba(79,70,229,0.07);
    font-family:var(--afont);
    transition:background .3s,box-shadow .3s;
}
.ALH_nav.ALH_scrolled {
    background:rgba(255,255,255,0.85);
    box-shadow:0 10px 30px -10px rgba(0,0,0,0.1);
}
html.alh-dark .ALH_nav.ALH_scrolled { background:rgba(15,23,42,0.85); }

.ALH_inner {
    max-width:1400px; width:100%; margin:0 auto; height:100%;
    display:flex; align-items:center; justify-content:space-between;
    padding:0 1.5rem; gap:1rem;
}

/* ── LOGO ── */
.ALH_logo {
    font-weight:800; font-size:1.2rem; letter-spacing:-.04em;
    background:linear-gradient(135deg,#4f46e5,#0ea5e9);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent;
    background-clip:text; white-space:nowrap; flex-shrink:0;
    transition:opacity .2s;
}
.ALH_logo:hover { opacity:.75; }

/* ── MENU LIST ── */
.ALH_menu {
    display:flex; align-items:center; gap:2px;
    height:100%; flex:1; justify-content:center;
}

/* ── BASE LINK ── */
.ALH_link {
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 14px; border-radius:var(--ar1);
    font-size:.92rem; font-weight:700; color:var(--atxt);
    transition:all .2s ease;
    white-space:nowrap;
}
.ALH_link i { font-size:.85em; opacity:0.8; }
.ALH_link:hover { color:var(--ap); background:rgba(79,70,229,0.08); transform:translateY(-1px); }
.ALH_link.alh-active { color:var(--ap); background:rgba(79,70,229,0.1); }

/* ── JOIN BUTTON — vivid gradient pill ── */
.ALH_join {
    display:inline-flex; align-items:center; gap:7px;
    padding:8px 22px; border-radius:50px;
    font-size:.88rem; font-weight:800;
    color:#fff !important; letter-spacing:.01em;
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);
    box-shadow:0 4px 18px rgba(79,70,229,0.42), inset 0 1px 0 rgba(255,255,255,0.2);
    transition:transform .2s, box-shadow .2s;
    white-space:nowrap; position:relative; overflow:hidden;
}
.ALH_join::after {
    content:''; position:absolute; inset:0;
    background:linear-gradient(135deg,rgba(255,255,255,0.16),transparent 55%);
    border-radius:inherit; pointer-events:none;
}
.ALH_join:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(79,70,229,0.55), inset 0 1px 0 rgba(255,255,255,0.2); }
.ALH_join:active { transform:translateY(0); }

/* ── DROPDOWN WRAPPER ── */
.ALH_drop { position:relative; }

/* ── DROP BUTTON ── */
.ALH_dbtn {
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 14px; border-radius:var(--ar1);
    font-size:.92rem; font-weight:700; color:var(--atxt);
    cursor:pointer; white-space:nowrap;
    transition:all .2s ease;
}
.ALH_dbtn i:not(.ALH_caret) { font-size:.85em; opacity:0.8; }
.ALH_dbtn i.ALH_caret { font-size:.75em; transition:transform .25s; margin-left:2px; }
.ALH_dbtn:hover, .ALH_dbtn.alh-active { color:var(--ap); background:rgba(79,70,229,0.08); transform:translateY(-1px); }
.ALH_drop:hover .ALH_dbtn .ALH_caret, .ALH_dbtn.ALH_dopen .ALH_caret { transform:rotate(180deg); }

/* ── DROP PANEL ── */
.ALH_panel {
    position:absolute; top:calc(100% + 10px); left:0; z-index:9999;
    background:var(--asurf);
    border:1px solid var(--abdr);
    border-radius:var(--ar3);
    box-shadow:0 24px 60px rgba(0,0,0,0.15), 0 4px 12px rgba(79,70,229,0.08);
    min-width:210px; padding:6px;
    opacity:0; visibility:hidden;
    transform:translateY(10px) scale(.97); transform-origin:top left;
    transition:opacity .2s, visibility .2s, transform .2s;
}
html.alh-dark .ALH_panel { box-shadow:0 24px 60px rgba(0,0,0,0.5); }
.ALH_drop:hover .ALH_panel, .ALH_panel.ALH_popen {
    opacity:1; visibility:visible; transform:translateY(0) scale(1);
}

/* ── DROP ITEMS ── */
.ALH_ditem {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:var(--ar1);
    font-size:.88rem; font-weight:700; color:var(--atxt);
    transition:all .17s; cursor:pointer; width:100%;
}
.ALH_dico {
    width:30px; height:30px; border-radius:8px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:.82rem; transition:transform .2s;
    background:rgba(79,70,229,0.08); color:var(--ap);
}
.ALH_ditem:hover { background:rgba(79,70,229,0.07); color:var(--ap); transform:translateX(3px); }
.ALH_ditem:hover .ALH_dico { transform:scale(1.12); }

/* ── MEGA PANEL ── */
.ALH_mega {
    min-width:500px; left:50%;
    transform:translateX(-50%) translateY(10px) scale(.97);
    transform-origin:top center;
}
.ALH_drop:hover .ALH_mega, .ALH_mega.ALH_popen {
    opacity:1; visibility:visible; transform:translateX(-50%) translateY(0) scale(1);
}
.ALH_mlabel {
    font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
    color:var(--atxt3); padding:4px 10px 8px;
}
.ALH_hdiv { height:1px; background:var(--abdr); margin:6px 0; }

/* Class grid — Single Row */
.ALH_cg { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:12px; }

.ALH_cb {
    display:flex; flex-direction:column; align-items:center; gap:6px;
    padding:10px 6px; border-radius:var(--ar1); text-align:center;
    font-size:.8rem; font-weight:800; color:var(--atxt) !important;
    cursor:pointer; border:none; width:100%;
    transition:all .22s ease; position:relative; overflow:hidden;
    background:none;
}
.ALH_cb:nth-child(1) { --cb-clr: #4f46e5; --cb-bg: rgba(79,70,229,0.08); }
.ALH_cb:nth-child(2) { --cb-clr: #0ea5e9; --cb-bg: rgba(14,165,233,0.08); }
.ALH_cb:nth-child(3) { --cb-clr: #10b981; --cb-bg: rgba(16,185,129,0.08); }
.ALH_cb:hover { transform:translateY(-2px); background:var(--cb-bg); color:var(--cb-clr) !important; }
.ALH_cbico {
    width:36px; height:36px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:.95rem; transition:transform .22s;
    background:var(--cb-bg); color:var(--cb-clr);
    flex-shrink:0;
}
.ALH_cb:hover .ALH_cbico { transform:scale(1.1) rotate(-5deg); background:var(--cb-clr); color:#fff; }
.ALH_cbtxt { display:flex; flex-direction:column; gap:4px; align-items:center; }
.ALH_cbtitle { line-height:1.2; display:block; }
.ALH_cbsub { font-size:.68rem; font-weight:600; opacity:0.8; line-height:1.2; }

/* Action chips — 3 vivid pills */
.ALH_ar { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; }
.ALH_ac {
    display:flex; flex-direction:column; align-items:center; gap:5px;
    padding:11px 6px; border-radius:var(--ar1); text-align:center;
    font-size:.76rem; font-weight:700; cursor:pointer;
    transition:transform .2s, filter .2s; color:#fff !important;
    border:none; font-family:var(--afont);
}
.ALH_ac:nth-child(1) { background:linear-gradient(135deg,#0284c7,#0369a1); box-shadow:0 6px 20px rgba(2,132,199,0.45); }
.ALH_ac:nth-child(2) { background:linear-gradient(135deg,#6d28d9,#4c1d95); box-shadow:0 6px 20px rgba(109,40,217,0.45); }
.ALH_ac:nth-child(3) { background:linear-gradient(135deg,#059669,#064e3b); box-shadow:0 6px 20px rgba(5,150,105,0.45); }
.ALH_ac:hover { transform:translateY(-3px); filter:brightness(1.1); }
.ALH_ac:active { transform:translateY(0); }
.ALH_ac i { font-size:1.15rem; }

/* Profile drop right-align */
.ALH_pdrop .ALH_panel { left:auto; right:0; transform-origin:top right; }
.ALH_pdrop .ALH_panel.ALH_popen, .ALH_pdrop:hover .ALH_panel { transform:translateY(0) scale(1); }

/* User info */
.ALH_uinfo {
    padding:10px 12px 12px; margin-bottom:4px;
    background:linear-gradient(135deg,rgba(79,70,229,0.07),rgba(14,165,233,0.07));
    border-radius:var(--ar1); border:1px solid rgba(79,70,229,0.1);
}
.ALH_uname { font-weight:700; font-size:.88rem; color:var(--atxt); }
.ALH_uemail { font-size:.73rem; color:var(--atxt3); margin-top:2px; }

/* ── PLAN CHIP ── */
.ALH_chip {
    display:inline-flex; align-items:center; gap:6px;
    padding:5px 14px 5px 9px; border-radius:50px;
    font-size:.73rem; font-weight:700; letter-spacing:.03em;
    white-space:nowrap; cursor:pointer; text-decoration:none;
    transition:transform .2s, box-shadow .2s;
    overflow:hidden; position:relative;
}
.ALH_chip::before {
    content:''; position:absolute; top:0; left:-80%; width:50%; height:100%;
    background:linear-gradient(120deg,transparent,rgba(255,255,255,0.32),transparent);
    transform:skewX(-18deg); transition:left .5s; pointer-events:none;
}
.ALH_chip:hover::before { left:150%; }
.ALH_chip.premium { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff !important; box-shadow:0 3px 12px rgba(79,70,229,0.4); }
.ALH_chip.basic   { background:linear-gradient(135deg,#f59e0b,#ef4444); color:#fff !important; box-shadow:0 3px 12px rgba(245,158,11,0.35); }
.ALH_chip:hover   { transform:translateY(-2px) scale(1.03); }
.ALH_dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; animation:ALH_p 2s infinite; }
.ALH_chip.premium .ALH_dot { background:#c4b5fd; }
.ALH_chip.basic   .ALH_dot { background:#fde68a; }
@keyframes ALH_p {
    0%   { box-shadow:0 0 0 0 rgba(255,255,255,.75); }
    65%  { box-shadow:0 0 0 6px rgba(255,255,255,0); }
    100% { box-shadow:0 0 0 0 rgba(255,255,255,0); }
}

/* ── RIGHT CLUSTER ── */
.ALH_right { display:flex; align-items:center; gap:8px; flex-shrink:0; }

/* ── HAMBURGER ── */
.ALH_burger {
    display:none; flex-direction:column; justify-content:center; align-items:center; gap:5px;
    width:40px; height:40px; border-radius:var(--ar1);
    background:rgba(79,70,229,0.08); border:1.5px solid rgba(79,70,229,0.15);
    cursor:pointer; transition:background .2s; flex-shrink:0;
    z-index:10100; position:relative;
}
.ALH_burger:hover { background:rgba(79,70,229,0.15); }
.ALH_burger span {
    display:block; width:18px; height:2px;
    background:var(--ap); border-radius:2px;
    transition:transform .3s, opacity .3s, width .3s;
}
.ALH_burger.ALH_bopen span:nth-child(1) { transform:translateY(7px) rotate(45deg); }
.ALH_burger.ALH_bopen span:nth-child(2) { opacity:0; width:0; }
.ALH_burger.ALH_bopen span:nth-child(3) { transform:translateY(-7px) rotate(-45deg); }

/* ── OVERLAY ── */
.ALH_overlay {
    position:fixed; inset:0; z-index:9950;
    background:transparent;
    opacity:0; pointer-events:none;
    transition:opacity .35s;
}
.ALH_overlay.ALH_ovopen { opacity:1; pointer-events:auto; }

/* ============================================================
   MOBILE SIDEBAR — completely self-contained & opaque
   ============================================================ */
@media (max-width:768px) {

    .ALH_burger { display:flex; }

    .ALH_menu {
        position:fixed; top:0; left:0; bottom:0;
        width:min(85vw, 320px);
        height:100dvh;
        z-index:9980;
        background:var(--asurf);
        flex-direction:column; align-items:stretch; justify-content:flex-start;
        gap:0; padding:0;
        overflow-y:auto; overflow-x:hidden;
        border-radius:0 24px 24px 0;
        box-shadow:20px 0 60px rgba(0,0,0,0.25);
        transform:translateX(-110%);
        transition:transform .45s cubic-bezier(0.4, 0, 0.2, 1);
        visibility:hidden;
    }

    html.alh-dark .ALH_menu { background:#0f172a; box-shadow:20px 0 60px rgba(0,0,0,0.6); }

    .ALH_menu.ALH_mopen { transform:translateX(0); visibility:visible; }

    /* Modern Sidebar Header */
    .ALH_menu::before {
        content:'AhmadLearningHub';
        display:flex; align-items:flex-end;
        min-height:160px;
        padding:2.5rem 1.5rem;
        font-family:var(--afont); font-weight:800; font-size:1.4rem; letter-spacing:-.04em;
        color:#fff;
        background:linear-gradient(135deg, var(--ap) 0%, var(--ap2) 100%);
        margin-bottom:1rem;
        position:relative;
        clip-path:polygon(0 0, 100% 0, 100% 88%, 0 100%);
    }

    .ALH_menu > li {
        padding:0 12px;
        margin-bottom:4px;
        opacity:0; transform:translateX(-20px);
        transition:opacity .4s ease, transform .4s ease;
    }

    /* Section Label for Mobile */
    .ALH_menu::after {
        content:'MAIN MENU';
        display:block;
        padding:1.5rem 1.5rem 0.6rem;
        font-size:0.65rem; font-weight:800; color:var(--atxt3);
        letter-spacing:0.12em;
    }

    .ALH_menu.ALH_mopen > li { opacity:1; transform:none; }
    .ALH_menu.ALH_mopen > li:nth-child(1) { transition-delay: .08s; }
    .ALH_menu.ALH_mopen > li:nth-child(2) { transition-delay: .12s; }
    .ALH_menu.ALH_mopen > li:nth-child(3) { transition-delay: .16s; }
    .ALH_menu.ALH_mopen > li:nth-child(4) { transition-delay: .20s; }
    .ALH_menu.ALH_mopen > li:nth-child(5) { transition-delay: .24s; }

    .ALH_link, .ALH_dbtn {
        width:100%; padding:14px 18px;
        border-radius:16px; font-size:1rem; font-weight:700;
        color:var(--atxt); justify-content:flex-start; gap:12px;
        transform:none !important;
    }
    html.alh-dark .ALH_link, html.alh-dark .ALH_dbtn { color:#f1f5f9; }
    .ALH_link.alh-active { background:rgba(79,70,229,0.1); color:var(--ap); }

    /* JOIN full-width */
    .ALH_join {
        margin:1.2rem 12px; width:calc(100% - 24px);
        padding:16px; border-radius:18px; font-size:1.1rem;
        justify-content:center;
    }

    /* Mobile dropdown panels — static accordion */
    .ALH_panel, .ALH_mega {
        position:static !important;
        min-width:unset !important; width:100%;
        transform:none !important; left:auto !important; right:auto !important;
        background:rgba(79,70,229,0.03) !important;
        opacity:0; visibility:hidden;
        max-height:0; overflow:hidden;
        padding:0 4px;
        transition:all .4s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius:12px !important;
        border:none !important;
    }
    html.alh-dark .ALH_panel, html.alh-dark .ALH_mega { background:rgba(255,255,255,0.02) !important; }

    .ALH_panel.ALH_popen, .ALH_mega.ALH_popen {
        opacity:1; visibility:visible; max-height:1000px; padding:10px !important; margin:4px 0 10px 0 !important;
    }

    /* 2-col class grid on mobile */
    .ALH_cg { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; }
    .ALH_cbsub { display:none; } 
    .ALH_cb { font-size:.7rem; }
    /* 3-col action row stays */
    .ALH_ar { grid-template-columns:repeat(3,1fr); }
    .ALH_ac { padding:9px 4px; font-size:.71rem; }

    /* hide desktop plan chip in top-right, show inside menu */
    .ALH_right .ALH_chip { display:none; }
    .ALH_deskonly { display:none !important; }
    .ALH_mobonly { display:block !important; }

    /* caret rotation */
    .ALH_dbtn.ALH_dopen .ALH_caret { transform:rotate(180deg); }
}

@media (min-width:769px) { .ALH_mobonly { display:none !important; } }

@media (max-width:400px) {
    .ALH_inner { padding:0 .85rem; }
    .ALH_logo { font-size:1rem; }
    .ALH_menu { width:min(90vw, 290px); }
}

/* Push body content below navbar */
body { padding-top:var(--ah, 66px); }

/* ==========================================================
   MODALS — scoped
   ========================================================== */

/* Auth */
.ALH_authmodal {
    position:fixed; inset:0; z-index:10500;
    background:rgba(4,9,24,0.78);
    backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
    display:flex; align-items:center; justify-content:center;
    opacity:0; visibility:hidden; pointer-events:none;
    transition:opacity .25s, visibility .25s;
}
.ALH_authmodal.ALH_show { opacity:1; visibility:visible; pointer-events:auto; }
.ALH_acard {
    background:var(--asurf); border-radius:var(--ar3); padding:2.2rem;
    width:90%; max-width:400px; position:relative;
    box-shadow:0 30px 70px rgba(0,0,0,0.28);
    transform:translateY(16px); transition:transform .3s;
    font-family:var(--afont);
}
.ALH_authmodal.ALH_show .ALH_acard { transform:translateY(0); }
.ALH_xbtn {
    position:absolute; top:1rem; right:1rem;
    width:30px; height:30px; border-radius:50%;
    background:rgba(148,163,184,0.14); color:var(--atxt2);
    display:flex; align-items:center; justify-content:center; font-size:1rem;
    cursor:pointer; transition:all .2s;
}
.ALH_xbtn:hover { background:rgba(239,68,68,0.12); color:#ef4444; transform:rotate(90deg); }
.ALH_atitle { font-size:1.5rem; font-weight:800; color:var(--atxt); margin-bottom:.35rem; }
.ALH_asub { font-size:.9rem; color:var(--atxt2); margin-bottom:1.5rem; line-height:1.5; }
.ALH_abtn {
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; padding:.82rem; border-radius:var(--ar1);
    font-size:.94rem; font-weight:700; text-align:center;
    margin-bottom:.6rem; transition:all .2s; font-family:var(--afont);
}
.ALH_abtn-p { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff !important; box-shadow:0 4px 16px rgba(79,70,229,0.36); }
.ALH_abtn-p:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(79,70,229,0.46); }
.ALH_abtn-s { background:var(--asurf2); color:var(--atxt); border:1.5px solid var(--abdr); }
.ALH_abtn-s:hover { background:var(--abdr); }
.ALH_afooter { font-size:.82rem; color:var(--atxt3); text-align:center; margin-top:.8rem; }

/* Upgrade */
.ALH_upmodal {
    position:fixed; inset:0; z-index:10600;
    background:rgba(4,9,24,0.82);
    backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px);
    display:none; align-items:center; justify-content:center;
    animation:ALH_fi .3s;
}
@keyframes ALH_fi { from{opacity:0;} to{opacity:1;} }
@keyframes ALH_su { from{transform:translateY(36px);opacity:0;} to{transform:translateY(0);opacity:1;} }
.ALH_upcard {
    background:var(--asurf); border-radius:var(--ar3); padding:2.2rem;
    width:90%; max-width:430px; text-align:center;
    box-shadow:0 30px 70px rgba(0,0,0,0.3);
    font-family:var(--afont); position:relative; overflow:hidden;
    animation:ALH_su .4s cubic-bezier(.175,.885,.32,1.275);
}
.ALH_upico {
    width:54px; height:54px; border-radius:15px;
    background:linear-gradient(135deg,#f59e0b,#d97706);
    display:flex; align-items:center; justify-content:center;
    font-size:1.35rem; color:#fff; margin:0 auto 1rem;
    box-shadow:0 8px 20px rgba(245,158,11,0.35);
}
.ALH_uptitle { font-size:1.5rem; font-weight:800; color:var(--atxt); margin-bottom:.4rem; }
.ALH_uptext { color:var(--atxt2); font-size:.9rem; margin-bottom:1.1rem; line-height:1.5; }
.ALH_upfeats { background:var(--asurf2); border-radius:var(--ar1); padding:.9rem 1.1rem; margin-bottom:1.2rem; text-align:left; }
.ALH_ufeat { display:flex; align-items:center; gap:8px; font-size:.87rem; font-weight:600; color:var(--atxt); padding:4px 0; }
.ALH_ufeat i { color:var(--ag); font-size:.92rem; }
.ALH_upcta {
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; padding:.85rem; border-radius:var(--ar2);
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    color:#fff !important; font-size:1rem; font-weight:800;
    box-shadow:0 6px 20px rgba(79,70,229,0.36);
    transition:all .2s; margin-bottom:.6rem;
}
.ALH_upcta:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(79,70,229,0.46); }
.ALH_uplater { background:none; color:var(--atxt3); font-size:.88rem; font-weight:600; padding:.4rem; transition:color .2s; cursor:pointer; }
.ALH_uplater:hover { color:var(--atxt); }

/* Class modal */
.ALH_clsmodal {
    position:fixed; inset:0; z-index:10600;
    background:rgba(4,9,24,0.80);
    backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
    display:none; align-items:center; justify-content:center;
    animation:ALH_fi .3s;
}
.ALH_clscard {
    background:var(--asurf); border-radius:var(--ar3); padding:2.2rem;
    width:90%; max-width:420px; text-align:center;
    box-shadow:0 30px 70px rgba(0,0,0,0.25);
    font-family:var(--afont);
    animation:ALH_su .4s cubic-bezier(.175,.885,.32,1.275);
}
.ALH_clstitle { font-size:1.6rem; font-weight:800; color:var(--atxt); margin-bottom:.35rem; }
.ALH_clssub { color:var(--atxt2); font-size:.9rem; margin-bottom:1.6rem; line-height:1.5; }
.ALH_clsopts { display:flex; flex-direction:column; gap:.65rem; }
.ALH_clsopt {
    display:flex; align-items:center; justify-content:space-between;
    padding:1.2rem 1.4rem; border-radius:var(--ar2);
    border:none; background:linear-gradient(135deg,#4f46e5,#7c3aed);
    font-size:1.05rem; font-weight:800; color:#fff !important;
    cursor:pointer; transition:all .25s; font-family:var(--afont);
    box-shadow:0 6px 18px rgba(79,70,229,0.3);
}
.ALH_clsopt:nth-child(1) { background:linear-gradient(135deg,#10b981,#059669); box-shadow:0 6px 18px rgba(16,185,129,0.3); }
.ALH_clsopt:nth-child(2) { background:linear-gradient(135deg,#4f46e5,#7c3aed); box-shadow:0 6px 18px rgba(79,70,229,0.3); }
.ALH_clsopt:nth-child(3) { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 6px 18px rgba(245,158,11,0.3); }
.ALH_clsopt i { color:#fff; font-size:1.3rem; opacity:0.8; transition:transform .3s; }
.ALH_clsopt:hover { transform:translateY(-3px); filter:brightness(1.1); box-shadow:0 10px 25px rgba(0,0,0,0.2); }
.ALH_clsopt:hover i { transform:scale(1.2) rotate(-8deg); opacity:1; }
</style>

<?php if (isset($extraHead)) echo $extraHead; ?>

<?php if (isset($only_head) && $only_head): ?>
<?php if (!isset($skip_shell)): ?></head><?php endif; ?>
<?php return; ?>
<?php endif; ?>

<?php if (!isset($skip_shell) && !isset($only_navbar)): ?>
</head>
<body>
<?php endif; ?>

<?php alh_navbar_start: ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<div class="ALH_root">

<!-- ════════════════════════════════════
     NAVBAR
     ════════════════════════════════════ -->
<nav class="ALH_nav" id="ALH_nav">
  <div class="ALH_inner">

    <a href="<?= $assetBase ?>index.php" class="ALH_logo">AhmadLearningHub</a>

    <ul class="ALH_menu" id="ALH_menu">

      <!-- Home -->
      <li>
        <a href="<?= $assetBase ?>index" class="ALH_link <?= (basename($current_page)=='index.php'||basename($current_page)=='') ? 'alh-active' : '' ?>">
          <i class="fas fa-home"></i> Home
        </a>
      </li>

      <!-- Generate Paper MEGA -->
      <li class="ALH_drop" id="ALH_gpdrop">
        <button class="ALH_dbtn <?= $is_gen_paper_active?'alh-active':'' ?>" type="button" id="ALH_gpbtn">
          <i class="fas fa-file-alt"></i> Generate Paper <i class="fas fa-caret-down ALH_caret"></i>
        </button>
        <div class="ALH_panel ALH_mega" id="ALH_gppanel">
          <div class="ALH_mlabel">Create Question Paper</div>
          <div class="ALH_cg">
            <button class="ALH_cb ALH_cct" data-action="generate_paper" data-class="School" data-grade="9" type="button">
              <span class="ALH_cbico"><i class="fas fa-school"></i></span>
              <div class="ALH_cbtxt">
                <span class="ALH_cbtitle">Class 9/10</span>
                <span class="ALH_cbsub">Secondary School Paper Generator</span>
              </div>
            </button>
            <button class="ALH_cb ALH_cct" data-action="generate_paper" data-class="College" data-grade="college" type="button">
              <span class="ALH_cbico"><i class="fas fa-graduation-cap"></i></span>
              <div class="ALH_cbtxt">
                <span class="ALH_cbtitle">Class 11/12</span>
                <span class="ALH_cbsub">Intermediate & Higher Secondary</span>
              </div>
            </button>
            <button class="ALH_cb ALH_cct" data-action="generate_paper" data-class="College" data-grade="university" type="button">
              <span class="ALH_cbico"><i class="fas fa-university"></i></span>
              <div class="ALH_cbtxt">
                <span class="ALH_cbtitle">University</span>
                <span class="ALH_cbsub">Bachelor & Master Level Assessments</span>
              </div>
            </button>
          </div>
          <div class="ALH_hdiv"></div>
          <div class="ALH_mlabel">Quick Actions</div>
          <div class="ALH_ar">
            <a href="<?= $assetBase ?>online_quiz_host_new" class="ALH_ac <?= is_active('online_quiz_host_new.php') ?>">
              <i class="fas fa-broadcast-tower"></i>Host Quiz
            </a>
            <button class="ALH_ac ALH_cct" data-action="online_mcqs" type="button">
              <i class="fas fa-question-circle"></i>MCQs Quiz
            </button>
            <a href="<?= $assetBase ?>online_quiz_join" class="ALH_ac <?= is_active('online_quiz_join.php') ?>">
              <i class="fas fa-gamepad"></i>Join Quiz
            </a>
          </div>
        </div>
      </li>

      <!-- Board Exam Prep MEGA -->
      <li class="ALH_drop" id="ALH_bedrop">
        <button class="ALH_dbtn <?= is_active('examPreparation/') ? 'alh-active' : '' ?>" type="button" id="ALH_bebtn">
          <i class="fas fa-user-graduate"></i> Board Exam Prep <i class="fas fa-caret-down ALH_caret"></i>
        </button>
        <div class="ALH_panel ALH_mega" id="ALH_bepanel">
          <div class="ALH_mlabel">Board Exam Preparation</div>
          <div class="ALH_cg">
            <button class="ALH_cb ALH_cct" data-action="board_exam_prep" data-class="School" type="button">
              <span class="ALH_cbico"><i class="fas fa-school"></i></span>
              <div class="ALH_cbtxt">
                <span class="ALH_cbtitle">Class 9/10</span>
                <span class="ALH_cbsub">Secondary Board Exam Preparation</span>
              </div>
            </button>
            <button class="ALH_cb ALH_cct" data-action="board_exam_prep" data-class="College" type="button">
              <span class="ALH_cbico"><i class="fas fa-university"></i></span>
              <div class="ALH_cbtxt">
                <span class="ALH_cbtitle">Class 11/12</span>
                <span class="ALH_cbsub">Intermediate Board Exam Preparation</span>
              </div>
            </button>
            <button class="ALH_cb ALH_cct" data-action="online_mcqs" type="button">
              <span class="ALH_cbico"><i class="fas fa-question-circle"></i></span>
              <div class="ALH_cbtxt">
                <span class="ALH_cbtitle">Online MCQs Test</span>
                <span class="ALH_cbsub">Practice chapter-wise MCQs online</span>
              </div>
            </button>
          </div>
        </div>
      </li>

      <!-- Notes -->
      <li>
        <a href="<?= $assetBase ?>note" class="ALH_link <?= is_active('note.php') ?>">
          <i class="fas fa-book-open"></i> Notes
        </a>
      </li>

      <!-- Join CTA pill -->
      <li>
        <a href="<?= $assetBase ?>online_quiz_join" class="ALH_join">
          <i class="fas fa-gamepad"></i> Join Quiz
        </a>
      </li>

      <!-- About dropdown -->
      <li class="ALH_drop" id="ALH_aboutdrop">
        <button class="ALH_dbtn <?= is_active('about.php')||is_active('reviews.php')?'alh-active':'' ?>" type="button" id="ALH_aboutbtn">
          <i class="fas fa-info-circle"></i> About <i class="fas fa-caret-down ALH_caret"></i>
        </button>
        <div class="ALH_panel" id="ALH_aboutpanel">
          <a href="<?= $assetBase ?>about" class="ALH_ditem <?= is_active('about.php') ?>">
            <span class="ALH_dico"><i class="fas fa-info-circle"></i></span>About Us
          </a>
          <a href="<?= $assetBase ?>reviews" class="ALH_ditem <?= is_active('reviews.php') ?>">
            <span class="ALH_dico"><i class="fas fa-star"></i></span>Reviews
          </a>
        </div>
      </li>

      <?php if(isset($_SESSION['user_id'])): ?>
        <?php
        if(file_exists(__DIR__.'/middleware/SubscriptionCheck.php')){
            require_once __DIR__.'/middleware/SubscriptionCheck.php';
            $subInfo = getSubscriptionInfo($_SESSION['user_id']);
        }
        ?>

        <!-- Plan chip (mobile only inside sidebar) -->
        <?php if(isset($subInfo)&&$subInfo): ?>
        <li class="ALH_mobonly" style="padding:4px 10px 2px;">
          <a href="<?= $subInfo['is_premium']?($assetBase.'subscription'):'javascript:void(0)' ?>"
             onclick="<?= $subInfo['is_premium']?'':'showAlhUpgradeModal(\'general\')' ?>"
             class="ALH_chip <?= $subInfo['is_premium']?'premium':'basic' ?>" style="display:inline-flex;">
            <span class="ALH_dot"></span>
            <span><?= htmlspecialchars($subInfo['plan_name']) ?></span>
          </a>
        </li>
        <?php endif; ?>

        <!-- Profile dropdown -->
        <li class="ALH_drop ALH_pdrop" id="ALH_profdrop">
          <button class="ALH_dbtn <?= is_active('profile.php') ?>" type="button" id="ALH_profbtn">
            <i class="fas fa-user-circle"></i> Profile <i class="fas fa-caret-down ALH_caret"></i>
          </button>
          <div class="ALH_panel" id="ALH_profpanel">
            <div class="ALH_uinfo">
              <div class="ALH_uname"><?= htmlspecialchars($_SESSION['name']??'User') ?></div>
              <div class="ALH_uemail"><?= htmlspecialchars($_SESSION['email']??'') ?></div>
            </div>
            <a href="<?= $assetBase ?>profile" class="ALH_ditem <?= is_active('profile.php') ?>">
              <span class="ALH_dico"><i class="fas fa-user-cog"></i></span>My Profile
            </a>
            <button class="ALH_ditem" id="ALH_modeswitch" type="button" style="width:100%;text-align:left;background:none;border:none;font-family:inherit;">
              <span class="ALH_dico"><i class="fas fa-exchange-alt"></i></span>
              <span id="ALH_modelabel">Switch Mode</span>
            </button>
            <a href="<?= $assetBase ?>settings" class="ALH_ditem <?= is_active('settings.php') ?>">
              <span class="ALH_dico"><i class="fas fa-cog"></i></span>Settings
            </a>
            <a href="<?= $assetBase ?>logout" class="ALH_ditem">
              <span class="ALH_dico" style="background:rgba(239,68,68,.1);color:#ef4444;"><i class="fas fa-sign-out-alt"></i></span>Logout
            </a>
          </div>
        </li>

      <?php else: ?>
        <li>
          <a href="<?= $assetBase ?>login" class="ALH_link <?= is_active('login.php') ?>">
            <i class="fas fa-sign-in-alt"></i> Login
          </a>
        </li>
      <?php endif; ?>

      <li class="ALH_mobonly">
        <a href="<?= $assetBase ?>settings" class="ALH_link <?= is_active('settings.php') ?>">
          <i class="fas fa-cog"></i> Settings
        </a>
      </li>

    </ul>

    <!-- Right cluster (desktop only) -->
    <div class="ALH_right">
      <?php if(isset($_SESSION['user_id'])&&isset($subInfo)&&$subInfo): ?>
        <a href="<?= $subInfo['is_premium']?($assetBase.'subscription'):'javascript:void(0)' ?>"
           onclick="<?= $subInfo['is_premium']?'':'showAlhUpgradeModal(\'general\')' ?>"
           class="ALH_chip <?= $subInfo['is_premium']?'premium':'basic' ?> ALH_deskonly">
          <span class="ALH_dot"></span>
          <span><?= htmlspecialchars($subInfo['plan_name']) ?></span>
        </a>
      <?php endif; ?>
      <button class="ALH_burger" id="ALH_burger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>

  </div>
</nav>

<div class="ALH_overlay" id="ALH_overlay"></div>

<!-- ════════════════════════════════════
     CLASS SELECTION MODAL
     ════════════════════════════════════ -->
<div id="ALH_clsmodal" class="ALH_clsmodal">
  <div class="ALH_clscard">
    <h2 class="ALH_clstitle">Pick Your Level</h2>
    <p class="ALH_clssub">Choose your study level to personalise your experience.</p>
    <div class="ALH_clsopts">
      <button class="ALH_clsopt" onclick="alhDoClass('School')" type="button">
        <span>School (Class 9 &amp; 10)</span><i class="fas fa-school"></i>
      </button>
      <button class="ALH_clsopt" onclick="alhDoClass('College')" type="button">
        <span>College (Class 11 &amp; 12)</span><i class="fas fa-university"></i>
      </button>
      <button class="ALH_clsopt" onclick="alhDoClass('University')" type="button">
        <span>University (Bachelor / Master)</span><i class="fas fa-graduation-cap"></i>
      </button>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════
     AUTH MODAL
     ════════════════════════════════════ -->
<div id="ALH_authmodal" class="ALH_authmodal">
  <div class="ALH_acard">
    <span class="ALH_xbtn" id="ALH_xauth">&times;</span>
    <h2 class="ALH_atitle" id="ALH_atitle">Welcome!</h2>
    <p class="ALH_asub" id="ALH_asub">Login or create an account to access premium features.</p>
    <a href="<?= $assetBase ?>login" class="ALH_abtn ALH_abtn-p"><i class="fas fa-sign-in-alt"></i> Login to My Account</a>
    <a href="<?= $assetBase ?>register" class="ALH_abtn ALH_abtn-s"><i class="fas fa-user-plus"></i> Create New Account</a>
    <p class="ALH_afooter">Unlock smart paper generation, quizzes &amp; expert notes!</p>
  </div>
</div>

<!-- ════════════════════════════════════
     UPGRADE MODAL
     ════════════════════════════════════ -->
<div id="ALH_upmodal" class="ALH_upmodal">
  <div class="ALH_upcard">
    <div class="ALH_upico"><i class="fas fa-crown"></i></div>
    <h2 class="ALH_uptitle" id="ALH_uptitle">Unlock Full Access</h2>
    <p class="ALH_uptext" id="ALH_uptext">Upgrade to premium for unlimited features and no ads.</p>
    <div class="ALH_upfeats">
      <div class="ALH_ufeat"><i class="fas fa-check-circle"></i>Unlimited paper generation</div>
      <div class="ALH_ufeat"><i class="fas fa-check-circle"></i>No platform-wide advertisements</div>
      <div class="ALH_ufeat"><i class="fas fa-check-circle"></i>Advanced AI paper builder</div>
    </div>
    <a href="<?= $assetBase ?>subscription" class="ALH_upcta"><i class="fas fa-rocket"></i> Upgrade Now</a>
    <button class="ALH_uplater" onclick="alhCloseUpgrade()" type="button">Maybe Later</button>
  </div>
</div>

</div><!-- /ALH_root -->

<!-- ════════════════════════════════════
     JAVASCRIPT
     ════════════════════════════════════ -->
<script>
(function(){
'use strict';
const $=id=>document.getElementById(id);

/* class quick-pick - moved to top for priority */
let _pa=null,_pg=null;
document.addEventListener('click',function(e){
    const btn = e.target && e.target.closest ? e.target.closest('.ALH_cct') : null;
    if(!btn) return;
    
    const action = btn.getAttribute('data-action');
    if(!action) return;

    e.preventDefault();
    e.stopPropagation(); 
    
    const dc = btn.getAttribute('data-class');
    const grade = btn.getAttribute('data-grade');
    const bypass = btn.classList.contains('bypass-user-type');
    
    if(dc){ 
        localStorage.setItem('user_class_level_selection',dc); 
        alhGo(dc,action,grade); 
        return; 
    }
    
    const stored = localStorage.getItem('user_class_level_selection');
    if(stored && !bypass){ 
        alhGo(stored,action,null); 
    }
    else{ 
        _pa=action; 
        _pg=grade || null;
        const m = document.getElementById('ALH_clsmodal');
        if(m) m.style.setProperty('display','flex','important');
    }
});

const burger=$('ALH_burger'), menu=$('ALH_menu'), overlay=$('ALH_overlay'), nav=$('ALH_nav');
const isMob=()=>window.innerWidth<=768;

/* scroll effect */
window.addEventListener('scroll',()=>nav.classList.toggle('ALH_scrolled',scrollY>20),{passive:true});

/* sidebar open/close */
function openSB(){
    menu.classList.add('ALH_mopen');
    overlay.classList.add('ALH_ovopen');
    burger.classList.add('ALH_bopen');
    burger.setAttribute('aria-expanded','true');
    document.body.style.overflow='hidden';
}
function closeSB(){
    menu.classList.remove('ALH_mopen');
    overlay.classList.remove('ALH_ovopen');
    burger.classList.remove('ALH_bopen');
    burger.setAttribute('aria-expanded','false');
    document.body.style.overflow='';
    document.querySelectorAll('.ALH_panel.ALH_popen,.ALH_mega.ALH_popen').forEach(p=>{
        p.classList.remove('ALH_popen');
        const b=p.previousElementSibling; if(b) b.classList.remove('ALH_dopen');
    });
}

burger.addEventListener('click',()=>menu.classList.contains('ALH_mopen')?closeSB():openSB());
overlay.addEventListener('click',closeSB);
menu.addEventListener('click',e=>{ if(!e.target.closest('.ALH_cct')) e.stopPropagation(); });
document.addEventListener('keydown',e=>e.key==='Escape'&&closeSB());
window.addEventListener('resize',()=>!isMob()&&closeSB());

/* close sidebar on plain link click */
menu.querySelectorAll('a.ALH_link,a.ALH_join,a.ALH_ditem,a.ALH_ac').forEach(a=>{
    a.addEventListener('click',()=>isMob()&&closeSB());
});

/* mobile dropdown toggles */
document.querySelectorAll('.ALH_dbtn').forEach(btn=>{
    btn.addEventListener('click',function(e){
        if(!isMob()) return;
        e.preventDefault(); e.stopPropagation();
        const panel=this.nextElementSibling; if(!panel) return;
        const opening=!panel.classList.contains('ALH_popen');
        document.querySelectorAll('.ALH_panel.ALH_popen,.ALH_mega.ALH_popen').forEach(p=>{
            if(p!==panel){ p.classList.remove('ALH_popen'); const b=p.previousElementSibling; if(b) b.classList.remove('ALH_dopen'); }
        });
        panel.classList.toggle('ALH_popen',opening);
        this.classList.toggle('ALH_dopen',opening);
    });
});

/* moved up */

function alhGo(cls,action,grade){
    const base='<?= $assetBase ?>'; let url='';
    if(action==='generate_paper'){
        if(grade==='9'||grade==='10') url=base+'class-9th-and-10th-online-question-paper-generator';
        else if(grade==='college') url=base+'select_class.php';
        else if(grade==='university') url=base+'online-question-paper-generator';
        else {
            if(cls==='School') url=base+'class-9th-and-10th-online-question-paper-generator';
            else if(cls==='College') url=base+'select_class.php';
            else url=base+'online-question-paper-generator';
        }
    } else if(action==='online_mcqs'){
        if(cls==='School') url=base+'online-mcqs-test-for-9th-and-10th-board-exams';
        else if(cls==='College') url=base+'class-11-and-12-online-mcqs-prepation-test';
        else if(cls==='University') url=base+'class-11-and-12-online-mcqs-prepation-test';
    } else if(action==='board_exam_prep'){
        if(cls==='School') url=base+'Class-9-10-pastPaper-&-Test-Papers';
        else if(cls==='College') url=base+'Class-11-12-pastPaper-&-Test-Papers';
        else url=base+'University-pastPaper-&-Test-Papers';
    }
    if(url) window.location.href=url;
}

/* class modal */
function alhShowCls(){ const m=document.getElementById('ALH_clsmodal'); if(m) m.style.setProperty('display','flex','important'); }
function alhHideCls(){ const m=$('ALH_clsmodal'); if(m) m.style.display='none'; }
window.alhDoClass=function(cls){ localStorage.setItem('user_class_level_selection',cls); alhHideCls(); if(_pa) alhGo(cls,_pa,_pg); };
const clsm=$('ALH_clsmodal'); if(clsm) clsm.addEventListener('click',e=>{ if(e.target===clsm) alhHideCls(); });

/* upgrade modal */
const upm=$('ALH_upmodal');
window.showAlhUpgradeModal=window.showGlobalUpgradeModal=function(type='general'){
    const t=$('ALH_uptitle'),tx=$('ALH_uptext');
    if(type==='topics'){ t.textContent='Unlock Unlimited Topics'; tx.innerHTML='Upgrade to select <strong>unlimited topics</strong> per assessment.'; }
    else if(type==='questions'){ t.textContent='Maximum Questions Reached'; tx.innerHTML='Free users are limited. <strong>Upgrade for unlimited counts.</strong>'; }
    else { t.textContent='Unlock Premium Features'; tx.textContent='Experience the full power of Ahmad Learning Hub.'; }
    if(upm){ upm.style.display='flex'; document.body.style.overflow='hidden'; }
};
window.alhCloseUpgrade=window.closeGlobalUpgradeModal=function(){ if(upm){ upm.style.display='none'; document.body.style.overflow=''; } };
if(upm) upm.addEventListener('click',e=>{ if(e.target===upm) alhCloseUpgrade(); });

/* auth modal */
const am=$('ALH_authmodal'); const xa=$('ALH_xauth');
const isLI=<?= isset($_SESSION['user_id'])?'true':'false' ?>;
function showAuth(){
    if(!am) return;
    if(localStorage.getItem('alh_was_logged_in')==='true'){ $('ALH_atitle').textContent='Welcome Back!'; $('ALH_asub').textContent='Your session expired. Please login again.'; }
    am.classList.add('ALH_show');
}
function hideAuth(){ if(am){ am.classList.remove('ALH_show'); sessionStorage.setItem('alh_auth_seen','1'); } }
if(xa) xa.addEventListener('click',hideAuth);
if(am) am.addEventListener('click',e=>{ if(e.target===am) hideAuth(); });
window.addEventListener('load',()=>{
    const p=window.location.pathname;
    if(!isLI&&!/login|register|forgot_password|reset_password/.test(p)&&!sessionStorage.getItem('alh_auth_seen')){
        sessionStorage.setItem('alh_auth_seen','1'); setTimeout(showAuth,20000);
    }
    if(isLI) localStorage.setItem('alh_was_logged_in','true');
});

/* mode switch */
function syncMode(){
    const t=localStorage.getItem('user_type_preference')||'School', s=t==='School';
    document.documentElement.classList.toggle('alh-school',s);
    document.documentElement.classList.toggle('school-mode',s);
    const lbl=$('ALH_modelabel'); if(lbl) lbl.textContent=s?'Switch to Advance Mode':'Switch to School Mode';
    const bS=document.getElementById('btnSchoolMode'),bA=document.getElementById('btnAdvanceMode');
    if(bS&&bA){ bS.classList.toggle('active',s); bA.classList.toggle('active',!s); }
}
const msw=$('ALH_modeswitch');
if(msw) msw.addEventListener('click',function(e){
    e.preventDefault();
    const cur=localStorage.getItem('user_type_preference')||'School';
    const nxt=cur==='School'?'Other':'School';
    localStorage.setItem('user_type_preference',nxt);
    if(typeof selectUserType==='function') selectUserType(nxt);
    syncMode();
});
window.addEventListener('storage',e=>{ if(e.key==='user_type_preference') syncMode(); });
syncMode();

})();
</script>