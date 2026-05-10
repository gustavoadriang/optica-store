<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
  cart: { type: Object, required: true },
  shippingOptions: { type: Array, default: () => [] },
  savedAddress: { type: Object, default: null },
  countries: { type: Array, default: () => [] },
  hasDeliveryShipping: { type: Boolean, required: true },
})

const page = usePage()

// ─── Steps ────────────────────────────────────────────────────────────────────
// Always: 1 = facturación, 2 = envío, 3 = dirección (delivery only), confirm = 3 or 4
const selectedIsCollect = computed(() => {
  const opt = props.shippingOptions.find(o => o.identifier === selectedShipping.value)
  return opt?.collect ?? true
})

// Address step only exists in delivery mode when a non-collect option is chosen
const showAddressStep = computed(() =>
  props.hasDeliveryShipping && !selectedIsCollect.value && currentStep.value >= 3,
)

const confirmStep = computed(() => (showAddressStep.value ? 4 : 3))

const stepLabels = computed(() => {
  if (showAddressStep.value) return ['Facturación', 'Envío', 'Dirección', 'Pago']
  return ['Facturación', 'Envío', 'Pago']
})

const currentStep = ref(props.savedAddress ? 2 : 1)

// ─── Address form ─────────────────────────────────────────────────────────────
const address = ref({
  first_name: props.savedAddress?.first_name ?? '',
  last_name: props.savedAddress?.last_name ?? '',
  company_name: props.savedAddress?.company_name ?? '',
  line_one: props.savedAddress?.line_one ?? '',
  line_two: props.savedAddress?.line_two ?? '',
  line_three: props.savedAddress?.line_three ?? '',
  city: props.savedAddress?.city ?? '',
  state: props.savedAddress?.state ?? '',
  postcode: props.savedAddress?.postcode ?? '',
  country_id: props.savedAddress?.country_id ?? '',
  contact_email: props.savedAddress?.contact_email ?? '',
  contact_phone: props.savedAddress?.contact_phone ?? '',
  delivery_instructions: props.savedAddress?.delivery_instructions ?? '',
  shipping_is_billing: true,
})

const addressErrors = ref({})
const addressLoading = ref(false)

// ─── Shipping selection ───────────────────────────────────────────────────────
const selectedShipping = ref(props.shippingOptions[0]?.identifier ?? null)
const shippingErrors = ref({})
const shippingLoading = ref(false)

// ─── Payment — Card Payment Brick ─────────────────────────────────────────────
const payLoading = ref(false)
const paymentError = ref(null)
const mpReady = ref(false)

let cardPaymentBrickController = null

// ─── Cart totals (may update after shipping chosen) ───────────────────────────
const cartTotal = ref(props.cart.total)
const cartSubTotal = ref(props.cart.sub_total)

const selectedShippingOption = computed(() =>
  props.shippingOptions.find(o => o.identifier === selectedShipping.value),
)

// ─── CSRF helper ──────────────────────────────────────────────────────────────
function getCsrf() {
  return decodeURIComponent(
    document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
  )
}

async function jsonPost(url, data) {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrf(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(data),
    credentials: 'same-origin',
  })
  const json = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw { status: response.status, data: json }
  }
  return json
}

// ─── Step 1: save billing/contact info ───────────────────────────────────────
async function submitBilling() {
  addressErrors.value = {}
  addressLoading.value = true
  try {
    await jsonPost('/checkout/address', {
      first_name: address.value.first_name,
      last_name: address.value.last_name,
      contact_email: address.value.contact_email,
      contact_phone: address.value.contact_phone,
      save_type: 'contact',
    })
    currentStep.value = 2
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      addressErrors.value = err.data.errors
    } else {
      addressErrors.value = { _general: ['Error al guardar los datos. Intentá de nuevo.'] }
    }
  } finally {
    addressLoading.value = false
  }
}

// ─── Step 2: save shipping option ────────────────────────────────────────────
async function submitShipping() {
  shippingErrors.value = {}
  shippingLoading.value = true
  try {
    await jsonPost('/checkout/shipping', { identifier: selectedShipping.value })
    // collect (retiro) → skip address, go to confirm (step 3)
    // delivery → show address form (step 3, which maps to address panel)
    currentStep.value = 3
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      shippingErrors.value = err.data.errors
    } else {
      shippingErrors.value = { _general: ['Error al seleccionar el envío. Intentá de nuevo.'] }
    }
  } finally {
    shippingLoading.value = false
  }
}

// ─── Step 3: save delivery address (only for non-collect options) ─────────────
async function submitAddress() {
  addressErrors.value = {}
  addressLoading.value = true
  try {
    await jsonPost('/checkout/address', { ...address.value, save_type: 'delivery' })
    currentStep.value = 4
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      addressErrors.value = err.data.errors
    } else {
      addressErrors.value = { _general: ['Error al guardar la dirección. Intentá de nuevo.'] }
    }
  } finally {
    addressLoading.value = false
  }
}

// ─── Card Payment Brick (designed for Orders API) ────────────────────────────

const mpPublicKey = computed(() => page.props.mpPublicKey)

function loadMpSdk() {
  return new Promise((resolve, reject) => {
    if (window.MercadoPago) {
      resolve()
      return
    }
    const script = document.createElement('script')
    script.src = 'https://sdk.mercadopago.com/js/v2'
    script.onload = resolve
    script.onerror = reject
    document.head.appendChild(script)
  })
}

async function mountCardPaymentBrick() {
  paymentError.value = null

  if (!mpPublicKey.value) {
    paymentError.value = 'Clave pública de MercadoPago no disponible.'
    return
  }

  try {
    await loadMpSdk()

    const mp = new window.MercadoPago(mpPublicKey.value, { locale: 'es-AR' })
    const bricksBuilder = mp.bricks()

    cardPaymentBrickController = await bricksBuilder.create(
      'cardPayment',
      'cardPaymentBrick_container',
      {
        initialization: {
          amount: props.cart.total_raw,
          payer: {
            email: address.value.contact_email || '',
          },
        },
        customization: {
          visual: {
            style: {
              theme: 'custom',
              customVariables: {
                'base-color': '#000000',
                'button-text-color': '#ffffff'
              }
            }
          },
          paymentMethods: {
            creditCard: 'all',
            debitCard: 'all',
          },
        },
        callbacks: {
          onReady: () => {
            mpReady.value = true
          },
          onSubmit: (formData, additionalData) => {
            payLoading.value = true
            paymentError.value = null

            return new Promise((resolve) => {
              jsonPost('/checkout/payment', {
                token: formData.token,
                payment_method_id: formData.payment_method_id,
                installments: formData.installments,
                payment_type_id: additionalData?.paymentTypeId ?? 'credit_card',
                payer: {
                  email: formData.payer?.email || address.value.contact_email,
                  identification: formData.payer?.identification || {},
                },
              })
                .then((result) => {
                  paymentError.value = null
                  router.visit(`/checkout/success?order=${encodeURIComponent(result.reference)}`)
                  resolve()
                })
                .catch((err) => {
                  if (err.status === 422) {
                    paymentError.value = err.data?.message ?? 'Pago rechazado. Verificá los datos de tu tarjeta.'
                  } else {
                    paymentError.value = err.data?.message ?? 'Error al procesar el pago. Intentá de nuevo más tarde.'
                  }
                  resolve() // always resolve so Brick re-enables the button
                })
                .finally(() => {
                  payLoading.value = false
                })
            })
          },
          onError: (error) => {
            console.error('Card Payment Brick error:', error)
            paymentError.value = 'Error en el formulario de pago. Intentá de nuevo.'
          },
        },
      },
    )
  } catch (err) {
    console.error('Error mounting Card Payment Brick:', err)
    paymentError.value = 'No se pudo cargar el formulario de pago.'
  }
}

function destroyBrick() {
  if (cardPaymentBrickController) {
    try { cardPaymentBrickController.unmount() } catch { /* ignore */ }
    cardPaymentBrickController = null
  }
  mpReady.value = false
}

watch(currentStep, (step) => {
  if (step === confirmStep.value && !cardPaymentBrickController) {
    setTimeout(() => mountCardPaymentBrick(), 100)
  } else if (step !== confirmStep.value && cardPaymentBrickController) {
    destroyBrick()
    paymentError.value = null
  }
})

onUnmounted(() => {
  destroyBrick()
})
</script>

<template>
  <section class="bg-gray-50 py-12 lg:py-24">
    <div class="max-w-screen-xl px-4 mx-auto sm:px-6 lg:px-8">

      <!-- Page title -->
      <div class="mb-12">
        <h1 class="text-4xl font-black text-gray-900 uppercase tracking-tighter italic">
          Checkout
          <span class="block text-[10px] font-black text-primary-500 uppercase tracking-[0.3em] mt-2 not-italic">
            Finalizá tu compra de forma segura
          </span>
        </h1>
      </div>

      <div class="grid grid-cols-1 gap-12 lg:grid-cols-3 lg:items-start">

        <!-- Order summary sidebar -->
        <div class="px-8 py-10 space-y-6 bg-white border border-gray-100 lg:sticky lg:top-32 rounded-2xl shadow-sm lg:order-last">
          <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 flex items-center gap-2">
            Resumen del Pedido
            <span class="h-1 w-1 rounded-full bg-primary-500" />
          </h3>

          <div class="flow-root">
            <div class="-my-6 divide-y divide-gray-50">
              <div
                v-for="line in cart.lines"
                :key="line.id"
                class="flex items-center py-6"
              >
                <img
                  v-if="line.thumbnail"
                  :src="line.thumbnail"
                  class="object-cover w-16 h-16 rounded-xl shadow-sm"
                  alt=""
                >
                <div
                  v-else
                  class="w-16 h-16 rounded-xl bg-gray-100 shadow-sm"
                />

                <div class="flex-1 ml-4">
                  <p class="text-xs font-bold text-gray-900 leading-tight max-w-[25ch]">
                    {{ line.description }}
                  </p>
                  <div class="flex items-center justify-between mt-2">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                      Cant. {{ line.quantity }}
                    </span>
                    <span class="text-[10px] font-black text-primary-500">
                      {{ line.sub_total }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flow-root pt-6 mt-6 border-t border-gray-100">
            <dl class="-my-3 text-[10px] uppercase tracking-widest font-bold divide-y divide-gray-50">
              <div class="flex flex-wrap py-3 items-center justify-between">
                <dt class="text-gray-400">Sub Total</dt>
                <dd class="text-gray-900 font-black">{{ cart.sub_total }}</dd>
              </div>

              <div
                v-if="selectedShippingOption && currentStep >= confirmStep"
                class="flex flex-wrap py-3 items-center justify-between"
              >
                <dt class="text-gray-400">Envío ({{ selectedShippingOption.name }})</dt>
                <dd class="text-gray-900 font-black">{{ selectedShippingOption.price }}</dd>
              </div>

              <div
                v-for="(tax, i) in cart.tax_breakdown"
                :key="i"
                class="flex flex-wrap py-3 items-center justify-between"
              >
                <dt class="text-gray-400">{{ tax.description }}</dt>
                <dd class="text-gray-900 font-black">{{ tax.price }}</dd>
              </div>

              <div class="flex flex-wrap pt-6 mt-3 items-center justify-between border-t-2 border-gray-900">
                <dt class="text-sm font-black text-gray-900">TOTAL</dt>
                <dd class="text-lg font-black text-primary-500">{{ cart.total }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Steps -->
        <div class="space-y-8 lg:col-span-2">

          <!-- Step indicator -->
          <div class="flex items-center gap-3 mb-4">
            <div
              v-for="(label, idx) in stepLabels"
              :key="idx"
              class="flex items-center gap-2"
            >
              <span
                :class="[
                  'w-6 h-6 rounded-full text-[9px] font-black flex items-center justify-center transition-colors',
                  currentStep === idx + 1
                    ? 'bg-black text-white'
                    : currentStep > idx + 1
                      ? 'bg-primary-500 text-white'
                      : 'bg-gray-100 text-gray-400',
                ]"
              >{{ idx + 1 }}</span>
              <span
                :class="[
                  'text-[9px] font-black uppercase tracking-widest transition-colors',
                  currentStep === idx + 1 ? 'text-gray-900' : 'text-gray-400',
                ]"
              >{{ label }}</span>
              <span v-if="idx < stepLabels.length - 1" class="h-px w-6 bg-gray-200" />
            </div>
          </div>

          <!-- ─── Step 1: Facturación ───────────────────────────────────────── -->
          <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between h-16 px-8 border-b border-gray-50 bg-gray-50/50">
              <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 flex items-center gap-2">
                Datos de Facturación
                <span class="h-1 w-1 rounded-full bg-primary-500" />
              </h3>
              <button
                v-if="currentStep > 1"
                type="button"
                class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-black transition-colors flex items-center gap-1 group"
                @click="currentStep = 1"
              >
                Editar
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
            </div>

            <div v-if="currentStep === 1" class="p-8">
              <div v-if="addressErrors._general" class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-[10px] font-bold text-red-600 uppercase tracking-widest">
                {{ addressErrors._general[0] }}
              </div>
              <form class="grid grid-cols-6 gap-6" @submit.prevent="submitBilling">
                <div class="col-span-3">
                  <AppInput v-model="address.first_name" label="Nombre *" type="text" :error="addressErrors.first_name?.[0]" />
                </div>
                <div class="col-span-3">
                  <AppInput v-model="address.last_name" label="Apellido *" type="text" :error="addressErrors.last_name?.[0]" />
                </div>
                <div class="col-span-6 sm:col-span-3">
                  <AppInput v-model="address.contact_email" label="Email *" type="email" :error="addressErrors.contact_email?.[0]" />
                </div>
                <div class="col-span-6 sm:col-span-3">
                  <AppInput v-model="address.contact_phone" label="Teléfono" type="tel" />
                </div>
                <div class="col-span-6 text-right">
                  <AppButton type="submit" variant="secondary" size="lg" :disabled="addressLoading" class="ml-auto">
                    {{ addressLoading ? 'Guardando...' : 'Continuar' }}
                  </AppButton>
                </div>
              </form>
            </div>

            <div v-else-if="currentStep > 1" class="p-8">
              <dl class="grid grid-cols-1 gap-4 text-[10px] uppercase tracking-widest font-bold sm:grid-cols-3">
                <div>
                  <dt class="text-gray-400">Nombre</dt>
                  <dd class="mt-1 text-gray-900 font-black">{{ address.first_name }} {{ address.last_name }}</dd>
                </div>
                <div v-if="address.contact_email">
                  <dt class="text-gray-400">Email</dt>
                  <dd class="mt-1 text-gray-900 font-black lowercase tracking-normal">{{ address.contact_email }}</dd>
                </div>
                <div v-if="address.contact_phone">
                  <dt class="text-gray-400">Teléfono</dt>
                  <dd class="mt-1 text-gray-900 font-black">{{ address.contact_phone }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- ─── Step 2: Método de Envío (always shown) ────────────────────── -->
          <div v-if="currentStep >= 2" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between h-16 px-8 border-b border-gray-50 bg-gray-50/50">
              <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 flex items-center gap-2">
                Método de Envío
                <span class="h-1 w-1 rounded-full bg-primary-500" />
              </h3>
              <button
                v-if="currentStep > 2"
                type="button"
                class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-black transition-colors flex items-center gap-1 group"
                @click="currentStep = 2"
              >
                Editar
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
            </div>

            <div v-if="currentStep === 2" class="p-8">
              <div v-if="shippingErrors._general" class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-[10px] font-bold text-red-600 uppercase tracking-widest">
                {{ shippingErrors._general[0] }}
              </div>
              <div v-if="shippingOptions.length === 0" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                No hay opciones de envío disponibles.
              </div>
              <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-for="option in shippingOptions" :key="option.identifier">
                  <input :id="option.identifier" v-model="selectedShipping" class="hidden peer" type="radio" :value="option.identifier" name="shippingOption">
                  <label
                    :for="option.identifier"
                    class="flex items-center justify-between p-5 text-[10px] font-black uppercase tracking-widest border border-gray-100 rounded-xl shadow-sm cursor-pointer peer-checked:border-primary-500 hover:bg-gray-50 peer-checked:ring-2 peer-checked:ring-primary-500/20 transition-all duration-300"
                  >
                    <p class="text-gray-900">{{ option.name }}</p>
                    <p class="text-primary-500">{{ option.price }}</p>
                  </label>
                </div>
              </div>
              <p v-if="shippingErrors.identifier" class="mt-4 text-[10px] font-bold text-red-600 uppercase tracking-widest">
                {{ shippingErrors.identifier[0] }}
              </p>
              <div class="mt-10 text-right">
                <AppButton variant="secondary" size="lg" :disabled="shippingLoading || !selectedShipping" class="ml-auto" @click="submitShipping">
                  {{ shippingLoading ? 'Procesando...' : 'Continuar' }}
                </AppButton>
              </div>
            </div>

            <div v-else-if="currentStep > 2 && selectedShippingOption" class="p-8">
              <dl class="flex flex-wrap max-w-xs text-[10px] font-black uppercase tracking-widest">
                <dt class="w-1/2 text-gray-400">{{ selectedShippingOption.name }}</dt>
                <dd class="w-1/2 text-right text-primary-500">{{ selectedShippingOption.price }}</dd>
              </dl>
            </div>
          </div>

          <!-- ─── Step 3: Dirección de Entrega (solo si eligió domicilio) ──── -->
          <div v-if="showAddressStep" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between h-16 px-8 border-b border-gray-50 bg-gray-50/50">
              <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 flex items-center gap-2">
                Dirección de Entrega
                <span class="h-1 w-1 rounded-full bg-primary-500" />
              </h3>
              <button
                v-if="currentStep > 3"
                type="button"
                class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-black transition-colors flex items-center gap-1 group"
                @click="currentStep = 3"
              >
                Editar
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
              </button>
            </div>

            <div v-if="currentStep === 3" class="p-8">
              <div v-if="addressErrors._general" class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-[10px] font-bold text-red-600 uppercase tracking-widest">
                {{ addressErrors._general[0] }}
              </div>
              <form class="grid grid-cols-6 gap-6" @submit.prevent="submitAddress">
                <div class="col-span-3">
                  <AppInput v-model="address.company_name" label="Empresa (Opcional)" type="text" />
                </div>
                <div class="col-span-3" />
                <div class="col-span-3 sm:col-span-2">
                  <AppInput v-model="address.line_one" label="Dirección *" type="text" :error="addressErrors.line_one?.[0]" />
                </div>
                <div class="col-span-3 sm:col-span-2">
                  <AppInput v-model="address.line_two" label="Piso/Depto" type="text" />
                </div>
                <div class="col-span-3 sm:col-span-2">
                  <AppInput v-model="address.line_three" label="Referencia" type="text" />
                </div>
                <div class="col-span-3 sm:col-span-2">
                  <AppInput v-model="address.city" label="Ciudad *" type="text" :error="addressErrors.city?.[0]" />
                </div>
                <div class="col-span-3 sm:col-span-2">
                  <AppInput v-model="address.state" label="Provincia" type="text" />
                </div>
                <div class="col-span-3 sm:col-span-2">
                  <AppInput v-model="address.postcode" label="Cód. Postal *" type="text" :error="addressErrors.postcode?.[0]" />
                </div>
                <div class="col-span-6">
                  <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1">País *</label>
                  <select v-model="address.country_id" required class="w-full px-4 py-3 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-gray-50 outline-none" :class="{ 'border-red-400': addressErrors.country_id }">
                    <option value="">Seleccionar país</option>
                    <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
                  </select>
                  <p v-if="addressErrors.country_id" class="mt-1 text-[9px] text-red-500 font-bold">{{ addressErrors.country_id[0] }}</p>
                </div>
                <div class="col-span-6 text-right">
                  <AppButton type="submit" variant="secondary" size="lg" :disabled="addressLoading" class="ml-auto">
                    {{ addressLoading ? 'Guardando...' : 'Guardar Dirección' }}
                  </AppButton>
                </div>
              </form>
            </div>

            <div v-else-if="currentStep > 3" class="p-8">
              <dl class="text-[10px] uppercase tracking-widest font-bold">
                <dt class="text-gray-400">Dirección</dt>
                <dd class="mt-1 text-gray-900 font-black">
                  {{ address.line_one }}<br>
                  <template v-if="address.line_two">{{ address.line_two }}<br></template>
                  <template v-if="address.city">{{ address.city }}, </template>
                  <template v-if="address.state">{{ address.state }} </template>
                  CP: {{ address.postcode }}
                </dd>
              </dl>
            </div>
          </div>

          <!-- ─── Payment step: Card Payment Brick ────────────────────────────── -->
          <div v-if="currentStep >= confirmStep" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="h-16 px-8 border-b border-gray-50 bg-gray-50/50 flex items-center justify-between">
              <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 flex items-center gap-2">
                Pago
                <span class="h-1 w-1 rounded-full bg-primary-500" />
              </h3>
              <span v-if="!mpReady" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest animate-pulse">
                Cargando formulario…
              </span>
            </div>

            <div class="p-8">
              <div
                v-if="paymentError"
                class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-[10px] font-bold text-red-600 uppercase tracking-widest"
              >
                {{ paymentError }}
              </div>

              <!-- Card Payment Brick renders its own form -->
              <div id="cardPaymentBrick_container" />
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>
</template>
