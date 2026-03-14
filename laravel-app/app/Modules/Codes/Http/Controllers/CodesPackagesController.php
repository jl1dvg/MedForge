<?php

declare(strict_types=1);

namespace App\Modules\Codes\Http\Controllers;

use App\Modules\Codes\Services\CodesPackageService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class CodesPackagesController
{
    private CodesPackageService $packages;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->packages = new CodesPackageService($pdo);
    }

    public function list(Request $request): JsonResponse
    {
        $filters = [
            'active' => (int) $request->query('active', 0),
            'search' => (string) $request->query('q', ''),
            'limit' => (int) $request->query('limit', 50),
            'offset' => (int) $request->query('offset', 0),
        ];

        return response()->json([
            'ok' => true,
            'data' => $this->packages->list($filters),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $package = $this->packages->find($id);
        if ($package === null) {
            return response()->json(['ok' => false, 'error' => 'Paquete no encontrado'], 404);
        }

        return response()->json(['ok' => true, 'data' => $package]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $payload = $this->getPayload($request);
            $package = $this->packages->create($payload, LegacySessionAuth::userId($request) ?? 0);

            return response()->json(['ok' => true, 'data' => $package], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['ok' => false, 'error' => 'No se pudo guardar el paquete'], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $payload = $this->getPayload($request);
            $package = $this->packages->update($id, $payload, LegacySessionAuth::userId($request) ?? 0);
            if ($package === null) {
                return response()->json(['ok' => false, 'error' => 'Paquete no encontrado'], 404);
            }

            return response()->json(['ok' => true, 'data' => $package]);
        } catch (RuntimeException $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['ok' => false, 'error' => 'No se pudo actualizar el paquete'], 500);
        }
    }

    public function delete(int $id): JsonResponse
    {
        $deleted = $this->packages->delete($id);

        if ($deleted) {
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'error' => 'No se pudo eliminar el paquete'], 500);
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayload(Request $request): array
    {
        $json = $request->json()->all();
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $all = $request->all();

        return is_array($all) ? $all : [];
    }
}

