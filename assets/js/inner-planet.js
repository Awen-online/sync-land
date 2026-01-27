// inner-planet.js

// Check if Three.js is available
if (typeof THREE === 'undefined') {
    console.error('Three.js is not loaded. Please ensure it is enqueued in WordPress.');
} else {
    // Function to create and initialize the inner planet
    function createInnerPlanet(containerId = 'planet-container') {
        // Check if container exists
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container with ID '${containerId}' not found.`);
            return;
        }

        // Scene setup
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 1, 1000);
        camera.position.z = 150; // Adjust to fit the planet within the container

        // Inner planet (solid with shader-based dense wavy bands, Jupiter-like)
        const planetGeometry = new THREE.SphereGeometry(50, 64, 64); // High segments for smooth surface
        const planetMaterial = new THREE.ShaderMaterial({
            uniforms: {
                time: { value: 0 }
            },
            vertexShader: `
                varying vec3 vNormal;
                varying vec2 vUv;
                void main() {
                    vNormal = normal;
                    vUv = uv;
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                }
            `,
            fragmentShader: `
                uniform float time;
                varying vec3 vNormal;
                varying vec2 vUv;
                void main() {
                    // Solid base color (Jupiter-like, dark with illumination)
                    vec3 baseColor = vec3(0.2, 0.15, 0.1); // Dark orange-brown base

                    // Dense wavy orange/red bands (10x more, internal)
                    float wave1 = sin(vUv.y * 100.0 + time * 0.1); // 10x frequency
                    float wave2 = sin(vUv.y * 100.0 + vUv.x * 10.0 + time * 0.1); // Additional variation
                    vec3 orangeRed = mix(vec3(0.9, 0.4, 0.1), vec3(1.0, 0.6, 0.0), vUv.x); // Rich orange shades
                    if (wave1 > 0.3 && wave1 < 0.7 || wave2 > 0.3 && wave2 < 0.7) {
                        baseColor = mix(baseColor, orangeRed, smoothstep(0.3, 0.7, max(wave1, wave2)) * 0.95); // Strong orange bands
                    }

                    // Dense blue/purple/pink bands (10x more, internal)
                    float blueWave1 = sin(vUv.y * 100.0 - vUv.x * 10.0 + time * 0.1); // 10x frequency
                    float blueWave2 = sin(vUv.y * 100.0 + vUv.x * 15.0 + time * 0.1); // Additional variation
                    vec3 bluePurplePink = mix(vec3(0.0, 0.0, 1.0), vec3(0.8, 0.0, 0.8), vUv.y); // Blue to purple/pink
                    if (blueWave1 > 0.3 && blueWave1 < 0.7 || blueWave2 > 0.3 && blueWave2 < 0.7) {
                        baseColor = mix(baseColor, bluePurplePink, smoothstep(0.3, 0.7, max(blueWave1, blueWave2)) * 0.9); // Strong blue/purple bands
                    }

                    // Subtle red accents for variety
                    float redWave = sin(vUv.y * 100.0 + vUv.x * 20.0 + time * 0.1);
                    vec3 redAccent = vec3(1.0, 0.3, 0.3); // Reddish hue
                    if (redWave > 0.4) {
                        baseColor = mix(baseColor, redAccent, smoothstep(0.4, 1.0, redWave) * 0.7); // Subtle red accents
                    }

                    // Add shading and lighting for a 3D, solid Jupiter-like effect
                    vec3 lightDir = normalize(vec3(1.0, 1.0, 1.0));
                    float diffuse = max(dot(vNormal, lightDir), 0.4); // Higher minimum for brightness
                    float specular = pow(max(dot(reflect(-lightDir, vNormal), normalize(-cameraPosition)), 0.0), 10.0) * 0.7; // Strong gloss
                    gl_FragColor = vec4((baseColor * diffuse + specular), 1.0); // Fully opaque (no transparency)
                }
            `,
            side: THREE.FrontSide
        });

        const innerPlanet = new THREE.Mesh(planetGeometry, planetMaterial);
        scene.add(innerPlanet);

        // Add lighting for realistic shadows and highlights
        const ambientLight = new THREE.AmbientLight(0x606060, 0.8); // Brighter ambient light
        scene.add(ambientLight);

        const directionalLight = new THREE.DirectionalLight(0xffffff, 1.0); // Stronger directional light
        directionalLight.position.set(5, 5, 5); // Position the light above and to the side
        directionalLight.castShadow = true;     // Enable shadows
        scene.add(directionalLight);

        // Configure shadow properties
        directionalLight.shadow.mapSize.width = 1024;
        directionalLight.shadow.mapSize.height = 1024;
        directionalLight.shadow.camera.near = 0.5;
        directionalLight.shadow.camera.far = 500;
        directionalLight.shadow.bias = -0.0001;

        // Enable shadows for the renderer and planet
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true }); // Enable alpha for transparent background
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap; // Soft shadows for realism
        renderer.setClearColor(0x000000, 0); // Transparent background
        innerPlanet.castShadow = true; // Planet casts shadows
        innerPlanet.receiveShadow = true; // Planet receives shadows

        // Resize renderer to fit the container
        renderer.setSize(container.offsetWidth, container.offsetHeight);
        container.appendChild(renderer.domElement);

        // Handle window resize for responsive design
        function onWindowResize() {
            camera.aspect = container.offsetWidth / container.offsetHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(container.offsetWidth, container.offsetHeight);
        }
        window.addEventListener('resize', onWindowResize);

        // Animation loop
        function animate() {
            requestAnimationFrame(animate);
            innerPlanet.rotation.y += 0.001; // Slow rotation for planet
            renderer.render(scene, camera);
        }

        animate();
    }

    // Initialize the planet when the DOM is loaded, targeting the default container ID
    document.addEventListener('DOMContentLoaded', function() {
        createInnerPlanet(); // Calls with default 'planet-container' ID
    });
}