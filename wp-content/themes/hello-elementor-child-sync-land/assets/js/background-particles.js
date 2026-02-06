let renderer, scene, camera;
let outerParticles;
const PARTICLE_SIZE = 20;
let raycaster, intersects;
let pointer = new THREE.Vector2(), INTERSECTED;

// Audio-reactive state
let audioVisualizerEnabled = false;
let baseSizes = []; // store original sizes for audio reactivity
let basePositions = []; // store original positions

function createOuterParticles() {
    // Outer particle sphere
    let outerGeometry = new THREE.SphereGeometry(150, 32, 32); // Wider radius (150) and high segments (32)
    outerGeometry.deleteAttribute('normal'); // Remove normals to simplify
    outerGeometry.deleteAttribute('uv');     // Remove UVs to simplify

    const outerPositionAttribute = outerGeometry.getAttribute('position');
    const outerColors = [];
    const outerSizes = [];
    const outerColor = new THREE.Color();

    for (let i = 0, l = outerPositionAttribute.count; i < l; i++) {
        outerColor.setHSL(0.01 + 0.1 * (i / l), 1.0, 0.5);
        outerColor.toArray(outerColors, i * 3);
        outerSizes[i] = PARTICLE_SIZE * 0.5;
    }

    // Store base sizes and positions for audio reactivity
    baseSizes = outerSizes.slice();
    for (let i = 0; i < outerPositionAttribute.count * 3; i++) {
        basePositions.push(outerPositionAttribute.array[i]);
    }

    const outerParticleGeometry = new THREE.BufferGeometry();
    outerParticleGeometry.setAttribute('position', outerPositionAttribute.clone()); // Cloned to ensure data integrity
    outerParticleGeometry.setAttribute('customColor', new THREE.Float32BufferAttribute(outerColors, 3));
    outerParticleGeometry.setAttribute('size', new THREE.Float32BufferAttribute(outerSizes, 1));

    const outerMaterial = new THREE.ShaderMaterial({
        uniforms: {
            color: { value: new THREE.Color(0xffffff) },
            pointTexture: { value: new THREE.TextureLoader().load('https://threejs.org/examples/textures/sprites/disc.png') },
            alphaTest: { value: 0.9 }
        },
        vertexShader: `
            attribute float size;
            attribute vec3 customColor;
            varying vec3 vColor;
            void main() {
                vColor = customColor;
                vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);
                gl_PointSize = size * (300.0 / -mvPosition.z); // Uniform scaling
                gl_Position = projectionMatrix * mvPosition;
            }
        `,
        fragmentShader: `
            uniform vec3 color;
            uniform sampler2D pointTexture;
            uniform float alphaTest;
            varying vec3 vColor;
            void main() {
                gl_FragColor = vec4(color * vColor, 1.0);
                gl_FragColor = gl_FragColor * texture2D(pointTexture, gl_PointCoord);
                if (gl_FragColor.a < alphaTest) discard;
            }
        `,
        blending: THREE.AdditiveBlending,
        depthTest: false,
        transparent: true,
        depthWrite: false // Ensure particles are drawn on top of other objects
    });

    outerParticles = new THREE.Points(outerParticleGeometry, outerMaterial);
    scene.add(outerParticles);
}

function init() {
    const container = document.createElement('div');
    container.id = 'threejs-background';
    document.body.appendChild(container);

    container.style.position = 'fixed';
    container.style.top = '0';
    container.style.left = '0';
    container.style.width = '100%';
    container.style.height = '100%';
    container.style.zIndex = '-1';

    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 1, 10000);
    camera.position.z = 250;

    // Create outer particles
    createOuterParticles();

    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(window.innerWidth, window.innerHeight);
    container.appendChild(renderer.domElement);

    raycaster = new THREE.Raycaster();
    raycaster.params.Points.threshold = 6;

    document.addEventListener('pointermove', onPointerMove);
    window.addEventListener('resize', onWindowResize);

    animate();
}

function onPointerMove(event) {
    pointer.x = (event.clientX / window.innerWidth) * 2 - 1;
    pointer.y = -(event.clientY / window.innerHeight) * 2 + 1;
}

function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
}

function animate() {
    requestAnimationFrame(animate);

    // Base rotation
    var baseRotX = 0.0005;
    var baseRotY = 0.001;

    // Audio-reactive enhancements when visualizer is enabled
    if (audioVisualizerEnabled && window.FMLAudioData) {
        var audio = window.FMLAudioData;
        var bass = audio.bass || 0;
        var mid = audio.mid || 0;
        var treble = audio.treble || 0;
        var intensity = audio.intensity || 0;

        // Dramatically speed up rotation with audio
        outerParticles.rotation.x += baseRotX + bass * 0.03;
        outerParticles.rotation.y += baseRotY + mid * 0.02;
        // Add z-axis wobble on treble hits
        outerParticles.rotation.z += Math.sin(Date.now() * 0.003) * treble * 0.01;

        // Pulse particle sizes with audio
        var sizes = outerParticles.geometry.attributes.size.array;
        var positions = outerParticles.geometry.attributes.position.array;
        var time = Date.now() * 0.001;

        for (var i = 0, l = sizes.length; i < l; i++) {
            // Each particle reacts to a frequency band based on its index
            var band = i % 3;
            var audioPulse;
            if (band === 0) {
                // Bass particles grow massive
                audioPulse = 1 + bass * 6.0;
            } else if (band === 1) {
                audioPulse = 1 + mid * 4.0;
            } else {
                audioPulse = 1 + treble * 3.5;
            }

            // Strong wave effect - particles ripple outward
            var wave = 1 + Math.sin(time * 3 + i * 0.15) * intensity * 0.8;

            // Skip the currently hovered particle
            if (i !== INTERSECTED) {
                sizes[i] = baseSizes[i] * audioPulse * wave;
            }

            // Displace positions dramatically outward with bass, scatter with treble
            var bx = basePositions[i * 3];
            var by = basePositions[i * 3 + 1];
            var bz = basePositions[i * 3 + 2];
            var len = Math.sqrt(bx * bx + by * by + bz * bz);
            if (len > 0) {
                // Strong expansion on bass hits + chaotic displacement on treble
                var expand = 1 + bass * 0.5 + Math.sin(time * 2 + i * 0.08) * intensity * 0.2;
                // Scatter effect: each particle moves slightly randomly with treble
                var scatterX = Math.sin(time * 4 + i * 0.3) * treble * 8;
                var scatterY = Math.cos(time * 3.5 + i * 0.4) * treble * 8;
                var scatterZ = Math.sin(time * 5 + i * 0.2) * treble * 5;

                positions[i * 3] = bx * expand + scatterX;
                positions[i * 3 + 1] = by * expand + scatterY;
                positions[i * 3 + 2] = bz * expand + scatterZ;
            }
        }

        outerParticles.geometry.attributes.size.needsUpdate = true;
        outerParticles.geometry.attributes.position.needsUpdate = true;

        // Shift colors dramatically - cycle through warm/cool with music
        var colors = outerParticles.geometry.attributes.customColor.array;
        var tempColor = new THREE.Color();
        for (var i = 0, l = colors.length / 3; i < l; i++) {
            var t = i / l;
            // Bass pushes toward deep reds/oranges, treble toward blues/purples
            var hueShift = bass * 0.08 - treble * 0.15;
            var hue = (0.01 + 0.1 * t + hueShift + Math.sin(time * 0.5) * intensity * 0.05) % 1;
            if (hue < 0) hue += 1;
            var sat = 1.0;
            // Dramatic brightness pulsing
            var light = 0.4 + intensity * 0.5 + Math.sin(time * 4 + i * 0.1) * bass * 0.15;
            light = Math.min(light, 0.95);
            tempColor.setHSL(hue, sat, light);
            colors[i * 3] = tempColor.r;
            colors[i * 3 + 1] = tempColor.g;
            colors[i * 3 + 2] = tempColor.b;
        }
        outerParticles.geometry.attributes.customColor.needsUpdate = true;

        // Camera shake on big bass hits
        if (bass > 0.5) {
            camera.position.x = Math.sin(time * 20) * bass * 3;
            camera.position.y = Math.cos(time * 15) * bass * 2;
        } else {
            // Smoothly return camera to center
            camera.position.x *= 0.9;
            camera.position.y *= 0.9;
        }
    } else {
        outerParticles.rotation.x += baseRotX;
        outerParticles.rotation.y += baseRotY;

        // Reset to base sizes/positions when visualizer is off
        if (!audioVisualizerEnabled && baseSizes.length > 0) {
            var sizes = outerParticles.geometry.attributes.size.array;
            var positions = outerParticles.geometry.attributes.position.array;
            var needsSizeUpdate = false;
            var needsPosUpdate = false;

            for (var i = 0, l = sizes.length; i < l; i++) {
                if (i !== INTERSECTED && sizes[i] !== baseSizes[i]) {
                    sizes[i] = baseSizes[i];
                    needsSizeUpdate = true;
                }
            }
            for (var i = 0, l = basePositions.length; i < l; i++) {
                if (positions[i] !== basePositions[i]) {
                    positions[i] = basePositions[i];
                    needsPosUpdate = true;
                }
            }
            if (needsSizeUpdate) outerParticles.geometry.attributes.size.needsUpdate = true;
            if (needsPosUpdate) outerParticles.geometry.attributes.position.needsUpdate = true;
        }
    }

    render();
}

function render() {
    raycaster.setFromCamera(pointer, camera);
    intersects = raycaster.intersectObject(outerParticles); // Only outer particles interactive

    if (intersects.length > 0) {
        if (INTERSECTED != intersects[0].index) {
            outerParticles.geometry.attributes.size.array[INTERSECTED] = PARTICLE_SIZE * 0.5;
            INTERSECTED = intersects[0].index;
            outerParticles.geometry.attributes.size.array[INTERSECTED] = PARTICLE_SIZE * 1.25;
            outerParticles.geometry.attributes.size.needsUpdate = true;
        }
    } else if (INTERSECTED !== null) {
        outerParticles.geometry.attributes.size.array[INTERSECTED] = PARTICLE_SIZE * 0.5;
        outerParticles.geometry.attributes.size.needsUpdate = true;
        INTERSECTED = null;
    }

    renderer.render(scene, camera);
}

// Expose toggle for the music player
// This controls the Three.js background particles, NOT the inner player visualizer
window.toggleBackgroundAudioVisualizer = function(enabled) {
    audioVisualizerEnabled = enabled;
    console.log('[Three.js Background] Audio visualizer:', enabled ? 'ON' : 'OFF');

    // If turning off, smoothly reset particles to base state
    if (!enabled && outerParticles) {
        var sizes = outerParticles.geometry.attributes.size.array;
        var positions = outerParticles.geometry.attributes.position.array;
        for (var i = 0; i < sizes.length; i++) {
            sizes[i] = baseSizes[i];
        }
        for (var i = 0; i < basePositions.length; i++) {
            positions[i] = basePositions[i];
        }
        outerParticles.geometry.attributes.size.needsUpdate = true;
        outerParticles.geometry.attributes.position.needsUpdate = true;
        // Reset camera position
        camera.position.x = 0;
        camera.position.y = 0;
    }
};

// Check if visualizer was already toggled before this script loaded
window.getBackgroundVisualizerState = function() {
    return audioVisualizerEnabled;
};

document.addEventListener('DOMContentLoaded', init);