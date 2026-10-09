/**
 * Mapa 3D da topologia em Three.js. Os dispositivos ficam nas posicoes fixas gravadas no cadastro (altura por
 * camada, sem layout de forca), com forma por tipo (roteador, servidor, switch), cor pelo estado de saude e
 * halo pulsante quando ha perda ou degradacao. Os enlaces sao linhas de espessura em pixels (Line2): cor pelo
 * estado, espessura pela utilizacao e tracejado quando a porta caiu ou nao ha medicao. Quando um incidente esta
 * em foco, so o raio de impacto fica em destaque e o resto esmaece. A cena e criada uma vez; a topologia
 * reconstroi os objetos e um efeito separado so reaplica cores e estilos a cada novo dado, sem recriar nada.
 * Clique em um no devolve o id dele por onSelectNode, e clique no vazio devolve null.
 */

import { useEffect, useRef } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { CSS2DObject, CSS2DRenderer } from 'three/examples/jsm/renderers/CSS2DRenderer.js';
import { Line2 } from 'three/examples/jsm/lines/Line2.js';
import { LineGeometry } from 'three/examples/jsm/lines/LineGeometry.js';
import { LineMaterial } from 'three/examples/jsm/lines/LineMaterial.js';

const HEIGHT = 560;
const SCALE = { x: 1.5, y: 2.4, z: 1.5 };
const LAYER_SIZE = { gateway: 0.6, core: 0.65, server: 0.55, distribution: 0.5, access: 0.4 };
const STATUS_COLOR = {
    healthy: 0x34d399,
    degraded: 0xf59e0b,
    critical: 0xf43f5e,
    down: 0xf43f5e,
    no_data: 0x64748b,
};
const ALERTING = ['degraded', 'critical', 'down'];

const colorOf = (status) => STATUS_COLOR[status] ?? STATUS_COLOR.no_data;
const sizeOf = (node) => LAYER_SIZE[node.layer] ?? 0.45;

function geometryFor(node) {
    const size = sizeOf(node);

    if (node.kind === 'router') {
        return new THREE.OctahedronGeometry(size * 1.2);
    }

    if (node.kind === 'server') {
        return new THREE.BoxGeometry(size * 1.5, size * 1.5, size * 1.5);
    }

    return new THREE.SphereGeometry(size, 32, 20);
}

function positionOf(node, index) {
    const p = node.position ?? { x: index * 2, y: 0, z: 0 };

    return new THREE.Vector3(p.x * SCALE.x, p.y * SCALE.y, p.z * SCALE.z);
}

function clearGroup(group) {
    [...group.children].forEach((child) => {
        child.traverse((object) => {
            object.geometry?.dispose();
            object.material?.dispose();

            if (object.isCSS2DObject) {
                object.element.remove();
            }
        });

        group.remove(child);
    });
}

export default function NetworkMap3D({ topology, health, latest, focus, selectedId, onSelectNode }) {
    const container = useRef(null);
    const stage = useRef(null);
    const graph = useRef({ nodes: new Map(), links: new Map() });
    const selectHandler = useRef(onSelectNode);

    selectHandler.current = onSelectNode;

    useEffect(() => {
        const element = container.current;
        const width = element.clientWidth;

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0b0f19);

        const camera = new THREE.PerspectiveCamera(45, width / HEIGHT, 0.1, 500);
        camera.position.set(10, 8, 22);

        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setSize(width, HEIGHT);
        renderer.domElement.style.display = 'block';
        element.appendChild(renderer.domElement);

        const labels = new CSS2DRenderer();
        labels.setSize(width, HEIGHT);
        labels.domElement.style.position = 'absolute';
        labels.domElement.style.top = '0';
        labels.domElement.style.left = '0';
        labels.domElement.style.pointerEvents = 'none';
        element.appendChild(labels.domElement);

        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;

        scene.add(new THREE.AmbientLight(0xffffff, 0.9));
        const sun = new THREE.DirectionalLight(0xffffff, 1.2);
        sun.position.set(8, 14, 10);
        scene.add(sun);

        const group = new THREE.Group();
        scene.add(group);

        const raycaster = new THREE.Raycaster();
        const pointer = new THREE.Vector2();
        let pressed = null;

        const onDown = (event) => {
            pressed = { x: event.clientX, y: event.clientY };
        };

        const onUp = (event) => {
            if (!pressed) {
                return;
            }

            const moved = Math.hypot(event.clientX - pressed.x, event.clientY - pressed.y);
            pressed = null;

            if (moved > 4) {
                return;
            }

            const rect = renderer.domElement.getBoundingClientRect();
            pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
            pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(pointer, camera);

            const meshes = [...graph.current.nodes.values()].map((entry) => entry.mesh);
            const hit = raycaster.intersectObjects(meshes, false)[0];

            selectHandler.current(hit ? hit.object.userData.id : null);
        };

        renderer.domElement.addEventListener('pointerdown', onDown);
        renderer.domElement.addEventListener('pointerup', onUp);

        renderer.setAnimationLoop((time) => {
            const pulse = (Math.sin(time / 350) + 1) / 2;

            graph.current.nodes.forEach((entry) => {
                if (entry.halo.visible) {
                    entry.halo.scale.setScalar(1.4 + pulse * 0.5);
                    entry.halo.material.opacity = 0.12 + pulse * 0.22;
                }
            });

            controls.update();
            renderer.render(scene, camera);
            labels.render(scene, camera);
        });

        const observer = new ResizeObserver(() => {
            const w = element.clientWidth;

            renderer.setSize(w, HEIGHT);
            labels.setSize(w, HEIGHT);
            camera.aspect = w / HEIGHT;
            camera.updateProjectionMatrix();
            graph.current.links.forEach((entry) => entry.material.resolution.set(w, HEIGHT));
        });

        observer.observe(element);

        stage.current = { camera, controls, group, width: () => element.clientWidth };

        return () => {
            observer.disconnect();
            renderer.setAnimationLoop(null);
            renderer.domElement.removeEventListener('pointerdown', onDown);
            renderer.domElement.removeEventListener('pointerup', onUp);
            controls.dispose();
            clearGroup(group);
            renderer.dispose();
            renderer.domElement.remove();
            labels.domElement.remove();
            graph.current = { nodes: new Map(), links: new Map() };
            stage.current = null;
        };
    }, []);

    useEffect(() => {
        const current = stage.current;

        clearGroup(current.group);
        graph.current = { nodes: new Map(), links: new Map() };

        const nodes = topology?.nodes ?? [];
        const edges = topology?.edges ?? [];

        if (nodes.length === 0) {
            return;
        }

        const positions = new Map();
        const box = new THREE.Box3();

        nodes.forEach((node, index) => {
            const position = positionOf(node, index);
            positions.set(node.id, position);
            box.expandByPoint(position);

            const material = new THREE.MeshStandardMaterial({
                color: 0x64748b,
                emissive: 0x64748b,
                emissiveIntensity: 0.35,
                roughness: 0.45,
                metalness: 0.1,
                transparent: true,
            });

            const mesh = new THREE.Mesh(geometryFor(node), material);
            mesh.position.copy(position);
            mesh.userData.id = node.id;

            const halo = new THREE.Mesh(
                new THREE.SphereGeometry(sizeOf(node) * 1.3, 24, 16),
                new THREE.MeshBasicMaterial({ color: 0xf43f5e, transparent: true, opacity: 0.25, depthWrite: false })
            );
            halo.visible = false;
            mesh.add(halo);

            const label = document.createElement('div');
            label.className = 'rounded bg-slate-900/80 px-1.5 py-0.5 text-[11px] font-semibold text-slate-200';
            label.textContent = node.name;

            const labelObject = new CSS2DObject(label);
            labelObject.position.set(0, -(sizeOf(node) + 0.55), 0);
            mesh.add(labelObject);

            current.group.add(mesh);
            graph.current.nodes.set(node.id, { mesh, material, halo, label });
        });

        const width = current.width();

        edges.forEach((edge) => {
            const from = positions.get(edge.from_device_id);
            const to = positions.get(edge.to_device_id);

            if (!from || !to) {
                return;
            }

            const geometry = new LineGeometry();
            geometry.setPositions([from.x, from.y, from.z, to.x, to.y, to.z]);

            const material = new LineMaterial({
                color: 0x64748b,
                linewidth: 2,
                transparent: true,
                dashSize: 0.35,
                gapSize: 0.25,
            });
            material.resolution.set(width, HEIGHT);

            const line = new Line2(geometry, material);
            line.computeLineDistances();

            current.group.add(line);
            graph.current.links.set(edge.id, { line, material, edge });
        });

        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());
        const radius = Math.max(size.length() / 2, 3);
        const distance = (radius / Math.tan(THREE.MathUtils.degToRad(current.camera.fov) / 2)) * 1.1 + 2;

        current.camera.position.set(center.x + distance * 0.3, center.y + distance * 0.25, center.z + distance * 0.9);
        current.controls.target.copy(center);
        current.controls.update();
    }, [topology]);

    useEffect(() => {
        const deviceRows = new Map((health?.devices ?? []).map((row) => [Number(row.device_id), row]));
        const linkRows = new Map((health?.links ?? []).map((row) => [Number(row.link_id), row]));
        const latestDevices = latest?.devices ?? {};
        const latestLinks = latest?.links ?? {};
        const focusIds = focus?.ids ?? null;

        graph.current.nodes.forEach((entry, id) => {
            const status = deviceRows.get(id)?.status ?? 'no_data';
            const color = colorOf(status);
            const loss = latestDevices[id]?.ping_loss_pct ?? 0;
            const dimmed = focusIds !== null && !focusIds.has(id);
            const emphasized = (focusIds !== null && focusIds.has(id)) || selectedId === id;

            entry.material.color.setHex(color);
            entry.material.emissive.setHex(color);
            entry.material.emissiveIntensity = emphasized ? 0.9 : 0.35;
            entry.material.opacity = dimmed ? 0.18 : 1;
            entry.mesh.scale.setScalar(selectedId === id || focus?.rootId === id ? 1.3 : 1);
            entry.label.style.opacity = dimmed ? '0.25' : '1';
            entry.halo.material.color.setHex(color);
            entry.halo.visible = !dimmed && (loss > 1 || ALERTING.includes(status));
        });

        graph.current.links.forEach((entry, id) => {
            const status = linkRows.get(id)?.status ?? 'no_data';
            const util = latestLinks[id]?.if_util_pct ?? 0;
            const broken = status === 'down' || status === 'no_data';
            const dimmed = focusIds !== null && !focusIds.has(entry.edge.to_device_id);

            entry.material.color.setHex(colorOf(status));
            entry.material.linewidth = broken ? 2 : 1.5 + (Math.min(util, 100) / 100) * 5;
            entry.material.opacity = dimmed ? 0.12 : 1;

            if (entry.material.dashed !== broken) {
                entry.material.dashed = broken;
                entry.material.needsUpdate = true;
            }
        });
    }, [topology, health, latest, focus, selectedId]);

    return <div ref={container} className="relative w-full overflow-hidden rounded-lg border border-slate-800" style={{ height: HEIGHT }} />;
}
