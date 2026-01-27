let renderer, scene, camera;
let outerParticles;
const PARTICLE_SIZE = 20;
let raycaster, intersects;
let pointer = new THREE.Vector2(), INTERSECTED;

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
    outerParticles.rotation.x += 0.0005;
    outerParticles.rotation.y += 0.001;
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

document.addEventListener('DOMContentLoaded', init);