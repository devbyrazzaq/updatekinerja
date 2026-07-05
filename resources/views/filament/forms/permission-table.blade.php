@php
    $statePath = $getStatePath();

    $allKeys = [];
    foreach ($sections as $section) {
        foreach (array_keys($section['options']) as $key) {
            $allKeys[] = $key;
        }
    }

    $primaryColors = \Filament\Support\Facades\FilamentColor::getColors()['primary'] ?? [];
    $onColor = is_string($primaryColors[600] ?? null) ? $primaryColors[600] : '#2563eb';
    $offColor = '#d1d5db';

    $switchBase = 'position:relative;display:inline-flex;flex-shrink:0;height:1.25rem;width:2.25rem;border-radius:9999px;cursor:pointer;border:none;padding:0;transition:background-color .2s;';
    $knobBase = 'position:absolute;top:2px;left:2px;height:1rem;width:1rem;border-radius:9999px;background:#ffffff;border:1px solid rgba(0,0,0,.12);transition:transform .2s;box-shadow:0 1px 2px rgba(0,0,0,.2);';
@endphp

<div
    x-data="{
        selected: $wire.entangle('{{ $statePath }}'),
        search: '',
        all: @js($allKeys),
        isOn(name) { return (this.selected ?? []).includes(name) },
        toggle(name) { this.selected = this.isOn(name) ? this.selected.filter(v => v !== name) : [...(this.selected ?? []), name] },
        get allSelected() { return this.all.length > 0 && (this.selected?.length ?? 0) === this.all.length },
        toggleAll(v) { this.selected = v ? [...this.all] : [] },
        matches(text) { return this.search === '' || text.includes(this.search.toLowerCase()) },
    }"
    class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10"
>
    <div class="border-b border-gray-200 p-3 dark:border-white/10">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
            <x-filament::input type="text" x-model="search" placeholder="Cari hak akses..." />
        </x-filament::input.wrapper>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Hak Akses</th>
                    <th class="px-4 py-2 font-medium">Deskripsi</th>
                    <th class="w-24 px-4 py-2 text-center font-medium">
                        <div class="flex flex-col items-center gap-1">
                            <span>Aktif</span>
                            <button type="button" role="switch" @click="toggleAll(! allSelected)"
                                :style="{ backgroundColor: allSelected ? @js($onColor) : @js($offColor) }" style="{{ $switchBase }}">
                                <span :style="{ transform: allSelected ? 'translateX(1rem)' : 'translateX(0)' }" style="{{ $knobBase }}"></span>
                            </button>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($sections as $section)
                    @if (! empty($section['title']))
                        <tr class="bg-gray-100/70 dark:bg-white/10">
                            <td colspan="3" class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ $section['title'] }}</td>
                        </tr>
                    @endif

                    @foreach ($section['options'] as $name => $label)
                        <tr x-show="matches(@js(\Illuminate\Support\Str::lower($label.' '.($section['descriptions'][$name] ?? ''))))" class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-2 align-top font-medium text-gray-950 dark:text-white">{{ $label }}</td>
                            <td class="px-4 py-2 align-top text-gray-500 dark:text-gray-400">{{ $section['descriptions'][$name] ?? '—' }}</td>
                            <td class="px-4 py-2 text-center align-top">
                                <button type="button" role="switch" @click="toggle(@js($name))"
                                    :style="{ backgroundColor: isOn(@js($name)) ? @js($onColor) : @js($offColor) }" style="{{ $switchBase }}">
                                    <span :style="{ transform: isOn(@js($name)) ? 'translateX(1rem)' : 'translateX(0)' }" style="{{ $knobBase }}"></span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
