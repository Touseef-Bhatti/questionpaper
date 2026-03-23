/**
 * Funny Audio Manager
 * Handles caching, specific quiz-level sounds, and playback duration limits.
 */
const FunnyAudioManager = {
    _cache: {},
    _maxDuration: 3000, // 3 seconds limit
    _activeAudios: new Set(),
    basePath: '', // Base path to be set from the calling script

    /**
     * Set the base path for sounds
     * @param {string} path 
     */
    setBasePath: function(path) {
        // Ensure path ends with a slash if not empty
        if (path && !path.endsWith('/')) {
            path += '/';
        }
        this.basePath = path;
    },

    /**
     * Get or create cached Audio object
     * @param {string} path 
     * @returns {Audio}
     */
    _getAudio: function(path) {
        // Combine with base path
        const fullPath = this.basePath + path;
        
        if (!this._cache[fullPath]) {
            // Use encodeURI to handle spaces and special characters in filenames safely
            this._cache[fullPath] = new Audio(encodeURI(fullPath));
            this._cache[fullPath].preload = 'auto';
        }
        return this._cache[fullPath];
    },

    /**
     * Play audio with a specified duration limit
     * @param {string} path 
     */
    play: function(path) {
        try {
            const audio = this._getAudio(path);
            const fullPath = this.basePath + path;
            
            // If already playing, reset it
            audio.pause();
            audio.currentTime = 0;
            
            audio.play().then(() => {
                this._activeAudios.add(audio);
                
                // Stop after max duration
                setTimeout(() => {
                    if (this._activeAudios.has(audio)) {
                        audio.pause();
                        audio.currentTime = 0;
                        this._activeAudios.delete(audio);
                    }
                }, this._maxDuration);
                
                // Also cleanup when it ends naturally
                audio.onended = () => {
                    this._activeAudios.delete(audio);
                };
            }).catch(e => console.warn('Audio playback failed:', fullPath, e));
        } catch (err) {
            console.error('FunnyAudioManager Error:', err);
        }
    },

    playStartSound: function() {
        if (!window.funnyModeActive) return;
        this.play('quiz/funny_sounds/quiz start.mp3');
    },

    toggleModeSound: function() {
        if (window.funnyModeActive) {
            this.play('quiz/funny_sounds/onSwitchMode.mp3');
        }
    },
    
    playResultSound: function(pct) {
        if (!window.funnyModeActive) {
            this.playStandardResultSound(pct);
            return;
        }
        
        let soundFile = '';
        if (pct === 0) {
            soundFile = 'zeroMarks.mp3';
        } else if (pct <= 30) {
            soundFile = 'marks less 30.mp3';
        } else if (pct <= 60) {
            soundFile = 'marks 60.mp3';
        } else if (pct >= 80) {
            soundFile = 'marks 80.mp3';
        }
        
        if (soundFile) {
            this.play('quiz/funny_sounds/' + soundFile);
        } else {
            this.playStandardResultSound(pct);
        }
    },
    
    playStandardResultSound: function(pct) {
        if (typeof playSound === 'function') {
            playSound(pct >= 60 ? 'correct' : 'wrong');
        }
    },

    /**
     * Interface for answer sounds (used by quiz.php)
     */
    playAnswerSound: function(type, soundFile) {
        const folder = type === 'correct' ? 'correct' : 'incorrect';
        this.play(`quiz/funny_sounds/${folder}/${soundFile}`);
    }
};
