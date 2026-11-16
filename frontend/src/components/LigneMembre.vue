<script setup>
import { RouterLink } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'

/**
 * Membre dans une liste (recherche, demandes, abonnés, amis) : avatar, @pseudo, nom complet,
 * et les actions propres à la liste dans le slot.
 */
defineProps({
  membre: { type: Object, required: true },
  // Précision sous le nom (« profil privé »…)
  detail: { type: String, default: '' },
})
</script>

<template>
  <li class="ligne ligne-membre">
    <RouterLink :to="{ name: 'membre', params: { pseudo: membre.pseudo } }" class="ligne-membre__lien">
      <AvatarMembre :membre="membre" :taille="44" />
      <span class="ligne__textes">
        <b class="ligne-membre__pseudo">@{{ membre.pseudo }}</b>
        <span v-if="membre.nomComplet || detail" class="texte-secondaire">{{ [membre.nomComplet, detail].filter(Boolean).join(' · ') }}</span>
      </span>
    </RouterLink>
    <!-- Actions propres à la liste (accepter une demande, choisir comme ami…) -->
    <slot />
  </li>
</template>

<style scoped>
.ligne-membre__lien {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--couleur-texte);
}

.ligne-membre__lien:hover {
  color: var(--couleur-texte);
}

.ligne-membre__pseudo {
  overflow-wrap: anywhere;
}
</style>
