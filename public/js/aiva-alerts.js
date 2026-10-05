/**
 * AIVA Attention Alert System (Web Audio API Synthesizer + SLA Telemetry)
 */
class AivaAlertSystem {
    constructor() {
        this.audioCtx = null;
        this.isPlaying = false;
        this.intervalId = null;
    }

    initAudioContext() {
        if (!this.audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.audioCtx = new AudioContext();
            }
        }
        if (this.audioCtx && this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }
    }

    /**
     * Sintetizar el sonido "AIVA Attention" (Ding -> Ding -> DING)
     */
    playChime() {
        try {
            this.initAudioContext();
            if (!this.audioCtx) return;

            const now = this.audioCtx.currentTime;
            
            // Tono 1: 880 Hz (La5)
            this.playTone(880, now, 0.15);
            // Tono 2: 1174.66 Hz (Re6)
            this.playTone(1174.66, now + 0.2, 0.15);
            // Tono 3: 1760 Hz (La6 - DING destello)
            this.playTone(1760, now + 0.4, 0.35);
        } catch (e) {
            console.warn('Audio alert warning:', e);
        }
    }

    playTone(freq, startTime, duration) {
        const osc = this.audioCtx.createOscillator();
        const gain = this.audioCtx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, startTime);

        gain.gain.setValueAtTime(0.001, startTime);
        gain.gain.exponentialRampToValueAtTime(0.25, startTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

        osc.connect(gain);
        gain.connect(this.audioCtx.destination);

        osc.start(startTime);
        osc.stop(startTime + duration);
    }

    startAlertLoop() {
        if (this.isPlaying) return;
        this.isPlaying = true;
        this.playChime();
        this.intervalId = setInterval(() => {
            this.playChime();
        }, 4000);
    }

    stopAlertLoop() {
        this.isPlaying = false;
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
}

window.aivaAlerts = new AivaAlertSystem();
