import { OrbitControls, PerspectiveCamera, TransformControls } from '@react-three/drei'
import { Canvas, useThree } from '@react-three/fiber'
import { Suspense, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import * as THREE from 'three'
import { usePartMaterial } from '../configurator/materials/visualMaterials'

function toMeters(mm) {
  return mm / 1000
}

const CAMERA_DEFAULT = {
  position: [2, 1.6, 2],
  target: [0, 0.4, 0],
}

function CameraController({ resetKey }) {
  const { camera, controls } = useThree()

  useEffect(() => {
    if (!controls) {
      return
    }

    camera.position.set(...CAMERA_DEFAULT.position)
    controls.target.set(...CAMERA_DEFAULT.target)
    controls.update()
  }, [camera, controls, resetKey])

  return null
}

function ShapeGeometry({ shape, length, width, height }) {
  if (shape === 'circular') {
    const radius = Math.max(0.005, length / 2)
    return <cylinderGeometry args={[radius, radius, height, 48]} />
  }

  if (shape === 'cube_foot') {
    return <boxGeometry args={[length, height, width]} />
  }

  if (shape === 'angular_foot') {
    return <coneGeometry args={[Math.max(0.005, length / 2), height, 4]} />
  }

  if (shape === 'round_foot') {
    return <sphereGeometry args={[Math.max(0.005, length / 2), 32, 32]} />
  }

  if (shape === 'rectangular_leg') {
    return <boxGeometry args={[length, height, width]} />
  }

  if (shape === 'cylinder_leg') {
    const radius = Math.max(0.005, length / 2)
    return <cylinderGeometry args={[radius, radius, height, 32]} />
  }

  if (shape === 'knob' || shape === 'bulb_knob') {
    return <sphereGeometry args={[Math.max(0.005, length / 2), 32, 32]} />
  }

  if (shape === 'bar') {
    return <boxGeometry args={[length, height, width]} />
  }

  return <boxGeometry args={[length, height, width]} />
}

function geometryHeightMm(component) {
  if (['round_foot', 'knob', 'bulb_knob'].includes(component.shape)) {
    return component.length_mm
  }
  return component.height_mm
}

function BuilderMesh({
  component,
  material,
  isSelected,
  onSelect,
  onUpdate,
  transformMode,
  transformSpace,
}) {
  const groupRef = useRef(null)
  const transformRef = useRef(null)

  const meshMaterial = usePartMaterial(material, 1)

  const length = toMeters(component.length_mm)
  const width = toMeters(component.width_mm)
  const height = toMeters(component.height_mm)
  const verticalExtent = toMeters(geometryHeightMm(component))

  const basePosition = useMemo(
    () =>
      new THREE.Vector3(
        toMeters(component.pos_x_mm),
        toMeters(component.pos_y_mm) + verticalExtent / 2,
        toMeters(component.pos_z_mm),
      ),
    [component.pos_x_mm, component.pos_y_mm, component.pos_z_mm, verticalExtent],
  )

  const baseRotation = useMemo(
    () =>
      new THREE.Euler(
        THREE.MathUtils.degToRad(component.rot_x_deg),
        THREE.MathUtils.degToRad(component.rot_y_deg),
        THREE.MathUtils.degToRad(component.rot_z_deg),
      ),
    [component.rot_x_deg, component.rot_y_deg, component.rot_z_deg],
  )

  const selectionMaterial = useMemo(() => {
    if (!isSelected) {
      return null
    }

    return new THREE.MeshBasicMaterial({
      color: '#3b82f6',
      wireframe: true,
      transparent: true,
      opacity: 0.5,
    })
  }, [isSelected])

  useEffect(() => () => selectionMaterial?.dispose(), [selectionMaterial])

  useEffect(() => {
    if (groupRef.current) {
      groupRef.current.position.copy(basePosition)
      groupRef.current.rotation.copy(baseRotation)
    }
  }, [basePosition, baseRotation])

  useEffect(() => {
    const controls = transformRef.current

    if (!controls || !onUpdate) {
      return undefined
    }

    function onChange() {
      const group = groupRef.current

      if (!group) {
        return
      }

      onUpdate({
        pos_x_mm: Math.round(group.position.x * 1000),
        pos_y_mm: Math.round((group.position.y - verticalExtent / 2) * 1000),
        pos_z_mm: Math.round(group.position.z * 1000),
        rot_x_deg: Math.round(THREE.MathUtils.radToDeg(group.rotation.x)),
        rot_y_deg: Math.round(THREE.MathUtils.radToDeg(group.rotation.y)),
        rot_z_deg: Math.round(THREE.MathUtils.radToDeg(group.rotation.z)),
      })
    }

    controls.addEventListener('mouseUp', onChange)

    return () => {
      controls.removeEventListener('mouseUp', onChange)
    }
  }, [verticalExtent, onUpdate])

  return (
    <>
      <group
        ref={groupRef}
        position={basePosition}
        rotation={baseRotation}
        onClick={(event) => {
          event.stopPropagation()
          onSelect?.(component.id)
        }}
      >
        <mesh castShadow receiveShadow material={meshMaterial}>
          <ShapeGeometry shape={component.shape} length={length} width={width} height={height} />
        </mesh>

        {isSelected && selectionMaterial ? (
          <mesh material={selectionMaterial}>
            <ShapeGeometry shape={component.shape} length={length} width={width} height={height} />
          </mesh>
        ) : null}
      </group>

      {isSelected ? (
        <TransformControls
          ref={transformRef}
          object={groupRef}
          mode={transformMode}
          space={transformSpace}
          size={0.6}
          showX
          showY
          showZ
        />
      ) : null}
    </>
  )
}

function SceneContents({
  components,
  materials,
  selectedId,
  onSelect,
  onUpdate,
  transformMode,
  transformSpace,
  resetKey,
}) {
  const componentMaterials = useMemo(() => {
    const map = new Map()

    for (const component of components) {
      const material = materials.find(
        (candidate) => String(candidate.id) === String(component.material_id),
      )

      map.set(component.id, material ?? null)
    }

    return map
  }, [components, materials])

  return (
    <>
      <CameraController resetKey={resetKey} />
      <PerspectiveCamera makeDefault position={CAMERA_DEFAULT.position} fov={45} />
      <OrbitControls
        makeDefault
        enablePan
        enableZoom
        enableRotate
        minDistance={0.5}
        maxDistance={8}
        target={CAMERA_DEFAULT.target}
      />

      <ambientLight intensity={0.6} />
      <directionalLight
        position={[2, 4, 3]}
        intensity={1.2}
        castShadow
        shadow-mapSize-width={1024}
        shadow-mapSize-height={1024}
      />
      <directionalLight position={[-2, 2, -3]} intensity={0.4} />

      <group>
        {components.map((component) => (
          <BuilderMesh
            key={component.id}
            component={component}
            material={componentMaterials.get(component.id)}
            isSelected={component.id === selectedId}
            onSelect={onSelect}
            onUpdate={component.id === selectedId ? onUpdate : undefined}
            transformMode={transformMode}
            transformSpace={transformSpace}
          />
        ))}
      </group>

      <mesh receiveShadow rotation={[-Math.PI / 2, 0, 0]} position={[0, 0, 0]}>
        <planeGeometry args={[8, 8]} />
        <shadowMaterial opacity={0.15} />
      </mesh>
    </>
  )
}

export function BuilderPreview({ components, materials, onComponentUpdate, onSelectionChange }) {
  const [selectedId, setSelectedId] = useState(null)
  const [transformMode, setTransformMode] = useState('translate')
  const [transformSpace, setTransformSpace] = useState('world')
  const [resetKey, setResetKey] = useState(0)

  const handleSelect = useCallback((id) => {
    setSelectedId(id)
    onSelectionChange?.(id)
  }, [onSelectionChange])

  const handleUpdate = useCallback(
    (updates) => {
      if (!selectedId || !onComponentUpdate) {
        return
      }

      onComponentUpdate(selectedId, updates)
    },
    [onComponentUpdate, selectedId],
  )

  return (
    <div className="scene-container builder-preview">
      <div className="builder-preview-overlay">
        <div className="builder-preview-controls">
          <span>Tool:</span>
          <button
            type="button"
            className={transformMode === 'translate' ? 'is-active' : undefined}
            onClick={() => setTransformMode('translate')}
          >
            Move
          </button>
          <button
            type="button"
            className={transformMode === 'rotate' ? 'is-active' : undefined}
            onClick={() => setTransformMode('rotate')}
          >
            Rotate
          </button>
          <span>Axes:</span>
          <button
            type="button"
            className={transformSpace === 'world' ? 'is-active' : undefined}
            onClick={() => setTransformSpace('world')}
            aria-pressed={transformSpace === 'world'}
            title="Move along the scene's X, Y, and Z coordinates"
          >
            World
          </button>
          <button
            type="button"
            className={transformSpace === 'local' ? 'is-active' : undefined}
            onClick={() => setTransformSpace('local')}
            aria-pressed={transformSpace === 'local'}
            title="Move along the selected component's rotated axes"
          >
            Local
          </button>
          <button
            type="button"
            onClick={() => {
              setSelectedId(null)
              onSelectionChange?.(null)
              setResetKey((value) => value + 1)
            }}
          >
            Reset camera
          </button>
        </div>
        <span className="builder-selection-hint">
          {selectedId
            ? `Drag the gizmo to move or rotate the selected component. ${transformSpace === 'world' ? 'World axes keep X, Y, and Z aligned to the scene.' : 'Local axes follow the component rotation.'}`
            : 'Click a component to select it.'}
        </span>
      </div>

      <Canvas shadows onPointerMissed={() => { setSelectedId(null); onSelectionChange?.(null) }}>
        <Suspense fallback={null}>
          <SceneContents
            components={components}
            materials={materials}
            selectedId={selectedId}
            onSelect={handleSelect}
            onUpdate={handleUpdate}
            transformMode={transformMode}
            transformSpace={transformSpace}
            resetKey={resetKey}
          />
        </Suspense>
      </Canvas>
    </div>
  )
}
