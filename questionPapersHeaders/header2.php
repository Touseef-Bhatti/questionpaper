<!-- Header 2: Modern Minimal Clean -->
<style>
.h2-wrap { font-family: 'Arial', sans-serif; margin-bottom: 18px; border-bottom: 3px solid #1a1a2e; padding-bottom: 14px; }
.h2-wrap .h2-top { text-align: center; margin-bottom: 12px; }
.h2-wrap .h2-school { font-size: 24px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #1a1a2e; line-height: 1.2; }
.h2-wrap .h2-tagline { font-size: 12px; color: #555; margin-top: 3px; letter-spacing: 1px; text-transform: uppercase; }
.h2-wrap .h2-divider { width: 60px; height: 3px; background: #1a1a2e; margin: 8px auto; }
.h2-wrap .h2-subject { font-size: 15px; font-weight: bold; color: #1a1a2e; text-transform: uppercase; margin-top: 4px; }
.h2-wrap .h2-meta { display: flex; justify-content: space-between; align-items: center; background: #f4f4f4; border: 1px solid #ddd; border-radius: 4px; padding: 8px 16px; font-size: 13px; margin-top: 10px; }
.h2-wrap .h2-meta-item { text-align: center; }
.h2-wrap .h2-meta-label { font-size: 10px; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 1px; display: block; }
.h2-wrap .h2-meta-val { font-size: 14px; font-weight: bold; color: #1a1a2e; display: block; margin-top: 2px; }
.h2-wrap .h2-student-row { display: flex; justify-content: space-between; margin-top: 8px; font-size: 13px; }
.h2-wrap [contenteditable="true"]:hover { background: rgba(0,0,0,0.04); cursor: text; border-radius: 2px; }
.h2-wrap [contenteditable="true"]:focus { outline: 1.5px dashed #1a1a2e; background: #fffef0; }
</style>
<div class="h2-wrap header-design header-design-2">
    <div class="h2-top">
        <div class="h2-school"><span contenteditable="true"><?php echo htmlspecialchars($instituteName ?? 'OPF School & College'); ?></span></div>
        <div class="h2-tagline"><span contenteditable="true">Excellence in Education</span></div>
        <div class="h2-divider"></div>
        <div class="h2-subject"><span contenteditable="true"><?php echo htmlspecialchars($bookName ?? 'Subject Name'); ?></span></div>
    </div>
    <div class="h2-meta">
        <div class="h2-meta-item">
            <span class="h2-meta-label">Class</span>
            <span class="h2-meta-val"><span contenteditable="true"><?php echo htmlspecialchars($classNameHeader ?? 'Class'); ?></span></span>
        </div>
        <div class="h2-meta-item">
            <span class="h2-meta-label">Examination</span>
            <span class="h2-meta-val"><span contenteditable="true">First Term</span></span>
        </div>
        <div class="h2-meta-item">
            <span class="h2-meta-label">Total Marks</span>
            <span class="h2-meta-val"><span contenteditable="true"><?php echo htmlspecialchars((string)($totalMarks ?? '0')); ?></span></span>
        </div>
        <div class="h2-meta-item">
            <span class="h2-meta-label">Time Allowed</span>
            <span class="h2-meta-val"><span contenteditable="true">1 Hour</span></span>
        </div>
        <div class="h2-meta-item">
            <span class="h2-meta-label">Session</span>
            <span class="h2-meta-val"><span contenteditable="true">2025–26</span></span>
        </div>
    </div>
    <div class="h2-student-row">
        <span><strong>Student Name:</strong> <span contenteditable="true" style="display:inline-block; min-width:180px; border-bottom:1px solid #333;">_______________________________</span></span>
        <span><strong>Roll No:</strong> <span contenteditable="true" style="display:inline-block; min-width:80px; border-bottom:1px solid #333;">___________</span></span>
    </div>
</div>
