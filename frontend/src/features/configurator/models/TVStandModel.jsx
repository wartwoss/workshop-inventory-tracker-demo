import { usePartMaterial } from '../materials/visualMaterials'

const mmToM = (value) => Number(value || 0) / 1000
const repeatScale = (...meters) => Math.max(1, Math.round(Math.max(...meters) * 2))

/**
 * Low media console with three columns and two rows of open cubbies.
 * Shelf segments stop at the dividers rather than intersecting them.
 */
export function TVStandModel({ dimensions, partMaterials }) {
  const length = mmToM(dimensions.length)
  const depth = mmToM(dimensions.width)
  const height = mmToM(dimensions.height)

  const boardThickness = 0.018
  const footHeight = 0.07
  const legWidth = 0.045
  const bodyHeight = Math.max(boardThickness * 3, height - footHeight)
  const interiorHeight = Math.max(boardThickness, bodyHeight - boardThickness * 2)
  const innerLength = Math.max(boardThickness * 3, length - boardThickness * 2)
  const bayWidth = Math.max(boardThickness, (innerLength - boardThickness * 2) / 3)
  const shelfDepth = depth
  const leftInside = -length / 2 + boardThickness

  const topMaterial = usePartMaterial(partMaterials.top, repeatScale(length, depth))
  const bottomMaterial = usePartMaterial(partMaterials.bottom, repeatScale(innerLength, depth))
  const sidesMaterial = usePartMaterial(partMaterials.sides, repeatScale(bodyHeight, depth))
  const shelvesMaterial = usePartMaterial(partMaterials.shelves, repeatScale(bayWidth, shelfDepth))
  const dividersMaterial = usePartMaterial(partMaterials.dividers, repeatScale(interiorHeight, depth))
  const legsMaterial = usePartMaterial(partMaterials.legs, 1)

  const sideX = length / 2 - boardThickness / 2
  const sideCenterY = footHeight + boardThickness + interiorHeight / 2
  const dividerXs = [
    leftInside + bayWidth + boardThickness / 2,
    leftInside + bayWidth * 2 + boardThickness * 1.5,
  ]
  const bayCenters = [0, 1, 2].map((index) =>
    leftInside + bayWidth / 2 + index * (bayWidth + boardThickness),
  )
  const shelfYs = [1, 2].map((level) =>
    footHeight + boardThickness + (interiorHeight * level) / 3,
  )

  const legInsetX = Math.min(0.045, length * 0.08)
  const legInsetZ = Math.min(0.025, depth * 0.08)
  const legX = Math.max(legWidth / 2, length / 2 - legWidth / 2 - legInsetX)
  const legZ = Math.max(legWidth / 2, depth / 2 - legWidth / 2 - legInsetZ)
  const legs = [
    [legX, footHeight / 2, legZ],
    [-legX, footHeight / 2, legZ],
    [legX, footHeight / 2, -legZ],
    [-legX, footHeight / 2, -legZ],
  ]

  return (
    <group>
      {/* Full-length top and fitted bottom panel. */}
      <mesh castShadow receiveShadow position={[0, height - boardThickness / 2, 0]} material={topMaterial}>
        <boxGeometry args={[length, boardThickness, depth]} />
      </mesh>
      <mesh castShadow receiveShadow position={[0, footHeight + boardThickness / 2, 0]} material={bottomMaterial}>
        <boxGeometry args={[innerLength, boardThickness, depth]} />
      </mesh>

      {/* Side panels fit between the top and bottom. */}
      {[-sideX, sideX].map((x) => (
        <mesh key={`side-${x}`} castShadow receiveShadow position={[x, sideCenterY, 0]} material={sidesMaterial}>
          <boxGeometry args={[boardThickness, interiorHeight, depth]} />
        </mesh>
      ))}

      {/* Two vertical dividers split the console into three columns. */}
      {dividerXs.map((x) => (
        <mesh key={`divider-${x}`} castShadow receiveShadow position={[x, sideCenterY, 0]} material={dividersMaterial}>
          <boxGeometry args={[boardThickness, interiorHeight, depth]} />
        </mesh>
      ))}

      {/* Six shelf segments fit neatly between the sides and dividers. */}
      {shelfYs.flatMap((y, rowIndex) => bayCenters.map((x, columnIndex) => (
        <mesh
          key={`shelf-${rowIndex}-${columnIndex}`}
          castShadow
          receiveShadow
          position={[x, y, 0]}
          material={shelvesMaterial}
        >
          <boxGeometry args={[bayWidth, boardThickness, shelfDepth]} />
        </mesh>
      )))}

      {legs.map((position, index) => (
        <mesh key={`leg-${index}`} castShadow receiveShadow position={position} material={legsMaterial}>
          <boxGeometry args={[legWidth, footHeight, legWidth]} />
        </mesh>
      ))}
    </group>
  )
}
