<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/auth'

const auth = useAuthStore()
const router = useRouter()

const inputClass =
  'mb-4 w-full rounded border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100'

const form = reactive({
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
  orgName: '',
  error: null,
  submitting: false,
})

async function submit() {
  form.error = null
  form.submitting = true

  try {
    await auth.register(form.name, form.email, form.password, form.passwordConfirmation, form.orgName)
    router.push('/')
  } catch (e) {
    const errors = e.response?.data?.errors
    form.error =
      errors?.name?.[0] ??
      errors?.email?.[0] ??
      errors?.password?.[0] ??
      errors?.org_name?.[0] ??
      'Registration failed.'
  } finally {
    form.submitting = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-950">
    <form
      class="w-full max-w-sm rounded border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-900"
      @submit.prevent="submit"
    >
      <h1 class="mb-6 text-lg font-semibold text-gray-900 dark:text-gray-100">NightOwl</h1>

      <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
      <input v-model="form.name" type="text" required :class="inputClass" />

      <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
      <input v-model="form.email" type="email" required :class="inputClass" />

      <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
      <input v-model="form.password" type="password" required :class="inputClass" />

      <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</label>
      <input v-model="form.passwordConfirmation" type="password" required :class="inputClass" />

      <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Organization name</label>
      <input v-model="form.orgName" type="text" required :class="inputClass" />

      <p v-if="form.error" class="mb-4 text-sm text-red-600 dark:text-red-400">{{ form.error }}</p>

      <button
        type="submit"
        :disabled="form.submitting"
        class="w-full rounded bg-primary-600 px-3 py-2 font-medium text-white hover:bg-primary-700 disabled:opacity-50"
      >
        {{ form.submitting ? 'Creating account…' : 'Sign up' }}
      </button>

      <p class="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
        Already have an account?
        <router-link to="/login" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
          Sign in
        </router-link>
      </p>
    </form>
  </div>
</template>
