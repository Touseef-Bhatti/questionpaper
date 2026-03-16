/**
 * Funny Audio Manager
 * Handles caching, specific quiz-level sounds, and playback duration limits.
 */
const FunnyAudioManager = {
    _cache: {},
    _maxDuration: 3000, // 3 seconds limit
    _activeAudios: new Set(),

    /**
     * Get or create cached Audio object
     * @param {string} path 
     * @returns {Audio}
     */
    _getAudio: function(path) {
        if (!this._cache[path]) {
            this._cache[path] = new Audio(path);
            this._cache[path].preload = 'auto';
        }
        return this._cache[path];
    },

    /**
     * Play audio with a specified duration limit
     * @param {string} path 
     */
    play: function(path) {
        try {
            const audio = this._getAudio(path);
            
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
            }).catch(e => console.warn('Audio playback failed:', path, e));
        } catch (err) {
            console.error('FunnyAudioManager Error:', err);
        }
    },

    playStartSound: function() {
        if (!window.funnyModeActive) return;
        this.play('funny_sounds/quiz start.mp3');
    },

    toggleModeSound: function() {
        if (window.funnyModeActive) {
            this.play('funny_sounds/onSwitchMode.mp3');
        }
    },
    
    playResultSound: function(pct) {
        if (!window.funnyModeActive) {
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
            soundFile = 'marks 80.mp3';
        }
        
        if (soundFile) {
            this.play('funny_sounds/' + soundFile);
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
        this.play(`funny_sounds/${folder}/${soundFile}`);
    }
};

// Pre-cache known important sounds
['funny_sounds/quiz start.mp3', 'funny_sounds/onSwitchMode.mp3'].forEach(path => {
    FunnyAudioManager._getAudio(path);
});
