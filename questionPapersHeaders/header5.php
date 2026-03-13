<!-- Header 5: Boxed Info / Corporate Style -->
<style>
.h5-wrap { font-family: Arial, Helvetica, sans-serif; margin-bottom: 18px; border: 2px solid #000; }
.h5-header-bar { background: #000; color: #fff; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; }
.h5-school-name { font-size: 20px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; color: #fff; }
.h5-badge { background: #fff; color: #000; font-size: 10px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; padding: 3px 8px; border-radius: 2px; }
.h5-body { display: flex; border-top: none; }
.h5-left { flex: 2; border-right: 1.5px solid #000; padding: 12px 16px; }
.h5-right { flex: 1; padding: 12px 16px; }
.h5-subject-title { font-size: 16px; font-weight: 900; text-transform: uppercase; border-bottom: 1.5px solid #000; padding-bottom: 6px; margin-bottom: 8px; letter-spacing: 1px; }
.h5-field-row { display: flex; font-size: 12.5px; margin: 5px 0; line-height: 1.5; }
.h5-field-label { font-weight: bold; min-width: 100px; color: #333; }
.h5-right-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #666; display: block; margin-bottom: 2px; }
.h5-right-val { font-size: 14px; font-weight: bold; border-bottom: 1.5px solid #000; margin-bottom: 10px; display: block; padding-bottom: 3px; min-width: 80px; }
.h5-marks-circle { width: 56px; height: 56px; border: 3px solid #000; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 6px auto 0; text-align: center; }
.h5-marks-num { font-size: 18px; font-weight: 900; line-height: 1; }
.h5-marks-lbl { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; }
.h5-wrap [contenteditable="true"]:hover { background: rgba(255,255,255,0.15); cursor: text; border-radius: 2px; }
.h5-header-bar [contenteditable="true"]:focus { outline: 1.5px dashed #fff; background: rgba(255,255,255,0.1); }
.h5-body [contenteditable="true"]:hover { background: rgba(0,0,0,0.04); }
.h5-body [contenteditable="true"]:focus { outline: 1.5px dashed #333; background: #fffef0; }
</style>
<div class="h5-wrap header-design header-design-5">
    <div class="h5-header-bar">
        <div class="h5-school-name"><span contenteditable="true"><?php echo htmlspecialchars($instituteName ?? 'OPF School & College'); ?></span></div>
        <div class="h5-badge"><span contenteditable="true">Official Examination</span></div>
    </div>
    <div class="h5-body">
        <div class="h5-left">
            <div class="h5-subject-title"><span contenteditable="true"><?php echo htmlspecialchars($bookName ?? 'Subject Name'); ?></span></div>
            <div class="h5-field-row"><span class="h5-field-label">Class:</span><span contenteditable="true"><?php echo htmlspecialchars($classNameHeader ?? 'Class Name'); ?></span></div>
            <div class="h5-field-row"><span class="h5-field-label">Examination:</span><span contenteditable="true">First Term</span></div>
            <div class="h5-field-row"><span class="h5-field-label">Syllabus:</span><span contenteditable="true"><?php echo htmlspecialchars($chapterHeaderLabel ?? 'Full Book'); ?></span></div>
            <div class="h5-field-row"><span class="h5-field-label">Date:</span><span contenteditable="true"><?php echo date('d F, Y'); ?></span></div>
        </div>
        <div class="h5-right">
            <span class="h5-right-label">Student Name</span>
            <span class="h5-right-val"><span contenteditable="true">________________________</span></span>
            <span class="h5-right-label">Roll Number</span>
            <span class="h5-right-val"><span contenteditable="true">____________</span></span>
            <div class="h5-marks-circle">
                <span class="h5-marks-num"><span contenteditable="true"><?php echo htmlspecialchars((string)($totalMarks ?? '0')); ?></span></span>
                <span class="h5-marks-lbl">Marks</span>
            </div>
        </div>
    </div>
</div>
