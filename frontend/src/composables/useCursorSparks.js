/**
 * Gold spark particle system that follows the cursor.
 * Spawns small gold particles at cursor position within a container.
 * Particles fade out and fall with gravity.
 */
export function useCursorSparks(options = {}) {
  const {
    maxParticles = 20,
    spawnRate = 3,         // particles per mousemove event
    lifetime = 800,        // ms before particle recycles
    gravity = 0.15,        // downward acceleration per frame
    spread = 20,           // random velocity spread (px)
    sizeMin = 2,
    sizeMax = 6
  } = options

  let container = null
  let pool = []
  let rafId = null
  let lastSpawnTime = 0
  const SPAWN_THROTTLE = 40 // ms between spawns

  function createParticle() {
    const el = document.createElement('div')
    el.style.cssText = `
      position: absolute;
      border-radius: 50%;
      pointer-events: none;
      will-change: transform, opacity;
      background: radial-gradient(circle, #D4A843 0%, rgba(212, 168, 67, 0.4) 60%, transparent 100%);
      box-shadow: 0 0 4px rgba(212, 168, 67, 0.3);
    `
    return {
      el,
      x: 0, y: 0,
      vx: 0, vy: 0,
      size: 0,
      opacity: 1,
      born: 0,
      active: false
    }
  }

  function initPool() {
    pool = []
    for (let i = 0; i < maxParticles; i++) {
      const p = createParticle()
      container.appendChild(p.el)
      p.el.style.display = 'none'
      pool.push(p)
    }
  }

  function spawn(clientX, clientY) {
    const rect = container.getBoundingClientRect()
    const x = clientX - rect.left
    const y = clientY - rect.top

    let spawned = 0
    for (let i = 0; i < pool.length && spawned < spawnRate; i++) {
      const p = pool[i]
      if (p.active) continue

      const size = sizeMin + Math.random() * (sizeMax - sizeMin)
      p.x = x - size / 2
      p.y = y - size / 2
      p.vx = (Math.random() - 0.5) * spread
      p.vy = (Math.random() - 0.8) * spread // bias upward
      p.size = size
      p.opacity = 0.8 + Math.random() * 0.2
      p.born = performance.now()
      p.active = true

      p.el.style.display = 'block'
      p.el.style.width = `${size}px`
      p.el.style.height = `${size}px`

      spawned++
    }
  }

  function animate(now) {
    let anyActive = false

    for (const p of pool) {
      if (!p.active) continue
      anyActive = true

      const age = now - p.born
      if (age > lifetime) {
        p.active = false
        p.el.style.display = 'none'
        continue
      }

      p.vy += gravity
      p.x += p.vx
      p.y += p.vy
      p.vx *= 0.98 // friction
      p.opacity = 1 - age / lifetime

      p.el.style.transform = `translate(${p.x}px, ${p.y}px)`
      p.el.style.opacity = p.opacity
    }

    if (anyActive) {
      rafId = requestAnimationFrame(animate)
    } else {
      rafId = null
    }
  }

  function onMouseMove(e) {
    const now = performance.now()
    if (now - lastSpawnTime < SPAWN_THROTTLE) return
    lastSpawnTime = now

    spawn(e.clientX, e.clientY)

    if (!rafId) {
      rafId = requestAnimationFrame(animate)
    }
  }

  function init(containerEl) {
    if (!containerEl) return
    container = containerEl
    initPool()
    window.addEventListener('mousemove', onMouseMove, { passive: true })
  }

  function destroy() {
    window.removeEventListener('mousemove', onMouseMove)
    if (rafId) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
    for (const p of pool) {
      p.el.remove()
    }
    pool = []
    container = null
  }

  return { init, destroy }
}
