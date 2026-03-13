<!-- ======================================================
     PAPER CUSTOMISATION POPUP — include in any paper page
     Requires: paper-layouts.css, FontAwesome
     ====================================================== -->
<style>
/* ── Customise Trigger Button ── */
.btn-customise {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff;
    border: none;
    border-radius: 30px;
    padding: 12px 22px;
    font-weight: 700;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(79,70,229,0.35);
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
    white-space: nowrap;
}
.btn-customise:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(79,70,229,0.45); }

/* ── Overlay ── */
.cust-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(15,15,30,0.55);
    backdrop-filter: blur(4px);
    z-index: 9998;
    animation: fadeOverlay .25s ease;
}
.cust-overlay.open { display: block; }
@keyframes fadeOverlay { from { opacity:0; } to { opacity:1; } }

/* ── Modal Panel ── */
.cust-panel {
    display: none;
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%) scale(0.96);
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 30px 80px rgba(0,0,0,0.22);
    z-index: 9999;
    width: min(820px, 94vw);
    max-height: 88vh;
    overflow-y: auto;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    transition: transform .25s cubic-bezier(.4,0,.2,1), opacity .25s;
    opacity: 0;
}
.cust-panel.open { display: block; transform: translate(-50%,-50%) scale(1); opacity: 1; }

/* ── Panel Header ── */
.cust-panel-header {
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    color: #fff;
    padding: 20px 28px;
    border-radius: 20px 20px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
}
.cust-panel-title { font-size: 18px; font-weight: 800; letter-spacing: 0.5px; }
.cust-panel-subtitle { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 2px; }
.cust-close {
    background: rgba(255,255,255,0.12); border: none; color: #fff;
    width: 34px; height: 34px; border-radius: 50%; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .2s;
}
.cust-close:hover { background: rgba(255,255,255,0.25); }

/* ── Tabs ── */
.cust-tabs {
    display: flex; gap: 0;
    border-bottom: 2px solid #f0f0f5;
    padding: 0 28px;
    background: #fafafa;
}
.cust-tab {
    padding: 14px 20px; font-size: 13px; font-weight: 700;
    color: #888; cursor: pointer; border-bottom: 3px solid transparent;
    margin-bottom: -2px; transition: all .2s; white-space: nowrap;
    display: flex; align-items: center; gap: 6px;
}
.cust-tab:hover { color: #4f46e5; }
.cust-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; }

/* ── Tab Content ── */
.cust-body { padding: 24px 28px 28px; }
.cust-tab-pane { display: none; }
.cust-tab-pane.active { display: block; }
.cust-section-label {
    font-size: 11px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1.5px; color: #aaa; margin-bottom: 14px;
}

/* ── Option Grid ── */
.cust-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.cust-card {
    border: 2px solid #e8e8f0; border-radius: 12px; padding: 12px 10px 10px;
    cursor: pointer; text-align: center; transition: all .25s;
    background: #fff;
}
.cust-card:hover { border-color: #c4b5fd; background: #faf5ff; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(79,70,229,0.08); }
.cust-card.selected { border-color: #4f46e5; background: #eef2ff; box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
.cust-card .cust-preview {
    height: 52px; border-radius: 6px; margin-bottom: 8px;
    border: 1px solid #ddd; background: #f8f8fb; overflow: hidden; position: relative;
}
.cust-card-label { font-size: 11px; font-weight: 700; color: #444; }
.cust-card.selected .cust-card-label { color: #4f46e5; }

/* Header preview thumbnails */
.cp-h1::before { content:''; position:absolute; inset:6px; border:1.5px solid #999; }
.cp-h1::after  { content:''; position:absolute; top:14px; left:6px; right:6px; height:2px; background:#999; }
.cp-h2::before { content:''; position:absolute; top:8px; left:15%; right:15%; height:3px; background:#1a1a2e; border-radius:2px; }
.cp-h2::after  { content:''; position:absolute; top:16px; left:8px; right:8px; height:1px; background:#ccc; }
.cp-h3 { background:linear-gradient(to bottom, #111 18px, #f4f4f4 18px) !important; }
.cp-h4::before { content:''; position:absolute; inset:5px; border:2px double #999; }
.cp-h5 { background:linear-gradient(to bottom, #000 22px, #fff 22px) !important; }
.cp-h6::before { content:''; position:absolute; top:4px; left:0; right:0; height:3px; background:#000; }
.cp-h6::after  { content:''; position:absolute; bottom:4px; left:0; right:0; height:2px; background:#000; }

/* Paper preview thumbnails */
.pp-1 { background:#fff; border:1px solid #bbb; }
.pp-2 { background:repeating-linear-gradient(to bottom,#fff 0,#fff 8px,#c8d8f0 9px); border:1px solid #3b5fa0; }
.pp-3 { background:#fdf6e3; border:1px double #c8a96e; }
.pp-4 { background:#f4fff7; border:1px solid #2e7d32; }
.pp-5 { background:#fff; border-top:4px solid #1a1a2e; border-left:1px solid #eee; border-right:1px solid #eee; border-bottom:1px solid #eee; }

/* ── Font Size Slider ── */
.cust-range-row { display:flex; align-items:center; gap:14px; margin-top:8px; }
.cust-range-row label { font-size:13px; font-weight:600; min-width:120px; }
.cust-range-row input[type=range] { flex:1; accent-color: #4f46e5; }
.cust-range-val { font-size:13px; font-weight:700; color:#4f46e5; min-width:36px; text-align:right; }

/* ── Apply Button ── */
.cust-apply {
    width:100%; margin-top:20px; padding:14px;
    background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff;
    border:none; border-radius:12px; font-size:15px; font-weight:800;
    cursor:pointer; transition:all .3s; letter-spacing:0.5px;
}
.cust-apply:hover { opacity:0.92; transform:translateY(-2px); box-shadow:0 8px 24px rgba(79,70,229,0.3); }

@media(max-width:600px) { .cust-grid { grid-template-columns: repeat(2,1fr); } .cust-panel { border-radius:14px; } }
</style>

<!-- Overlay -->
<div class="cust-overlay" id="custOverlay" onclick="closeCustPanel()"></div>

<!-- Customisation Panel -->
<div class="cust-panel" id="custPanel">
    <div class="cust-panel-header">
        <div>
            <div class="cust-panel-title"><i class="fas fa-sliders-h me-2"></i>Paper Customisation</div>
            <div class="cust-panel-subtitle">Choose header style, paper layout &amp; font size</div>
        </div>
        <button class="cust-close" onclick="closeCustPanel()">✕</button>
    </div>

    <div class="cust-tabs">
        <div class="cust-tab active" onclick="switchCustTab('header', this)"><i class="fas fa-heading"></i> Header Style</div>
        <div class="cust-tab" onclick="switchCustTab('paper', this)"><i class="fas fa-file-alt"></i> Paper Layout</div>
        <div class="cust-tab" onclick="switchCustTab('font', this)"><i class="fas fa-text-height"></i> Typography</div>
    </div>

    <div class="cust-body">
        <!-- Header Tab -->
        <div class="cust-tab-pane active" id="cust-pane-header">
            <div class="cust-section-label">Choose Header Design</div>
            <div class="cust-grid">
                <div class="cust-card selected" id="hcard-1" onclick="selectCustHeader(1)">
                    <div class="cust-preview cp-h1"></div>
                    <div class="cust-card-label">1 — Formal Table</div>
                </div>
                <div class="cust-card" id="hcard-2" onclick="selectCustHeader(2)">
                    <div class="cust-preview cp-h2"></div>
                    <div class="cust-card-label">2 — Modern Minimal</div>
                </div>
                <div class="cust-card" id="hcard-3" onclick="selectCustHeader(3)">
                    <div class="cust-preview cp-h3"></div>
                    <div class="cust-card-label">3 — Board / Gov't</div>
                </div>
                <div class="cust-card" id="hcard-4" onclick="selectCustHeader(4)">
                    <div class="cust-preview cp-h4"></div>
                    <div class="cust-card-label">4 — Elegant Double</div>
                </div>
                <div class="cust-card" id="hcard-5" onclick="selectCustHeader(5)">
                    <div class="cust-preview cp-h5"></div>
                    <div class="cust-card-label">5 — Corporate Box</div>
                </div>
                <div class="cust-card" id="hcard-6" onclick="selectCustHeader(6)">
                    <div class="cust-preview cp-h6"></div>
                    <div class="cust-card-label">6 — Centered Academic</div>
                </div>
            </div>
        </div>

        <!-- Paper Layout Tab -->
        <div class="cust-tab-pane" id="cust-pane-paper">
            <div class="cust-section-label">Choose Paper Layout</div>
            <div class="cust-grid">
                <div class="cust-card selected" id="pcard-1" onclick="selectCustPaper(1)">
                    <div class="cust-preview pp-1"></div>
                    <div class="cust-card-label">1 — Classic White</div>
                </div>
                <div class="cust-card" id="pcard-2" onclick="selectCustPaper(2)">
                    <div class="cust-preview pp-2"></div>
                    <div class="cust-card-label">2 — Lined Notebook</div>
                </div>
                <div class="cust-card" id="pcard-3" onclick="selectCustPaper(3)">
                    <div class="cust-preview pp-3"></div>
                    <div class="cust-card-label">3 — Aged Parchment</div>
                </div>
                <div class="cust-card" id="pcard-4" onclick="selectCustPaper(4)">
                    <div class="cust-preview pp-4"></div>
                    <div class="cust-card-label">4 — Government Green</div>
                </div>
                <div class="cust-card" id="pcard-5" onclick="selectCustPaper(5)">
                    <div class="cust-preview pp-5"></div>
                    <div class="cust-card-label">5 — Modern Dark Accent</div>
                </div>
            </div>
        </div>

        <!-- Font/Typography Tab -->
        <div class="cust-tab-pane" id="cust-pane-font">
            <div class="cust-section-label">Question Font Size</div>
            <div class="cust-range-row">
                <label>Question Text</label>
                <input type="range" id="fontSizeRange" min="12" max="22" value="15" oninput="applyFontSize(this.value)">
                <span class="cust-range-val" id="fontSizeVal">15px</span>
            </div>
            <div class="cust-range-row" style="margin-top:16px;">
                <label>Option Text</label>
                <input type="range" id="optionSizeRange" min="10" max="20" value="14" oninput="applyOptionSize(this.value)">
                <span class="cust-range-val" id="optionSizeVal">14px</span>
            </div>
            <div class="cust-range-row" style="margin-top:16px;">
                <label>Line Spacing</label>
                <input type="range" id="lineHeightRange" min="14" max="28" value="18" oninput="applyLineHeight(this.value)">
                <span class="cust-range-val" id="lineHeightVal">1.8</span>
            </div>
        </div>

        <button class="cust-apply" onclick="applyCustomisation()"><i class="fas fa-check-circle me-2"></i>Apply Customisation</button>
    </div>
</div>

<script>
let _custHeaderSel = 1, _custPaperSel = 1;

function openCustPanel() {
    document.getElementById('custPanel').classList.add('open');
    document.getElementById('custOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeCustPanel() {
    document.getElementById('custPanel').classList.remove('open');
    document.getElementById('custOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
function switchCustTab(name, el) {
    document.querySelectorAll('.cust-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.cust-tab-pane').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('cust-pane-' + name).classList.add('active');
}
function selectCustHeader(id) {
    _custHeaderSel = id;
    for (let i = 1; i <= 6; i++) {
        const c = document.getElementById('hcard-' + i);
        if (c) c.classList.toggle('selected', i === id);
    }
}
function selectCustPaper(id) {
    _custPaperSel = id;
    for (let i = 1; i <= 5; i++) {
        const c = document.getElementById('pcard-' + i);
        if (c) c.classList.toggle('selected', i === id);
    }
}
function applyFontSize(val) {
    document.getElementById('fontSizeVal').textContent = val + 'px';
    document.querySelectorAll('.q-text, .mcq-question').forEach(el => el.style.fontSize = val + 'px');
}
function applyOptionSize(val) {
    document.getElementById('optionSizeVal').textContent = val + 'px';
    document.querySelectorAll('.option, .option-text, .mcq-options div').forEach(el => el.style.fontSize = val + 'px');
}
function applyLineHeight(val) {
    document.getElementById('lineHeightVal').textContent = (val / 10).toFixed(1);
    const paper = document.getElementById('paper') || document.querySelector('.paper-preview');
    if (paper) paper.style.lineHeight = (val / 10).toFixed(1);
}
function applyCustomisation() {
    // Apply header
    if (typeof changeHeader === 'function') {
        const hEl = document.querySelector('#hcard-' + _custHeaderSel);
        changeHeader(_custHeaderSel, hEl || document.createElement('div'));
    }
    // Apply paper layout
    const paper = document.getElementById('paper') || document.querySelector('.paper-preview');
    if (paper) {
        for (let i = 1; i <= 5; i++) paper.classList.remove('pl-' + i);
        paper.classList.add('pl-' + _custPaperSel);
    }
    closeCustPanel();
}
</script>
