<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

// Données d'exemple reprises de la maquette, en attendant l'API
const article = {
  nom: 'Pull col roulé',
  infos: 'COS · Laine mérinos · Taille M',
  statut: 'Disponible',
  fond: 'var(--couleur-vignette-bordeaux)',
  datePhoto: '14 oct.',
  nbPhotos: 3,
  emplacement: 'Chambre › Armoire › Étagère 2',
  idPiece: 1,
  categorie: 'Pull',
  couleur: { nom: 'Bordeaux', valeur: 'var(--couleur-vignette-bordeaux)' },
  etiquettes: ['Travail', 'Hiver', 'Week-end'],
}

const indexPhoto = ref(0)
const estPersonnel = ref(false)
</script>

<template>
  <div class="ecran">
    <div class="photo" :style="{ background: article.fond }">
      <svg width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" /></svg>
      <RouterLink :to="`/pieces/${article.idPiece}`" class="photo__bouton photo__bouton--gauche" aria-label="Retour">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
      </RouterLink>
      <button type="button" class="photo__bouton photo__bouton--droite" aria-label="Modifier l'article">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
      </button>
      <span class="photo__source">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" /><circle cx="12" cy="13" r="4" /></svg>
        Photo du scan · {{ article.datePhoto }}
      </span>
      <span class="photo__indicateurs">
        <span v-for="index in article.nbPhotos" :key="index" class="indicateur" :class="{ 'indicateur--actif': index - 1 === indexPhoto }"></span>
      </span>
    </div>

    <main class="contenu">
      <div class="titre">
        <div class="titre__textes">
          <h1 class="titre__nom">{{ article.nom }}</h1>
          <span class="titre__infos">{{ article.infos }}</span>
        </div>
        <span class="statut">{{ article.statut }}</span>
      </div>

      <div class="emplacement">
        <span class="emplacement__icone">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
        </span>
        <span class="emplacement__textes">
          <span class="emplacement__libelle">Rangé dans</span>
          <span class="emplacement__chemin">{{ article.emplacement }}</span>
        </span>
      </div>

      <dl class="caracteristiques">
        <div class="caracteristique">
          <dt class="caracteristique__libelle">Catégorie</dt>
          <dd class="caracteristique__valeur">{{ article.categorie }}</dd>
        </div>
        <div class="caracteristique">
          <dt class="caracteristique__libelle">Couleur</dt>
          <dd class="caracteristique__valeur caracteristique__valeur--couleur">
            <span class="pastille-couleur" :style="{ background: article.couleur.valeur }"></span>{{ article.couleur.nom }}
          </dd>
        </div>
      </dl>

      <div class="etiquettes">
        <span class="etiquettes__titre">Étiquettes</span>
        <div class="etiquettes__liste">
          <span v-for="etiquette in article.etiquettes" :key="etiquette" class="etiquette">{{ etiquette }}</span>
          <button type="button" class="etiquette-ajout">+ Ajouter</button>
        </div>
      </div>

      <label class="personnel">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="personnel__icone"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
        <span class="personnel__textes">
          <span class="personnel__titre">Article personnel</span>
          <span class="personnel__aide">Visible uniquement par vous</span>
        </span>
        <input v-model="estPersonnel" type="checkbox" class="visuellement-cache" />
        <span class="interrupteur" :class="{ 'interrupteur--actif': estPersonnel }" aria-hidden="true">
          <span class="interrupteur__curseur"></span>
        </span>
      </label>
    </main>

    <div class="actions">
      <a href="#" class="action action--secondaire">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4" /></svg>
        Prêter
      </a>
      <a href="#" class="action action--principale">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><circle cx="7" cy="7" r="1" /></svg>
        Mettre en vente
      </a>
    </div>
  </div>
</template>

<style scoped>
.photo {
  position: relative;
  height: 330px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone-grande);
}

.photo__bouton {
  position: absolute;
  top: 16px;
  width: 44px;
  height: 44px;
  border-radius: 14px;
  border: 0;
  background: var(--couleur-photo-bouton);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.photo__bouton--gauche {
  left: 16px;
}

.photo__bouton--droite {
  right: 16px;
}

/* 36 px = 16 px de marge + les 20 px recouverts par le contenu */
.photo__source {
  position: absolute;
  left: 16px;
  bottom: 36px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 10px;
  background: var(--couleur-photo-voile);
  color: var(--couleur-sur-primaire);
  font-size: 12px;
  font-weight: 600;
}

.photo__indicateurs {
  position: absolute;
  right: 16px;
  bottom: 40px;
  display: flex;
  gap: 6px;
}

.indicateur {
  width: 6px;
  height: 6px;
  border-radius: 3px;
  background: var(--couleur-photo-indicateur);
}

.indicateur--actif {
  width: 18px;
  background: var(--couleur-sur-primaire);
}

.contenu {
  flex: 1;
  margin-top: -20px;
  padding: 22px 20px 0;
  border-radius: 24px 24px 0 0;
  background: var(--couleur-fond);
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.titre {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.titre__textes {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.titre__nom {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.titre__infos {
  font-size: 14px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.statut {
  flex-shrink: 0;
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--couleur-succes-fond);
  color: var(--couleur-succes);
  font-size: 12px;
  font-weight: 700;
}

.emplacement {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 18px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.emplacement__icone {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  border-radius: 12px;
  background: var(--couleur-sur-primaire-voile);
  display: flex;
  align-items: center;
  justify-content: center;
}

.emplacement__textes {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.emplacement__libelle {
  font-size: 12px;
  font-weight: 600;
  opacity: 0.8;
}

.emplacement__chemin {
  font-size: 15px;
  font-weight: 700;
}

.caracteristiques {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.caracteristique {
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.caracteristique__libelle {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.caracteristique__valeur {
  margin: 2px 0 0;
  font-size: 14px;
  font-weight: 700;
}

.caracteristique__valeur--couleur {
  display: flex;
  align-items: center;
  gap: 6px;
}

.pastille-couleur {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.etiquettes {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.etiquettes__titre {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.etiquettes__liste {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.etiquette {
  padding: 6px 12px;
  border-radius: 999px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  font-size: 13px;
  font-weight: 700;
}

.etiquette-ajout {
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px dashed var(--couleur-bordure-pointillee);
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.personnel {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  cursor: pointer;
}

.personnel__icone {
  color: var(--couleur-primaire);
}

.personnel__textes {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.personnel__titre {
  font-size: 14px;
  font-weight: 700;
}

.personnel__aide {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.interrupteur {
  width: 46px;
  height: 28px;
  border-radius: 999px;
  background: var(--couleur-interrupteur-inactif);
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding: 3px;
  box-sizing: border-box;
}

.interrupteur--actif {
  background: var(--couleur-primaire);
  justify-content: flex-end;
}

.personnel input:focus-visible + .interrupteur {
  outline: 2px solid var(--couleur-primaire);
  outline-offset: 2px;
}

.interrupteur__curseur {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--couleur-surface);
  box-shadow: var(--ombre-curseur);
}

.actions {
  position: sticky;
  bottom: 0;
  display: flex;
  gap: 10px;
  padding: 12px 20px 22px;
  background: var(--couleur-surface);
  border-top: 1px solid var(--couleur-bordure);
}

.action {
  flex: 1;
  height: 52px;
  box-sizing: border-box;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
}

.action--secondaire {
  border: 1.5px solid var(--couleur-primaire);
  color: var(--couleur-primaire);
}

.action--principale {
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.action--principale:hover {
  color: var(--couleur-sur-primaire);
  background: var(--couleur-primaire-fonce);
}
</style>
