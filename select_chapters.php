<?php
// Require authentication before accessing this page
// require_once 'auth_check.php';
include 'db_connect.php';

// Ensure class_id and book_name are provided
if (!isset($_GET['class_id']) || empty($_GET['class_id']) || !isset($_GET['book_name']) || empty($_GET['book_name'])) {
	header('Location: select_class.php');
	exit;
}

// Retrieve and sanitize input
$classId = intval($_GET['class_id']);
$book_name = trim($conn->real_escape_string($_GET['book_name']));

// Function to get pattern defaults based on class and book name
function getPatternDefaults($classId, $book_name) {
    $book_name_lower = strtolower($book_name);
    
    // Check if class is 9 or 10
    if ($classId == 9 || $classId == 10) {
        // For physics, chemistry, biology
        if (in_array($book_name_lower, ['physics', 'chemistry', 'biology'])) {
            return [
                'mcqs' => 12,
                'sq' => 24,
                'long' => 3
            ];
        }
        // For computer
        if ($book_name_lower == 'computer') {
            return [
                'mcqs' => 10,
                'sq' => 18,
                'long' => 3
            ];
        }
    }
    
    // Default values if conditions don't match
    return [
        'mcqs' => 0,
        'sq' => 0,
        'long' => 0
    ];
}

// Get pattern defaults
$patternDefaults = getPatternDefaults($classId, $book_name);

// Fetch chapters
$chapterQuery = "SELECT chapter_id, chapter_name FROM chapter WHERE class_id = $classId AND book_name = '$book_name' ORDER BY chapter_id ASC";
$result = $conn->query($chapterQuery);

// Check for errors
if (!$result) {
	die("<h2 style='color:red;'>Error fetching data: " . $conn->error . "</h2>");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/select_chapter.css">
    <link rel="stylesheet" href="css/buttons.css">



	<?php include 'header.php'; ?>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Select Chapters</title>
  
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pattern defaults from PHP
    const patternDefaults = {
        mcqs: <?= $patternDefaults['mcqs'] ?>,
        sq: <?= $patternDefaults['sq'] ?>,
        long: <?= $patternDefaults['long'] ?>
    };
    const classId = <?= $classId ?>;
    const bookName = '<?= strtolower($book_name) ?>';
    const isComputer = bookName === 'computer';
    
    const numberInputs = document.querySelectorAll('input[type="number"]');
    const form = document.querySelector('form[action="select_question.php"]');
    const patternYes = document.getElementById('pattern_yes');
    const patternNo = document.getElementById('pattern_no');
    const totalMcqsInput = document.getElementById('total_mcqs');
    const totalLongsInput = document.getElementById('total_longs');
    const statMcq = document.getElementById('stat_mcq');
    const statLong = document.getElementById('stat_long');
    const statShort = document.getElementById('stat_short');

    function isWithPattern() {
        return patternYes && patternYes.checked;
    }
    
    // Function to auto-fill pattern defaults
    function applyPatternDefaults() {
        if (isWithPattern() && (classId === 9 || classId === 10)) {
            const isScience = ['physics', 'chemistry', 'biology'].includes(bookName);
            
            if (isScience || isComputer) {
                if (patternDefaults.mcqs > 0 && totalMcqsInput) {
                    totalMcqsInput.value = patternDefaults.mcqs;
                }
                if (patternDefaults.long > 0 && totalLongsInput) {
                    totalLongsInput.value = patternDefaults.long;
                }
                if (patternDefaults.sq > 0) {
                    // Short questions are handled in updateShortsVisibility
                }
                recalcStatus();
            }
        }
    }

    function recalcStatus() {
        const withPattern = isWithPattern();
        // Use pattern defaults if applicable
        let defaultMcq = 12;
        if (withPattern && (classId === 9 || classId === 10)) {
            const isScience = ['physics', 'chemistry', 'biology'].includes(bookName);
            if (isScience || isComputer) {
                defaultMcq = patternDefaults.mcqs;
            }
        }
        const targetMcq = parseInt(totalMcqsInput?.value) || defaultMcq;
        const desiredLongs = parseInt(totalLongsInput?.value) || 0;
        // For computer, don't multiply by 2 (no parts), for other subjects with pattern, multiply by 2
        const targetLong = withPattern && !isComputer ? desiredLongs * 2 : desiredLongs;

        let sumMcq = 0, sumLong = 0, sumShort = 0;
        document.querySelectorAll('input[name^="mcqs["]').forEach(inp => { sumMcq += (parseInt(inp.value) || 0); });
        document.querySelectorAll('input[name^="long_questions["]').forEach(inp => { sumLong += (parseInt(inp.value) || 0); });
        document.querySelectorAll('input[name^="short_questions["]').forEach(inp => { sumShort += (parseInt(inp.value) || 0); });

        // MCQ tile
        statMcq.querySelector('.val').textContent = sumMcq;
        statMcq.querySelector('.target').textContent = targetMcq;
        const mcqRem = targetMcq - sumMcq;
        statMcq.querySelector('.rem').textContent = ` (${mcqRem === 0 ? 'done' : (mcqRem > 0 ? mcqRem + ' remaining' : Math.abs(mcqRem) + ' over')})`;
        statMcq.classList.toggle('ok', mcqRem === 0);
        statMcq.classList.toggle('warn', mcqRem > 0);
        statMcq.classList.toggle('over', mcqRem < 0);

        // LONG tile
        statLong.querySelector('.val').textContent = sumLong;
        statLong.querySelector('.target').textContent = targetLong;
        const longRem = targetLong - sumLong;
        statLong.querySelector('.rem').textContent = ` (${longRem === 0 ? 'done' : (longRem > 0 ? longRem + ' remaining' : Math.abs(longRem) + ' over')})`;
        statLong.classList.toggle('ok', longRem === 0);
        statLong.classList.toggle('warn', longRem > 0);
        statLong.classList.toggle('over', longRem < 0);
        document.querySelectorAll('input[name^="long_questions["]').forEach(inp => inp.classList.toggle('over-field', longRem < 0));

        // SHORT tile
        const isScience = ['biology', 'chemistry', 'physics'].includes(bookName);
        statShort.querySelector('.val').textContent = sumShort;
        const hint = statShort.querySelector('.hint');
        if ((isScience || isComputer) && withPattern && (classId === 9 || classId === 10)) {
            const targetShort = patternDefaults.sq;
            const delta = targetShort - sumShort;
            statShort.classList.toggle('ok', delta === 0);
            statShort.classList.toggle('warn', delta > 0);
            statShort.classList.toggle('over', delta < 0);
            hint.textContent = ` (${delta === 0 ? 'done' : (delta > 0 ? delta + ' remaining to reach ' + targetShort : Math.abs(delta) + ' over ' + targetShort)})`;
            document.querySelectorAll('input[name^="short_questions["]').forEach(inp => inp.classList.toggle('over-field', delta < 0));
        } else {
            statShort.classList.remove('ok', 'warn', 'over');
            if (!withPattern) {
                hint.textContent = ` (user input: ${sumShort})`;
            } else {
                hint.textContent = '';
            }
            document.querySelectorAll('input[name^="short_questions["]').forEach(inp => inp.classList.remove('over-field'));
        }
    }

    function ensureChapterChecked(el) {
        const checkbox = el.closest('label')?.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = true;
    }

    function getQCount() {
        return Math.max(1, Math.min(parseInt(totalLongsInput?.value) || 1, 10));
    }

    function renderLongPlacementsForChapter(chapterId, count) {
        const wrap = document.getElementById('long-placement-' + chapterId);
        if (!wrap) return;
        const n = Math.max(0, parseInt(count || 0));
        wrap.innerHTML = '';
        if (n <= 0 || !isWithPattern()) return;

        const title = document.createElement('div');
        title.textContent = 'Long question placements for this chapter:';
        title.style.margin = '6px 0';
        title.style.fontSize = '14px';
        wrap.appendChild(title);
        
        for (let i = 0; i < n; i++) {
            const row = document.createElement('div');
            row.className = 'placement-row';
            if (isComputer) {
                // For computer, no parts - just question number
                row.innerHTML = `
                    <label>#${i + 1} Question</label>
                    <select name="long_qnum[${chapterId}][]" style="padding:5px; min-width:90px;">
                        ${Array.from({ length: getQCount() }, (_, k) => `<option value="${k + 1}">Q${k + 1}</option>`).join('')}
                    </select>
                `;
            } else {
                // For other subjects, include parts (a) and (b)
                row.innerHTML = `
                    <label>#${i + 1} Question</label>
                    <select name="long_qnum[${chapterId}][]" style="padding:5px; min-width:90px;">
                        ${Array.from({ length: getQCount() }, (_, k) => `<option value="${k + 1}">Q${k + 1}</option>`).join('')}
                    </select>
                    <label>Part</label>
                    <select name="long_part[${chapterId}][]" style="padding:5px;">
                        <option value="a">a</option>
                        <option value="b">b</option>
                    </select>
                `;
            }
            wrap.appendChild(row);
        }
    }

    function rerenderAllPlacements() {
        document.querySelectorAll('input[name^="long_questions["]').forEach(inp => {
            const chapterId = inp.getAttribute('data-chapter-id');
            renderLongPlacementsForChapter(chapterId, inp.value);
        });
        recalcStatus();
    }

    numberInputs.forEach(input => {
        input.addEventListener('input', () => {
            ensureChapterChecked(input);
            const chapterId = input.getAttribute('data-chapter-id');
            if (chapterId && input.name.startsWith('long_questions[')) {
                renderLongPlacementsForChapter(chapterId, input.value);
            }
            recalcStatus();
        });
        const chapterId = input.getAttribute('data-chapter-id');
        if (chapterId && input.name.startsWith('long_questions[') && input.value) {
            renderLongPlacementsForChapter(chapterId, input.value);
        }
    });

    [patternYes, patternNo, totalLongsInput].forEach(el => {
        el?.addEventListener('change', rerenderAllPlacements);
        el?.addEventListener('input', rerenderAllPlacements);
    });
    // Toggle total_shorts visibility based on pattern and book
    const totalShortsInput = document.getElementById('total_shorts');
    const totalShortsLabel = document.getElementById('total_shorts_label');
    function updateShortsVisibility() {
        const withPattern = isWithPattern();
        const isScience = ['biology', 'chemistry', 'physics'].includes(bookName);
        
        if (!withPattern) {
            totalShortsLabel.style.display = 'inline-block';
        } else if ((isScience || isComputer) && withPattern && (classId === 9 || classId === 10)) {
            totalShortsLabel.style.display = 'none';
            // Use pattern defaults for shorts
            if (totalShortsInput && patternDefaults.sq > 0) {
                totalShortsInput.value = patternDefaults.sq;
            }
        } else {
            totalShortsLabel.style.display = 'none';
            if (totalShortsInput) totalShortsInput.value = '';
        }
    }
    [patternYes, patternNo].forEach(el => {
        el?.addEventListener('change', function() {
            updateShortsVisibility();
            applyPatternDefaults();
        });
    });
    updateShortsVisibility();
    // Apply defaults on initial load if pattern is selected
    if (patternYes && patternYes.checked) {
        applyPatternDefaults();
    }
    
    // Add event listeners to remove highlighting when selections change
    function addChangeListenersToSelects() {
        document.querySelectorAll('select[name^="long_qnum["], select[name^="long_part["]').forEach(select => {
            select.addEventListener('change', function() {
                // Remove highlighting from this select
                this.style.border = '';
                this.style.backgroundColor = '';
            });
        });
    }
    
    // Initial setup of listeners
    addChangeListenersToSelects();
    
    // Add listeners to new selects when they're created
    const originalRenderLongPlacementsForChapter = renderLongPlacementsForChapter;
    renderLongPlacementsForChapter = function(chapterId, count) {
        originalRenderLongPlacementsForChapter(chapterId, count);
        addChangeListenersToSelects();
    };

    form?.addEventListener('submit', function(e) {
        const withPattern = isWithPattern();

        // Auto-unselect chapters with all zero inputs
        document.querySelectorAll('label > input[type="checkbox"][name="chapters[]"]').forEach(cb => {
            const wrap = cb.closest('label');
            const m = parseInt(wrap.querySelector('input[name^="mcqs["]')?.value) || 0;
            const s = parseInt(wrap.querySelector('input[name^="short_questions["]')?.value) || 0;
            const l = parseInt(wrap.querySelector('input[name^="long_questions["]')?.value) || 0;
            if (m === 0 && s === 0 && l === 0) {
                cb.checked = false;
            }
        });

        const desiredMcqs = parseInt(totalMcqsInput?.value) || 0;
        const desiredLongs = parseInt(totalLongsInput?.value) || 0;
        let sumMcqs = 0, sumLongs = 0, sumShort = 0;
        document.querySelectorAll('input[name^="mcqs["]').forEach(inp => { sumMcqs += (parseInt(inp.value) || 0); });
        document.querySelectorAll('input[name^="long_questions["]').forEach(inp => { sumLongs += (parseInt(inp.value) || 0); });
        document.querySelectorAll('input[name^="short_questions["]').forEach(inp => { sumShort += (parseInt(inp.value) || 0); });

        if (desiredMcqs > 20) {
            e.preventDefault();
            alert('Maximum allowed MCQs is 20.');
            return;
        }
        if (sumMcqs !== desiredMcqs) {
            e.preventDefault();
            alert(`Total MCQs across chapters (${sumMcqs}) must equal the requested MCQs (${desiredMcqs}).`);
            return;
        }

        const targetLongs = withPattern && !isComputer ? desiredLongs * 2 : desiredLongs;
        if (sumLongs !== targetLongs) {
            e.preventDefault();
            let message;
            if (withPattern && !isComputer) {
                message = `Total long questions across chapters (${sumLongs}) must equal 2 × requested long questions (${targetLongs}). Each long prints as parts (a) and (b).`;
            } else if (withPattern && isComputer) {
                message = `Total long questions across chapters (${sumLongs}) must equal the requested long questions (${targetLongs}).`;
            } else {
                message = `Total long questions across chapters (${sumLongs}) must equal the requested long questions (${targetLongs}).`;
            }
            alert(message);
            return;
        }

        const isScience = ['biology', 'chemistry', 'physics'].includes(bookName);
        if ((isScience || isComputer) && withPattern && (classId === 9 || classId === 10)) {
            const targetShort = patternDefaults.sq;
            if (sumShort !== targetShort) {
                e.preventDefault();
                alert('For ' + bookName + ' with pattern (Class ' + classId + '), you must enter exactly ' + targetShort + ' short questions.');
                return;
            }
        }

        // If without pattern, require Total Short Questions input and validate it matches distributed shorts
        if (!withPattern) {
            const desiredShorts = parseInt(totalShortsInput?.value);
            if (totalShortsInput && (isNaN(desiredShorts) || totalShortsInput.value === '')) {
                e.preventDefault();
                alert('Please enter the total number of short questions when not using pattern.');
                return;
            }
            if (!isNaN(desiredShorts) && sumShort !== desiredShorts) {
                e.preventDefault();
                alert(`Total short questions across chapters (${sumShort}) must equal the requested short questions (${desiredShorts}).`);
                return;
            }
        }

        if (withPattern) {
            if (isComputer) {
                // For computer: only check for duplicate question numbers (no parts)
                const questionNumbers = [];
                const duplicateSelects = new Set();
                
                // Clear any previous highlighting
                document.querySelectorAll('select[name^="long_qnum["]').forEach(select => {
                    select.style.border = '';
                    select.style.backgroundColor = '';
                });
                
                // Collect all question numbers
                document.querySelectorAll('[id^="long-placement-"]').forEach(wrap => {
                    wrap.querySelectorAll('.placement-row').forEach(row => {
                        const qSelect = row.querySelector('select[name^="long_qnum["]');
                        const q = qSelect?.value;
                        if (q) {
                            questionNumbers.push({q, qSelect});
                        }
                    });
                });
                
                // Check for duplicate question numbers
                const duplicates = [];
                for (let i = 0; i < questionNumbers.length; i++) {
                    for (let j = i + 1; j < questionNumbers.length; j++) {
                        if (questionNumbers[i].q === questionNumbers[j].q) {
                            duplicates.push(`Q${questionNumbers[i].q}`);
                            duplicateSelects.add(questionNumbers[i].qSelect);
                            duplicateSelects.add(questionNumbers[j].qSelect);
                        }
                    }
                }
                
                // Apply highlighting to duplicates
                duplicateSelects.forEach(select => {
                    select.style.border = '2px solid #ff4d4f';
                    select.style.backgroundColor = '#fff1f0';
                });
                
                if (duplicates.length > 0) {
                    e.preventDefault();
                    alert('Duplicate question numbers detected: ' + [...new Set(duplicates)].join(', ') + '. Each question number must be used only once.');
                    return;
                }
            } else {
                // For other subjects: check for parts (a) and (b)
                const partsByQ = {};
                const allParts = [];
                const allSelects = {qnum: [], part: []};
                
                // Clear any previous highlighting
                document.querySelectorAll('select[name^="long_qnum["], select[name^="long_part["]').forEach(select => {
                    select.style.border = '';
                    select.style.backgroundColor = '';
                });
                
                // Collect all question-part combinations
                document.querySelectorAll('[id^="long-placement-"]').forEach(wrap => {
                    wrap.querySelectorAll('.placement-row').forEach(row => {
                        const qSelect = row.querySelector('select[name^="long_qnum["]');
                        const pSelect = row.querySelector('select[name^="long_part["]');
                        const q = qSelect?.value;
                        const p = pSelect?.value;
                        
                        if (q && p) {
                            // Store each question-part combination for duplicate checking
                            allParts.push({q, p, qSelect, pSelect});
                            
                            // Track parts by question for the missing parts check
                            if (!partsByQ[q]) partsByQ[q] = new Set();
                            partsByQ[q].add(p);
                            
                            // Store selects for highlighting
                            allSelects.qnum.push(qSelect);
                            allSelects.part.push(pSelect);
                        }
                    });
                });
                
                // Check for duplicates
                const duplicates = [];
                const duplicateSelects = new Set();
                
                for (let i = 0; i < allParts.length; i++) {
                    for (let j = i + 1; j < allParts.length; j++) {
                        if (allParts[i].q === allParts[j].q && allParts[i].p === allParts[j].p) {
                            const dupKey = `Q${allParts[i].q}${allParts[i].p}`;
                            duplicates.push(dupKey);
                            
                            // Highlight duplicate selects
                            duplicateSelects.add(allParts[i].qSelect);
                            duplicateSelects.add(allParts[i].pSelect);
                            duplicateSelects.add(allParts[j].qSelect);
                            duplicateSelects.add(allParts[j].pSelect);
                        }
                    }
                }
                
                // Apply highlighting to duplicates
                duplicateSelects.forEach(select => {
                    select.style.border = '2px solid #ff4d4f';
                    select.style.backgroundColor = '#fff1f0';
                });
                
                if (duplicates.length > 0) {
                    e.preventDefault();
                    alert('Duplicate question parts detected: ' + [...new Set(duplicates)].join(', ') + '. Each question part must be used only once.');
                    return;
                }

                const requiredQ = getQCount();
                const missing = [];
                for (let q = 1; q <= requiredQ; q++) {
                    const set = partsByQ[q] || new Set();
                    // Only check for missing parts if this question number is actually used
                    if (set.size > 0) {
                        if (!set.has('a')) missing.push(`Q${q}a`);
                        if (!set.has('b')) missing.push(`Q${q}b`);
                    }
                }
                if (missing.length > 0) {
                    e.preventDefault();
                    alert('Each long question must have both parts a and b. Missing: ' + missing.join(', '));
                }
            }
        }
    });

  // Auto-fill distribution logic (shared handler)
  function autoFillHandler(suppressAlert = false) {
    const withPattern = isWithPattern();
    const chapters = Array.from(document.querySelectorAll('label > input[type="checkbox"][name="chapters[]"]')).map(cb => ({
      checkbox: cb,
      wrap: cb.closest('label'),
      id: cb.value.split('|')[0]
    }));

    // Ensure at least one chapter selected; if none, select all
    const selected = chapters.filter(c => c.checkbox.checked);
    if (selected.length === 0) {
      chapters.forEach(c => c.checkbox.checked = true);
    }

    const activeChapters = chapters.filter(c => c.checkbox.checked);
    if (activeChapters.length === 0) return alert('No chapters available to distribute questions.');

    const totalMcqs = Math.min(20, parseInt(totalMcqsInput.value) || 0);
    const totalLongs = Math.min(5, parseInt(totalLongsInput.value) || 0);
    const totalShorts = parseInt(totalShortsInput?.value) || 0;

    // Distribute integers as evenly as possible, left-to-right
    function distribute(total, n) {
      const base = Math.floor(total / n);
      const rem = total % n;
      return Array.from({length: n}, (_, i) => base + (i < rem ? 1 : 0));
    }

    // MCQs
    const mcqDist = distribute(totalMcqs, activeChapters.length);
    activeChapters.forEach((c, idx) => {
      const inp = c.wrap.querySelector('input[name^="mcqs["]');
      if (inp) { inp.value = mcqDist[idx]; }
    });

    // Longs - note: if withPattern and not computer, totalLongs is number of printed long questions (max 5), but internal sum should be 2x
    // For computer, no parts, so don't multiply by 2
    const longTargetPerDisplay = withPattern && !isComputer ? totalLongs * 2 : totalLongs;
    const longDist = distribute(longTargetPerDisplay, activeChapters.length);
    activeChapters.forEach((c, idx) => {
      const inp = c.wrap.querySelector('input[name^="long_questions["]');
      if (inp) { inp.value = longDist[idx]; renderLongPlacementsForChapter(c.id, inp.value); }
    });

    // Shorts
    if (!withPattern) {
      const shortDist = distribute(totalShorts, activeChapters.length);
      activeChapters.forEach((c, idx) => {
        const inp = c.wrap.querySelector('input[name^="short_questions["]');
        if (inp) inp.value = shortDist[idx];
      });
    } else {
      // withPattern: use pattern defaults for science and computer subjects (class 9 or 10)
      const isScience = ['biology', 'chemistry', 'physics'].includes(bookName);
      if ((isScience || isComputer) && (classId === 9 || classId === 10) && patternDefaults.sq > 0) {
        const shortDist = distribute(patternDefaults.sq, activeChapters.length);
        activeChapters.forEach((c, idx) => {
          const inp = c.wrap.querySelector('input[name^="short_questions["]');
          if (inp) inp.value = shortDist[idx];
        });
      }
    }

    // For withPattern, ensure long part selects exist and assign unique (q,part) pairs across ALL placements
    if (withPattern) {
      // First, re-render placements for all chapters
      activeChapters.forEach((c) => {
        const longInp = c.wrap.querySelector('input[name^="long_questions["]');
        const cnt = parseInt(longInp?.value) || 0;
        renderLongPlacementsForChapter(c.id, cnt);
      });

      // Collect all placement rows globally
      const allPlacementRows = [];
      activeChapters.forEach((c) => {
        const rows = Array.from(document.querySelectorAll(`#long-placement-${c.id} .placement-row`));
        rows.forEach(r => allPlacementRows.push(r));
      });

      if (isComputer) {
        // For computer: only assign question numbers (no parts)
        const requiredQ = getQCount();
        const totalSlots = allPlacementRows.length;
        const pool = [];
        for (let q = 1; q <= requiredQ; q++) {
          pool.push(q);
        }

        // If pool smaller than slots, extend by adding higher q numbers
        let nextQ = requiredQ + 1;
        while (pool.length < totalSlots) {
          pool.push(nextQ);
          nextQ++;
        }

        // Shuffle pool
        for (let i = pool.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [pool[i], pool[j]] = [pool[j], pool[i]];
        }

        // Assign unique question numbers sequentially to placement rows
        for (let i = 0; i < allPlacementRows.length; i++) {
          const row = allPlacementRows[i];
          const qNum = pool[i];
          const qSelect = row.querySelector('select[name^="long_qnum["]');
          if (qSelect) {
            // If qSelect doesn't have the option (e.g., q > getQCount()), add it
            const qVal = String(qNum);
            if (!Array.from(qSelect.options).some(o => o.value === qVal)) {
              const opt = document.createElement('option');
              opt.value = qVal;
              opt.textContent = 'Q' + qVal;
              qSelect.appendChild(opt);
            }
            qSelect.value = qVal;
          }
        }
      } else {
        // For other subjects: assign (q,part) pairs
        const requiredQ = getQCount();
        const totalSlots = allPlacementRows.length; // this should equal totalLongs*2 ideally
        const pool = [];
        for (let q = 1; q <= requiredQ; q++) {
          pool.push({q: q, p: 'a'});
          pool.push({q: q, p: 'b'});
        }

        // If pool smaller than slots (unlikely), extend by adding higher q numbers
        let nextQ = requiredQ + 1;
        while (pool.length < totalSlots) {
          pool.push({q: nextQ, p: 'a'});
          pool.push({q: nextQ, p: 'b'});
          nextQ++;
        }

        // Shuffle pool
        for (let i = pool.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [pool[i], pool[j]] = [pool[j], pool[i]];
        }

        // Assign unique pairs sequentially to placement rows
        for (let i = 0; i < allPlacementRows.length; i++) {
          const row = allPlacementRows[i];
          const pair = pool[i];
          const qSelect = row.querySelector('select[name^="long_qnum["]');
          const pSelect = row.querySelector('select[name^="long_part["]');
          if (qSelect && pSelect) {
            // If qSelect doesn't have the option (e.g., q > getQCount()), add it
            const qVal = String(pair.q);
            if (!Array.from(qSelect.options).some(o => o.value === qVal)) {
              const opt = document.createElement('option');
              opt.value = qVal;
              opt.textContent = 'Q' + qVal;
              qSelect.appendChild(opt);
            }
            qSelect.value = qVal;
            pSelect.value = pair.p;
          }
        }
      }
    }

    recalcStatus();
    if (!suppressAlert) {
      alert('Auto-fill applied. Review counts and then proceed.');
    }
  }

  // Attach handler to all auto-fill buttons
  document.querySelectorAll('.auto-fill-btn').forEach(btn => btn.addEventListener('click', autoFillHandler));

    recalcStatus();
    
    // Auto-trigger auto-fill if pattern defaults are applicable
    const isScience = ['physics', 'chemistry', 'biology'].includes(bookName);
    if ((isScience || isComputer) && (classId === 9 || classId === 10) && patternDefaults.mcqs > 0) {
      // Apply pattern defaults to inputs first
      applyPatternDefaults();
      updateShortsVisibility();
      
      // Wait a bit for DOM to be fully ready, then auto-trigger auto-fill
      setTimeout(function() {
        // Check if there are chapters available
        const chapters = Array.from(document.querySelectorAll('label > input[type="checkbox"][name="chapters[]"]'));
        if (chapters.length > 0) {
          autoFillHandler(true); // Suppress alert for auto-trigger
        }
      }, 100);
    }
});
</script>
</head>
<body>
	<h1>Select Chapters from "<?= htmlspecialchars($book_name) ?> (Class <?= htmlspecialchars($classId) ?>)"</h1>
	
	<div class="container">
		<form action="select_question.php" method="POST">
			<input type="hidden" name="class_id" value="<?= htmlspecialchars($classId) ?>">
			<input type="hidden" name="book_name" value="<?= htmlspecialchars($book_name) ?>">

			<!-- MERGED: Totals + Pattern setup -->


      
			<div class="pattern-toggle">
				<div style="margin-bottom:8px; font-weight:700;">Paper setup</div>
                <div class="pattern-controls">
                    <label>Total MCQs (max 20): <input type="number" id="total_mcqs" name="total_mcqs" min="0" max="20"  required></label>
                    <label>Total Long Questions (max 5): <input type="number" id="total_longs" name="total_longs" min="0" max="5" required></label>
                    <label id="total_shorts_label" style="display:none;">Total Short Questions: <input type="number" id="total_shorts" name="total_shorts" min="0" max="200" ></label>
					<div style="flex-basis:100%; height:0;"></div>
					<label><input type="radio" id="pattern_no" name="pattern_mode" value="without"> Without pattern</label>
					<label><input type="radio" id="pattern_yes" name="pattern_mode" value="with" checked> With pattern (assign Q-number and part for long questions; Computer has no parts)</label>
					<!-- Number of long questions is derived from Total Long Questions above -->
				</div>
				<div style="margin-top:6px; font-size:12px; color:#555;">Note: With pattern - For most subjects, 1 long question will be printed with parts (a) and (b). For Computer, long questions have no parts. Without pattern - 1 long = 1 question.</div>
                <div class="status-bar" id="status_bar">
					<div class="stat" id="stat_mcq"><strong>MCQs:</strong> <span class="val">0</span> / <span class="target">0</span> <span class="rem"></span></div>
					<div class="stat" id="stat_long"><strong>Long:</strong> <span class="val">0</span> / <span class="target">0</span> <span class="rem"></span></div>
					<div class="stat" id="stat_short"><strong>Shorts:</strong> <span class="val">0</span> <span class="hint"></span></div>
				</div>
			</div>

			<!-- How to make paper instructions -->
			<div class="pattern-toggle" style="background:#f2f7ff; border-color:#cfe3ff;">
				<div style="margin-bottom:8px; font-weight:700;">How to make paper</div>
				<div style="font-size:14px; color:#333; text-align:left;">
					<ul style="margin:8px 0 0 18px;">
						<li>Enter <strong>Total MCQs</strong> (maximum 20). Then distribute MCQs across selected chapters so the sum matches.</li>
						<li><strong>With pattern:</strong> For most subjects, each long prints as parts <strong>(a)</strong> and <strong>(b)</strong>. Distribute exactly <strong>2 × Total Longs</strong> across chapters. For <strong>Computer</strong>, long questions have no parts - distribute exactly <strong>Total Longs</strong> across chapters.</li>
						<li><strong>Without pattern:</strong> Each long prints as a single question. Distribute exactly <strong>Total Longs</strong> across chapters.</li>
						<li><strong>With pattern only:</strong> For <strong>Biology, Chemistry, Physics</strong> - enter <strong>exactly 24 short questions</strong> across chapters. For <strong>Computer</strong> - enter <strong>exactly 18 short questions</strong> across chapters.</li>
						<li><strong>Without pattern:</strong> Enter any number of short questions as desired.</li>
					</ul>
				</div>
                <!-- Stylish Animated Button -->
                <div class="btn-wrapper">
                  <button type="button" id="auto_fill_btn" class="btn auto-fill-btn">
    <svg class="btn-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"
      ></path>
    </svg>

    <div " class="txt-wrapper">
      <!-- First text: Auto-select -->
      <div class="txt-1">
        <span class="btn-letter">A</span>
        <span class="btn-letter">u</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">o</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">S</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">l</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">c</span>
        <span class="btn-letter">t</span>
      </div>

      <!-- Second text: Selected -->
      <div class="txt-2">
        <span class="btn-letter">S</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">l</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">c</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">d</span>
      </div>
    </div>
  </button>
</div>

			</div>

			<?php
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$chapter_display = "{$row['chapter_name']}";
					$chapter_value = "{$row['chapter_id']}|{$row['chapter_name']}";
			?>
					<label>
						<input type="checkbox" name="chapters[]" value="<?= htmlspecialchars($chapter_value) ?>">
						<div class="chapter-box">
							<?= htmlspecialchars($chapter_display) ?>
							<div style="margin-top: 10px;">
								<input type="number" name="mcqs[<?= htmlspecialchars($row['chapter_id']) ?>]" placeholder="MCQs" min="0" style="margin-right: 10px; padding: 5px; width: 100px;" data-chapter-id="<?= htmlspecialchars($row['chapter_id']) ?>">
								<input type="number" name="short_questions[<?= htmlspecialchars($row['chapter_id']) ?>]" placeholder="Short Questions" min="0" style="margin-right: 10px; padding: 5px; width: 120px;" data-chapter-id="<?= htmlspecialchars($row['chapter_id']) ?>">
								<input type="number" name="long_questions[<?= htmlspecialchars($row['chapter_id']) ?>]" placeholder="Long Questions" min="0" style="padding: 5px; width: 120px;" data-chapter-id="<?= htmlspecialchars($row['chapter_id']) ?>">
								<div id="long-placement-<?= htmlspecialchars($row['chapter_id']) ?>" style="margin-top:8px;"></div>
							</div>
						</div>
					</label>
			<?php
				}
			} else {
				echo "<h3 style='color:red;'>No chapters found for this book.</h3>";
			}
			?>

			<br>
			<div class="button-container"style=" justify-content: center; display: flex;align-items: center; padding: 10px; border-radius: 5px; color: white; font-weight: bold; margin-bottom: 15px;">
<button style="height: 55px; margin-top:6% ; align-items:center ;justify-content:center " class="go-back-btn" onclick="window.history.back()">⬅ Go Back </button>
  <!-- Stylish Animated Button -->
<div style="margin-top: 8%;" class="btn-wrapper">
  <button type="button" class="btn auto-fill-btn">
    <svg class="btn-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"
      ></path>
    </svg>

    <div class="txt-wrapper">
      <!-- First text: Auto-select -->
      <div class="txt-1">
        <span class="btn-letter">A</span>
        <span class="btn-letter">u</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">o</span>
        <span style="opacity: 0;" class="btn-letter">-</span>
        <span class="btn-letter">S</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">l</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">c</span>
        <span class="btn-letter">t</span>
      </div>

      <!-- Second text: Selected -->
      <div class="txt-2">
        <span class="btn-letter">S</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">l</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">c</span>
        <span class="btn-letter">t</span>
        <span class="btn-letter">e</span>
        <span class="btn-letter">d</span>
      </div>
    </div>
  </button>
</div>
      <!--================== NEXT BUTTON -->
           <button style="margin-top: 6%;" type="submit" class="Btn-Container">
  <span class="text">Next</span>
  <span class="icon-Container">
    <svg
      width="16"
      height="19"
      viewBox="0 0 16 19"
      fill="nones"
      xmlns="http://www.w3.org/2000/svg"
    >
      <circle cx="1.61321" cy="1.61321" r="1.5" fill="black"></circle>
      <circle cx="5.73583" cy="1.61321" r="1.5" fill="black"></circle>
      <circle cx="5.73583" cy="5.5566" r="1.5" fill="black"></circle>
      <circle cx="9.85851" cy="5.5566" r="1.5" fill="black"></circle>
      <circle cx="9.85851" cy="9.5" r="1.5" fill="black"></circle>
      <circle cx="13.9811" cy="9.5" r="1.5" fill="black"></circle>
      <circle cx="5.73583" cy="13.4434" r="1.5" fill="black"></circle>
      <circle cx="9.85851" cy="13.4434" r="1.5" fill="black"></circle>
      <circle cx="1.61321" cy="17.3868" r="1.5" fill="black"></circle>
      <circle cx="5.73583" cy="17.3868" r="1.5" fill="black"></circle>
    </svg>
  </span>
</button>
</div>

		</form>

		
	</div>
  </div>
         
      



    <?php include 'footer.php'; ?>
</body>
</html>
