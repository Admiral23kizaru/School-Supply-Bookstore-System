(function() {
    // Inject styles carefully so it doesn't break anything existing
    const style = document.createElement('style');
    style.textContent = `
        body { background: #060608 !important; }
        body > *:not(#ssb-bg):not([class*="swal2"]) { position: relative; z-index: 1; }
        #ssb-bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
        #ssb-bg canvas { position: absolute; inset: 0; width: 100%; height: 100%; }
    `;
    document.head.appendChild(style);

    // Create the wrapper
    const wrapper = document.createElement('div');
    wrapper.id = 'ssb-bg';
    document.body.insertBefore(wrapper, document.body.firstChild);

    // Create 4 canvas layers for maximum performance
    const cvsGlows = document.createElement('canvas');
    const cvsStars = document.createElement('canvas');
    const cvsShelves = document.createElement('canvas');
    const cvsParticles = document.createElement('canvas');

    [cvsGlows, cvsStars, cvsShelves, cvsParticles].forEach(c => wrapper.appendChild(c));

    const ctxGlows = cvsGlows.getContext('2d', { alpha: false }); // false for bottom layer optimizations if browser supports
    const ctxStars = cvsStars.getContext('2d');
    const ctxShelves = cvsShelves.getContext('2d');
    const ctxParticles = cvsParticles.getContext('2d');

    let width = 0;
    let height = 0;
    let time = 0;

    // Data arrays
    const stars = [];
    const glows = [];
    const particles = [];

    // Configuration
    const numStars = 150;
    const numParticles = 40;
    const colorsGlow = [
        [150, 20, 100],  // purple
        [20, 40, 150],   // dark blue
        [120, 10, 20],   // dark red
        [10, 100, 120],  // teal
        [150, 80, 10]    // amber
    ];
    const itemTypes = ['✏️', '✂️', '📏', '📎', '📚', '📓'];
    
    function init() {
        resize();
        window.addEventListener('resize', resize);
        
        // Generate Glows
        for (let i = 0; i < 5; i++) {
            glows.push({
                x: Math.random() * width,
                y: Math.random() * height,
                vx: (Math.random() - 0.5) * 0.5,
                vy: (Math.random() - 0.5) * 0.5,
                r: Math.random() * 200 + 200,
                color: colorsGlow[i % colorsGlow.length]
            });
        }

        // Generate Stars
        for (let i = 0; i < numStars; i++) {
            stars.push({
                x: Math.random() * 100, // percentage
                y: Math.random() * 100, // percentage
                r: Math.random() * 1.5 + 0.5,
                speed: Math.random() * 0.02 + 0.005,
                offset: Math.random() * Math.PI * 2
            });
        }

        // Generate Particles
        for (let i = 0; i < numParticles; i++) {
            particles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                s: Math.random() * 10 + 5, // size
                vy: (Math.random() * -0.5) - 0.1, // Moving up slowly
                rot: Math.random() * Math.PI * 2,
                vrot: (Math.random() - 0.5) * 0.02,
                type: Math.random() > 0.5 ? 'icon' : 'dot',
                icon: itemTypes[Math.floor(Math.random() * itemTypes.length)],
                color: `hsla(${Math.random() * 360}, 50%, 50%, 0.15)`
            });
        }

        requestAnimationFrame(render);
    }

    function resize() {
        width = window.innerWidth;
        height = window.innerHeight;

        [cvsGlows, cvsStars, cvsShelves, cvsParticles].forEach(c => {
            c.width = width;
            c.height = height;
        });

        // Whenever resize happens, redraw the static shelves layer exactly ONCE
        drawShelves();
    }

    function drawShelves() {
        ctxShelves.clearRect(0, 0, width, height);
        const numShelves = Math.ceil(height / 200);
        
        ctxShelves.globalAlpha = 0.4;
        
        for (let i = 0; i < numShelves; i++) {
            const shelfY = i * 200 + 150;
            
            // Draw shelf board
            ctxShelves.fillStyle = '#0a0a0f';
            ctxShelves.fillRect(0, shelfY, width, 15);
            ctxShelves.fillStyle = '#08080a';
            ctxShelves.fillRect(0, shelfY + 15, width, 5);

            // Draw books randomly along shelf
            let currentX = 0;
            while (currentX < width) {
                // Gap
                if (Math.random() < 0.3) {
                    currentX += Math.random() * 100 + 20;
                    continue;
                }
                
                // Book block
                const numBooks = Math.floor(Math.random() * 8) + 3;
                for (let b = 0; b < numBooks; b++) {
                    const bw = Math.random() * 15 + 10;
                    const bh = Math.random() * 60 + 50;
                    
                    // Lean some books
                    ctxShelves.save();
                    ctxShelves.translate(currentX, shelfY);
                    if (b === 0 && Math.random() < 0.5) {
                        ctxShelves.rotate(-0.15); // Lean left
                    } else if (b === numBooks - 1 && Math.random() < 0.5) {
                        ctxShelves.rotate(0.15); // Lean right
                    }

                    // Faint muted book colors
                    const hues = [200, 350, 140, 30]; // navy, maroon, green, brown
                    const hue = hues[Math.floor(Math.random() * hues.length)];
                    ctxShelves.fillStyle = `hsl(${hue}, 40%, 12%)`;
                    
                    ctxShelves.fillRect(0, -bh, bw, bh);
                    // Add a tiny highlight line
                    ctxShelves.fillStyle = `rgba(255,255,255,0.02)`;
                    ctxShelves.fillRect(2, -bh, 2, bh);

                    ctxShelves.restore();
                    
                    currentX += bw + 1;
                    if (currentX > width) break;
                }
            }
        }
        ctxShelves.globalAlpha = 1;
    }

    function render() {
        time++;

        // 1. Render Glows (Bottom layer needs clearing to #060608)
        ctxGlows.fillStyle = '#060608';
        ctxGlows.fillRect(0, 0, width, height);

        for (let g of glows) {
            g.x += g.vx;
            g.y += g.vy;
            if (g.x < -g.r) g.x = width + g.r;
            if (g.x > width + g.r) g.x = -g.r;
            if (g.y < -g.r) g.y = height + g.r;
            if (g.y > height + g.r) g.y = -g.r;

            const grad = ctxGlows.createRadialGradient(g.x, g.y, 0, g.x, g.y, g.r);
            grad.addColorStop(0, `rgba(${g.color[0]}, ${g.color[1]}, ${g.color[2]}, 0.15)`);
            grad.addColorStop(1, `rgba(${g.color[0]}, ${g.color[1]}, ${g.color[2]}, 0)`);
            
            ctxGlows.fillStyle = grad;
            ctxGlows.fillRect(g.x - g.r, g.y - g.r, g.r * 2, g.r * 2);
        }

        // 2. Render Stars
        ctxStars.clearRect(0, 0, width, height);
        ctxStars.fillStyle = '#ffffff';
        for (let s of stars) {
            const actualX = (s.x / 100) * width;
            const actualY = (s.y / 100) * height;
            // Opacity pulses using sine wave
            const alpha = Math.max(0, Math.sin(time * s.speed + s.offset)) * 0.6;
            if (alpha > 0) {
                ctxStars.globalAlpha = alpha;
                ctxStars.beginPath();
                ctxStars.arc(actualX, actualY, s.r, 0, Math.PI * 2);
                ctxStars.fill();
            }
        }
        ctxStars.globalAlpha = 1;

        // 3. Render Particles (Items & Dots drifting up)
        ctxParticles.clearRect(0, 0, width, height);
        for (let p of particles) {
            p.y += p.vy;
            p.x += Math.sin(time * 0.01 + p.rot) * 0.2; // subtle wave
            p.rot += p.vrot;

            if (p.y < -30) {
                p.y = height + 30;
                p.x = Math.random() * width;
            }

            ctxParticles.save();
            ctxParticles.translate(p.x, p.y);
            ctxParticles.rotate(p.rot);
            
            if (p.type === 'dot') {
                ctxParticles.fillStyle = p.color;
                ctxParticles.beginPath();
                ctxParticles.arc(0, 0, p.s, 0, Math.PI*2);
                ctxParticles.fill();
            } else {
                ctxParticles.font = `${p.s + 10}px sans-serif`;
                ctxParticles.globalAlpha = 0.08; // very low opacity
                ctxParticles.textAlign = 'center';
                ctxParticles.textBaseline = 'middle';
                ctxParticles.fillText(p.icon, 0, 0);
            }
            ctxParticles.restore();
        }

        requestAnimationFrame(render);
    }

    // Start everything
    init();
})();
