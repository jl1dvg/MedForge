<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Http\Controllers;

use App\Models\PharmacyDelivery;
use App\Models\PharmacyInventory;
use App\Models\PharmacyPrescription;
use App\Modules\Pharmacy\Services\InventoryService;
use App\Modules\Pharmacy\Services\PrescriptionService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PharmacyUiController
{
    private PrescriptionService $prescriptionService;
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->prescriptionService = new PrescriptionService();
        $this->inventoryService    = new InventoryService();
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $query = PharmacyPrescription::with(['patient', 'items']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }
        if ($request->filled('clinica')) {
            $query->where('clinica', 'LIKE', '%' . $request->query('clinica') . '%');
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_prescripcion', '>=', $request->query('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_prescripcion', '<=', $request->query('fecha_hasta'));
        }

        $prescriptions = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('pharmacy.index', [
            'pageTitle'     => 'Farmacia Pro — Recetas',
            'currentUser'   => LegacyCurrentUser::resolve($request),
            'prescriptions' => $prescriptions,
            'filters'       => $request->only(['estado', 'clinica', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $prescription = PharmacyPrescription::with([
            'patient',
            'items.inventory',
            'delivery',
            'reminders',
            'whatsappLogs',
        ])->findOrFail($id);

        return view('pharmacy.show', [
            'pageTitle'    => 'Farmacia Pro — Receta #' . $id,
            'currentUser'  => LegacyCurrentUser::resolve($request),
            'prescription' => $prescription,
        ]);
    }

    public function updateEstado(Request $request, int $id): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $data = $request->validate([
            'estado' => 'required|in:pendiente,procesada,parcial,entregada,cancelada',
        ]);

        $prescription = PharmacyPrescription::findOrFail($id);
        $this->prescriptionService->updateStatus($prescription, $data['estado']);

        return redirect('/v2/pharmacy/prescriptions/' . $id)
            ->with('success', 'Estado actualizado correctamente.');
    }

    public function inventory(Request $request): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $query = PharmacyInventory::query();

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->query('categoria'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        $items    = $query->orderBy('nombre')->paginate(30)->withQueryString();
        $lowStock = $this->inventoryService->getLowStockItems();

        return view('pharmacy.inventory', [
            'pageTitle'   => 'Farmacia Pro — Inventario',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'items'       => $items,
            'lowStock'    => $lowStock,
            'filters'     => $request->only(['categoria', 'estado']),
        ]);
    }

    public function storeInventory(Request $request): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'principio_activo' => 'nullable|string|max:255',
            'categoria'       => 'required|in:colirios,unguentos,oral,inyectables,lagrimas,antiglaucomatosos,antibioticos,antiinflamatorios,otros',
            'presentacion'    => 'nullable|string|max:255',
            'stock'           => 'nullable|integer|min:0',
            'stock_minimo'    => 'nullable|integer|min:0',
            'precio'          => 'nullable|numeric|min:0',
            'estado'          => 'nullable|in:activo,inactivo',
        ]);

        PharmacyInventory::create($data);

        return redirect('/v2/pharmacy/inventory')->with('success', 'Medicamento agregado al inventario.');
    }

    public function updateInventory(Request $request, int $id): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'principio_activo' => 'nullable|string|max:255',
            'categoria'       => 'required|in:colirios,unguentos,oral,inyectables,lagrimas,antiglaucomatosos,antibioticos,antiinflamatorios,otros',
            'presentacion'    => 'nullable|string|max:255',
            'stock'           => 'nullable|integer|min:0',
            'stock_minimo'    => 'nullable|integer|min:0',
            'precio'          => 'nullable|numeric|min:0',
            'estado'          => 'nullable|in:activo,inactivo',
        ]);

        $item = PharmacyInventory::findOrFail($id);
        $item->update($data);

        return redirect('/v2/pharmacy/inventory')->with('success', 'Inventario actualizado.');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $recetasPendientes   = PharmacyPrescription::where('estado', 'pendiente')->count();
        $procesadasEsteMes   = PharmacyPrescription::whereIn('estado', ['procesada', 'entregada'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $stockBajoCount      = $this->inventoryService->getLowStockItems()->count();
        $entregasActivas     = PharmacyDelivery::whereIn('estado', ['preparando', 'en_camino'])->count();
        $recordatoriosProximos = \App\Models\PharmacyReminder::where('estado', 'pendiente')
            ->whereBetween('fecha_recordatorio', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();

        $topMedicamentos = \Illuminate\Support\Facades\DB::table('pharmacy_prescription_items')
            ->select('nombre_medicamento', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
            ->groupBy('nombre_medicamento')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('pharmacy.dashboard', [
            'pageTitle'             => 'Farmacia Pro — Dashboard',
            'currentUser'           => LegacyCurrentUser::resolve($request),
            'recetasPendientes'     => $recetasPendientes,
            'procesadasEsteMes'     => $procesadasEsteMes,
            'stockBajoCount'        => $stockBajoCount,
            'entregasActivas'       => $entregasActivas,
            'recordatoriosProximos' => $recordatoriosProximos,
            'topMedicamentos'       => $topMedicamentos,
        ]);
    }
}
