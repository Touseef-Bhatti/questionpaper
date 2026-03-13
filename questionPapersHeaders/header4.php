<!-- Header 4: Elegant Double Border -->
<style>
.h4-wrap { font-family: 'Times New Roman', Times, serif; margin-bottom: 18px; border: 1px solid #000; padding: 6px; }
.h4-inner { border: 3px double #000; padding: 14px 18px; }
.h4-top { text-align: center; border-bottom: 1.5px solid #000; padding-bottom: 10px; margin-bottom: 10px; }
.h4-school { font-size: 26px; font-weight: 900; text-transform: uppercase; letter-spacing: 3px; }
.h4-stars { font-size: 14px; letter-spacing: 6px; color: #333; margin: 2px 0; }
.h4-exam { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; }
.h4-subject { font-size: 14px; margin-top: 3px; font-style: italic; }
.h4-body { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 13px; }
.h4-field { display: flex; flex-direction: column; padding: 4px 0; border-bottom: 1px dotted #aaa; }
.h4-field strong { font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; color: #555; }
.h4-wrap [contenteditable="true"]:hover { background: rgba(0,0,0,0.04); cursor: text; border-radius: 2px; }
.h4-wrap [contenteditable="true"]:focus { outline: 1.5px dashed #555; background: #fffef0; }
</style>
<div class="h4-wrap header-design header-design-4">
    <div class="h4-inner">
        <div class="h4-top">
            <div class="h4-school"><span contenteditable="true"><?php echo htmlspecialchars($instituteName ?? 'OPF School & College'); ?></span></div>
            <div class="h4-stars">✦ ✦ ✦</div>
            <div class="h4-exam"><span contenteditable="true">First Term Examination — 2025/2026</span></div>
            <div class="h4-subject">Subject: <em><span contenteditable="true"><?php echo htmlspecialchars($bookName ?? 'Subject Name'); ?></span></em></div>
        </div>
        <div class="h4-body">
            <div class="h4-field">
                <strong>Class &amp; Section</strong>
                <span contenteditable="true"><?php echo htmlspecialchars($classNameHeader ?? 'Class Name'); ?></span>
            </div>
            <div class="h4-field">
                <strong>Date</strong>
                <span contenteditable="true"><?php echo date('d F, Y'); ?></span>
            </div>
            <div class="h4-field">
                <strong>Student's Full Name</strong>
                <span contenteditable="true">_________________________________</span>
            </div>
            <div class="h4-field">
                <strong>Roll No.</strong>
                <span contenteditable="true">______________</span>
            </div>
            <div class="h4-field">
                <strong>Maximum Marks</strong>
                <span contenteditable="true"><?php echo htmlspecialchars((string)($totalMarks ?? '0')); ?></span>
            </div>
            <div class="h4-field">
                <strong>Time Allowed</strong>
                <span contenteditable="true">1 Hour</span>
            </div>
        </div>
    </div>
</div>
