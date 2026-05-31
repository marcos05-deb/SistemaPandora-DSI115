<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Expediente;
use Illuminate\Support\Facades\Auth;

class EnforceAreaScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Especialista|null $especialista */
        $especialista = Auth::user();

        if (!$especialista) {
            return abort(401);
        }

        // El sysadmin no puede acceder a rutas clínicas
        if ($especialista->hasRole('sysadmin')) {
            return abort(403, 'Acceso denegado: El rol de administrador de sistema no tiene acceso a datos clínicos.');
        }

        $expedienteId = $request->route('expediente');

        if ($expedienteId) {
            $areaIds = $especialista->areas()->pluck('areas.id')->toArray();
            
            if ($expedienteId instanceof Expediente) {
                if (!in_array($expedienteId->area_id, $areaIds)) {
                    return abort(403, 'Acceso denegado a este expediente clínico.');
                }
            } else {
                $expediente = Expediente::withoutGlobalScopes()->find($expedienteId);
                
                if ($expediente && !in_array($expediente->area_id, $areaIds)) {
                    return abort(403, 'Acceso denegado a este expediente clínico.');
                }
            }
        }

        return $next($request);
    }
}
