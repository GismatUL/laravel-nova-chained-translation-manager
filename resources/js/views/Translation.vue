<template>
  <loading-view :loading="initialLoading">
    <Head class="mb-3" :title="__('Translations')" />

    <div class="translation-manager">
      <div class="flex items-center">
        <!-- search -->
        <div class="relative flex items-center mb-6 h-9 flex-no-shrink">
          <icon type="search" class="absolute ml-1 search-icon-center text-70" />
          <label for="search">
            <input
                v-model="search"
                :placeholder="__('Search text')"
                type="search"
                name="search"
                class="p-2 pl-8 rounded-md shadow appearance-none form-search w-search pl-search dark:bg-gray-800"
                @keyup.enter="getTranslations"
            />
          </label>
        </div>

        <!-- only missing -->
        <checkbox-with-label
            :checked="onlyMissing"
            class="mb-6 ml-3"
            @input="onlyMissing = !onlyMissing"
        >
          {{ __("Show only missing translations") }}
        </checkbox-with-label>

        <!-- select group (single) -->
        <div class="ml-auto relative flex items-center mb-6 h-9 flex-no-shrink">
          <dropdown class="mb-6 rounded bg-30 hover:bg-40">
            <dropdown-trigger class="px-3">
              {{ __("Site") }}
              <span v-if="selectedGroup" class="ml-2 text-80 font-semibold">({{ selectedGroup }})</span>
            </dropdown-trigger>
            <template #menu>
              <dropdown-menu slot="menu" direction="rtl" width="280">
                <div class="p-4">
                  <ul class="list-reset">
                    <li v-for="g in groups" :key="g" class="flex items-center mb-3">
                      <label class="flex items-center cursor-pointer" @click.stop>
                        <input
                            type="radio"
                            :value="g"
                            v-model="selected.group"
                            @change="onGroupChange(g)"
                            class="form-radio"
                        />
                        <span class="ml-2">{{ g }}</span>
                      </label>
                    </li>
                  </ul>
                </div>
              </dropdown-menu>
            </template>
          </dropdown>
        </div>

        <!-- select language (single) -->
        <div class="ml-2 relative flex items-center mb-6 h-9 flex-no-shrink">
          <dropdown class="mb-6 rounded bg-30 hover:bg-40">
            <dropdown-trigger class="px-3">
              {{ __("Language") }}
              <span v-if="selectedLocale" class="ml-2 text-80 font-semibold">({{ selectedLocale }})</span>
            </dropdown-trigger>
            <template #menu>
              <dropdown-menu slot="menu" direction="rtl" width="280">
                <div class="p-4">
                  <ul class="list-reset">
                    <li v-for="l in locales" :key="l.locale" class="flex items-center mb-3">
                      <label class="flex items-center cursor-pointer" @click.stop>
                        <input
                            type="radio"
                            :value="l.locale"
                            v-model="selected.locales[0]"
                            @change="onLocaleChange(l.locale)"
                            class="form-radio"
                        />
                        <span class="ml-2">{{ l.language }}</span>
                      </label>
                    </li>
                  </ul>
                </div>
              </dropdown-menu>
            </template>
          </dropdown>
        </div>

        <!-- Add translation button -->
        <div class="relative flex items-center mb-6 h-9 flex-no-shrink">
          <button
              class="shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold"
              :disabled="!selectedGroup || !selectedLocale"
              @click="openCreateModal"
          >
            {{ __('Add translation') }}
          </button>
        </div>
      </div>

      <!-- translations list -->
      <template v-if="filteredTranslations && filteredTranslations.length">
        <card v-for="t in filteredTranslations" :key="t.id" class="px-4 py-2 my-2">
          <div class="flex mr-6 font-bold no-underline border-b dark:border-slate-600 text-90">
            <div class="w-12/12">
              {{ t.group.toUpperCase() }} - {{ t.key }}
            </div>
          </div>

          <div class="my-3">
            <editable-input
                v-if="selectedLocale"
                :locale="selectedLocale"
                :translation="t"
                :editing="field === `${t.id}_${selectedLocale}`"
                :config="config"
                @toggle="field = `${t.id}_${selectedLocale}`"
                @submit="submit"
                @cancel="cancel"
            />
          </div>
        </card>
      </template>

      <template v-else>
        <card class="px-4 py-3 my-2">
          <div class="text-80">
            {{ __('No translations found with the current filters.') }}
          </div>
        </card>
      </template>
    </div>

    <!-- Create Translation Modal -->
    <modal v-if="showCreate" @close="closeCreateModal">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-4xl">
        <div class="px-6 py-4 border-b dark:border-gray-700">
          <h2 class="text-xl font-semibold">{{ __('Create translation') }}</h2>
        </div>

        <div class="px-6 py-4 space-y-4">
          <div class="flex items-center">
            <div class="w-2/3 font-medium">{{ selectedGroup || '—' }}</div>
          </div>

          <div class="flex items-center">
            <div class="w-2/3 font-medium">{{ selectedLocale || '—' }}</div>
          </div>

          <div>
            <label class="block text-80 mb-1">{{ __('Key (dot notation)') }}</label>
            <input
                v-model.trim="createForm.key"
                type="text"
                class="w-full form-control form-input form-input-bordered"
                placeholder="e.g. Home.Main.title"
            />
            <p class="mt-1 text-70 text-xs">
              {{ __('Use dot notation to nest keys (e.g., "Section.Sub.key")') }}
            </p>
          </div>

          <div>
            <label class="block text-80 mb-1">{{ __('Value') }}</label>
            <textarea
                v-model="createForm.value"
                rows="5"
                class="block w-full form-control form-input form-control-bordered py-3 h-auto"
                :placeholder="__('Enter translation value')"
            />
          </div>

          <div v-if="createError" class="text-danger text-sm">
            {{ createError }}
          </div>
        </div>

        <div class="px-6 py-4 border-t dark:border-gray-700 flex items-center justify-end space-x-2">
          <button class="btn btn-link" type="button" @click="closeCreateModal">
            {{ __('Cancel') }}
          </button>
          <button
              class="shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold"
              :disabled="isCreating"
              @click="createTranslation"
          >
            <span v-if="!isCreating">{{ __('Create') }}</span>
            <span v-else>{{ __('Creating…') }}</span>
          </button>
        </div>
      </div>
    </modal>
  </loading-view>
</template>

<script>
import EditableInputVue from "../components/EditableInput.vue";
import CheckboxInput from "../components/CheckboxInput.vue";
import Modal from "../components/Modal.vue";

export default {
  components: {
    "editable-input": EditableInputVue,
    "checkbox-input": CheckboxInput,
    'modal': Modal,
  },

  data() {
    return {
      initialLoading: true,
      translations: null,

      search: null,
      onlyMissing: false,

      groups: [],
      locales: [],
      config: [],

      selected: {
        group: null,     // single group
        locales: [],     // single locale, e.g. ['en']
      },

      field: null,

      // create modal state
      showCreate: false,
      isCreating: false,
      createError: null,
      createForm: {
        key: "",
        value: "",
      },
    };
  },

  computed: {
    selectedGroup() { return this.selected.group || null; },
    selectedLocale() { return this.selected.locales[0] || null; },

    filteredTranslations() {
      if (!this.translations) return [];
      const sel = this.selectedLocale;

      return this.translations
          .filter(({ translations }) => {
            if (!this.onlyMissing) return true;
            const v = translations?.[sel];
            return v === undefined || v === null || v === '';
          })
          .filter((v) => {
            if (!this.search) return true;
            const key = (v.key || '').toString().toLowerCase();
            const t   = (v.translations?.[sel] || '').toString().toLowerCase();
            const q   = this.search.toLowerCase();
            return key.includes(q) || t.includes(q);
          });
    },
  },

  created() {
    this.getTranslations();
  },

  methods: {
    onGroupChange(g) {
      this.selected.group = g;
      this.selected.locales = [];
      this.getTranslations();
    },

    onLocaleChange(l) {
      this.selected.locales = [l];
      this.getTranslations();
    },

    getTranslations() {
      this.initialLoading = true;

      const params = {
        search: this.search || undefined,
        group:  this.selectedGroup || undefined,
        locale: this.selectedLocale || undefined,
      };

      Nova.request()
          .get("/nova-vendor/translation-manager/translations", { params })
          .then(({ data }) => {
            const { groups, selected_group, languages, selected_locales, config, translations } = data;

            this.groups  = Array.isArray(groups) ? groups : [];
            this.locales = Array.isArray(languages) ? languages : [];
            this.config  = config || [];

            if (!this.selected.group && selected_group) {
              this.selected.group = selected_group;
            } else if (!this.selected.group && this.groups.length) {
              this.selected.group = this.groups[0];
            }

            if (!this.selected.locales.length && Array.isArray(selected_locales) && selected_locales.length) {
              this.selected.locales = [selected_locales[0]];
            } else if (!this.selected.locales.length && this.locales.length) {
              this.selected.locales = [this.locales[0].locale];
            }

            this.translations = translations?.data || [];
          })
          .catch(() => {
            Nova.error(this.__("Failed to load translations"));
          })
          .then(() => {
            this.initialLoading = false;
          });
    },

    updateTranslations(val) {
      const [id, locale] = this.field.split(/_(.*)/s);
      const idx = this.translations.findIndex((t) => t.id === id);
      if (idx !== -1) {
        this.translations[idx].translations[locale] = val.value;
        this.translations[idx].updated = locale;
      }
      Nova.success(this.__("Updated"));
      this.cancel();
    },

    submit(val) {
      if (val && val.value !== undefined) {
        val['_method'] = 'PUT';
        Nova.request()
            .post("/nova-vendor/translation-manager/translations", val)
            .then(() => this.updateTranslations(val))
            .catch(() => Nova.error(this.__("Something went wrong!")));
      } else {
        this.field = null;
        Nova.error(this.__("A translation string is required"));
      }
    },

    cancel() {
      this.field = null;
    },

    // --- Create translation ---
    openCreateModal() {
      console.log('openCreateModal clicked', { group: this.selectedGroup, locale: this.selectedLocale });
      this.createError = null;
      this.createForm = { key: "", value: "" };
      this.showCreate = true;
    },

    closeCreateModal() {
      if (this.isCreating) return;
      this.showCreate = false;
    },

    createTranslation() {
      this.createError = null;

      const group  = this.selectedGroup;
      const locale = this.selectedLocale;
      const key    = (this.createForm.key || "").trim();
      const value  = this.createForm.value;

      if (!group || !locale) {
        this.createError = this.__("Please select a site and a language first.");
        return;
      }
      if (!key) {
        this.createError = this.__("Key is required.");
        return;
      }

      this.isCreating = true;

      const payload = {
        _method: 'PUT', // same endpoint supports create via save()
        group,
        locale,
        key,
        value,
      };

      Nova.request()
          .post("/nova-vendor/translation-manager/translations", payload)
          .then(() => {
            // Optimistically add to current list (faster UX). Alternatively call this.getTranslations().
            const id = this.randId();
            const existingIndex = this.translations.findIndex(t => t.group === group && t.key === key);
            if (existingIndex === -1) {
              this.translations.unshift({
                id,
                type: 'group',
                group,
                key,
                translations: { [locale]: value },
                updated: locale,
              });
            } else {
              // If row exists (from another locale), just add the new locale’s value
              this.translations[existingIndex].translations[locale] = value;
              this.translations[existingIndex].updated = locale;
            }

            Nova.success(this.__("Created"));
            this.isCreating = false;
            this.showCreate = false;
          })
          .catch(() => {
            this.isCreating = false;
            this.createError = this.__("Something went wrong while creating the translation.");
          });
    },

    randId() {
      // simple client-side id for list rendering; backend generates ids differently
      return Math.random().toString(36).slice(2, 10) + Math.random().toString(36).slice(2, 10);
    },
  },
};
</script>
