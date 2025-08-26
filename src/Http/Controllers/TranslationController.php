<?php

namespace Statikbe\NovaTranslationManager\Http\Controllers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\ChainLoader;
use Statikbe\NovaTranslationManager\Http\Requests\UpdateTranslationRequest;
use Statikbe\NovaTranslationManager\TranslationManager;
use App\Models\Sites;

class TranslationController extends AbstractTranslationController
{
    /**
     * @var ChainLoader $translationLoader
     */
    private $translationLoader;

    /**
     * @var FileSystem $fileSystem
     */
    private $fileSystem;

    /**
     * @var ChainedTranslationManager
     */
    private $chainedTranslationManager;

    public function __construct(
        ChainLoader $translationLoader,
        Filesystem $filesystem,
        ChainedTranslationManager $chainedTranslationManager
    ) {
        $this->translationLoader = $translationLoader;
        $this->fileSystem = $filesystem;
        $this->chainedTranslationManager = $chainedTranslationManager;
    }

    /**
     * Nova AJAX endpoint to return the translations, translation groups, languages and current locale
     * @return \Illuminate\Http\JsonResponse
     */
    // use Illuminate\Http\Request;  <-- make sure this is imported

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1) Sites the user can see
        $sites = $user->isAdmin()
            ? Sites::query()->select(['id','title','slug','locales'])->get()
            : $user->allowedSites()->select('sites.id','sites.title','sites.slug','sites.locales')->get()->unique('id');

        $sitesBySlug = $sites->keyBy(fn($s) => (string)$s->slug);
        $sitesById   = $sites->keyBy('id');

        // 2) Build "groups" from site slugs (what the FE treats as groups)
        $groups = $sites->pluck('slug')->filter()->values()->all();
        $groupLabels = $sites->mapWithKeys(fn($s) => [$s->slug => $s->title])->all();

        // 3) Pick selected group (slug)
        $selectedGroup = $request->query('group');
        if (!$selectedGroup || !in_array($selectedGroup, $groups, true)) {
            $sessionSiteId = (int) session('selected_site_id');
            if ($sessionSiteId && $sitesById->has($sessionSiteId)) {
                $selectedGroup = (string) $sitesById->get($sessionSiteId)->slug;
            }
            $selectedGroup = $selectedGroup ?: ($groups[0] ?? null);
        }

        // 4) Resolve selected site + allowed locales for that site
        $selectedSite = $selectedGroup && $sitesBySlug->has($selectedGroup)
            ? $sitesBySlug->get($selectedGroup)
            : null;

        $selectedSiteId = $selectedSite ? (int) $selectedSite->id : null;
        $siteLocales    = $selectedSite ? $this->normalizeLocalesFromSite($selectedSite->locales) : [];

        $allowedLocales = $selectedSiteId
            ? ($user->isAdmin() ? $siteLocales : $user->allowedLocalesForSite($selectedSiteId))
            : [];

        // fallback if somehow empty
        if (empty($allowedLocales)) {
            $allowedLocales = array_column($this->getLocalesData(), 'locale');
        }

        // 5) Languages list only for allowed locales
        $allLanguages = $this->getLocalesData(); // [{locale, language}, ...]
        $languages = array_values(array_filter(
            $allLanguages,
            fn ($l) => in_array($l['locale'], $allowedLocales, true)
        ));

        // 6) Selected locale (single)
        $reqLocale = $request->query('locale');
        $selectedLocale = (is_string($reqLocale) && in_array($reqLocale, $allowedLocales, true))
            ? $reqLocale
            : ($allowedLocales[0] ?? config('app.locale'));

        // 7) Load ONLY the selected group + locale
        $languagesToLoad = array_values(array_filter(
            $languages,
            fn ($l) => $l['locale'] === $selectedLocale
        ));
        $groupsToLoad = $selectedGroup ? [$selectedGroup] : [];

        $translations = $this->getTranslations($languagesToLoad, $groupsToLoad);

        return response()->json([
            'source_language'   => config('app.locale'),
            'groups'            => $groups,                 // array of slugs
            'group_labels'      => $groupLabels,            // slug => site title
            'selected_group'    => $selectedGroup,          // slug
            'languages'         => $languages,              // allowed for that site
            'selected_locales'  => [$selectedLocale],       // single
            'selected_site_id'  => $selectedSiteId,         // handy for UI/session
            'config'            => TranslationManager::getConfig(),
            'translations'      => ['data' => $translations],
        ]);
    }

// same helper you already have elsewhere
    private function normalizeLocalesFromSite($value): array
    {
        if (is_array($value)) return collect($value)->filter()->unique()->values()->all();
        if (is_string($value)) {
            $trim = trim($value); if ($trim==='') return [];
            $decoded = json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return collect($decoded)->filter()->unique()->values()->all();
            }
            return collect(explode(',', $trim))->map('trim')->filter()->unique()->values()->all();
        }
        return [];
    }

    /**
     * Nova AJAX endpoint to save the translation.
     * @param  UpdateTranslationRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateTranslationRequest $request): JsonResponse
    {
        # If the editor is a Trix Editor we will stripe all HTML tags excluding the ones defined by the user.
        $value = $request->input('value');
        if (TranslationManager::getConfig('editor') === 'trix') {
            $value = strip_tags(
                $request->input('value'),
                TranslationManager::getConfig('trix_allowed_tags', '')
            );
        }

        $this->chainedTranslationManager->save(
            $request->input('locale'),
            $request->input('group'),
            $request->input('key'),
            $value
        );

        return response()->json(['success' => true]);
    }

    private function getTranslations(array $languages, array $allGroups): array
    {
        $data = [];
        foreach ($languages as $language) {
            foreach ($allGroups as $group) {
                $this->addTranslationsToData($data, $language, $group);
            }
        }

        return array_values($data);
    }

    private function addTranslationsToData(array &$data, array $language, string $group): array
    {
        $translations = $this->chainedTranslationManager->getTranslationsForGroup($language['locale'], $group);

        //transform to data structure necessary for frontend
        foreach ($translations as $key => $translation) {
            $dataKey = $group.'.'.$key;
            if (!array_key_exists($dataKey, $data)) {
                $data[$dataKey] = [
                    'id' => Str::random(20),
                    'type' => 'group',
                    'group' => $group,
                    'key' => $key,
                    'translations' => [],
                ];
            }
            $data[$dataKey]['translations'][$language['locale']] = $translation;
        }

        return $data;
    }
}
