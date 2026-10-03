<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  cartCount: { type: Number, default: 0 },
})

const emit = defineEmits(['open-cart'])

const page = usePage()
const auth = computed(() => page.props.auth)
const navCollections = computed(() => page.props.navCollections ?? [])

const mobileMenuOpen = ref(false)

watch(mobileMenuOpen, (open) => {
  document.documentElement.style.overflow = open ? 'hidden' : ''
  document.body.style.overflow = open ? 'hidden' : ''
})


function logout() {
  router.post('/logout')
}

function closeMobileMenu() {
  mobileMenuOpen.value = false
}
</script>

<template>
  <!-- Top bar: secondary links + auth -->
  <div class="hidden lg:block bg-primary-500 text-white text-xs py-1.5 w-full relative z-[50]">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
      <!-- Secondary nav links (desktop only) -->
      <nav class="hidden lg:flex items-center gap-4">
        <a :href="route('contact.view')" class="hover:underline">Contacto</a>
        <span class="text-white/40">|</span>
        <a :href="route('refund-policy.view')" class="hover:underline">Política de Devolución</a>
        <span class="text-white/40">|</span>
        <a :href="route('faq.view')" class="hover:underline">Preguntas Frecuentes</a>
      </nav>
      <!-- Auth links -->
      <div class="flex items-center gap-2 ml-auto">
        <template v-if="auth?.user">
          <span class="flex items-center gap-1">Hola, {{ auth.user.name }}</span>
          <span class="text-white/40">|</span>
          <button type="button" class="hover:underline" @click="logout">Cerrar sesión</button>
        </template>
        <template v-else>
          <a :href="route('login')" class="hover:underline flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Iniciar sesión
          </a>
          <span class="text-white/40">|</span>
          <a :href="route('register')" class="hover:underline">Crear cuenta</a>
        </template>
      </div>
    </div>
  </div>

  <!-- Main header -->
  <header class="w-full bg-black text-white sticky top-0 z-[60]">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- Desktop: search | wordmark | cart -->
      <div class="hidden lg:grid grid-cols-3 items-center py-4">
        <!-- Search pill -->
        <form action="/search" method="GET">
          <div class="flex items-center border border-white/30 rounded-full px-4 py-2 gap-2 hover:border-white/60 transition w-fit min-w-[220px] overflow-hidden">
            <input
              type="search"
              name="q"
              placeholder="¿Qué estás buscando?"
              class="bg-transparent text-white text-sm placeholder-white/50 focus:outline-none focus:ring-0 appearance-none border-0 w-full"
            >
            <button type="submit" class="text-white/70 hover:text-white transition flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z" />
              </svg>
            </button>
          </div>
        </form>

        <!-- Wordmark centrado -->
        <div class="flex justify-center py-3">
          <a href="/" class="block text-white font-bold tracking-widest text-3xl leading-none hover:opacity-80 transition">Orquesta</a>
        </div>

        <!-- Cart -->
        <div class="flex justify-end">
          <button
            type="button"
            class="flex items-center gap-2 border border-white/30 rounded-full px-4 py-2 text-white hover:border-white/60 transition"
            @click="$emit('open-cart')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.4 5M17 13l1.4 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />
            </svg>
            <span class="text-sm">({{ cartCount }})</span>
          </button>
        </div>
      </div>

      <!-- Desktop collections nav -->
      <nav class="hidden lg:flex justify-center gap-8 text-sm font-medium text-white/80 border-t border-white/10 py-3">
        <a
          v-for="col in navCollections"
          :key="col.slug"
          :href="route('collection.view', { slug: col.slug })"
          class="hover:text-primary-500 transition"
        >
          {{ col.name }}
        </a>
      </nav>

      <!-- Mobile: hamburger | wordmark | search + cart -->
      <div class="lg:hidden grid grid-cols-3 items-center py-2">
        <!-- Left: Hamburger -->
        <div class="flex">
          <button
            type="button"
            class="flex items-center justify-center w-10 h-10 text-white hover:text-primary-500 transition"
            @click="mobileMenuOpen = true"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>

        <!-- Center: Wordmark -->
        <div class="flex justify-center py-4">
          <a href="/" class="block text-white font-bold tracking-widest text-2xl leading-none hover:opacity-80 transition">Orquesta</a>
        </div>

        <!-- Right: Cart -->
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="relative flex items-center justify-center w-10 h-10 text-white hover:text-primary-500 transition"
            @click="$emit('open-cart')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.4 5M17 13l1.4 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />
            </svg>
            <span
              v-if="cartCount > 0"
              class="absolute -top-1 -right-1 bg-primary-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center"
            >{{ cartCount }}</span>
          </button>
        </div>
      </div>

      <!-- Mobile search — always visible, slim -->
      <div class="lg:hidden pb-3">
        <form action="/search" method="GET">
          <div class="flex items-center border border-white/40 rounded-full px-4 py-1 gap-2 overflow-hidden">
            <input
              type="search"
              name="q"
              placeholder="¿Qué estás buscando?"
              class="bg-transparent text-white text-sm placeholder-white/70 focus:outline-none focus:ring-0 appearance-none border-0 w-full"
            >
            <button type="submit" class="text-white hover:text-primary-500 transition flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z" />
              </svg>
            </button>
          </div>
        </form>
      </div>
    </div>
  </header>

  <!-- Mobile sidebar menu -->
  <Teleport to="body">
    <!-- Backdrop -->
    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="mobileMenuOpen"
        class="fixed inset-0 bg-black/60 z-[70] lg:hidden"
        @click="closeMobileMenu"
      />
    </Transition>

    <!-- Sidebar panel -->
    <Transition
      enter-active-class="transition-transform duration-300"
      enter-from-class="-translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-300"
      leave-from-class="translate-x-0"
      leave-to-class="-translate-x-full"
    >
      <div
        v-if="mobileMenuOpen"
        class="fixed inset-y-0 left-0 w-4/5 max-w-xs bg-neutral-950 text-white z-[80] flex flex-col overflow-hidden lg:hidden shadow-2xl"
      >
        <!-- Header -->
        <div class="shrink-0 flex items-center justify-center px-5 py-8 border-b border-white/8">
          <img :src="'/images/logo.webp'" alt="Óptica Guzmán" class="h-14 w-auto">
        </div>

        <!-- Colecciones + links secundarios (scrolleable) -->
        <nav class="flex-1 overflow-y-auto min-h-0 flex flex-col">
          <ul class="py-3">
            <li v-for="col in navCollections" :key="col.slug">
              <a
                :href="route('collection.view', { slug: col.slug })"
                class="flex items-center justify-between px-5 py-3.5 text-sm font-semibold text-white/85 hover:text-white hover:bg-white/5 transition"
                @click="closeMobileMenu"
              >
                {{ col.name }}
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-white/25 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </a>
            </li>
          </ul>

          <div class="mt-auto border-t border-white/8 py-3">
            <a :href="route('contact.view')" class="flex items-center px-5 py-3 text-xs text-white/40 hover:text-white/70 hover:bg-white/5 transition" @click="closeMobileMenu">Contacto</a>
            <a :href="route('refund-policy.view')" class="flex items-center px-5 py-3 text-xs text-white/40 hover:text-white/70 hover:bg-white/5 transition" @click="closeMobileMenu">Política de Devolución</a>
            <a :href="route('faq.view')" class="flex items-center px-5 py-3 text-xs text-white/40 hover:text-white/70 hover:bg-white/5 transition" @click="closeMobileMenu">Preguntas Frecuentes</a>
          </div>
        </nav>

        <!-- Auth -->
        <div class="shrink-0 border-t border-white/8 px-5 py-5">
          <template v-if="auth?.user">
            <p class="text-xs text-white/35 mb-3">Hola, {{ auth.user.name }}</p>
            <button
              type="button"
              class="w-full py-3 text-sm text-white/60 hover:text-white border border-white/15 hover:border-white/40 rounded-lg transition"
              @click="logout"
            >
              Cerrar sesión
            </button>
          </template>
          <template v-else>
            <div class="flex flex-col gap-2.5">
              <a
                :href="route('login')"
                class="block w-full py-3 text-center text-sm font-medium text-white/80 hover:text-white border border-white/20 hover:border-white/50 rounded-lg transition"
                @click="closeMobileMenu"
              >
                Iniciar sesión
              </a>
              <a
                :href="route('register')"
                class="block w-full py-3 text-center text-sm font-bold text-black bg-primary-500 hover:bg-primary-400 rounded-lg transition"
                @click="closeMobileMenu"
              >
                Crear cuenta
              </a>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
