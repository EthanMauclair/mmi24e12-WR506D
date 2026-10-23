<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'

// Données d'exemple reprises de la maquette, en attendant l'API
const logement = {
  nom: 'Appartement Lyon',
  role: 'Propriétaire · 2 co-propriétaires',
  nbArticles: 248,
  nbPieces: 4,
  nbPretes: 3,
}

const meteo = 'Lyon · Pluie · 12 °C'

const pieces = [
  { id: 1, nom: 'Chambre', stockages: 'Armoire · Commode · Sous le lit', nbArticles: 112, fond: 'var(--couleur-pastel-rose)' },
  { id: 2, nom: 'Entrée', stockages: 'Placard · Meuble à chaussures', nbArticles: 38, fond: 'var(--couleur-pastel-sable)' },
  { id: 3, nom: 'Salon', stockages: 'Étagère vinyles · Bibliothèque', nbArticles: 61, fond: 'var(--couleur-pastel-ardoise)' },
  { id: 4, nom: 'Bureau', stockages: 'Étagère comics · Cartons stock', nbArticles: 37, fond: 'var(--couleur-pastel-lavande)' },
]

const recherche = ref('')
</script>

<template>
  <div class="ecran">
    <main class="contenu">
      <header class="entete">
        <div class="entete__logement">
          <button type="button" class="logement">
            {{ logement.nom }}
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
          </button>
          <span class="role">{{ logement.role }}</span>
        </div>
        <button type="button" class="bouton-icone notifications" aria-label="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
          <span class="notifications__pastille"></span>
        </button>
      </header>

      <label class="recherche">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" /></svg>
        <span class="visuellement-cache">Rechercher</span>
        <input v-model="recherche" type="search" placeholder="Où est rangé… ? Article, marque, couleur" />
      </label>

      <div class="chiffres">
        <div class="chiffre">
          <span class="chiffre__valeur">{{ logement.nbArticles }}</span>
          <span class="chiffre__libelle">articles</span>
        </div>
        <div class="chiffre">
          <span class="chiffre__valeur">{{ logement.nbPieces }}</span>
          <span class="chiffre__libelle">pièces</span>
        </div>
        <a href="#" class="chiffre">
          <span class="chiffre__valeur chiffre__valeur--accent">{{ logement.nbPretes }}</span>
          <span class="chiffre__libelle">prêtés</span>
        </a>
      </div>

      <a href="#" class="tenue-du-jour">
        <span class="tenue-du-jour__icone">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25" /><path d="M8 19v2M8 13v2M16 19v2M16 13v2M12 21v2M12 15v2" /></svg>
        </span>
        <span class="tenue-du-jour__textes">
          <span class="tenue-du-jour__meteo">{{ meteo }}</span>
          <span class="tenue-du-jour__titre">Générer ma tenue du jour</span>
        </span>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
      </a>

      <section class="pieces">
        <div class="pieces__entete">
          <h2 class="pieces__titre">Pièces</h2>
          <button type="button" class="pieces__ajout">+ Ajouter une pièce</button>
        </div>
        <div class="pieces__liste">
          <RouterLink v-for="piece in pieces" :key="piece.id" :to="`/pieces/${piece.id}`" class="piece">
            <span class="piece__icone" :style="{ background: piece.fond }">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z" /><path d="m3 8 9 5 9-5M12 13v8" /></svg>
            </span>
            <span class="piece__textes">
              <span class="piece__nom">{{ piece.nom }}</span>
              <span class="piece__stockages">{{ piece.stockages }}</span>
            </span>
            <span class="piece__nombre">{{ piece.nbArticles }}</span>
            <svg class="piece__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
          </RouterLink>
        </div>
      </section>
    </main>

    <BarreNavigation actif="dressing" />
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 20px 20px 0;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.entete {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.entete__logement {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.logement {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  color: var(--couleur-texte);
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
  cursor: pointer;
}

.role {
  display: inline-flex;
  align-self: flex-start;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  font-size: 12px;
  font-weight: 700;
}

.notifications {
  position: relative;
}

.notifications__pastille {
  position: absolute;
  top: 9px;
  right: 10px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--couleur-primaire);
  border: 2px solid var(--couleur-surface);
}

.recherche {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 48px;
  padding: 0 16px;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  color: var(--couleur-texte-secondaire);
}

.recherche input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 15px;
  color: var(--couleur-texte);
}

.recherche:focus-within {
  border-color: var(--couleur-primaire);
}

.chiffres {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.chiffre {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 14px;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  color: var(--couleur-texte);
}

.chiffre__valeur {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.chiffre__valeur--accent {
  color: var(--couleur-primaire);
}

.chiffre__libelle {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
  font-weight: 500;
}

.tenue-du-jour {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 20px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.tenue-du-jour:hover {
  color: var(--couleur-sur-primaire);
}

.tenue-du-jour__icone {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  border-radius: 14px;
  background: var(--couleur-sur-primaire-voile);
  display: flex;
  align-items: center;
  justify-content: center;
}

.tenue-du-jour__textes {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.tenue-du-jour__meteo {
  font-size: 13px;
  font-weight: 600;
  opacity: 0.85;
}

.tenue-du-jour__titre {
  font-size: 16px;
  font-weight: 700;
}

.pieces {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pieces__entete {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.pieces__titre {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.pieces__ajout {
  border: 0;
  background: none;
  padding: 6px 0;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-primaire);
  cursor: pointer;
}

.pieces__liste {
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
}

.piece {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--couleur-separateur);
  color: var(--couleur-texte);
}

.piece__icone {
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  border-radius: 12px;
  color: var(--couleur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
}

.piece__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.piece__nom {
  font-size: 15px;
  font-weight: 700;
}

.piece__stockages {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.piece__nombre {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.piece__chevron {
  color: var(--couleur-chevron);
}
</style>
