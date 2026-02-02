/*
 * FML Player Visualizer
 * Canvas 2D audio-reactive particle visualization for the music player
 * No external dependencies (no Three.js required)
 */

(function() {
    'use strict';

    function PlayerVisualizer() {
        this.container = document.getElementById('player-visualizer-bg');
        if (!this.container) {
            console.warn('Visualizer container not found');
            return;
        }

        this.active = false;
        this.particleCount = 120;
        this.particles = [];
        this.canvas = null;
        this.ctx = null;
        this.animFrameId = null;

        this._init();
    }

    PlayerVisualizer.prototype._init = function() {
        // Create canvas
        this.canvas = document.createElement('canvas');
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.display = 'block';
        this.container.appendChild(this.canvas);

        this.ctx = this.canvas.getContext('2d');
        this._resize();

        // Create particles
        for (var i = 0; i < this.particleCount; i++) {
            this.particles.push(this._createParticle(i));
        }

        window.addEventListener('resize', this._resize.bind(this));
    };

    PlayerVisualizer.prototype._resize = function() {
        if (!this.canvas || !this.container) return;
        this.canvas.width = this.container.offsetWidth || 400;
        this.canvas.height = this.container.offsetHeight || 100;
    };

    PlayerVisualizer.prototype._createParticle = function(index) {
        var w = this.canvas.width || 400;
        var h = this.canvas.height || 100;
        var t = index / this.particleCount;

        // Gradient from coral (#e94560) through orange (#ff6b35) to deep blue (#0f3460)
        var r, g, b;
        if (t < 0.5) {
            var s = t * 2;
            r = Math.floor(233 + (255 - 233) * s);
            g = Math.floor(69 + (107 - 69) * s);
            b = Math.floor(96 + (53 - 96) * s);
        } else {
            var s = (t - 0.5) * 2;
            r = Math.floor(255 + (15 - 255) * s);
            g = Math.floor(107 + (52 - 107) * s);
            b = Math.floor(53 + (96 - 53) * s);
        }

        return {
            x: Math.random() * w,
            y: Math.random() * h,
            baseX: Math.random() * w,
            baseY: Math.random() * h,
            baseSize: 1 + Math.random() * 3,
            size: 1 + Math.random() * 3,
            speedX: (Math.random() - 0.5) * 0.5,
            speedY: (Math.random() - 0.5) * 0.3,
            phase: Math.random() * Math.PI * 2,
            freqBand: index % 3, // 0=bass, 1=mid, 2=treble
            r: r,
            g: g,
            b: b,
            alpha: 0.3 + Math.random() * 0.5
        };
    };

    PlayerVisualizer.prototype.start = function() {
        this.active = true;
        this._resize();
        this._animate();
    };

    PlayerVisualizer.prototype.stop = function() {
        this.active = false;
        if (this.animFrameId) {
            cancelAnimationFrame(this.animFrameId);
            this.animFrameId = null;
        }
    };

    PlayerVisualizer.prototype._animate = function() {
        if (!this.active) return;

        var self = this;
        this.animFrameId = requestAnimationFrame(function() { self._animate(); });

        var ctx = this.ctx;
        var w = this.canvas.width;
        var h = this.canvas.height;

        // Clear
        ctx.clearRect(0, 0, w, h);

        // Get audio data
        var audio = window.FMLAudioData || { intensity: 0, bass: 0, mid: 0, treble: 0 };
        var now = Date.now() * 0.001;

        // Update and draw particles
        for (var i = 0; i < this.particles.length; i++) {
            var p = this.particles[i];

            // Audio reactivity per frequency band
            var audioMult = 1;
            if (p.freqBand === 0) {
                audioMult = 1 + audio.bass * 3;
            } else if (p.freqBand === 1) {
                audioMult = 1 + audio.mid * 2;
            } else {
                audioMult = 1 + audio.treble * 2;
            }

            // Wave motion
            var waveX = Math.sin(now * 0.8 + p.phase) * 15 * (1 + audio.bass);
            var waveY = Math.cos(now * 0.6 + p.phase * 1.3) * 8 * (1 + audio.intensity);

            // Update position with drift + wave
            p.x = p.baseX + waveX + p.speedX * now * 10;
            p.y = p.baseY + waveY + p.speedY * now * 10;

            // Wrap around
            if (p.x > w + 10) p.baseX -= w + 20;
            if (p.x < -10) p.baseX += w + 20;
            if (p.y > h + 10) p.baseY -= h + 20;
            if (p.y < -10) p.baseY += h + 20;

            // Size reacts to audio
            p.size = p.baseSize * audioMult;

            // Draw particle with glow
            var alpha = p.alpha * (0.5 + audio.intensity * 0.5);
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(' + p.r + ',' + p.g + ',' + p.b + ',' + alpha + ')';
            ctx.fill();

            // Glow effect when audio is playing
            if (audio.intensity > 0.05) {
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size * 2.5, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(' + p.r + ',' + p.g + ',' + p.b + ',' + (alpha * 0.15) + ')';
                ctx.fill();
            }
        }

        // Draw connecting lines between nearby particles (subtle)
        if (audio.intensity > 0.1) {
            ctx.strokeStyle = 'rgba(233, 69, 96, ' + (audio.intensity * 0.08) + ')';
            ctx.lineWidth = 0.5;
            for (var i = 0; i < this.particles.length; i++) {
                for (var j = i + 1; j < this.particles.length; j++) {
                    var dx = this.particles[i].x - this.particles[j].x;
                    var dy = this.particles[i].y - this.particles[j].y;
                    var dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 60) {
                        ctx.beginPath();
                        ctx.moveTo(this.particles[i].x, this.particles[i].y);
                        ctx.lineTo(this.particles[j].x, this.particles[j].y);
                        ctx.stroke();
                    }
                }
            }
        }
    };

    PlayerVisualizer.prototype.destroy = function() {
        this.stop();
        if (this.canvas && this.container) {
            this.container.removeChild(this.canvas);
        }
        this.particles = [];
    };

    // Expose globally
    window.PlayerVisualizer = PlayerVisualizer;

})();
