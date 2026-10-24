import * as THREE from 'three'
import { useEffect, useMemo } from 'react'
import { usePartMaterial } from '../materials/visualMaterials'

function toMeters(mm) {
  return mm / 1000
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

  if (shape === 'knob') {
    return <sphereGeometry args={[Math.max(0.005, length / 2), 32, 32]} />
  }

  if (shape === 'bulb_knob') {
    return <sphereGeometry args={[Math.max(0.005, length / 2), 32, 32]} />
  }

  if (shape === 'bar') {
    return <boxGeometry args={[length, height, width]} />
  }

  return <boxGeometry args={[length, height, width]} />
}

function PartMesh({ part, material, isSelected, onSelect }) {
  const meshMaterial = usePartMaterial(material, 1)
  const selectionMaterial = useMemo(() => new THREE.MeshBasicMaterial({
    color: '#2563eb',
    wireframe: true,
    transparent: true,
    opacity: 0.75,
    depthTest: false,
  }), [])
  useEffect(() => () => selectionMaterial.dispose(), [selectionMaterial])

  const length = toMeters(part.length_mm ?? 0)
  const width = toMeters(part.width_mm ?? 0)
  const height = toMeters(part.height_mm ?? 0)
  const verticalExtent = ['round_foot', 'knob', 'bulb_knob'].includes(part.shape)
    ? length
    : height

  const position = [
    toMeters(part.pos_x_mm ?? 0),
    toMeters(part.pos_y_mm ?? 0) + verticalExtent / 2,
    toMeters(part.pos_z_mm ?? 0),
  ]

  const rotation = [
    THREE.MathUtils.degToRad(part.rot_x_deg ?? 0),
    THREE.MathUtils.degToRad(part.rot_y_deg ?? 0),
    THREE.MathUtils.degToRad(part.rot_z_deg ?? 0),
  ]

  return (
    <>
    <mesh
      castShadow
      receiveShadow
      position={position}
      rotation={rotation}
      material={meshMaterial}
      onClick={(event) => {
        event.stopPropagation()
        onSelect?.(part.code)
      }}
    >
      <ShapeGeometry shape={part.shape} length={length} width={width} height={height} />
    </mesh>
    {isSelected ? (
      <mesh position={position} rotation={rotation} material={selectionMaterial} renderOrder={10}>
        <ShapeGeometry shape={part.shape} length={length} width={width} height={height} />
      </mesh>
    ) : null}
    </>
  )
}

export function CustomModel({ parts, partMaterials, selectedPartCode, onSelectPart }) {
  return (
    <group>
      {parts.map((part) => (
        <PartMesh
          key={part.code}
          part={part}
          material={partMaterials[part.code]}
          isSelected={part.code === selectedPartCode}
          onSelect={onSelectPart}
        />
      ))}
    </group>
  )
}
