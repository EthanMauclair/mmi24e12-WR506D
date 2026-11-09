<script setup>
import { RouterLink } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'

defineProps({
  membre: { type: Object, required: true },
})
</script>

<template>
  <li class="ligne">
    <RouterLink :to="{ name: 'membre', params: { pseudo: membre.pseudo } }" class="ligne__lien">
      <AvatarMembre :membre="membre" :taille="44" />
      <span class="ligne__textes">
        <span class="ligne__pseudo">
          {{ membre.pseudo }}
          <svg v-if="membre.estPrive" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Profil privé"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
        </span>
        <span v-if="membre.nomComplet" class="ligne__nom">{{ membre.nomComplet }}</span>
      </span>
    </RouterLink>
    <!-- Actions propres à la liste (accepter une demande, choisir comme ami…) -->
    <slot />
  </li>
</template>

<style scoped>
.ligne {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ligne__lien {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--couleur-texte);
}

.ligne__textes {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.ligne__pseudo {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14.5px;
  font-weight: 800;
  overflow-wrap: anywhere;
}

.ligne__nom {
  font-size: 13px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}
</style>
