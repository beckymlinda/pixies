<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Item;
use App\Models\Bar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderRequestController extends Controller
{
    /**
     * Seller: List past stock requests.
     */
    public function sellerIndex()
    {
        $user = Auth::user();
        if (!$user->isSeller()) {
            abort(403, 'Only sellers can access this page.');
        }

        // Mark processed requests as seen before loading so badges clear immediately
        OrderRequest::where('bar_id', $user->bar_id)
            ->where('status', '!=', 'pending')
            ->where('seller_notified', false)
            ->update(['seller_notified' => true]);

        $requests = OrderRequest::where('bar_id', $user->bar_id)
            ->with(['items.item', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('seller.orders.index', compact('requests'));
    }

    /**
     * Seller: Form to request new stock.
     */
    public function sellerCreate()
    {
        $user = Auth::user();
        if (!$user->isSeller()) {
            abort(403, 'Only sellers can place stock requests.');
        }

        if (!$user->bar_id) {
            return redirect()->route('seller.dashboard')->with('error', 'You must be assigned to a bar to request stock.');
        }

        // Get all items grouped by category
        $items = Item::orderBy('category')->orderBy('name')->get();
        $bar = $user->bar;

        return view('seller.orders.create', compact('items', 'bar'));
    }

    /**
     * Seller: Store a new stock request.
     */
    public function sellerStore(Request $request)
    {
        $user = Auth::user();
        if (!$user->isSeller()) {
            abort(403);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $activeItems = collect($request->items)->filter(function ($item) {
            return ($item['quantity'] ?? 0) > 0;
        });

        if ($activeItems->isEmpty()) {
            return back()->withInput()->with('error', 'You must request at least one item with a quantity greater than zero.');
        }

        DB::beginTransaction();
        try {
            $orderRequest = OrderRequest::create([
                'user_id' => $user->id,
                'bar_id' => $user->bar_id,
                'status' => 'pending',
                'notes' => $request->notes,
                'seller_notified' => false,
                'date' => now()->format('Y-m-d'),
            ]);

            foreach ($activeItems as $itemData) {
                OrderRequestItem::create([
                    'order_request_id' => $orderRequest->id,
                    'item_id' => $itemData['item_id'],
                    'requested_quantity' => $itemData['quantity'],
                    'approved_quantity' => 0, // defaults to 0 until approved
                ]);
            }

            DB::commit();
            return redirect()->route('seller.orders.index')->with('success', 'Stock request submitted successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Error creating stock request: ' . $e->getMessage());
        }
    }

    /**
     * Director: Dashboard of all stock requests.
     */
    public function directorIndex(Request $request)
    {
        $user = Auth::user();
        if (!$user->isDirector()) {
            abort(403, 'Only directors can access order approvals.');
        }

        $statusFilter = $request->get('status', 'all');

        OrderRequest::markDirectorPendingAsSeen();

        $query = OrderRequest::with(['user', 'bar', 'items.item']);

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        } else {
            // Sort pending first, then by date desc
            $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                  ->orderBy('created_at', 'desc');
        }

        $requests = $query->paginate(10)->withQueryString();

        return view('director.orders.index', compact('requests', 'statusFilter'));
    }

    /**
     * Director: Detailed view of a single request — always editable for toggling.
     */
    public function directorShow(OrderRequest $orderRequest)
    {
        $user = Auth::user();
        if (!$user->isDirector()) {
            abort(403);
        }

        $orderRequest->load(['user', 'bar', 'items.item']);

        OrderRequest::markDirectorPendingAsSeen([$orderRequest->id]);

        return view('director.orders.show', compact('orderRequest'));
    }

    /**
     * Director: Process approval, denial, partial approval, or reopen (toggle).
     *
     * Actions:
     *  - approve  : Set approved quantities, deduct from director_stock. Refunds old approved quantities first if re-approving.
     *  - deny     : Set status to denied. Refunds any previously approved stock.
     *  - reopen   : Reset request back to pending. Refunds any previously approved stock so it can be re-reviewed.
     */
    public function directorApprove(Request $request, OrderRequest $orderRequest)
    {
        $user = Auth::user();
        if (!$user->isDirector()) {
            abort(403);
        }

        $request->validate([
            'action' => 'required|in:approve,deny,reopen',
            'items' => 'required_if:action,approve|array',
            'items.*.approved_quantity' => 'required_if:action,approve|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {

            // ── REOPEN: reset to pending ──
            if ($request->action === 'reopen') {
                // Reset all approved quantities to 0
                $orderRequest->items()->update(['approved_quantity' => 0]);

                $orderRequest->update([
                    'status' => 'pending',
                    'notes' => $request->notes ?? $orderRequest->notes,
                    'seller_notified' => false,
                ]);

                $seenIds = array_values(array_diff(session('director_seen_order_ids', []), [$orderRequest->id]));
                session(['director_seen_order_ids' => $seenIds]);

                DB::commit();
                return redirect()->route('director.orders.show', $orderRequest)
                    ->with('success', 'Request has been reopened and reset to Pending.');
            }

            // ── DENY: deny request ──
            if ($request->action === 'deny') {
                // Reset approved quantities
                $orderRequest->items()->update(['approved_quantity' => 0]);

                $orderRequest->update([
                    'status' => 'denied',
                    'notes' => $request->notes,
                    'seller_notified' => false,
                ]);

                DB::commit();
                return redirect()->route('director.orders.index')
                    ->with('success', 'Stock request denied.');
            }

            // ── APPROVE (or re-approve) ──
            $isPartial = false;
            $allZero = true;

            foreach ($request->items as $itemId => $itemData) {
                $reqItem = $orderRequest->items()->where('item_id', $itemId)->first();
                if (!$reqItem) continue;

                $approvedQty = (int)$itemData['approved_quantity'];
                $item = Item::findOrFail($itemId);

                // If approved quantity exceeds unified stock, automatically increase it
                if ($approvedQty > $item->director_stock) {
                    $item->update(['director_stock' => $approvedQty]);
                }

                if ($approvedQty < $reqItem->requested_quantity) {
                    $isPartial = true;
                }

                if ($approvedQty > 0) {
                    $allZero = false;
                }

                // Save new approved quantity
                $reqItem->update(['approved_quantity' => $approvedQty]);
            }

            // Determine resulting status
            if ($allZero) {
                $status = 'denied';
            } elseif ($isPartial) {
                $status = 'partially_approved';
            } else {
                $status = 'approved';
            }

            $orderRequest->update([
                'status' => $status,
                'notes' => $request->notes,
                'seller_notified' => false,
            ]);

            DB::commit();

            $label = match($status) {
                'approved' => '✅ Approved',
                'partially_approved' => 'ℹ️ Partially Approved',
                'denied' => '❌ Denied (all quantities were 0)',
                default => $status,
            };

            return redirect()->route('director.orders.show', $orderRequest)
                ->with('success', "Stock request updated: {$label}. Seller has been notified.");

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Helper: Under unified stock, approvals don't decrement stock, so refund is a no-op.
     */
    private function refundApprovedStock(OrderRequest $orderRequest): void
    {
        return;
    }
}
