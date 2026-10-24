import { useEffect, useMemo, useState } from 'react'
import * as THREE from 'three'

/**
 * Explicit visual mapping for workshop materials.
 *
 * Appearance is driven by the backend's `color_hex`, `texture_url`, and
 * optional `visual_key` field and is kept completely separate from inventory
 * calculations.
 */
const VISUAL_KEY_MAP = {
  'oak-natural': { color: '#c49a6c', roughness: 0.65, metalness: 0.0 },
  walnut: { color: '#5c4033', roughness: 0.6, metalness: 0.0 },
  pine: { color: '#e8d4a8', roughness: 0.7, metalness: 0.0 },
  brass: { color: '#b5a642', roughness: 0.25, metalness: 0.8 },
  steel: { color: '#9ca3af', roughness: 0.35, metalness: 0.7 },
  'ceramic-white': { color: '#f5f5f5', roughness: 0.15, metalness: 0.0 },
  'fabric-grey': { color: '#7a7a7a', roughness: 0.95, metalness: 0.0 },
  glue: { color: '#ffffff', roughness: 0.9, metalness: 0.0 },
}

const GROUP_FALLBACK_MAP = {
  wood: { color: '#c49a6c', roughness: 0.65, metalness: 0.0 },
  metal: { color: '#9ca3af', roughness: 0.35, metalness: 0.7 },
  ceramic: { color: '#f5f5f5', roughness: 0.15, metalness: 0.0 },
  upholstery: { color: '#7a7a7a', roughness: 0.95, metalness: 0.0 },
  finish: { color: '#e5e5e5', roughness: 0.8, metalness: 0.0 },
  hardware: { color: '#b5a642', roughness: 0.25, metalness: 0.8 },
}

const DEFAULT_PROPS = { color: '#e5e5e5', roughness: 0.8, metalness: 0.0 }

export function getMaterialProps(material) {
  if (!material) {
    return DEFAULT_PROPS
  }

  if (material.color_hex && /^#[0-9A-Fa-f]{6}$/.test(material.color_hex)) {
    return { color: material.color_hex, roughness: 0.7, metalness: 0.0 }
  }

  const key = material.visual_key?.toLowerCase()

  if (key && VISUAL_KEY_MAP[key]) {
    return VISUAL_KEY_MAP[key]
  }

  const group = material.material_group?.toLowerCase()

  if (group && GROUP_FALLBACK_MAP[group]) {
    return GROUP_FALLBACK_MAP[group]
  }

  return DEFAULT_PROPS
}

const textureCache = new Map()

function configureTexture(texture, repeatScale) {
  texture.colorSpace = THREE.SRGBColorSpace
  texture.wrapS = THREE.RepeatWrapping
  texture.wrapT = THREE.RepeatWrapping
  texture.repeat.set(repeatScale, repeatScale)
  texture.anisotropy = 16
}

/**
 * Load and configure a texture, using an in-memory cache so the same image is
 * only loaded once even when used by multiple parts.
 */
function loadTexture(url, repeatScale, onLoad, onError) {
  if (textureCache.has(url)) {
    const cached = textureCache.get(url)
    configureTexture(cached, repeatScale)
    onLoad(cached)
    return
  }

  const loader = new THREE.TextureLoader()
  loader.load(
    url,
    (loaded) => {
      configureTexture(loaded, repeatScale)
      textureCache.set(url, loaded)
      onLoad(loaded)
    },
    undefined,
    () => {
      onError()
    },
  )
}

/**
 * Build a THREE material for a selected material.
 *
 * - Uses the solid color when no texture is available.
 * - Loads the texture asynchronously and falls back to the color on failure.
 * - Textures are cached and repeated based on `repeatScale` to avoid stretching
 *   on large wooden panels.
 * - The returned material is disposed when the selection changes or the
 *   component unmounts; cached textures are not disposed because they may be
 *   shared.
 */
export function usePartMaterial(material, repeatScale = 1) {
  const props = useMemo(() => getMaterialProps(material), [material])
  const [texture, setTexture] = useState(null)

  useEffect(() => {
    if (!material?.texture_url) {
      setTexture(null)
      return undefined
    }

    let cancelled = false

    loadTexture(
      material.texture_url,
      repeatScale,
      (loaded) => {
        if (!cancelled) {
          setTexture(loaded)
        }
      },
      () => {
        if (!cancelled) {
          setTexture(null)
        }
      },
    )

    return () => {
      cancelled = true
    }
  }, [material?.texture_url, repeatScale])

  const meshMaterial = useMemo(() => {
    return new THREE.MeshStandardMaterial({
      color: texture ? '#ffffff' : props.color,
      map: texture,
      roughness: props.roughness,
      metalness: props.metalness,
    })
  }, [props, texture])

  useEffect(() => {
    return () => {
      meshMaterial.dispose()
    }
  }, [meshMaterial])

  return meshMaterial
}
