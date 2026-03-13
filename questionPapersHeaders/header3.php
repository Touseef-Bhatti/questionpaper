<!-- Header 3: Government / Board Exam Style -->
<style>
.h3-wrap { font-family: 'Times New Roman', Times, serif; margin-bottom: 18px; border: 2.5px solid #000; padding: 0; }
.h3-top-bar { background: #000; color: #fff; text-align: center; padding: 6px 10px; font-size: 11px; letter-spacing: 3px; text-transform: uppercase; font-weight: 600; }
.h3-main { display: flex; align-items: center; border-bottom: 2px solid #000; }
.h3-logo { width: 90px; min-width: 90px; border-right: 2px solid #000; padding: 12px; text-align: center; }
.h3-logo-circle { width: 58px; height: 58px; border: 3px double #000; border-radius: 50%; margin: 0 auto 4px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 18px; }
.h3-logo-text { font-size: 9px; letter-spacing: 1px; text-transform: uppercase; }
.h3-center { flex: 1; padding: 10px 16px; text-align: center; }
.h3-school { font-size: 22px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; line-height: 1.2; }
.h3-exam-title { font-size: 14px; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
.h3-subject { font-size: 13px; margin-top: 3px; }
.h3-stamp { width: 90px; min-width: 90px; border-left: 2px solid #000; padding: 10px; text-align: center; }
.h3-roll-box { border: 2px solid #000; padding: 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
.h3-roll-val { font-size: 13px; font-weight: bold; display: block; margin-top: 4px; border-top: 1px solid #000; padding-top: 4px; }
.h3-meta-row { display: flex; background: #f7f7f7; border-top: 1px solid #ddd; }
.h3-meta-cell { flex: 1; border-right: 1.5px solid #ccc; padding: 6px 10px; font-size: 12px; }
.h3-meta-cell:last-child { border-right: none; }
.h3-meta-cell strong { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #555; display: block; margin-bottom: 2px; }
.h3-wrap [contenteditable="true"]:hover { background: rgba(0,0,0,0.04); cursor: text; border-radius: 2px; }
.h3-wrap [contenteditable="true"]:focus { outline: 1.5px dashed #555; background: #fffef0; }
</style>
<div class="h3-wrap header-design header-design-3">
    <div class="h3-top-bar"><span contenteditable="true">Confidential — For Official Use Only</span></div>
    <div class="h3-main">
        <div class="h3-logo">
            <div class="h3-logo-circle"><span contenteditable="true" style="font-size:11px;">OPF</span></div>
            <div class="h3-logo-text"><span contenteditable="true">Est. 1971</span></div>
        </div>
        <div class="h3-center">
            <div class="h3-school"><span contenteditable="true"><?php echo htmlspecialchars($instituteName ?? 'OPF School & College'); ?></span></div>
            <div class="h3-exam-title"><span contenteditable="true">First Term Examination 2025–2026</span></div>
            <div class="h3-subject">Subject: <span contenteditable="true"><?php echo htmlspecialchars($bookName ?? 'Subject Name'); ?></span></div>
        </div>
        <div class="h3-stamp">
            <div class="h3-roll-box">
                Roll Number
                <span class="h3-roll-val"><span contenteditable="true">______</span></span>
            </div>
        </div>
    </div>
    <div class="h3-meta-row">
        <div class="h3-meta-cell"><strong>Class</strong><span contenteditable="true"><?php echo htmlspecialchars($classNameHeader ?? 'Class Name'); ?></span></div>
        <div class="h3-meta-cell"><strong>Maximum Marks</strong><span contenteditable="true"><?php echo htmlspecialchars((string)($totalMarks ?? '0')); ?></span></div>
        <div class="h3-meta-cell"><strong>Time Allowed</strong><span contenteditable="true">1 Hour</span></div>
        <div class="h3-meta-cell"><strong>Student's Name</strong><span contenteditable="true">_______________________</span></div>
    </div>
</div>
