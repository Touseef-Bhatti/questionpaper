<!-- Header 1: Classic Formal Table -->
<style>
.h1-wrap { width: 100%; border: 2.5px solid #000; border-collapse: collapse; font-family: 'Times New Roman', Times, serif; margin-bottom: 18px; }
.h1-wrap td, .h1-wrap th { border: 1.5px solid #000; padding: 6px 10px; }
.h1-wrap .school-cell { text-align: center; font-size: 22px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; padding: 10px; }
.h1-wrap .sub-row td { font-size: 13px; }
.h1-wrap [contenteditable="true"]:hover { background: rgba(0,0,0,0.04); cursor: text; border-radius: 2px; }
.h1-wrap [contenteditable="true"]:focus { outline: 1.5px dashed #555; background: #fffef0; }
.h1-label { font-weight: bold; font-size: 12px; color: #444; display: block; margin-bottom: 2px; }
</style>
<table class="h1-wrap header-design header-design-1">
    <tr>
        <td colspan="3" class="school-cell">
            <span contenteditable="true"><?php echo htmlspecialchars($instituteName ?? 'OPF School & College'); ?></span>
        </td>
    </tr>
    <tr class="sub-row">
        <td style="width:33%; text-align:center;">
            <span class="h1-label">Subject</span>
            <span contenteditable="true"><?php echo htmlspecialchars($bookName ?? 'Subject Name'); ?></span>
        </td>
        <td style="width:34%; text-align:center;">
            <span class="h1-label">Examination</span>
            <span contenteditable="true">First Term Examination</span>
        </td>
        <td style="width:33%; text-align:center;">
            <span class="h1-label">Session</span>
            <span contenteditable="true">2025 – 2026</span>
        </td>
    </tr>
    <tr class="sub-row">
        <td style="text-align:center;">
            <span class="h1-label">Class</span>
            <span contenteditable="true"><?php echo htmlspecialchars($classNameHeader ?? 'Class Name'); ?></span>
        </td>
        <td style="text-align:center;">
            <span class="h1-label">Total Marks</span>
            <span contenteditable="true"><?php echo htmlspecialchars((string)($totalMarks ?? '0')); ?></span>
        </td>
        <td style="text-align:center;">
            <span class="h1-label">Time Allowed</span>
            <span contenteditable="true">1 Hour</span>
        </td>
    </tr>
    <tr class="sub-row">
        <td colspan="2" style="text-align:left;">
            <span class="h1-label">Student's Name</span>
            <span contenteditable="true" style="display:inline-block; min-width:200px;">_________________________________</span>
        </td>
        <td style="text-align:center;">
            <span class="h1-label">Roll No.</span>
            <span contenteditable="true">____________</span>
        </td>
    </tr>
</table>
