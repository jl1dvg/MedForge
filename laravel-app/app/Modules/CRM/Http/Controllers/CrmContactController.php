<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmContactResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CrmContactController
{
    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
    ) {}

    public function show(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $contact = CrmContact::query()->with('opportunities')->find($id);
        if (!$contact instanceof CrmContact) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        return response()->json(['data' => $contact]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $contact = CrmContact::query()->find($id);
        if (!$contact instanceof CrmContact) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        $validated = $request->validate([
            'cedula'     => 'sometimes|string|max:30',
            'patient_id' => 'sometimes|nullable|integer',
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|nullable|email',
        ]);
        if (isset($validated['cedula'])) {
            $contact->cedula = $validated['cedula'];
            $contact->resolution = isset($validated['patient_id'])
                ? CrmContact::RESOLUTION_LINKED
                : CrmContact::RESOLUTION_IDENTIFIED;
        }
        if (array_key_exists('patient_id', $validated)) {
            $contact->patient_id = $validated['patient_id'];
            if ($contact->cedula) {
                $contact->resolution = CrmContact::RESOLUTION_LINKED;
            }
        }
        $contact->fill(array_intersect_key($validated, array_flip(['name', 'email'])));
        $contact->save();
        return response()->json(['data' => $contact->fresh()]);
    }

    public function merge(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $validated = $request->validate(['merge_into_id' => 'required|integer|different:id']);
        $source = CrmContact::query()->find($id);
        $target = CrmContact::query()->find($validated['merge_into_id']);
        if (!$source instanceof CrmContact || !$target instanceof CrmContact) {
            return response()->json(['error' => 'Contacto no encontrado'], 404);
        }
        DB::transaction(function () use ($source, $target): void {
            CrmOpportunity::query()->where('contact_id', $source->id)->update(['contact_id' => $target->id]);
            $source->delete();
        });
        return response()->json(['data' => $target->fresh('opportunities')]);
    }
}
