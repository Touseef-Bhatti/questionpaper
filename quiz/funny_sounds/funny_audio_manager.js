/**
 * Funny Audio Manager
 * Handles specific quiz-level funny sounds (start, end-of-quiz results)
 */
const FunnyAudioManager = {
    playStartSound: function() {
        if (!window.funnyModeActive) return;
        const audio = new Audio('funny_sounds/quiz start.mp3');
        audio.play().catch(e => console.warn('Quiz start sound failed:', e));
    },
    
    playResultSound: function(pct) {
        if (!window.funnyModeActive) {
            // If funny mode is OFF, play standard sounds
            this.playStandardResultSound(pct);
            return;
        }
        
        let soundFile = '';
        
        if (pct === 0) {
            soundFile = 'zero marks.mp3';
        } else if (pct <= 30) {
            soundFile = 'marks less 30.mp3';
        } else if (pct <= 60) {
            soundFile = 'marks 60.mp3';
        } else if (pct >= 80) {
            // User has both 'marks 80.mp3' and '80 marks.mp3', we'll use 'marks 80.mp3'
            soundFile = 'marks 80.mp3';
        }
        
        if (soundFile) {
            const audio = new Audio('funny_sounds/' + soundFile);
            audio.play().catch(e => {
                console.warn('Result funny sound failed:', e);
                this.playStandardResultSound(pct);
            });
        } else {
            // No specific funny sound for this range (e.g. 61-79), use default logic
            this.playStandardResultSound(pct);
        }
    },
    
    playStandardResultSound: function(pct) {
        // Uses the existing playSound function in quiz.php which handles 
        // both default oscillator sounds and funny answer sounds
        if (typeof playSound === 'function') {
            playSound(pct >= 60 ? 'correct' : 'wrong');
        }
    }
};
