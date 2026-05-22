<?php

declare(strict_types=1);

namespace App\Modules\Doctores\Http\Controllers;

use App\Modules\Doctores\Services\DoctoresService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DoctoresController
{
    public function __construct(private readonly DoctoresService $service)
    {
    }

    public function index(): View
    {
        try {
            $doctors = array_map(function (array $doctor): array {
                $performance = $this->service->getDoctorCardPerformanceSummary($doctor);
                $doctor['performance_summary'] = $performance['rating'] ?? [];
                $doctor['performance_quick_stats'] = $performance['quick_stats'] ?? [];
                return $doctor;
            }, $this->service->allDoctors());
        } catch (Throwable $e) {
            Log::error('DoctoresController::index failed: ' . $e->getMessage());
            $doctors = [];
        }

        return view('doctores.index', [
            'pageTitle' => 'Doctores',
            'doctors' => $doctors,
            'totalDoctors' => count($doctors),
        ]);
    }

    public function show(Request $request, int $doctor): View|JsonResponse|RedirectResponse
    {
        try {
            $doctorData = $this->service->findDoctor($doctor);
        } catch (Throwable $e) {
            Log::error('DoctoresController::show DB error: ' . $e->getMessage());
            return redirect('/doctores')->with('status_error', 'No se pudo cargar el perfil del doctor.');
        }

        if ($doctorData === null) {
            return redirect('/doctores');
        }

        $selectedDate = $request->query('fecha');
        if (is_string($selectedDate) && $selectedDate !== '') {
            $selectedDate = trim($selectedDate);
        } else {
            $selectedDate = null;
        }

        // JSON mode for AJAX date-paginator requests
        if ($request->query('json') === '1') {
            try {
                $payload = $this->service->buildAppointmentsJsonPayload($doctorData, $selectedDate);
            } catch (Throwable $e) {
                Log::error('DoctoresController::show JSON mode failed: ' . $e->getMessage());
                return response()->json(['error' => 'Error del servidor'], 500);
            }

            return response()->json($payload);
        }

        try {
            $insights = $this->service->buildDoctorInsights($doctorData, $selectedDate);
        } catch (Throwable $e) {
            Log::error('DoctoresController::show insights failed: ' . $e->getMessage());
            $insights = [
                'todayPatients' => [],
                'activityStats' => [],
                'careProgress' => [],
                'milestones' => [],
                'biographyParagraphs' => [],
                'availabilitySummary' => [],
                'focusAreas' => [],
                'supportChannels' => [],
                'researchHighlights' => [],
                'performanceSummary' => [],
                'operationalNotes' => [],
                'recentSurgeries' => [],
                'recentRequests' => [],
                'recentExams' => [],
                'appointmentsDays' => [],
                'appointments' => [],
                'appointmentsSelectedDate' => null,
                'appointmentsSelectedLabel' => null,
            ];
        }

        return view(
            'doctores.show',
            array_merge(
                $insights,
                [
                    'pageTitle' => $doctorData['display_name'] ?? $doctorData['name'] ?? 'Doctor',
                    'doctor' => $doctorData,
                ]
            )
        );
    }
}
