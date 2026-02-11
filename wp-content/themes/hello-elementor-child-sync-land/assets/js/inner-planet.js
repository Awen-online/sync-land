// inner-planet.js

// Check if Three.js is available
if (typeof THREE === 'undefined') {
    console.error('Three.js is not loaded. Please ensure it is enqueued in WordPress.');
} else {
    // Function to create and initialize the inner planet
    function createInnerPlanet(containerId = 'planet-container', options = {}) {
        // Check if container exists
        const container = typeof containerId === 'string'
            ? document.getElementById(containerId)
            : containerId;

        if (!container) {
            console.error(`Container with ID '${containerId}' not found.`);
            return null;
        }

        // Scene setup
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 1, 1000);
        camera.position.z = 150;

        // Inner planet with liquid lava lamp effect matching logo gradient
        // Colors: Coral (#FF7350) -> Hot Pink (#FF4D80) -> Magenta (#D933A0) -> Purple (#8C33B3) -> Deep Blue (#3340A6)
        const planetGeometry = new THREE.SphereGeometry(50, 128, 128); // Higher resolution for smoother look
        const planetMaterial = new THREE.ShaderMaterial({
            uniforms: {
                time: { value: 0 },
                audioIntensity: { value: 0.0 },
                bassLevel: { value: 0.0 },
                trebleLevel: { value: 0.0 }
            },
            vertexShader: `
                varying vec3 vNormal;
                varying vec2 vUv;
                varying vec3 vPosition;
                varying vec3 vWorldPosition;
                uniform float audioIntensity;
                uniform float bassLevel;
                uniform float time;

                void main() {
                    vNormal = normal;
                    vUv = uv;
                    vPosition = position;

                    // Audio-reactive vertex displacement - more organic blob movement
                    vec3 pos = position;
                    float displacement = sin(position.y * 3.0 + time * 0.5) * bassLevel * 3.0;
                    displacement += sin(position.x * 2.5 + position.z * 2.5 + time * 0.7) * audioIntensity * 2.0;
                    displacement += cos(position.z * 3.0 + time * 0.3) * bassLevel * 1.5;
                    pos += normal * displacement;

                    vWorldPosition = pos;
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(pos, 1.0);
                }
            `,
            fragmentShader: `
                uniform float time;
                uniform float audioIntensity;
                uniform float bassLevel;
                uniform float trebleLevel;
                varying vec3 vNormal;
                varying vec2 vUv;
                varying vec3 vPosition;
                varying vec3 vWorldPosition;

                // Logo gradient colors
                vec3 coral = vec3(1.0, 0.451, 0.314);       // #FF7350
                vec3 hotPink = vec3(1.0, 0.302, 0.502);     // #FF4D80
                vec3 magenta = vec3(0.851, 0.2, 0.627);     // #D933A0
                vec3 purple = vec3(0.549, 0.2, 0.702);      // #8C33B3
                vec3 deepBlue = vec3(0.2, 0.251, 0.651);    // #3340A6

                // Simplex-like noise for organic movement
                vec3 mod289(vec3 x) { return x - floor(x * (1.0 / 289.0)) * 289.0; }
                vec4 mod289(vec4 x) { return x - floor(x * (1.0 / 289.0)) * 289.0; }
                vec4 permute(vec4 x) { return mod289(((x*34.0)+1.0)*x); }
                vec4 taylorInvSqrt(vec4 r) { return 1.79284291400159 - 0.85373472095314 * r; }

                float snoise(vec3 v) {
                    const vec2 C = vec2(1.0/6.0, 1.0/3.0);
                    const vec4 D = vec4(0.0, 0.5, 1.0, 2.0);
                    vec3 i  = floor(v + dot(v, C.yyy));
                    vec3 x0 = v - i + dot(i, C.xxx);
                    vec3 g = step(x0.yzx, x0.xyz);
                    vec3 l = 1.0 - g;
                    vec3 i1 = min(g.xyz, l.zxy);
                    vec3 i2 = max(g.xyz, l.zxy);
                    vec3 x1 = x0 - i1 + C.xxx;
                    vec3 x2 = x0 - i2 + C.yyy;
                    vec3 x3 = x0 - D.yyy;
                    i = mod289(i);
                    vec4 p = permute(permute(permute(
                        i.z + vec4(0.0, i1.z, i2.z, 1.0))
                        + i.y + vec4(0.0, i1.y, i2.y, 1.0))
                        + i.x + vec4(0.0, i1.x, i2.x, 1.0));
                    float n_ = 0.142857142857;
                    vec3 ns = n_ * D.wyz - D.xzx;
                    vec4 j = p - 49.0 * floor(p * ns.z * ns.z);
                    vec4 x_ = floor(j * ns.z);
                    vec4 y_ = floor(j - 7.0 * x_);
                    vec4 x = x_ *ns.x + ns.yyyy;
                    vec4 y = y_ *ns.x + ns.yyyy;
                    vec4 h = 1.0 - abs(x) - abs(y);
                    vec4 b0 = vec4(x.xy, y.xy);
                    vec4 b1 = vec4(x.zw, y.zw);
                    vec4 s0 = floor(b0)*2.0 + 1.0;
                    vec4 s1 = floor(b1)*2.0 + 1.0;
                    vec4 sh = -step(h, vec4(0.0));
                    vec4 a0 = b0.xzyw + s0.xzyw*sh.xxyy;
                    vec4 a1 = b1.xzyw + s1.xzyw*sh.zzww;
                    vec3 p0 = vec3(a0.xy, h.x);
                    vec3 p1 = vec3(a0.zw, h.y);
                    vec3 p2 = vec3(a1.xy, h.z);
                    vec3 p3 = vec3(a1.zw, h.w);
                    vec4 norm = taylorInvSqrt(vec4(dot(p0,p0), dot(p1,p1), dot(p2,p2), dot(p3,p3)));
                    p0 *= norm.x;
                    p1 *= norm.y;
                    p2 *= norm.z;
                    p3 *= norm.w;
                    vec4 m = max(0.6 - vec4(dot(x0,x0), dot(x1,x1), dot(x2,x2), dot(x3,x3)), 0.0);
                    m = m * m;
                    return 42.0 * dot(m*m, vec4(dot(p0,x0), dot(p1,x1), dot(p2,x2), dot(p3,x3)));
                }

                void main() {
                    // Use 3D position for seamless noise (no UV seam)
                    vec3 pos = normalize(vPosition) * 2.0;

                    // Slow, organic time for lava lamp effect
                    float slowTime = time * 0.15;
                    float audioTime = slowTime * (1.0 + audioIntensity * 0.3);

                    // Multiple layers of flowing noise for liquid effect
                    float noise1 = snoise(pos * 1.5 + vec3(0.0, audioTime * 0.5, 0.0));
                    float noise2 = snoise(pos * 2.5 + vec3(audioTime * 0.3, 0.0, audioTime * 0.2));
                    float noise3 = snoise(pos * 0.8 + vec3(0.0, -audioTime * 0.4, audioTime * 0.1));

                    // Audio-reactive noise layer
                    float audioNoise = snoise(pos * 3.0 + vec3(bassLevel * 2.0, trebleLevel * 2.0, audioTime));

                    // Combine noise layers with different weights
                    float combinedNoise = noise1 * 0.5 + noise2 * 0.3 + noise3 * 0.2;
                    combinedNoise += audioNoise * audioIntensity * 0.3;

                    // Create flowing blobs that rise and fall
                    float blobPattern = sin(pos.y * 2.0 + combinedNoise * 3.0 + audioTime) * 0.5 + 0.5;
                    blobPattern = smoothstep(0.2, 0.8, blobPattern);

                    // Map vertical position to gradient with noise distortion
                    float gradientPos = (pos.y + 1.0) * 0.5; // 0 to 1 from bottom to top
                    gradientPos += combinedNoise * 0.25; // Add organic distortion
                    gradientPos = clamp(gradientPos, 0.0, 1.0);

                    // 5-color gradient with smooth transitions
                    vec3 baseColor;
                    if (gradientPos < 0.25) {
                        baseColor = mix(deepBlue, purple, smoothstep(0.0, 0.25, gradientPos));
                    } else if (gradientPos < 0.5) {
                        baseColor = mix(purple, magenta, smoothstep(0.25, 0.5, gradientPos));
                    } else if (gradientPos < 0.75) {
                        baseColor = mix(magenta, hotPink, smoothstep(0.5, 0.75, gradientPos));
                    } else {
                        baseColor = mix(hotPink, coral, smoothstep(0.75, 1.0, gradientPos));
                    }

                    // Create lava lamp blob effect - blend between colors based on blob pattern
                    vec3 blobColor = mix(magenta, hotPink, noise2 * 0.5 + 0.5);
                    blobColor = mix(blobColor, coral, blobPattern * 0.5);

                    // Mix base gradient with blob effect
                    float blobMix = smoothstep(0.3, 0.7, blobPattern + noise1 * 0.3);
                    baseColor = mix(baseColor, blobColor, blobMix * 0.6);

                    // Add flowing highlights
                    float highlight = snoise(pos * 4.0 + vec3(audioTime * 0.8, 0.0, 0.0));
                    highlight = smoothstep(0.3, 0.8, highlight);
                    baseColor = mix(baseColor, baseColor * 1.4, highlight * 0.3);

                    // Audio-reactive color pulsing
                    baseColor = mix(baseColor, baseColor * 1.3, bassLevel * 0.4);
                    baseColor = mix(baseColor, vec3(1.0, 0.6, 0.8), trebleLevel * 0.15);

                    // Soft lighting from upper-left for depth
                    vec3 lightDir = normalize(vec3(-0.8, 1.0, 0.8));
                    float diffuse = max(dot(vNormal, lightDir), 0.35);
                    diffuse = mix(diffuse, 1.0, 0.3); // Soften shadows for lava lamp look

                    // Audio-reactive brightness
                    diffuse *= (1.0 + audioIntensity * 0.25);

                    // Subtle glossy highlight
                    float specular = pow(max(dot(reflect(-lightDir, vNormal), normalize(-cameraPosition)), 0.0), 30.0) * 0.25;

                    // Inner glow effect
                    float rim = 1.0 - max(dot(vNormal, normalize(cameraPosition)), 0.0);
                    rim = pow(rim, 2.0) * 0.3;

                    // Combine lighting
                    vec3 finalColor = baseColor * diffuse + specular + baseColor * rim;
                    finalColor *= (1.0 + audioIntensity * 0.15);

                    // Ensure colors stay vibrant
                    finalColor = clamp(finalColor, 0.0, 1.0);

                    gl_FragColor = vec4(finalColor, 1.0);
                }
            `,
            side: THREE.FrontSide
        });

        const innerPlanet = new THREE.Mesh(planetGeometry, planetMaterial);
        scene.add(innerPlanet);

        // Soft ambient lighting
        const ambientLight = new THREE.AmbientLight(0x606060, 0.8);
        scene.add(ambientLight);

        // Main light from upper-left
        const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
        directionalLight.position.set(-5, 5, 5);
        scene.add(directionalLight);

        // Renderer setup
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setClearColor(0x000000, 0);
        renderer.setSize(container.offsetWidth, container.offsetHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        container.appendChild(renderer.domElement);

        // Handle window resize
        function onWindowResize() {
            camera.aspect = container.offsetWidth / container.offsetHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(container.offsetWidth, container.offsetHeight);
        }
        window.addEventListener('resize', onWindowResize);

        // Animation
        let elapsedTime = 0;
        const clock = new THREE.Clock();
        let animationId = null;

        function animate() {
            animationId = requestAnimationFrame(animate);

            const delta = clock.getDelta();
            elapsedTime += delta;

            planetMaterial.uniforms.time.value = elapsedTime;

            // Read audio data
            const audioData = window.FMLAudioData || { intensity: 0, bass: 0, treble: 0 };
            planetMaterial.uniforms.audioIntensity.value = audioData.intensity || 0;
            planetMaterial.uniforms.bassLevel.value = audioData.bass || 0;
            planetMaterial.uniforms.trebleLevel.value = audioData.treble || 0;

            // Slow, smooth rotation
            innerPlanet.rotation.y += 0.0008 + (audioData.bass || 0) * 0.003;

            renderer.render(scene, camera);
        }

        animate();

        // Return control object
        return {
            scene,
            camera,
            renderer,
            planet: innerPlanet,
            material: planetMaterial,
            stop: function() {
                if (animationId) {
                    cancelAnimationFrame(animationId);
                    animationId = null;
                }
            },
            resize: onWindowResize
        };
    }

    // Export module
    window.innerPlanetModule = { createInnerPlanet };

    // Auto-initialize for default container
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('planet-container') && !document.querySelector('.hero-planet-canvas')) {
            createInnerPlanet();
        }
    });
}
