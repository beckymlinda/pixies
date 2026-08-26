<?php

namespace App\Http\Controllers;

use App\Models\Bar;
use App\Models\DamagedGood;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DamagedGoodController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $bars = Bar::orderBy('name')->get();

        $query = DamagedGood::with(['bar', 'user'])
            ->where('from_balance', true)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($user->isSeller() && $user->bar_id) {
            $query->where('bar_id', $user->bar_id);
        } elseif ($user->isSeller()) {
            $query->whereRaw('1 = 0');
        } elseif ($request->filled('bar_id')) {
            $query->where('bar_id', $request->integer('bar_id'));
        }

        $damagedGoods = $query->paginate(15)->withQueryString();

        return view('damaged-goods.index', compact('damagedGoods', 'bars'));
    }

    /**
     * Stream a damage photo straight from the storage disk.
     *
     * Serving through a route avoids depending on the public/storage
     * symlink, which shared hosts (cPanel) often break or block with
     * a 403 when following symlinks outside the document root.
     */
    public function photo(Request $request, DamagedGood $damagedGood): StreamedResponse
    {
        $user = $request->user();

        // Sellers may only view photos recorded at their own bar.
        if ($user->isSeller() && $damagedGood->bar_id !== $user->bar_id) {
            abort(403);
        }

        if (!$damagedGood->photo_path || !Storage::disk('public')->exists($damagedGood->photo_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($damagedGood->photo_path);
    }

    public static function storeDamagePhoto(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        return $file->store('damaged-goods', 'public');
    }

    public static function deleteDamagePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
