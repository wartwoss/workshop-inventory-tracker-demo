import { usePartMaterial } from '../materials/visualMaterials'

function toMeters(mm) {
  return mm / 1000
}

function panelRepeatScale(lengthM, widthM) {
  return Math.max(1, Math.round(Math.max(lengthM, widthM) * 2))
}

export function TableModel({ dimensions, partMaterials, variant = 'dining' }) {
  const { length: lMm, width: wMm, height: hMm } = dimensions

  const length = toMeters(lMm)
  const width = toMeters(wMm)
  const height = toMeters(hMm)

  const isCoffeeTable = variant === 'coffee'
  const topThickness = isCoffeeTable ? 0.025 : 0.03
  const legWidth = isCoffeeTable ? 0.04 : 0.05
  const legHeight = Math.max(0.01, height - topThickness)

  const topMaterial = usePartMaterial(partMaterials.top, panelRepeatScale(length, width))
  const legsMaterial = usePartMaterial(partMaterials.legs, 1)

  const legInset = Math.min(0.05, length * 0.12, width * 0.12)
  const legOffsetX = Math.max(legWidth / 2, length / 2 - legWidth / 2 - legInset)
  const legOffsetZ = Math.max(legWidth / 2, width / 2 - legWidth / 2 - legInset)

  const legPositions = [
    [legOffsetX, legHeight / 2, legOffsetZ],
    [-legOffsetX, legHeight / 2, legOffsetZ],
    [legOffsetX, legHeight / 2, -legOffsetZ],
    [-legOffsetX, legHeight / 2, -legOffsetZ],
  ]

  return (
    <group>
      <mesh
        castShadow
        receiveShadow
        position={[0, height - topThickness / 2, 0]}
        material={topMaterial}
      >
        <boxGeometry args={[length, topThickness, width]} />
      </mesh>

      {legPositions.map((position, index) => (
        <mesh key={index} castShadow receiveShadow position={position} material={legsMaterial}>
          <boxGeometry args={[legWidth, legHeight, legWidth]} />
        </mesh>
      ))}
    </group>
  )
}
