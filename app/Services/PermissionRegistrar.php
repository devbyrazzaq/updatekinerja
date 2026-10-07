<?php

namespace App\Services;

use App\Models\UnitKerja;
use App\Models\User;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRegistrar
{
    /**
     * Field name tunggal tabel permission section "Pembatasan Data" terpusat.
     * Menampung semua dimensi scope (per entitas) yang berlaku global ke semua menu.
     */
    public const DATA_SCOPE_FIELD_NAME = 'perm_data_scope';

    /**
     * Prefix permission scope; diikuti key entitas + id record,
     * mis. `view_data_prodi_5`, `view_data_cabang_2`.
     */
    public const DATA_SCOPE_PERMISSION_PREFIX = 'view_data_';

    /**
     * Permission kustom yang tidak terikat resource tertentu.
     * `bypass_data_scope` WAJIB ada — master switch akses penuh (Super Admin).
     *
     * @var array<string, string>
     */
    public static array $customPermissions = [
        'bypass_data_scope' => 'Lewati pembatasan data (akses semua data)',
    ];

    /**
     * Urutan grup menu mengikuti AppPanelProvider::navigationGroups(). Dipakai untuk
     * mengurutkan section pada form permission agar mencerminkan sidebar.
     * SESUAIKAN dengan project.
     *
     * @var array<int, string>
     */
    protected const NAV_GROUP_ORDER = [
        'Dashboard',
        'Master Data',
        'Anggaran',
        'Program Kerja',
        'Pelaksanaan',
        'Pemasukan',
        'Verifikasi Pengajuan',
        'Verifikasi Realisasi',
        'Perencanaan',
        'Verifikasi Pengajuan Perencanaan',
        'Monitoring',
        'Pengguna',
        'Manajemen Akses',
        'Pengaturan Sistem',
    ];

    /**
     * Grup "Dashboard": menampung permission halaman Dashboard + semua widget yang
     * tampil di dalamnya (semua widget dimasukkan ke section ini).
     */
    protected const NAV_GROUP_DASHBOARD = 'Dashboard';

    /**
     * Grup semu (bukan menu sidebar) yang selalu diletakkan di akhir.
     */
    protected const NAV_GROUP_SCOPE = 'Pembatasan Data (Scope)';

    protected const NAV_GROUP_OTHER = 'Lainnya';

    /**
     * Deskripsi khusus per grup menu untuk section pada form permission. Grup yang
     * tidak terdaftar di sini memakai deskripsi default (lihat getGroupDescription()).
     * SESUAIKAN dengan project.
     *
     * @var array<string, string>
     */
    public static array $groupDescriptions = [
        'Dashboard' => 'Akses halaman dashboard dan widget yang tampil di dalamnya.',
        'Master Data' => 'Data referensi utama aplikasi.',
        'Anggaran' => 'Pengelolaan rekening dan pagu anggaran tahun kerja.',
        'Program Kerja' => 'Acuan dan penawaran program kerja.',
        'Pelaksanaan' => 'Daftar, pengajuan, dan realisasi program kerja unit kerja.',
        'Pemasukan' => 'Pencatatan pemasukan/pendapatan per unit kerja.',
        'Verifikasi Pengajuan' => 'Verifikasi tahap 1 atas pengajuan program kerja tahun berjalan.',
        'Verifikasi Realisasi' => 'Verifikasi realisasi oleh Rektor, Wakil Rektor, Biro Keuangan, dan laporan.',
        'Perencanaan' => 'Daftar dan pengajuan program kerja untuk tahun kerja yang sedang direncanakan.',
        'Verifikasi Pengajuan Perencanaan' => 'Verifikasi tahap 1 atas pengajuan program kerja tahun yang sedang direncanakan.',
        'Monitoring' => 'Pemantauan penyerapan anggaran dan capaian program kerja, termasuk rekap dan perbandingannya.',
        'Pengguna' => 'Pengelolaan akun pengguna, dipisah menurut role utamanya.',
        'Manajemen Akses' => 'Pengelolaan role dan hak akses pengguna.',
        'Pengaturan Sistem' => 'Pengaturan perilaku sistem.',
    ];

    /**
     * Daftar entitas scope project — SATU-SATUNYA tempat pembatasan data dikonfigurasi.
     * Kembalikan [] jika project tidak punya entitas pembatas data (section "Pembatasan
     * Data" tidak dibuat sama sekali). TANYAKAN dulu ke user (lihat SKILL.md & Ref 08).
     *
     * @return array<string, array{label: string, options: \Closure(): array<int|string, string>}>
     */
    protected static function dataScopeEntities(): array
    {
        return [
            'unit' => [
                'label' => 'Akses Data per Unit Kerja',
                'options' => fn (): array => UnitKerja::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all(),
            ],
        ];
    }

    /**
     * Id Unit Kerja yang menjadi cakupan data user SAAT INI. Bila user berwenang atas
     * lebih dari satu unit, hasilnya dipersempit ke unit yang sedang aktif (dipilih
     * lewat pengalih unit di topbar) sehingga seluruh menu memandang satu unit saja.
     * Untuk daftar lengkap unit yang boleh diakses, pakai allPermittedUnitIds().
     *
     * @return Collection<int, int>
     */
    public static function permittedUnitIds(User|int|null $user): Collection
    {
        $user = $user instanceof User ? $user : ($user !== null ? User::find($user) : null);

        if (! $user instanceof User) {
            return collect();
        }

        $ids = static::allPermittedUnitIds($user);
        $aktif = UnitKerjaAktif::id($user);

        return $aktif !== null && $ids->contains($aktif)
            ? collect([$aktif])
            : $ids;
    }

    /**
     * Seluruh Id Unit Kerja yang boleh diakses user: unit miliknya sendiri (kolom
     * `unit_kerja_id`) digabung dengan unit yang diberikan lewat permission scope
     * (umumnya dari role pembatas data "Unit: ...").
     *
     * @return Collection<int, int>
     */
    public static function allPermittedUnitIds(User|int|null $user): Collection
    {
        $user = $user instanceof User ? $user : ($user !== null ? User::find($user) : null);

        if (! $user instanceof User) {
            return collect();
        }

        $ids = static::permittedScopeIds($user, 'unit');

        if ($user->unit_kerja_id !== null) {
            $ids = $ids->push($user->unit_kerja_id);
        }

        return $ids->unique()->values();
    }

    /**
     * Nama seluruh permission milik resource/page/widget yang berada pada grup menu
     * tertentu. Dipakai RoleSeeder untuk memberi hak akses satu grup menu utuh tanpa
     * perlu menuliskan nama permission satu per satu.
     *
     * @param  array<int, string>  $navGroups
     * @return array<int, string>
     */
    public static function permissionNamesForNavGroups(array $navGroups): array
    {
        $names = [];

        foreach (static::collect() as $group) {
            if (! in_array($group['nav_group'] ?? null, $navGroups, true)) {
                continue;
            }

            $names = array_merge($names, array_keys($group['permissions']));
        }

        return array_values(array_unique($names));
    }

    /**
     * Nama permission milik menu tertentu (resource/page/widget), untuk memberi hak
     * akses satu menu saja tanpa membawa seluruh grupnya. Nilai tiap kelas berisi
     * daftar ability yang diberikan (mis. `['view_any', 'create']`), atau `null` bila
     * seluruh permission menu tersebut ikut diberikan.
     *
     * @param  array<class-string, array<int, string>|null>  $menus
     * @return array<int, string>
     */
    public static function permissionNamesForMenus(array $menus): array
    {
        $names = [];

        foreach (static::collect() as $group) {
            $className = $group['resource_class'] ?? null;

            if ($className === null || ! array_key_exists($className, $menus)) {
                continue;
            }

            $abilities = $menus[$className];

            if ($abilities === null) {
                $names = array_merge($names, array_keys($group['permissions']));

                continue;
            }

            foreach ($abilities as $ability) {
                $name = method_exists($className, 'getPermissionName')
                    ? $className::getPermissionName($ability)
                    : $ability;

                if (array_key_exists($name, $group['permissions'])) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function collect(): array
    {
        $groups = [
            ...static::collectFromFiles(app_path('Filament/Resources/*/*Resource.php'), Resource::class, 'Resource', 'resource'),
            ...static::collectFromFiles(app_path('Filament/Pages/*.php'), Page::class, 'Page', 'page'),
            ...static::collectFromFiles(app_path('Filament/Clusters/*/Pages/*.php'), Page::class, 'Page', 'page'),
            ...static::collectFromFiles(app_path('Filament/Widgets/*.php'), Widget::class, 'Widget', 'widget'),
        ];

        // Pembatasan data (scope) TERPUSAT: satu section khusus berisi checklist per
        // record entitas (dataScopeEntities), berlaku GLOBAL ke semua menu terkait.
        // Tiap menu membaca dimensi yang relevan lewat permittedScopeIds($user, $key).
        if (($scopeGroup = static::dataScopeGroup()) !== null) {
            $groups[] = $scopeGroup;
        }

        if (static::$customPermissions !== []) {
            $groups[] = [
                'type' => 'custom',
                'resource_class' => null,
                'field_name' => 'perm_custom',
                'nav_group' => static::NAV_GROUP_OTHER,
                'nav_sort' => null,
                'in_menu' => true,
                'heading' => 'Hak Akses Kustom',
                'description' => 'Permission tambahan yang tidak terikat pada resource tertentu.',
                'permissions' => static::$customPermissions,
                'permission_descriptions' => [],
                'sections' => [[
                    'title' => null,
                    'permissions' => static::$customPermissions,
                    'permission_descriptions' => [],
                ]],
            ];
        }

        return $groups;
    }

    /**
     * Grup permission "Pembatasan Data" terpusat: satu field tunggal (`perm_data_scope`)
     * berisi satu sub-bagian per entitas scope. Null bila project tanpa entitas scope.
     *
     * @return array<string, mixed>|null
     */
    protected static function dataScopeGroup(): ?array
    {
        $entities = static::dataScopeEntities();

        if ($entities === []) {
            return null;
        }

        $sections = [];
        $allPermissions = [];

        foreach ($entities as $key => $entity) {
            $permissions = static::dataScopePermissions($key);
            $sections[] = [
                'title' => $entity['label'],
                'permissions' => $permissions,
                'permission_descriptions' => [],
            ];
            $allPermissions = array_merge($allPermissions, $permissions);
        }

        return [
            'type' => 'scope',
            'resource_class' => null,
            'field_name' => static::DATA_SCOPE_FIELD_NAME,
            'nav_group' => static::NAV_GROUP_SCOPE,
            'nav_sort' => null,
            'in_menu' => true,
            'heading' => 'Pembatasan Data',
            'description' => 'Batasi data yang dapat dilihat role ini. Satu setelan berlaku ke semua menu terkait. Kosongkan bila role tidak perlu dibatasi (atau beri "Lewati pembatasan data").',
            'permissions' => $allPermissions,
            'permission_descriptions' => [],
            'sections' => $sections,
        ];
    }

    /**
     * Permission scope satu entitas (satu per record aktif), mis. dataScopePermissions('prodi').
     *
     * @return array<string, string> permission => label (nama record)
     */
    public static function dataScopePermissions(string $entityKey): array
    {
        $entity = static::dataScopeEntities()[$entityKey] ?? null;

        if ($entity === null) {
            return [];
        }

        $prefix = static::dataScopePermissionPrefix($entityKey);

        return collect(($entity['options'])())
            ->mapWithKeys(fn (string $label, int|string $id): array => [$prefix.$id => $label])
            ->all();
    }

    public static function dataScopePermissionPrefix(string $entityKey): string
    {
        return static::DATA_SCOPE_PERMISSION_PREFIX.$entityKey.'_';
    }

    /**
     * Id record entitas yang boleh dilihat user, mis. permittedScopeIds($user, 'prodi').
     * Dipakai trait enforcement ScopesDataByPermission (Ref 08).
     *
     * @return Collection<int, int>
     */
    public static function permittedScopeIds(User|int|null $user, string $entityKey): Collection
    {
        return static::permittedIdsByPrefix($user, static::dataScopePermissionPrefix($entityKey));
    }

    /**
     * Ambil id dari permission user yang diawali prefix tertentu.
     *
     * @return Collection<int, int>
     */
    protected static function permittedIdsByPrefix(User|int|null $user, string $prefix): Collection
    {
        $user = $user instanceof User ? $user : ($user !== null ? User::find($user) : null);

        if (! $user instanceof User) {
            return collect();
        }

        return $user->getAllPermissions()
            ->pluck('name')
            ->filter(fn (string $name): bool => str_starts_with($name, $prefix))
            ->map(fn (string $name): int => (int) Str::after($name, $prefix))
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
    }

    /**
     * @param  class-string  $parentClass
     * @return array<int, array<string, mixed>>
     */
    protected static function collectFromFiles(string $pattern, string $parentClass, string $suffix, string $type): array
    {
        $groups = [];
        $files = glob($pattern) ?: [];

        foreach ($files as $file) {
            $relative = str_replace([app_path().'/', '.php'], '', $file);
            $className = 'App\\'.str_replace('/', '\\', $relative);

            if (! class_exists($className) || ! is_subclass_of($className, $parentClass)) {
                continue;
            }

            if ((new \ReflectionClass($className))->isAbstract()) {
                continue;
            }

            if (! method_exists($className, 'getPermissionDefinitions')) {
                continue;
            }

            $permissions = $className::getPermissionDefinitions();

            if ($permissions === []) {
                continue;
            }

            $key = Str::snake(str_replace($suffix, '', class_basename($className)));

            $groups[] = [
                'type' => $type,
                'resource_class' => $className,
                'field_name' => 'perm_'.$key,
                'nav_group' => match (true) {
                    $type === 'widget' => static::NAV_GROUP_DASHBOARD,
                    is_a($className, Dashboard::class, true) => static::NAV_GROUP_DASHBOARD,
                    default => static::resolveNavigationGroup($className),
                },
                'nav_sort' => static::resolveNavigationSort($className),
                'in_menu' => static::resolveShouldRegisterNavigation($className),
                'heading' => method_exists($className, 'getPermissionHeading')
                    ? $className::getPermissionHeading()
                    : Str::headline($key),
                'description' => method_exists($className, 'getPermissionDescription')
                    ? $className::getPermissionDescription()
                    : 'Hak akses untuk mengelola '.Str::lower(Str::headline($key)).'.',
                'permissions' => $permissions,
                'permission_descriptions' => $descriptions = method_exists($className, 'getPermissionDescriptions')
                    ? $className::getPermissionDescriptions()
                    : [],
                'sections' => [[
                    'title' => null,
                    'permissions' => $permissions,
                    'permission_descriptions' => $descriptions,
                ]],
            ];
        }

        return $groups;
    }

    /**
     * Nama grup menu (navigation group) sebuah resource/page. Mengembalikan label
     * string; bila tidak ada/null dipetakan ke grup "Lainnya".
     *
     * @param  class-string  $className
     */
    protected static function resolveNavigationGroup(string $className): string
    {
        if (! method_exists($className, 'getNavigationGroup')) {
            return static::NAV_GROUP_OTHER;
        }

        try {
            $group = $className::getNavigationGroup();
        } catch (\Throwable) {
            return static::NAV_GROUP_OTHER;
        }

        if ($group === null) {
            return static::NAV_GROUP_OTHER;
        }

        if ($group instanceof \UnitEnum) {
            return property_exists($group, 'value') ? (string) $group->value : $group->name;
        }

        $label = trim((string) $group);

        return $label === '' ? static::NAV_GROUP_OTHER : $label;
    }

    /**
     * @param  class-string  $className
     */
    protected static function resolveNavigationSort(string $className): ?int
    {
        if (! method_exists($className, 'getNavigationSort')) {
            return null;
        }

        try {
            return $className::getNavigationSort();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  class-string  $className
     */
    protected static function resolveShouldRegisterNavigation(string $className): bool
    {
        if (! method_exists($className, 'shouldRegisterNavigation')) {
            return true;
        }

        try {
            return (bool) $className::shouldRegisterNavigation();
        } catch (\Throwable) {
            return true;
        }
    }

    public static function syncToDatabase(): void
    {
        foreach (static::allPermissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * Seluruh nama permission yang valid berdasarkan definisi & data aktif saat ini
     * (resource, page, widget, scope per entitas, dan permission kustom).
     *
     * @return array<int, string>
     */
    public static function allPermissionNames(): array
    {
        $names = [];

        foreach (static::collect() as $group) {
            $names = array_merge($names, array_keys($group['permissions']));
        }

        return array_values(array_unique($names));
    }

    /**
     * Pastikan permission yang dipilih benar-benar opsi valid sesuai data aktif saat
     * ini, lalu buat di database bila belum ada. Permission yang tidak dikenal (mis.
     * merujuk data nonaktif/terhapus atau hasil manipulasi) diabaikan. Dipakai saat
     * menyimpan role agar tidak perlu sinkronisasi seluruh permission terlebih dahulu.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public static function ensurePermissions(array $names): array
    {
        $valid = static::allPermissionNames();
        $resolved = [];

        foreach (array_unique($names) as $name) {
            if (! in_array($name, $valid, true)) {
                continue;
            }

            Permission::findOrCreate($name, 'web');
            $resolved[] = $name;
        }

        return $resolved;
    }

    /**
     * @return array<int, Section>
     */
    public static function buildFormSections(): array
    {
        $sections = [];

        foreach (static::getGroupedPermissions() as $navGroup => $groups) {
            if ($groups === []) {
                continue;
            }

            $sections[] = Section::make(static::getGroupHeading($navGroup))
                ->description(static::getGroupDescription($navGroup))
                ->schema(array_map(
                    fn (array $group): Section => static::buildPermissionFormSection($group),
                    $groups,
                ))
                ->columns(1)
                ->collapsible()
                ->persistCollapsed()
                ->columnSpanFull();
        }

        return $sections;
    }

    /**
     * Permission yang DIBERIKAN ke sebuah role, dikelompokkan per grup menu.
     * Dipakai blade `view-role.blade.php` (Ref 04).
     *
     * @return array<string, array{heading: string, description: string, groups: array<int, array{heading: string, description: string, labels: array<int, string>}>}>
     */
    public static function getGrantedPermissionSections(Role $record): array
    {
        $result = [];

        foreach (static::getGroupedPermissions() as $navGroup => $groups) {
            $filteredGroups = [];

            foreach ($groups as $group) {
                $labels = static::getPermissionLabelsForRecord($record, $group['permissions']);

                if ($labels === []) {
                    continue;
                }

                $filteredGroups[] = [
                    'heading' => $group['heading'],
                    'description' => $group['description'],
                    'labels' => $labels,
                ];
            }

            if ($filteredGroups === []) {
                continue;
            }

            $result[$navGroup] = [
                'heading' => static::getGroupHeading($navGroup),
                'description' => static::getGroupDescription($navGroup),
                'groups' => $filteredGroups,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, Section>
     */
    public static function buildInfolistSections(): array
    {
        $sections = [];

        foreach (static::getGroupedPermissions() as $navGroup => $groups) {
            if ($groups === []) {
                continue;
            }

            $allPermissions = array_merge(...array_column($groups, 'permissions'));

            $sections[] = Section::make(static::getGroupHeading($navGroup))
                ->description(static::getGroupDescription($navGroup))
                ->schema(array_map(
                    fn (array $group): Section => static::buildPermissionInfolistSection($group),
                    $groups,
                ))
                ->columns(2)
                ->collapsible()
                ->collapsed(fn (Role $record): bool => static::getPermissionLabelsForRecord($record, $allPermissions) === [])
                ->persistCollapsed()
                ->columnSpanFull();
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string>
     */
    public static function extractFromData(array $data): array
    {
        $permissions = [];

        foreach (static::collect() as $group) {
            $selected = $data[$group['field_name']] ?? [];

            if (is_array($selected)) {
                $permissions = array_merge($permissions, $selected);
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string>  $currentPermissions
     * @return array<string, mixed>
     */
    public static function fillFormData(array $data, array $currentPermissions): array
    {
        foreach (static::collect() as $group) {
            $availablePermissions = array_keys($group['permissions']);
            $data[$group['field_name']] = array_values(array_intersect($currentPermissions, $availablePermissions));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripPermissionFields(array $data): array
    {
        foreach (static::collect() as $group) {
            unset($data[$group['field_name']]);
        }

        return $data;
    }

    /**
     * Permission dikelompokkan berdasarkan grup menu (navigation group) dan
     * diurutkan mengikuti sidebar: grup terdaftar dulu (urutan AppPanelProvider),
     * lalu grup menu lain (alfabetis), terakhir grup semu (Scope, Lainnya).
     * Tiap grup diurutkan internal by nav_sort lalu heading.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected static function getGroupedPermissions(): array
    {
        $buckets = [];

        foreach (static::collect() as $group) {
            $navGroup = $group['nav_group'] ?? static::NAV_GROUP_OTHER;
            $buckets[$navGroup] ??= [];
            $buckets[$navGroup][] = $group;
        }

        $pseudoGroups = [static::NAV_GROUP_SCOPE, static::NAV_GROUP_OTHER];

        $remaining = array_values(array_diff(
            array_keys($buckets),
            static::NAV_GROUP_ORDER,
            $pseudoGroups,
        ));
        sort($remaining);

        $orderedKeys = [...static::NAV_GROUP_ORDER, ...$remaining, ...$pseudoGroups];

        $groupedPermissions = [];

        foreach ($orderedKeys as $navGroup) {
            if (! isset($buckets[$navGroup])) {
                continue;
            }

            $groups = $buckets[$navGroup];

            usort($groups, function (array $first, array $second): int {
                $sort = ($first['nav_sort'] ?? PHP_INT_MAX) <=> ($second['nav_sort'] ?? PHP_INT_MAX);

                return $sort !== 0 ? $sort : strcmp($first['heading'], $second['heading']);
            });

            $groupedPermissions[$navGroup] = $groups;
        }

        return $groupedPermissions;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    protected static function buildPermissionFormSection(array $group): Section
    {
        return Section::make(static::groupSectionHeading($group))
            ->description($group['description'])
            ->schema([
                ViewField::make($group['field_name'])
                    ->hiddenLabel()
                    ->view('filament.forms.permission-table')
                    ->viewData([
                        'sections' => array_map(fn (array $section): array => [
                            'title' => $section['title'] ?? null,
                            'options' => $section['permissions'],
                            'descriptions' => $section['permission_descriptions'] ?? [],
                        ], $group['sections'] ?? [[
                            'title' => null,
                            'permissions' => $group['permissions'],
                            'permission_descriptions' => $group['permission_descriptions'] ?? [],
                        ]]),
                    ])
                    ->default([])
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->persistCollapsed()
            ->columnSpanFull();
    }

    /**
     * Judul section per resource/page, ditambah penanda bila item tidak tampil di menu.
     *
     * @param  array{in_menu?: bool, heading: string}  $group
     */
    protected static function groupSectionHeading(array $group): string
    {
        $heading = $group['heading'];

        if (($group['in_menu'] ?? true) === false) {
            $heading .= ' · (tidak tampil di menu)';
        }

        return $heading;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    protected static function buildPermissionInfolistSection(array $group): Section
    {
        return Section::make(static::groupSectionHeading($group))
            ->description($group['description'])
            ->schema([
                TextEntry::make($group['field_name'])
                    ->hiddenLabel()
                    ->state(fn (Role $record): array => static::getPermissionLabelsForRecord($record, $group['permissions']))
                    ->badge()
                    ->placeholder('Belum ada hak akses yang diberikan.')
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed(fn (Role $record): bool => static::getPermissionLabelsForRecord($record, $group['permissions']) === [])
            ->persistCollapsed()
            ->columnSpan(1);
    }

    protected static function getGroupHeading(string $navGroup): string
    {
        return $navGroup;
    }

    protected static function getGroupDescription(string $navGroup): string
    {
        if (isset(static::$groupDescriptions[$navGroup])) {
            return static::$groupDescriptions[$navGroup];
        }

        return match ($navGroup) {
            static::NAV_GROUP_DASHBOARD => 'Akses halaman dashboard dan widget yang tampil di dalamnya.',
            static::NAV_GROUP_SCOPE => 'Pembatasan visibilitas data per entitas scope (mis. program studi).',
            static::NAV_GROUP_OTHER => 'Hak akses tambahan yang tidak terikat pada menu tertentu.',
            default => "Hak akses untuk menu pada grup \"{$navGroup}\".",
        };
    }

    /**
     * @param  array<string, string>  $permissions
     * @return array<int, string>
     */
    protected static function getPermissionLabelsForRecord(Role $record, array $permissions): array
    {
        $grantedPermissions = $record->loadMissing('permissions')->permissions
            ->pluck('name')
            ->all();

        return collect(array_keys($permissions))
            ->filter(fn (string $permission): bool => in_array($permission, $grantedPermissions, true))
            ->map(fn (string $permission): string => $permissions[$permission])
            ->values()
            ->all();
    }
}
