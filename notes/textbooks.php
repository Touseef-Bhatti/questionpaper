<?php
// Require authentication before accessing this page
// require_once 'auth/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Access free digital textbooks for 9th and 10th class Punjab Board students. Read Physics, Chemistry, Biology, Mathematics, and Computer Science textbooks online. Study materials for exam preparation.">
    <meta name="keywords" content="textbooks, digital textbooks, 9th class books, 10th class books, Punjab Board textbooks, online books, physics textbook, chemistry textbook, biology textbook, free textbooks, study materials">
    <meta name="author" content="Ahmad Learning Hub">
    <meta property="og:title" content="Digital Textbooks - Ahmad Learning Hub">
    <meta property="og:description" content="Access free digital textbooks for Punjab Board 9th and 10th class students. Study online with our comprehensive textbook collection.">
    <meta property="og:type" content="website">
    <title>Digital Textbooks - Free Online Books for 9th & 10th Class | Ahmad Learning Hub</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/notes.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/textbook.css">
</head>
<body>
    <?php include '../header.php'; ?>

    <div class="main-content">
        <div class="textbooks-container">
            <div class="textbooks-header">
                <h1>📖 Digital Textbooks</h1>
                <p>Access your textbooks online and study in a comfortable environment</p>
            </div>
            
            <!-- Books View (Initial View) -->
            <div class="books-view" id="booksView">
                <div class="books-container">
                    <div class="search-container">
                        <span class="search-icon">🔍</span>
                        <input type="text" class="search-bar" id="searchBar" placeholder="Search textbooks by title or subject...">
                    </div>
                    
                    <h2 style="text-align: center; margin: 2rem 0 1rem; color: #333;">Available Books</h2>
                    
                    <div class="books-grid" id="booksGrid">
                        <!-- Books will be populated here -->
                    </div>
                </div>
                
                <!-- SEO Content -->
                <div class="seo-content">
                    <h2>Free Digital Textbooks for 9th and 10th Class Students</h2>
                    <p>
                        Welcome to Ahmad Learning Hub's comprehensive digital textbook library. Access free online textbooks 
                        for all subjects including Physics, Chemistry, Biology, Mathematics, and Computer Science. Our 
                        collection is specifically curated for Punjab Board 9th and 10th class students to support their 
                        exam preparation and learning journey.
                    </p>
                    
                    <h3>Why Choose Our Digital Textbooks?</h3>
                    <ul>
                        <li><strong>Free Access:</strong> All textbooks are available completely free of charge</li>
                        <li><strong>Online Reading:</strong> Read your textbooks anywhere, anytime without downloading</li>
                        <li><strong>Study Tools:</strong> Built-in zoom, dark mode, timer, and bookmarking features</li>
                        <li><strong>Punjab Board Aligned:</strong> All books follow the official Punjab Board curriculum</li>
                        <li><strong>Mobile Friendly:</strong> Access textbooks on any device - phone, tablet, or computer</li>
                        <li><strong>No Registration Required:</strong> Start reading immediately without sign-up</li>
                    </ul>
                    
                    <h3>Available Subjects</h3>
                    <p>
                        Our digital textbook collection includes comprehensive study materials for:
                    </p>
                    <ul>
                        <li><strong>Physics:</strong> Complete physics textbooks with diagrams and solved examples</li>
                        <li><strong>Chemistry:</strong> Detailed chemistry books covering all chapters and topics</li>
                        <li><strong>Biology:</strong> Biology textbooks with illustrations and important concepts</li>
                        <li><strong>Mathematics:</strong> Math textbooks with step-by-step problem solutions</li>
                        <li><strong>Computer Science:</strong> Computer science books with programming concepts</li>
                    </ul>
                    
                    <h3>How to Use Our Digital Textbooks</h3>
                    <p>
                        Simply browse through our available books, click on any textbook to start reading. Use our 
                        advanced study tools including zoom controls, dark mode for comfortable night reading, study 
                        timer to track your learning time, and bookmark feature to save your progress. All features 
                        are designed to enhance your study experience and help you prepare effectively for your exams.
                    </p>
                    
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1.5rem; margin-top: 2rem; border-radius: 8px;">
                        <h3 style="color: #856404; margin-top: 0;">📚 Copyright & Attribution</h3>
                        <p style="color: #856404; margin-bottom: 1rem;">
                            <strong>Important Notice:</strong> All textbooks displayed on this platform are official publications 
                            of the <strong>Punjab Curriculum and Textbook Board (PCTB)</strong>, Government of Punjab, Pakistan. 
                            These digital textbooks are provided for educational purposes only.
                        </p>
                        <p style="color: #856404; margin-bottom: 1rem;">
                            <strong>Copyright Ownership:</strong> All content, including text, images, diagrams, and educational 
                            materials within these textbooks, are the exclusive property of the Punjab Curriculum and Textbook 
                            Board (PCTB). Ahmad Learning Hub does not claim any copyright ownership over these materials.
                        </p>
                        <p style="color: #856404; margin-bottom: 1rem;">
                            <strong>Educational Use:</strong> These textbooks are made available for students and educators 
                            for non-commercial, educational purposes. The content is used in accordance with fair use principles 
                            for educational access and learning support.
                        </p>
                        <p style="color: #856404; margin-bottom: 0;">
                            <strong>Official Source:</strong> For official downloads and the latest editions, please visit the 
                            official Punjab Curriculum and Textbook Board website at 
                            <a href="https://pctb.punjab.gov.pk" target="_blank" style="color: #856404; text-decoration: underline;">pctb.punjab.gov.pk</a>. 
                            All rights reserved by Punjab Curriculum and Textbook Board (PCTB).
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Book Viewer View (After Selection) -->
            <div class="book-viewer-view" id="bookViewerView">
                <button class="back-to-books" onclick="showBooksView()">← Back to Books</button>
                
                <div class="textbooks-layout" id="textbooksLayout">
                    <!-- Book Viewer -->
                    <div class="book-viewer-container">
                        <div class="book-viewer-header">
                            <div class="book-viewer-title" id="viewerTitle">Select a book to start reading</div>
                            <div class="book-controls" id="bookControls">
                                <div class="control-btn-group">
                                    <div class="zoom-controls">
                                        <button class="zoom-btn" onclick="zoomOut()" title="Zoom Out">−</button>
                                        <span class="zoom-level" id="zoomLevel">100%</span>
                                        <button class="zoom-btn" onclick="zoomIn()" title="Zoom In">+</button>
                                        <button class="zoom-btn" onclick="resetZoom()" title="Reset Zoom">⌂</button>
                                    </div>
                                </div>
                                <button class="control-btn" onclick="toggleDarkMode()" id="darkModeBtn" title="Toggle Dark Mode">🌙</button>
                                <button class="control-btn" onclick="openInNewTab()" title="Open in New Tab">🔗</button>
                                <button class="control-btn" onclick="fullscreenMode()" title="Fullscreen">⛶</button>
                                <button class="control-btn" onclick="printBook()" title="Print">🖨️</button>
                            </div>
                        </div>
                        <div id="bookViewer" >
                            <div class="no-book-selected">
                                Loading book...
                            </div>
                        </div>
                        <div class="study-tools" id="studyTools">
                            <div class="study-timer">
                                <span>⏱️ Study Time:</span>
                                <span class="timer-display" id="timerDisplay">00:00:00</span>
                                <div class="timer-controls">
                                    <button class="timer-btn" onclick="startTimer()" id="startTimerBtn">Start</button>
                                    <button class="timer-btn" onclick="pauseTimer()" id="pauseTimerBtn" style="display: none;">Pause</button>
                                    <button class="timer-btn" onclick="resetTimer()">Reset</button>
                                </div>
                            </div>
                            <div class="bookmark-section">
                                <button class="bookmark-btn" onclick="toggleBookmark()" id="bookmarkBtn" title="Bookmark this page">🔖 Bookmark</button>
                            </div>
                        </div>
                        <div class="reading-progress" id="readingProgress">
                            <div class="reading-progress-bar" id="progressBar"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="go-back-section">
                <button class="go-back-btn" onclick="window.history.back()">⬅ Go Back</button>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
    
    <script>
        // Books data - you can expand this array with more books
        const books = [
            {
                id: 1,
                title: "Physics ",
                class:10,
                subject: "Physics",
                driveId: "1ZUhUAkGyAxbVAWIWNF6G3m1q4HTWk604"
            },
            // Add more books here as needed
            {
                id: 2,
                title: "Chemistry ",
                class:10,
                subject: "Chemistry",
                driveId: "1Dc3uLt6sUFRPqOeIiHpGKkubOMPOxsz5"
            },
            {
                id: 3,
                title: "Biology ",
                class:10,
                subject: "Biology",
                driveId: "1_ZseEY6DSDJZ70bY-xwzNcvDt27jJ3fY",
            },
            {
                id: 4,
                title: "Computer Science",
                class:10,
                subject: "Computer Science",
                driveId: "1yKlB9hKd9tkz00xkmnNhzPD75mTYDzuF",
            }
            
            ];
        
        let currentBook = null;
        let zoomLevel = 100;
        let isDarkMode = false;
        let isSidebarHidden = false;
        let timerInterval = null;
        let timerSeconds = 0;
        let isTimerRunning = false;
        let bookmarks = JSON.parse(localStorage.getItem('bookBookmarks') || '{}');
        
        // Initialize books list
        function initializeBooks() {
            const booksGrid = document.getElementById('booksGrid');
            booksGrid.innerHTML = '';
            
            displayBooks(books);
            
            // Setup search functionality
            const searchBar = document.getElementById('searchBar');
            searchBar.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                const filteredBooks = books.filter(book => 
                    book.title.toLowerCase().includes(searchTerm) || 
                    book.subject.toLowerCase().includes(searchTerm) ||
                    book.class.toString().includes(searchTerm)
                );
                displayBooks(filteredBooks);
            });
            
            // Load dark mode preference
            if (localStorage.getItem('darkMode') === 'true') {
                toggleDarkMode();
            }
        }
        
        // Display books in grid
        function displayBooks(booksToShow) {
            const booksGrid = document.getElementById('booksGrid');
            booksGrid.innerHTML = '';
            
            if (booksToShow.length === 0) {
                booksGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #999; padding: 2rem;">No books found matching your search.</p>';
                return;
            }
            
            booksToShow.forEach(book => {
                const bookCard = document.createElement('div');
                bookCard.className = 'book-card';
                bookCard.onclick = () => loadBook(book);
                
                const icon = getBookIcon(book.subject);
                const bookmarkIcon = bookmarks[book.id] ? '🔖' : '';
                
                bookCard.innerHTML = `
                    <div class="book-card-icon">${icon}</div>
                    <div class="book-card-title">${book.title} ${book.class} ${bookmarkIcon}</div>
                    <div class="book-card-subject">${book.subject}</div>
                `;
                booksGrid.appendChild(bookCard);
            });
        }
        
        // Get icon based on subject
        function getBookIcon(subject) {
            const icons = {
                'Physics': '⚛️',
                'Chemistry': '🧪',
                'Biology': '🧬',
                'Mathematics': '📐',
                'Math': '📐',
                'Computer': '💻',
                'Computer Science': '💻',
                'English': '📚',
                'Urdu': '📖',
                'Islamiat': '🕌',
                'Pak Studies': '🇵🇰'
            };
            return icons[subject] || '📖';
        }
        
        // Show books view
        function showBooksView() {
            document.getElementById('booksView').classList.remove('hidden');
            document.getElementById('bookViewerView').classList.remove('active');
            document.body.classList.remove('book-viewer-active');
        }
        
        // Load book in viewer
        function loadBook(book) {
            currentBook = book;
            
            // Switch to book viewer view
            document.getElementById('booksView').classList.add('hidden');
            document.getElementById('bookViewerView').classList.add('active');
            document.body.classList.add('book-viewer-active');
            
            // Update viewer title
            document.getElementById('viewerTitle').textContent = book.title;
            
            // Show controls and tools
            document.getElementById('bookControls').style.display = 'flex';
            document.getElementById('studyTools').style.display = 'flex';
            document.getElementById('readingProgress').style.display = 'block';
            
            // Update bookmark button
            updateBookmarkButton();
            
            // Create iframe
            const viewer = document.getElementById('bookViewer');
            const embedUrl = `https://drive.google.com/file/d/${book.driveId}/preview`;
            
            viewer.innerHTML = `
                <iframe 
                    class="book-viewer-iframe" 
                    id="bookIframe"
                    src="${embedUrl}"
                    allow="autoplay"
                    allowfullscreen
                ></iframe>
            `;
            
            // Reset zoom
            resetZoom();
            
            // Track reading progress
            trackReadingProgress();
            
            // Scroll to top
            window.scrollTo(0, 0);
        }
        
        // Zoom functions
        function zoomIn() {
            if (zoomLevel < 200) {
                zoomLevel += 10;
                applyZoom();
            }
        }
        
        function zoomOut() {
            if (zoomLevel > 50) {
                zoomLevel -= 10;
                applyZoom();
            }
        }
        
        function resetZoom() {
            zoomLevel = 100;
            applyZoom();
        }
        
        function applyZoom() {
            const iframe = document.getElementById('bookIframe');
            if (iframe) {
                iframe.style.transform = `scale(${zoomLevel / 100})`;
                iframe.style.width = `${100 / (zoomLevel / 100)}%`;
                iframe.style.height = `${600 / (zoomLevel / 100)}px`;
                document.getElementById('zoomLevel').textContent = zoomLevel + '%';
            }
        }
        
        // Dark mode toggle
        function toggleDarkMode() {
            isDarkMode = !isDarkMode;
            document.body.classList.toggle('dark-mode', isDarkMode);
            document.getElementById('darkModeBtn').textContent = isDarkMode ? '☀️' : '🌙';
            localStorage.setItem('darkMode', isDarkMode);
        }
        
        
        // Timer functions
        function startTimer() {
            if (!isTimerRunning) {
                isTimerRunning = true;
                timerInterval = setInterval(() => {
                    timerSeconds++;
                    updateTimerDisplay();
                    
                    // Break reminder every 25 minutes (Pomodoro technique)
                    if (timerSeconds % 1500 === 0 && timerSeconds > 0) {
                        alert('⏰ Take a 5-minute break! You\'ve been studying for 25 minutes.');
                    }
                }, 1000);
                document.getElementById('startTimerBtn').style.display = 'none';
                document.getElementById('pauseTimerBtn').style.display = 'inline-block';
            }
        }
        
        function pauseTimer() {
            if (isTimerRunning) {
                isTimerRunning = false;
                clearInterval(timerInterval);
                document.getElementById('startTimerBtn').style.display = 'inline-block';
                document.getElementById('pauseTimerBtn').style.display = 'none';
            }
        }
        
        function resetTimer() {
            pauseTimer();
            timerSeconds = 0;
            updateTimerDisplay();
        }
        
        function updateTimerDisplay() {
            const hours = Math.floor(timerSeconds / 3600);
            const minutes = Math.floor((timerSeconds % 3600) / 60);
            const seconds = timerSeconds % 60;
            document.getElementById('timerDisplay').textContent = 
                `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
        
        // Bookmark functions
        function toggleBookmark() {
            if (!currentBook) return;
            
            if (bookmarks[currentBook.id]) {
                delete bookmarks[currentBook.id];
                document.getElementById('bookmarkBtn').classList.remove('active');
            } else {
                bookmarks[currentBook.id] = {
                    title: currentBook.title,
                    timestamp: new Date().toISOString()
                };
                document.getElementById('bookmarkBtn').classList.add('active');
            }
            
            localStorage.setItem('bookBookmarks', JSON.stringify(bookmarks));
            initializeBooks(); // Refresh book list to show bookmark icons
        }
        
        function updateBookmarkButton() {
            const bookmarkBtn = document.getElementById('bookmarkBtn');
            if (bookmarks[currentBook.id]) {
                bookmarkBtn.classList.add('active');
            } else {
                bookmarkBtn.classList.remove('active');
            }
        }
        
        // Reading progress tracking
        function trackReadingProgress() {
            const iframe = document.getElementById('bookIframe');
            if (iframe) {
                // Simulate progress (you can enhance this with actual scroll tracking)
                let progress = 0;
                const progressInterval = setInterval(() => {
                    if (progress < 100) {
                        progress += 0.1;
                        document.getElementById('progressBar').style.width = progress + '%';
                    } else {
                        clearInterval(progressInterval);
                    }
                }, 1000);
            }
        }
        
        // Open book in new tab
        function openInNewTab() {
            if (currentBook) {
                const url = `https://drive.google.com/file/d/${currentBook.driveId}/view`;
                window.open(url, '_blank');
            }
        }
        
        // Print function
        function printBook() {
            if (currentBook) {
                const url = `https://drive.google.com/file/d/${currentBook.driveId}/view`;
                const printWindow = window.open(url, '_blank');
                printWindow.onload = () => {
                    setTimeout(() => {
                        printWindow.print();
                    }, 1000);
                };
            }
        }
        
        // Fullscreen mode
        function fullscreenMode() {
            const viewer = document.getElementById('bookViewer');
            const iframe = viewer.querySelector('iframe');
            
            if (!iframe) return;
            
            if (!document.fullscreenElement) {
                if (viewer.requestFullscreen) {
                    viewer.requestFullscreen();
                } else if (viewer.webkitRequestFullscreen) {
                    viewer.webkitRequestFullscreen();
                } else if (viewer.mozRequestFullScreen) {
                    viewer.mozRequestFullScreen();
                } else if (viewer.msRequestFullscreen) {
                    viewer.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Only apply shortcuts when in book viewer view
            if (!document.getElementById('bookViewerView').classList.contains('active')) {
                return;
            }
            
            // Zoom with Ctrl/Cmd + Plus/Minus
            if ((e.ctrlKey || e.metaKey) && e.key === '=') {
                e.preventDefault();
                zoomIn();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === '-') {
                e.preventDefault();
                zoomOut();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === '0') {
                e.preventDefault();
                resetZoom();
            }
            // Toggle dark mode with 'D'
            if (e.key === 'd' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                toggleDarkMode();
            }
            // Go back to books with 'B'
            if (e.key === 'b' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                showBooksView();
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initializeBooks);
    </script>
</body>
</html>

