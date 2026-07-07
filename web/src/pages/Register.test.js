import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../services/api'
import Register from './Register.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
    ],
  })
}

async function mountPage() {
  const router = makeRouter()
  await router.push('/register')
  await router.isReady()
  const wrapper = mount(Register, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          stubActions: false,
          initialState: { auth: { user: null, checked: true } },
        }),
      ],
    },
  })
  return { wrapper, router }
}

async function fillForm(wrapper) {
  await wrapper.find('input[type="text"]').setValue('Ada Lovelace')
  await wrapper.find('input[type="email"]').setValue('ada@example.com')
  const passwords = wrapper.findAll('input[type="password"]')
  await passwords[0].setValue('secret123')
  await passwords[1].setValue('secret123')
  const orgInputs = wrapper.findAll('input[type="text"]')
  await orgInputs[1].setValue('Analytical Engines')
}

beforeEach(() => vi.clearAllMocks())

describe('Register', () => {
  it('renders the sign up form with a link back to login', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('NightOwl')
    expect(wrapper.text()).toContain('Sign up')
    expect(wrapper.text()).toContain('Already have an account?')
    expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
  })

  it('registers and redirects to the org dashboard on success', async () => {
    api.post.mockResolvedValue({})
    api.get.mockResolvedValue({ data: { user: { email: 'ada@example.com' } } })
    const { wrapper, router } = await mountPage()
    const push = vi.spyOn(router, 'push')

    await fillForm(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/register', {
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      password: 'secret123',
      password_confirmation: 'secret123',
      org_name: 'Analytical Engines',
    })
    expect(push).toHaveBeenCalledWith('/')
  })

  it('shows a validation error banner on failure', async () => {
    api.post.mockRejectedValue({ response: { data: { errors: { org_name: ['The org name field is required.'] } } } })
    const { wrapper } = await mountPage()

    await fillForm(wrapper)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The org name field is required.')
  })
})
