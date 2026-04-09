import countries from '@/data/globe-countries.json'

const GLOBE_RADIUS = 1

/**
 * Convert latitude/longitude to 3D coordinates on a sphere.
 */
function latLngToVector3(lat, lng, radius = GLOBE_RADIUS) {
  const phi = (90 - lat) * (Math.PI / 180)
  const theta = (lng + 180) * (Math.PI / 180)
  const x = -(radius * Math.sin(phi) * Math.cos(theta))
  const y = radius * Math.cos(phi)
  const z = radius * Math.sin(phi) * Math.sin(theta)
  return [x, y, z]
}

/**
 * Get all country positions as 3D coordinates.
 */
export function useGlobe() {
  const countryPoints = countries.map(c => ({
    ...c,
    position: latLngToVector3(c.lat, c.lng, GLOBE_RADIUS * 1.01)
  }))

  const homeCountry = countries.find(c => c.home)
  const homePosition = homeCountry
    ? latLngToVector3(homeCountry.lat, homeCountry.lng, GLOBE_RADIUS * 1.01)
    : [0, 0, 0]

  // Generate arc points between home and each country
  const arcs = countryPoints
    .filter(c => !c.home)
    .map(c => ({
      from: homePosition,
      to: c.position,
      name: c.name
    }))

  return {
    countries: countryPoints,
    homePosition,
    arcs,
    GLOBE_RADIUS
  }
}
