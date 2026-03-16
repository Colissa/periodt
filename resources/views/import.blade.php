<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Import History
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (!isset($preview))
                {{-- Tab Switcher --}}
                <div x-data="{ tab: '{{ old('tab', $tab ?? 'file') }}' }" class="space-y-6">

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200">
                            <nav class="flex -mb-px">
                                <button @click="tab = 'file'"
                                        :class="tab === 'file' ? 'border-pink-500 text-pink-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm transition">
                                    File Upload
                                </button>
                                <button @click="tab = 'quick'"
                                        :class="tab === 'quick' ? 'border-pink-500 text-pink-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm transition">
                                    Quick Entry
                                </button>
                            </nav>
                        </div>

                        {{-- File Upload Tab --}}
                        <div x-show="tab === 'file'" class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">Upload Your Data</h3>
                            <p class="text-gray-500 text-sm mb-6">
                                Upload a CSV, JSON, or XML export from your period tracking app. We'll auto-detect the format.
                            </p>

                            @if ($errors->has('file'))
                                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                                    {{ $errors->first('file') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('import.preview') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="flex flex-wrap gap-4 items-end">
                                    <div class="flex-1 min-w-[250px]">
                                        <label for="file" class="block text-sm font-medium text-gray-700 mb-1">File</label>
                                        <input type="file" name="file" id="file" required accept=".csv,.txt,.xml,.json"
                                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                                    </div>
                                    <div>
                                        <button type="submit"
                                                class="px-6 py-2 bg-pink-600 text-white rounded-md hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 transition">
                                            Preview
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        {{-- Quick Entry Tab --}}
                        <div x-show="tab === 'quick'" class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">Quick Entry</h3>
                            <p class="text-gray-500 text-sm mb-6">
                                Type or paste your period dates — one per line. Great for Samsung Health users or anyone entering dates manually from another app's screen.
                            </p>

                            @if ($errors->has('dates'))
                                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                                    {{ $errors->first('dates') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('import.quick-entry') }}">
                                @csrf
                                <div class="mb-4">
                                    <label for="dates" class="block text-sm font-medium text-gray-700 mb-1">Period Dates</label>
                                    <textarea name="dates" id="dates" rows="10" required
                                              placeholder="One period per line. Examples:&#10;&#10;2025-01-15&#10;2025-02-12 - 2025-02-17&#10;Mar 10, 2025&#10;4/7/2025 to 4/12/2025&#10;2025-05-05"
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500 text-sm font-mono">{{ old('dates') }}</textarea>
                                </div>

                                <div class="flex items-center justify-between">
                                    <p class="text-xs text-gray-400">Supports most date formats. Use " - " or "to" to include end dates.</p>
                                    <button type="submit"
                                            class="px-6 py-2 bg-pink-600 text-white rounded-md hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 transition">
                                        Preview
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Supported Formats --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Supported Apps</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div class="border border-gray-100 rounded-lg p-4">
                                    <p class="font-semibold text-gray-800">Clue</p>
                                    <p class="text-gray-500 mt-1">Export: Menu &rarr; Settings &rarr; Data Export &rarr; Download CSV. Upload the CSV directly.</p>
                                </div>
                                <div class="border border-gray-100 rounded-lg p-4">
                                    <p class="font-semibold text-gray-800">Flo</p>
                                    <p class="text-gray-500 mt-1">Export: Profile &rarr; Settings &rarr; Export Data. Upload the CSV.</p>
                                </div>
                                <div class="border border-gray-100 rounded-lg p-4">
                                    <p class="font-semibold text-gray-800">Apple Health</p>
                                    <p class="text-gray-500 mt-1">Export: Profile icon &rarr; Export All Health Data. Upload the <code class="text-pink-600">export.xml</code> file.</p>
                                </div>
                                <div class="border border-gray-100 rounded-lg p-4">
                                    <p class="font-semibold text-gray-800">Samsung Health</p>
                                    <p class="text-gray-500 mt-1">Samsung doesn't export period data in their standard CSV. Use <strong>Quick Entry</strong> — open your cycle history in Samsung Health and type the dates here. If you have a JSON export from dev mode, upload that.</p>
                                </div>
                                <div class="border border-gray-100 rounded-lg p-4">
                                    <p class="font-semibold text-gray-800">Generic CSV</p>
                                    <p class="text-gray-500 mt-1">Any CSV with a <code class="text-pink-600">start_date</code> column. Optional: <code class="text-pink-600">end_date</code>. Most date formats work.</p>
                                </div>
                                <div class="border border-gray-100 rounded-lg p-4">
                                    <p class="font-semibold text-gray-800">Any Other App</p>
                                    <p class="text-gray-500 mt-1">Use <strong>Quick Entry</strong> to type dates from any app's history screen. Supports dates in any format, one per line.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            @else
                {{-- Preview Step --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Preview</h3>
                            <span class="text-xs bg-pink-100 text-pink-700 px-2 py-1 rounded-full capitalize">
                                {{ str_replace('_', ' ', $format) }}
                            </span>
                        </div>

                        @if (!empty($warnings))
                            <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg mb-4 text-sm">
                                @foreach ($warnings as $warning)
                                    <p>{{ $warning }}</p>
                                @endforeach
                            </div>
                        @endif

                        <p class="text-sm text-gray-500 mb-4">Found <span class="font-semibold text-gray-800">{{ count($preview) }}</span> periods. Review below, then confirm to import.</p>

                        <div class="overflow-x-auto max-h-80 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-white">
                                    <tr class="text-left text-gray-500 border-b">
                                        <th class="pb-2 pr-4">#</th>
                                        <th class="pb-2">Start Date</th>
                                        <th class="pb-2">End Date</th>
                                        <th class="pb-2">Days</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($preview as $i => $period)
                                        <tr>
                                            <td class="py-2 pr-4 text-gray-400">{{ $i + 1 }}</td>
                                            <td class="py-2">{{ \Carbon\Carbon::parse($period['start_date'])->format('M j, Y') }}</td>
                                            <td class="py-2">
                                                @if ($period['end_date'])
                                                    {{ \Carbon\Carbon::parse($period['end_date'])->format('M j, Y') }}
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                @if ($period['end_date'])
                                                    {{ \Carbon\Carbon::parse($period['start_date'])->diffInDays(\Carbon\Carbon::parse($period['end_date'])) + 1 }}
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <form method="POST" action="{{ route('import.confirm') }}">
                                @csrf
                                <button type="submit"
                                        class="px-6 py-2 bg-pink-600 text-white rounded-md hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 transition">
                                    Import {{ count($preview) }} Periods
                                </button>
                            </form>
                            <a href="{{ route('import.show') }}"
                               class="px-6 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
