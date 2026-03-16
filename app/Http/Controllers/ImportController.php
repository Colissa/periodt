<?php

namespace App\Http\Controllers;

use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportController extends Controller
{
    public function show()
    {
        return view('import');
    }

    public function preview(Request $request, ImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:csv,txt,xml,json',
        ]);

        $file = $request->file('file');
        $contents = file_get_contents($file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());

        // Normalize txt to csv
        if ($extension === 'txt') $extension = 'csv';

        $result = $importService->parse($contents, $extension);

        if (empty($result['periods'])) {
            return back()->withErrors(['file' => 'No period data found. ' . implode(' ', $result['warnings'])]);
        }

        session(['import_preview' => $result]);

        return view('import', [
            'preview' => $result['periods'],
            'format' => $result['format'],
            'warnings' => $result['warnings'],
        ]);
    }

    public function quickEntry(Request $request, ImportService $importService)
    {
        $request->validate([
            'dates' => 'required|string|min:6',
        ]);

        $result = $importService->parseManualDates($request->input('dates'));

        if (empty($result['periods'])) {
            return back()->withErrors(['dates' => 'No valid dates found. ' . implode(' ', $result['warnings'])]);
        }

        session(['import_preview' => $result]);

        return view('import', [
            'preview' => $result['periods'],
            'format' => $result['format'],
            'warnings' => $result['warnings'],
            'tab' => 'quick',
        ]);
    }

    public function confirm(ImportService $importService)
    {
        $result = session('import_preview');

        if (!$result || empty($result['periods'])) {
            return redirect()->route('import.show')->withErrors(['file' => 'No data to import. Please upload a file first.']);
        }

        $stats = $importService->import(Auth::user(), $result['periods']);

        session()->forget('import_preview');

        $message = "Imported {$stats['imported']} period" . ($stats['imported'] !== 1 ? 's' : '') . ".";
        if ($stats['skipped'] > 0) {
            $message .= " Skipped {$stats['skipped']} duplicate" . ($stats['skipped'] !== 1 ? 's' : '') . ".";
        }

        return redirect()->route('dashboard')->with('success', $message);
    }
}
