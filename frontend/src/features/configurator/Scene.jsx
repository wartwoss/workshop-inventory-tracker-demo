import { OrbitControls, PerspectiveCamera } from '@react-three/drei'
import { Canvas } from '@react-three/fiber'
import { Suspense } from 'react'
import { BedsideModel } from './models/BedsideModel'
import { BookshelfModel } from './models/BookshelfModel'
import { CustomModel } from './models/CustomModel'
import { OfficeDeskModel } from './models/OfficeDeskModel'
import { TVStandModel } from './models/TVStandModel'
import { TableModel } from './models/TableModel'

export function Scene({ model, dimensions, partMaterials, customParts, selectedPartCode, onSelectPart }) {
  const objectLength = Math.max(0.8, (Number(dimensions?.length) || 1200) / 1000)
  const objectWidth = Math.max(0.8, (Number(dimensions?.width) || 700) / 1000)
  const objectHeight = Math.max(0.8, (Number(dimensions?.height) || 1000) / 1000)
  const modelCenterY = objectHeight / 2
  const cameraDistance = Math.max(1.8, Math.max(objectLength, objectWidth, objectHeight) * 1.5)
  const cameraPosition = [cameraDistance * 0.75, modelCenterY + Math.max(0.8, objectHeight * 0.22), cameraDistance]

  return (
    <div className="scene-container">
      <Canvas shadows onPointerMissed={() => onSelectPart?.(null)}>
        <PerspectiveCamera makeDefault position={cameraPosition} fov={45} />
        <OrbitControls target={[0, modelCenterY, 0]} enablePan={true} enableZoom={true} enableRotate={true} />

        <ambientLight intensity={0.6} />
        <directionalLight
          position={[2, 4, 3]}
          intensity={1.2}
          castShadow
          shadow-mapSize-width={1024}
          shadow-mapSize-height={1024}
        />
        <directionalLight position={[-2, 2, -3]} intensity={0.4} />

        <Suspense fallback={null}>
          <group rotation={[0, -Math.PI / 6, 0]}>
            {model?.is_custom ? (
              <CustomModel
                parts={customParts ?? model.parts}
                partMaterials={partMaterials}
                selectedPartCode={selectedPartCode}
                onSelectPart={onSelectPart}
              />
            ) : model?.slug === 'table' ? (
              <TableModel dimensions={dimensions} partMaterials={partMaterials} />
            ) : model?.slug === 'coffee-table' ? (
              <TableModel dimensions={dimensions} partMaterials={partMaterials} variant="coffee" />
            ) : model?.slug === 'bookshelf' ? (
              <BookshelfModel dimensions={dimensions} partMaterials={partMaterials} />
            ) : model?.slug === 'office-desk' ? (
              <OfficeDeskModel dimensions={dimensions} partMaterials={partMaterials} />
            ) : model?.slug === 'tv-stand' ? (
              <TVStandModel dimensions={dimensions} partMaterials={partMaterials} />
            ) : (
              <BedsideModel dimensions={dimensions} partMaterials={partMaterials} />
            )}
          </group>
        </Suspense>

        <mesh
          receiveShadow
          rotation={[-Math.PI / 2, 0, 0]}
          position={[0, 0, 0]}
          onClick={(event) => {
            event.stopPropagation()
            onSelectPart?.(null)
          }}
        >
          <planeGeometry args={[6, 6]} />
          <shadowMaterial opacity={0.15} />
        </mesh>
      </Canvas>
    </div>
  )
}
