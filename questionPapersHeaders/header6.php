<!-- Header 6: Centered Academic / AI Style -->
<style>
.h6-wrap { font-family: 'Times New Roman', Times, serif; margin-bottom: 18px; text-align: center; }
.h6-toprule { height: 4px; background: #000; margin-bottom: 0; }
.h6-bottomrule { height: 1.5px; background: #000; margin-top: 0; }
.h6-body { border-left: 4px solid #000; border-right: 4px solid #000; padding: 14px 20px 10px; }
.h6-school { font-size: 28px; font-weight: 900; text-transform: uppercase; letter-spacing: 3px; line-height: 1.1; }
.h6-subtitle { font-size: 12px; letter-spacing: 4px; text-transform: uppercase; color: #444; margin-top: 2px; }
.h6-divider { display: flex; align-items: center; margin: 8px 0; }
.h6-divider::before, .h6-divider::after { content: ''; flex: 1; height: 1px; background: #000; }
.h6-divider span { padding: 0 12px; font-size: 14px; color: #000; }
.h6-exam { font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; }
.h6-subject { font-size: 14px; margin-top: 2px; font-style: italic; }
.h6-meta { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 12px; border-top: 1.5px solid #000; padding-top: 8px; font-size: 13px; }
.h6-meta-left, .h6-meta-right { text-align: left; }
.h6-meta-center { text-align: center; }
.h6-meta span { display: block; margin: 2px 0; }
.h6-wrap [contenteditable="true"]:hover { background: rgba(0,0,0,0.04); cursor: text; border-radius: 2px; }
.h6-wrap [contenteditable="true"]:focus { outline: 1.5px dashed #555; background: #fffef0; }
</style>
<div class="h6-wrap header-design header-design-6">
    <div class="h6-toprule"></div>
    <div class="h6-body">
        <div class="h6-school"><span contenteditable="true"><?php echo htmlspecialchars($instituteName ?? 'OPF School & College'); ?></span></div>
        <div class="h6-subtitle"><span contenteditable="true">Knowledge • Discipline • Excellence</span></div>
        <div class="h6-divider"><span>✦</span></div>
        <div class="h6-exam"><span contenteditable="true">First Term Examination</span></div>
        <div class="h6-subject">Subject: <span contenteditable="true"><?php echo htmlspecialchars($bookName ?? 'Subject Name'); ?></span></div>
        <div class="h6-meta">
            <div class="h6-meta-left">
                <span><strong>Class:</strong> <span contenteditable="true"><?php echo htmlspecialchars($classNameHeader ?? 'Class Name'); ?></span></span>
                <span><strong>Session:</strong> <span contenteditable="true">2025 – 2026</span></span>
            </div>
            <div class="h6-meta-center">
                <span><strong>Date:</strong> <span contenteditable="true"><?php echo date('d M, Y'); ?></span></span>
                <span><strong>Total Marks:</strong> <span id="total-marks-display" contenteditable="true"><?php echo htmlspecialchars((string)($totalMarks ?? '0')); ?></span></span>
            </div>
            <div class="h6-meta-right">
                <span><strong>Time Allowed:</strong> <span contenteditable="true">1 Hour</span></span>
                <span><strong>Roll No:</strong> <span contenteditable="true">__________</span></span>
            </div>
        </div>
    </div>
    <div class="h6-bottomrule"></div>
</div>
