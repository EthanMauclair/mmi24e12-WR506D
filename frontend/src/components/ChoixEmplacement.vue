<script setup>
import { useI18n } from 'vue-i18n'

const emplacement = defineModel({ type: [Number, null], default: null })

defineProps({
  // Libellé du champ ; « Emplacement » (traduit) par défaut
  libelle: { type: String, default: '' },
  stockages: { type: Array, required: true },
  erreur: { type: String, default: '' },
})

const { t } = useI18n()
</script>

<template>
  <label class="champ">
    <span class="champ__libelle">{{ libelle || t('emplacement.libelle') }}</span>
    <select v-model="emplacement" class="saisie" :class="{ 'saisie--invalide': erreur }">
      <option :value="null">{{ t('emplacement.plusTard') }}</option>
      <option v-for="stockage in stockages" :key="stockage.id" :value="stockage.id">{{ stockage.chemin }}</option>
    </select>
    <span v-if="erreur" class="champ__erreur">{{ erreur }}</span>
  </label>
</template>
