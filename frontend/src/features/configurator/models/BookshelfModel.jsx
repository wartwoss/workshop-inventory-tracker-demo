import { usePartMaterial } from '../materials/visualMaterials'

function toMeters(mm) {
  return mm / 1000
}

function panelRepeatScale(...lengthsM) {
  return Math.max(1, Math.round(Math.max(...lengthsM) * 2))
}

/** A simple open-backed-front bookshelf whose outside dimensions follow the preset controls. */
export function BookshelfModel({ dimensions, partMaterials }) {
  const length = toMeters(dimensions.length)
  const depth = toMeters(dimensions.width)
  const height = toMeters(dimensions.height)

  const boardThickness = 0.018
  const backThickness = 0.012
  const insideLength = Math.max(0.02, length - boardThickness * 2)
  const insideDepth = Math.max(0.02, depth - backThickness)
  const shelfCount = 3

  const sidesMaterial = usePartMaterial(partMaterials.sides, panelRepeatScale(height, depth))
  const topBottomMaterial = usePartMaterial(partMaterials.topBottom, panelRepeatScale(insideLength, depth))
  const shelvesMaterial = usePartMaterial(partMaterials.shelves, panelRepeatScale(insideLength, insideDepth))
  const backMaterial = usePartMaterial(partMaterials.back, panelRepeatScale(length, height))

  const sideX = length / 2 - boardThickness / 2
  const shelfDepthCenterZ = backThickness / 2
  const shelfYPositions = Array.from({ length: shelfCount }, (_, index) =>
    height * (index + 1) / (shelfCount + 1),
  )

  return (
    <group>
      {/* Left and right full-height sides. */}
      {[-sideX, sideX].map((x) => (
        <mesh key={`side-${x}`} castShadow receiveShadow position={[x, height / 2, 0]} material={sidesMaterial}>
          <boxGeometry args={[boardThickness, height, depth]} />
        </mesh>
      ))}

      {/* Top and bottom sit between the two sides. */}
      {[boardThickness / 2, height - boardThickness / 2].map((y) => (
        <mesh key={`cap-${y}`} castShadow receiveShadow position={[0, y, 0]} material={topBottomMaterial}>
          <boxGeometry args={[insideLength, boardThickness, depth]} />
        </mesh>
      ))}

      {/* Three adjustable shelves, evenly distributed inside the frame. */}
      {shelfYPositions.map((y, index) => (
        <mesh key={`shelf-${index}`} castShadow receiveShadow position={[0, y, shelfDepthCenterZ]} material={shelvesMaterial}>
          <boxGeometry args={[insideLength, boardThickness, insideDepth]} />
        </mesh>
      ))}

      {/* Rear board: its outer face defines the back of the overall depth. */}
      <mesh
        castShadow
        receiveShadow
        position={[0, height / 2, -depth / 2 + backThickness / 2]}
        material={backMaterial}
      >
        <boxGeometry args={[length, height, backThickness]} />
      </mesh>
    </group>
  )
}
