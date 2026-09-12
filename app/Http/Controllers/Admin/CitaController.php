<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCitasRequest;
use App\Http\Requests\CitasPorDiaRequest;
use App\Http\Resources\CitaGridResource;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use Carbon\CarbonImmutable;
use Inertia\Inertia;

class CitaController extends Controller
{
    public function index(IndexCitasRequest $request)
    {
        // Si no vienen parámetros, por defecto mandamos mes actual +/- padding
        // Pero lo normal es que Vue mande start y end. Si no vienen, Vue los pedirá inmediatamente.
        if (!$request->has('start') || !$request->has('end')) {
            return Inertia::render('Admin/Citas/Index', [
                'citasBase' => []
            ]);
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d', $request->start, 'America/El_Salvador')
            ->startOfDay()
            ->setTimezone('UTC');

        $end = CarbonImmutable::createFromFormat('Y-m-d', $request->end, 'America/El_Salvador')
            ->endOfDay()
            ->setTimezone('UTC');

        $citas = Cita::withoutGlobalScope(\App\Models\Scopes\AreaScope::class)
            ->whereBetween('fecha_hora', [$start, $end])
            ->get();

        return Inertia::render('Admin/Citas/Index', [
            'citasBase' => CitaGridResource::collection($citas)->resolve()
        ]);
    }

    public function citasPorDia(CitasPorDiaRequest $request, string $date)
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $date, 'America/El_Salvador')
            ->startOfDay()
            ->setTimezone('UTC');

        $end = CarbonImmutable::createFromFormat('Y-m-d', $date, 'America/El_Salvador')
            ->endOfDay()
            ->setTimezone('UTC');

        $citas = Cita::withoutGlobalScope(\App\Models\Scopes\AreaScope::class)
            ->with(['expediente.paciente', 'profesional.especialista', 'area'])
            ->whereBetween('fecha_hora', [$start, $end])
            ->orderBy('fecha_hora')
            ->get();

        return CitaResource::collection($citas);
    }
}
