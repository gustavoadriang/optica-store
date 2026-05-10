<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import ProductCard from '@/Components/ProductCard.vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
  products: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
})

function changePage(page) {
  router.get(route('catalog.view'), { page, sort: filters.sort }, { preserveState: true, preserveScroll: false })
}

function changeSort(event) {
  router.get(route('catalog.view'), { sort: event.target.value }, { preserveState: true })
}
</script>

<template>
  <div class="bg-gray-50 min-h-screen">

    <!-- Page header -->
    <header class="bg-white border-b border-gray-100 py-16">
      <div class="max-w-screen-xl px-4 mx-auto sm:px-6 lg:px-8 text-center">
        <nav class="flex justify-center mb-4 text-[10px] font-bold tracking-widest uppercase text-gray-400 space-x-2">
          <a :href="route('home')" class="hover:text-primary-500 transition">Inicio</a>
          <span>/</span>
          <span class="text-gray-900">Catálogo</span>
        </nav>

        <h1 class="text-4xl font-black text-gray-900 sm:text-6xl tracking-tight uppercase">
          Nuestros <span class="text-primary-500">Productos</span>
        </h1>
        <p class="mt-4 max-w-xl mx-auto text-gray-500 text-lg font-medium">
          Calidad visual y las últimas tendencias en marcos y cristales.
        </p>
      </div>
    </header>

    <!-- Catalog body -->
    <div class="max-w-screen-xl px-4 mx-auto sm:px-6 lg:px-8 py-12">

      <!-- Controls: counter + sort -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-12 pb-6 border-b border-gray-100 gap-4">
        <div
          v-if="pagination.total"
          class="text-sm text-gray-500"
        >
          Mostrando
          <span class="text-gray-900 font-bold">{{ pagination.from }}</span>
          -
          <span class="text-gray-900 font-bold">{{ pagination.to }}</span>
          de
          <span class="text-gray-900 font-bold">{{ pagination.total }}</span>
          items
        </div>

        <div class="flex items-center gap-3">
          <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Ordenar por</span>
          <select
            :value="filters.sort"
            class="bg-white text-gray-900 text-xs border border-gray-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition"
            @change="changeSort"
          >
            <option value="newest">Novedades</option>
            <option value="price_asc">Precio: Menor a Mayor</option>
            <option value="price_desc">Precio: Mayor a Menor</option>
          </select>
        </div>
      </div>

      <!-- Product grid -->
      <div
        v-if="products.length"
        class="grid grid-cols-2 gap-x-4 gap-y-12 lg:grid-cols-4 lg:gap-x-8 lg:gap-y-16"
      >
        <ProductCard
          v-for="product in products"
          :key="product.id"
          :product="product"
        />
      </div>

      <!-- Empty state -->
      <div
        v-else
        class="py-20 text-center bg-white rounded-3xl border border-dashed border-gray-200 shadow-sm"
      >
        <p class="text-gray-500 font-medium italic">No hay productos disponibles en esta sección.</p>
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination.last_page > 1"
        class="mt-20 flex justify-center items-center gap-2"
      >
        <button
          v-for="page in pagination.last_page"
          :key="page"
          :class="[
            'w-10 h-10 text-sm font-bold rounded-full transition-all',
            page === pagination.current_page
              ? 'bg-primary-500 text-white shadow'
              : 'bg-white text-gray-600 border border-gray-200 hover:border-primary-500 hover:text-primary-500',
          ]"
          @click="changePage(page)"
        >
          {{ page }}
        </button>
      </div>
    </div>
  </div>
</template>
