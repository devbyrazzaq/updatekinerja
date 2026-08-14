@php
    $files = $files ?? [];
@endphp

<div class="w-full space-y-6">
    @forelse ($files as $file)
        @php
            $extension = strtolower($file['extension'] ?? '');
            $url = $file['url'] ?? null;
            $name = $file['name'] ?? 'Berkas';
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
            $isPdf = $extension === 'pdf';
        @endphp

        <div class="w-full">
            @if (count($files) > 1)
                <p class="mb-2 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <span class="inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">{{ $loop->iteration }}</span>
                    <span class="truncate">{{ $name }}</span>
                </p>
            @endif

            @if (blank($url))
                <p class="text-sm text-gray-500 dark:text-gray-400">Berkas tidak tersedia.</p>
            @elseif ($isImage)
                <img
                    src="{{ $url }}"
                    alt="{{ $name }}"
                    class="mx-auto max-h-[70vh] w-auto rounded-lg"
                >
            @elseif ($isPdf)
                <iframe
                    src="{{ $url }}"
                    title="{{ $name }}"
                    class="h-[70vh] w-full rounded-lg border border-gray-200 dark:border-gray-700"
                ></iframe>
            @else
                <div class="flex flex-col items-center gap-3 py-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Pratinjau tidak tersedia untuk jenis berkas ini.
                    </p>
                    <a
                        href="{{ $url }}"
                        target="_blank"
                        rel="noopener"
                        class="fi-link inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400"
                    >
                        Unduh berkas
                    </a>
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Berkas tidak tersedia.</p>
    @endforelse
</div>
