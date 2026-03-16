<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Periodt.
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Success Message --}}
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if ($prediction)
                {{-- Period Prediction Card --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-center">
                            @if ($prediction['days_until'] > 0)
                                <p class="text-sm text-gray-500 uppercase tracking-wide">Next period in</p>
                                <p class="text-5xl font-bold text-pink-600 mt-2">{{ $prediction['days_until'] }}</p>
                                <p class="text-gray-500 mt-1">days</p>
                            @elseif ($prediction['days_until'] == 0)
                                <p class="text-3xl font-bold text-pink-600">Today's the day!</p>
                                <p class="text-gray-500 mt-1">Your period is predicted to start today</p>
                            @else
                                <p class="text-sm text-gray-500 uppercase tracking-wide">Period expected</p>
                                <p class="text-3xl font-bold text-pink-600 mt-2">{{ abs($prediction['days_until']) }} days ago</p>
                                <p class="text-gray-500 mt-1">Have you started? Log it below!</p>
                            @endif

                            <div class="mt-6 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                <div class="bg-pink-50 rounded-lg p-3">
                                    <p class="text-gray-500">Predicted Start</p>
                                    <p class="font-semibold text-gray-800">{{ $prediction['predicted_start']->format('M j') }}</p>
                                </div>
                                <div class="bg-pink-50 rounded-lg p-3">
                                    <p class="text-gray-500">Likely Window</p>
                                    <p class="font-semibold text-gray-800">{{ $prediction['window_start']->format('M j') }} - {{ $prediction['window_end']->format('M j') }}</p>
                                </div>
                                <div class="bg-pink-50 rounded-lg p-3">
                                    <p class="text-gray-500">Confidence</p>
                                    <p class="font-semibold text-gray-800">{{ $prediction['confidence_pct'] }}%</p>
                                </div>
                                <div class="bg-pink-50 rounded-lg p-3">
                                    <p class="text-gray-500">Avg Cycle</p>
                                    <p class="font-semibold text-gray-800">{{ $prediction['avg_cycle_length'] }} days</p>
                                </div>
                                <div class="bg-pink-50 rounded-lg p-3">
                                    <p class="text-gray-500">Avg Period</p>
                                    <p class="font-semibold text-gray-800">{{ $prediction['avg_period_length'] }} days</p>
                                </div>
                                <div class="bg-pink-50 rounded-lg p-3">
                                    <p class="text-gray-500">Your Cycle</p>
                                    <p class="font-semibold text-gray-800 capitalize">{{ $prediction['regularity'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ovulation & Fertility Card --}}
                @php $ov = $prediction['ovulation']; @endphp
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Ovulation & Fertility</h3>
                            @if ($ov['is_next_cycle'])
                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full">Next cycle</span>
                            @endif
                        </div>

                        {{-- Current Status Banner --}}
                        <div class="rounded-lg p-4 mb-5 text-center
                            @if ($ov['status'] === 'peak fertility') bg-purple-100 border border-purple-200
                            @elseif ($ov['status'] === 'fertile') bg-purple-50 border border-purple-100
                            @else bg-gray-50 border border-gray-100
                            @endif">
                            @if ($ov['status'] === 'peak fertility')
                                <p class="text-sm text-purple-500 uppercase tracking-wide">Current Status</p>
                                <p class="text-2xl font-bold text-purple-700 mt-1">Peak Fertility</p>
                                <p class="text-sm text-purple-500 mt-1">Ovulation is happening around now</p>
                            @elseif ($ov['status'] === 'fertile')
                                <p class="text-sm text-purple-500 uppercase tracking-wide">Current Status</p>
                                <p class="text-2xl font-bold text-purple-600 mt-1">Fertile Window</p>
                                <p class="text-sm text-purple-400 mt-1">You're in your fertile window</p>
                            @else
                                @if ($ov['days_until_ovulation'] > 0)
                                    <p class="text-sm text-gray-500 uppercase tracking-wide">Ovulation in</p>
                                    <p class="text-3xl font-bold text-purple-600 mt-1">{{ $ov['days_until_ovulation'] }} days</p>
                                    <p class="text-sm text-gray-500 mt-1">Est. {{ $ov['date']->format('M j') }}</p>
                                @else
                                    <p class="text-sm text-gray-500 uppercase tracking-wide">Ovulation estimated</p>
                                    <p class="text-xl font-bold text-gray-600 mt-1">{{ abs($ov['days_until_ovulation']) }} days ago</p>
                                    <p class="text-sm text-gray-400 mt-1">Currently not in fertile window</p>
                                @endif
                            @endif
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div class="bg-purple-50 rounded-lg p-3">
                                <p class="text-gray-500">Ovulation Date</p>
                                <p class="font-semibold text-gray-800">{{ $ov['date']->format('M j') }}</p>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-3">
                                <p class="text-gray-500">Fertile Window</p>
                                <p class="font-semibold text-gray-800">{{ $ov['fertile_start']->format('M j') }} - {{ $ov['fertile_end']->format('M j') }}</p>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-3">
                                <p class="text-gray-500">Peak Fertility</p>
                                <p class="font-semibold text-gray-800">{{ $ov['peak_start']->format('M j') }} - {{ $ov['peak_end']->format('M j') }}</p>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-3">
                                <p class="text-gray-500">Est. Luteal Phase</p>
                                <p class="font-semibold text-gray-800">{{ $ov['luteal_length'] }} days</p>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-3">
                                <p class="text-gray-500">Estimate Confidence</p>
                                <p class="font-semibold text-gray-800 capitalize">{{ $ov['confidence'] }}</p>
                            </div>
                        </div>

                        <p class="text-xs text-gray-400 mt-4">
                            Ovulation estimated by counting back from predicted period start using your personal luteal phase estimate.
                            This is not a substitute for medical-grade fertility tracking (BBT, LH tests).
                        </p>
                    </div>
                </div>

                {{-- Insights --}}
                @if ($prediction['trend'] !== 'stable' || $prediction['pattern'] !== 'none')
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 text-center space-y-1">
                            @if ($prediction['trend'] !== 'stable')
                                <p class="text-xs text-pink-500">
                                    Trend detected: your cycles are {{ $prediction['trend'] }} — prediction adjusted.
                                </p>
                            @endif
                            @if ($prediction['pattern'] !== 'none')
                                <p class="text-xs text-pink-500">
                                    Pattern detected: {{ $prediction['pattern'] }} cycle — factored into prediction.
                                </p>
                            @endif
                            <p class="text-xs text-gray-400">
                                Based on {{ $prediction['cycles_analyzed'] }} cycle{{ $prediction['cycles_analyzed'] > 1 ? 's' : '' }}
                                &middot; primary method: {{ str_replace('_', ' ', $prediction['method']) }}
                            </p>
                        </div>
                    </div>
                @endif

            @else
                {{-- Welcome / No Data State --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-center py-4">
                            <p class="text-2xl font-bold text-gray-800">Welcome to Periodt.</p>
                            <p class="text-gray-500 mt-2">Log at least 2 periods to start getting predictions.</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Log Period Form --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Log a Period</h3>

                    <form method="POST" action="{{ route('cycles.store') }}" class="flex flex-wrap gap-4 items-end">
                        @csrf
                        <div class="flex-1 min-w-[150px]">
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date" required max="{{ date('Y-m-d') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500">
                            @error('start_date')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date <span class="text-gray-400">(optional)</span></label>
                            <input type="date" name="end_date" id="end_date" max="{{ date('Y-m-d') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500">
                            @error('end_date')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <button type="submit"
                                    class="px-6 py-2 bg-pink-600 text-white rounded-md hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 transition">
                                Log It
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Cycle History --}}
            @if ($cycles->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">History</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 border-b">
                                        <th class="pb-2">Start</th>
                                        <th class="pb-2">End</th>
                                        <th class="pb-2">Period Length</th>
                                        <th class="pb-2">Cycle Length</th>
                                        <th class="pb-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($cycles as $cycle)
                                        <tr>
                                            <td class="py-3">{{ $cycle->start_date->format('M j, Y') }}</td>
                                            <td class="py-3">
                                                @if ($cycle->end_date)
                                                    {{ $cycle->end_date->format('M j, Y') }}
                                                @else
                                                    <form method="POST" action="{{ route('cycles.update', $cycle) }}" class="flex items-center gap-2">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="date" name="end_date" required max="{{ date('Y-m-d') }}" min="{{ $cycle->start_date->format('Y-m-d') }}"
                                                               class="text-xs rounded border-gray-300 focus:border-pink-500 focus:ring-pink-500">
                                                        <button type="submit" class="text-pink-600 hover:text-pink-800 text-xs font-medium">Save</button>
                                                    </form>
                                                @endif
                                            </td>
                                            <td class="py-3">{{ $cycle->period_length ? $cycle->period_length . ' days' : '-' }}</td>
                                            <td class="py-3">{{ $cycle->cycle_length ? $cycle->cycle_length . ' days' : '-' }}</td>
                                            <td class="py-3 text-right">
                                                <form method="POST" action="{{ route('cycles.destroy', $cycle) }}" onsubmit="return confirm('Delete this entry?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-gray-400 hover:text-red-500 text-xs">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
