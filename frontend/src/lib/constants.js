export const STOCK_UNITS = [
  { value: 'm2', label: 'm² — square metre' },
  { value: 'm', label: 'm — linear metre' },
  { value: 'ea', label: 'ea — each' },
  { value: 'kg', label: 'kg — kilogram' },
  { value: 'g', label: 'g — gram' },
  { value: 'L', label: 'L — litre' },
  { value: 'mL', label: 'mL — millilitre' },
]

export const UNIT_VALUES = STOCK_UNITS.map((unit) => unit.value)

export const MATERIAL_GROUP_SUGGESTIONS = [
  'wood',
  'ceramic',
  'metal',
  'upholstery',
  'glass',
  'finish',
  'hardware',
]

export const MATERIAL_PURPOSES = [
  { value: 'board', label: 'Board — panels, tops, carcasses, doors' },
  { value: 'leg', label: 'Leg — table or cabinet legs and feet' },
  { value: 'handle', label: 'Handle — pulls and knobs' },
  { value: 'other', label: 'Other — screws, glue, hinges, supplies' },
]

export const MATERIAL_PURPOSE_LABELS = {
  board: 'Board',
  leg: 'Leg',
  handle: 'Handle',
  other: 'Other',
}

export const MOVEMENT_TYPE_LABELS = {
  opening: 'Opening stock',
  manual_adjustment: 'Manual adjustment',
  production: 'Production consumption',
}

export const PER_PAGE_OPTIONS = [10, 15, 25, 50]
