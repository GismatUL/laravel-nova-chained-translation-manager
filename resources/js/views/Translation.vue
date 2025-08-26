<template>
    <loading-view :loading="initialLoading">
        <Head class="mb-3" :title="__('Translations')" />

        <div class="translation-manager">
            <div class="flex">
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
                    {{ __("Only show missing translations") }}
                </checkbox-with-label>

                <!-- select group (single) -->
                <div class="ml-auto">
                    <dropdown class="mb-6 rounded bg-30 hover:bg-40">
                        <dropdown-trigger class="px-3">
                            {{ __("Select site") }}
                            <span v-if="selectedGroup" class="ml-2 text-80 font-semibold">
                ({{ selectedGroup }})
              </span>
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

                <!-- select language (single; depends on selected group) -->
                <div class="ml-2">
                    <dropdown class="mb-6 rounded bg-30 hover:bg-40">
                        <dropdown-trigger class="px-3">
                            {{ __("Select language") }}
                            <span v-if="selectedLocale" class="ml-2 text-80 font-semibold">
                ({{ selectedLocale }})
              </span>
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
    </loading-view>
</template>

<script>
import EditableInputVue from "../components/EditableInput.vue";
import CheckboxInput from "../components/CheckboxInput.vue";

export default {
    components: {
        "editable-input": EditableInputVue,
        "checkbox-input": CheckboxInput,
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
            // when group (site) changes, reset locale; server will send the allowed ones
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
    },
};
</script>
