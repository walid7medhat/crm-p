<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * iPhone / iPad can't save a generated (blob) PDF on their own — Safari just opens it in
 * a viewer. So the mobile offer uploads the finished PDF here and then navigates to a
 * short-lived signed link that is served as an attachment: Safari downloads it to Files
 * and the user stays on the CRM page.
 */
class OfferPdfController extends Controller
{
    private const DIR = 'offer-pdfs';
    private const LINK_MINUTES = 10;
    private const KEEP_SECONDS = 3600;

    /** POST /listings/properties/{id}/offer-pdf  (authenticated) */
    public function store(Request $request, $id): JsonResponse
    {
        Listing::findOrFail($id);

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:40960',
            'filename' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $this->purgeOldFiles();

        $token = Str::random(40);
        $request->file('file')->storeAs(self::DIR, $token . '.pdf', 'local');

        $filename = $request->input('filename') ?: 'sales-offer.pdf';
        if (! Str::endsWith(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        // Relative signature: valid whatever host/scheme the request arrives on (proxies).
        $url = URL::temporarySignedRoute(
            'offer-pdf.download',
            now()->addMinutes(self::LINK_MINUTES),
            ['token' => $token, 'name' => $filename],
            absolute: false
        );

        return response()->json(['status' => true, 'data' => ['url' => $url]]);
    }

    /** GET /offer-pdf/{token}?name=…&expires=…&signature=…  (signed link, no login header) */
    public function download(Request $request, string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);

        $path = self::DIR . '/' . $token . '.pdf';
        abort_unless(Storage::disk('local')->exists($path), 404);

        $name = (string) $request->query('name', 'sales-offer.pdf');
        if (preg_match('/^[A-Za-z0-9._-]+$/', $name) !== 1) {
            $name = 'sales-offer.pdf';
        }

        return response()->download(
            Storage::disk('local')->path($path),
            $name,
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store']
        );
    }

    private function purgeOldFiles(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DIR) as $file) {
            if (time() - $disk->lastModified($file) > self::KEEP_SECONDS) {
                $disk->delete($file);
            }
        }
    }
}
