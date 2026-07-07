import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../services/api'
import Login from './Login.vue'

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
  await router.push('/login')
  await router.isReady()
  const wrapper = mount(Login, {
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

async function fillForm(wrapper, email, password) {
  await wrapper.find('input[type="email"]').setValue(email)
  await wrapper.find('input[type="password"]').setValue(password)
}

beforeEach(() => vi.clearAllMocks())

describe('Login', () => {
  it('shows an inline required-fields error and does not call the login api when both fields are empty', async () => {
    const { wrapper } = await mountPage()

    await fillForm(wrapper, '', '')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Email and password are required.')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('shows an inline required-fields error and does not call the login api when both fields are whitespace-only', async () => {
    const { wrapper } = await mountPage()

    await fillForm(wrapper, '   ', '   ')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Email and password are required.')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('logs in with trimmed values and redirects to / on success', async () => {
    api.post.mockResolvedValue({})
    api.get.mockResolvedValue({ data: { user: { email: 'ada@example.com' } } })
    const { wrapper, router } = await mountPage()
    const push = vi.spyOn(router, 'push')

    await fillForm(wrapper, '  ada@example.com  ', '  secret123  ')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/login', { email: 'ada@example.com', password: 'secret123' })
    expect(push).toHaveBeenCalledWith('/')
  })

  it('shows the server error message when a valid submission is rejected', async () => {
    api.post.mockRejectedValue({ response: { data: { errors: { email: ['These credentials do not match our records.'] } } } })
    const { wrapper } = await mountPage()

    await fillForm(wrapper, 'ada@example.com', 'secret123')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('These credentials do not match our records.')
  })
})
