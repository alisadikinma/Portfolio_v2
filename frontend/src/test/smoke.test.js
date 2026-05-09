import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/**
 * Smoke test for the Vue component test infrastructure.
 *
 * Asserts that:
 *   1. Vitest globals are available
 *   2. @vue/test-utils mount() works
 *   3. jsdom DOM environment is functional
 *
 * If this passes, the rest of the suite can rely on the same pipeline.
 */
describe('vue test infrastructure smoke', () => {
  it('mounts a component and exposes its rendered HTML', () => {
    const Hello = defineComponent({
      props: { name: { type: String, default: 'world' } },
      setup(props) {
        return () => h('p', { class: 'greeting' }, `hello ${props.name}`)
      },
    })

    const wrapper = mount(Hello, { props: { name: 'gaspol' } })

    expect(wrapper.text()).toBe('hello gaspol')
    expect(wrapper.find('p.greeting').exists()).toBe(true)
  })

  it('exposes a real DOM via jsdom', () => {
    expect(typeof document).toBe('object')
    expect(typeof window).toBe('object')

    const el = document.createElement('div')
    el.textContent = 'jsdom is alive'
    expect(el.textContent).toBe('jsdom is alive')
  })
})
