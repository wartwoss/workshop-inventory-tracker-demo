import { usePartMaterial } from '../materials/visualMaterials'

const mmToM = (value) => Number(value || 0) / 1000
const repeatScale = (...meters) => Math.max(1, Math.round(Math.max(...meters) * 2))

/**
 * A compact writing desk with full-height side-panel supports and a lower shelf.
 * All boards fit together from the outer length/width/height dimension controls.
 */
export function OfficeDeskModel({ dimensions, partMaterials }) {
  const length = mmToM(dimensions.length)
  const depth = mmToM(dimensions.width)
  const height = mmToM(dimensions.height)

  const topThickness = 0.028
  const panelThickness = 0.018
  const shelfThickness = 0.018
  const supportHeight = Math.max(0.05, height - topThickness)
  const shelfLength = Math.max(0.04, length - panelThickness * 2)
  const shelfDepth = Math.max(0.04, depth - panelThickness * 2)

  const topMaterial = usePartMaterial(partMaterials.top, repeatScale(length, depth))
  const supportsMaterial = usePartMaterial(partMaterials.supports, repeatScale(supportHeight, depth))
  const shelfMaterial = usePartMaterial(partMaterials.shelf, repeatScale(shelfLength, shelfDepth))

  const supportX = length / 2 - panelThickness / 2
  const shelfY = Math.max(shelfThickness / 2 + panelThickness, supportHeight * 0.34)

  return (
    <group>
      {/* Broad worktop. */}
      <mesh castShadow receiveShadow position={[0, height - topThickness / 2, 0]} material={topMaterial}>
        <boxGeometry args={[length, topThickness, depth]} />
      </mesh>

      {/* Solid side-panel supports keep this desk visually distinct from a four-leg table. */}
      {[-supportX, supportX].map((x) => (
        <mesh key={`support-${x}`} castShadow receiveShadow position={[x, supportHeight / 2, 0]} material={supportsMaterial}>
          <boxGeometry args={[panelThickness, supportHeight, depth]} />
        </mesh>
      ))}

      {/* Lower storage shelf fitted between the two side supports. */}
      <mesh castShadow receiveShadow position={[0, shelfY, 0]} material={shelfMaterial}>
        <boxGeometry args={[shelfLength, shelfThickness, shelfDepth]} />
      </mesh>
    </group>
  )
}
