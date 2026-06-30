<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Http\Controllers;

use App\Models\PharmacyDelivery;
use App\Models\PharmacyInventory;
use App\Models\PharmacyPrescription;
use App\Models\PharmacyReminder;
use App\Modules\Pharmacy\Services\InventoryService;
use App\Modules\Pharmacy\Services\PrescriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PharmacyUiController
{
    private PrescriptionService $prescriptionService;
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->prescriptionService = new PrescriptionService();
        $this->inventoryService    = new InventoryService();
    }

    private function authGuard(): ?RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }
        return null;
    }

    /** All web UI routes return the React shell */
    public function app(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        return view('pharmacy.app');
    }

    /** @deprecated kept for backward compat — redirects to React shell */
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        return view('pharmacy.app');
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        return view('pharmacy.app');
    }

    public function inventory(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        return view('pharmacy.app');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        return view('pharmacy.app');
    }

    /** @deprecated Blade form action — kept for routes file compat */
    public function updateEstado(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        $data = $request->validate([
            'estado' => 'required|in:pendiente,procesada,parcial,entregada,cancelada',
        ]);
        $prescription = PharmacyPrescription::findOrFail($id);
        $this->prescriptionService->updateStatus($prescription, $data['estado']);
        return redirect('/v2/pharmacy')->with('success', 'Estado actualizado correctamente.');
    }

    /** @deprecated */
    public function storeInventory(Request $request): RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        $data = $this->validateInventoryRequest($request);
        PharmacyInventory::create($data);
        return redirect('/v2/pharmacy/inventory')->with('success', 'Medicamento agregado.');
    }

    /** @deprecated */
    public function updateInventory(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->authGuard()) return $redirect;
        $data = $this->validateInventoryRequest($request);
        PharmacyInventory::findOrFail($id)->update($data);
        return redirect('/v2/pharmacy/inventory')->with('success', 'Inventario actualizado.');
    }

    // -------------------------------------------------------------------------
    // JSON API methods (used by React frontend)
    // -------------------------------------------------------------------------

    public function apiPrescriptions(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $query = PharmacyPrescription::with(['patient', 'items']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('medico', 'LIKE', $search)
                  ->orWhere('clinica', 'LIKE', $search)
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('nombres', 'LIKE', $search)
                         ->orWhere('apellidos', 'LIKE', $search)
                         ->orWhere('identificacion', 'LIKE', $search);
                  });
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate(25);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function apiPrescription(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $prescription = PharmacyPrescription::with([
            'patient',
            'items.inventory',
            'whatsappLogs',
        ])->findOrFail($id);

        return response()->json(['data' => $prescription]);
    }

    public function apiUpdateEstado(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'estado' => 'required|in:pendiente,procesada,parcial,entregada,cancelada',
        ]);

        $prescription = PharmacyPrescription::findOrFail($id);
        $this->prescriptionService->updateStatus($prescription, $data['estado']);
        $prescription->refresh()->load(['patient', 'items', 'whatsappLogs']);

        return response()->json(['data' => $prescription]);
    }

    public function apiInventory(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $query = PharmacyInventory::query();

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->query('categoria'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereRaw('stock <= stock_minimo');
        }

        $items = $query->orderBy('nombre')->get();

        return response()->json(['data' => $items]);
    }

    public function apiStoreInventory(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $this->validateInventoryRequest($request);
        $item = PharmacyInventory::create($data);

        return response()->json(['data' => $item], 201);
    }

    public function apiUpdateInventory(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $this->validateInventoryRequest($request);
        $item = PharmacyInventory::findOrFail($id);
        $item->update($data);

        return response()->json(['data' => $item->fresh()]);
    }

    public function apiDashboard(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $recetasPendientes     = PharmacyPrescription::where('estado', 'pendiente')->count();
        $procesadasEsteMes     = PharmacyPrescription::whereIn('estado', ['procesada', 'entregada'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $stockBajo             = $this->inventoryService->getLowStockItems()->count();
        $entregasActivas       = PharmacyDelivery::whereIn('estado', ['preparando', 'en_camino'])->count();
        $recordatoriosProximos = PharmacyReminder::where('estado', 'pendiente')
            ->whereBetween('fecha_recordatorio', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();

        $topMedicamentos = DB::table('pharmacy_prescription_items')
            ->select('nombre_medicamento as nombre', DB::raw('COUNT(*) as count'))
            ->groupBy('nombre_medicamento')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['nombre' => $row->nombre, 'count' => (int) $row->count])
            ->values()
            ->toArray();

        return response()->json([
            'data' => [
                'recetas_pendientes'     => $recetasPendientes,
                'procesadas_este_mes'    => $procesadasEsteMes,
                'stock_bajo'             => $stockBajo,
                'entregas_activas'       => $entregasActivas,
                'recordatorios_proximos' => $recordatoriosProximos,
                'top_medicamentos'       => $topMedicamentos,
            ],
        ]);
    }

    private function validateInventoryRequest(Request $request): array
    {
        return $request->validate([
            'nombre'           => 'required|string|max:255',
            'principio_activo' => 'nullable|string|max:255',
            'categoria'        => 'required|in:colirios,unguentos,oral,inyectables,lagrimas,antiglaucomatosos,antibioticos,antiinflamatorios,otros',
            'presentacion'     => 'nullable|string|max:255',
            'stock'            => 'nullable|integer|min:0',
            'stock_minimo'     => 'nullable|integer|min:0',
            'precio'           => 'nullable|numeric|min:0',
            'estado'           => 'nullable|in:activo,inactivo',
        ]);
    }
}
