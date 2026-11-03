<?php

namespace App\Http\Middleware;

use App\Models\Especialista;
use App\Models\Expediente;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAreaScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Especialista|null $especialista */
        $especialista = Auth::user();

        if (! $especialista) {
            return abort(401);
        }

        if ($especialista->hasRole('sysadmin')) {
            return abort(403, 'El Administrador del sistema no tiene acceso a operaciones clínicas.');
        }

        $expedienteId = $request->route('expediente');

        if ($expedienteId) {
            $areaIds = $especialista->areas()->pluck('areas.id')->toArray();

            if ($expedienteId instanceof Expediente) {
                if (! in_array($expedienteId->area_id, $areaIds)) {
                    return abort(403, 'No tienes acceso a este expediente clínico. Solo puedes ver expedientes de tu área.');
                }
            } else {
                $expediente = Expediente::withoutGlobalScopes()->find($expedienteId);

                if ($expediente && ! in_array($expediente->area_id, $areaIds)) {
                    return abort(403, 'No tienes acceso a este expediente clínico. Solo puedes ver expedientes de tu área.');
                }
            }
        }

        return $next($request);
    }
}
