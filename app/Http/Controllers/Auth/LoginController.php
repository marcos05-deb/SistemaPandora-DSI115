<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Especialista;
use App\Services\Auth\JwtService;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LoginController — HU-01.
 *
 * Maneja la autenticación de Especialistas:
 * - Validación de credenciales contra la tabla users.
 * - Derivación de clave simétrica (Argon2id KDF) y almacenamiento en sesión.
 * - Bloqueo de cuenta tras 3 intentos fallidos (15 min).
 * - Emisión de token JWT (exp. 8h) en cookie HttpOnly.
 * - Mensajes genéricos que no revelan si el email existe.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly KeyDerivationService $kdf,
        private readonly JwtService $jwt,
    ) {}

    /**
     * Muestra la vista de login.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Procesa el intento de login.
     *
     * Flujo (HU-01):
     * 1. Verificar bloqueo de cuenta (3 intentos fallidos = 15 min).
     * 2. Validar credenciales (email + password).
     * 3. Autenticar con Auth::attempt().
     * 4. Resetear contador de intentos fallidos.
     * 5. Emitir token JWT (exp. 8h) en cookie HttpOnly.
     * 6. Derivar clave simétrica con sodium_crypto_pwhash (Argon2id).
     * 7. Almacenar clave en sesión como Base64.
     * 8. Limpiar contraseña de memoria con sodium_memzero().
     * 9. Regenerar sesión para prevenir session fixation.
     */
    public function store(LoginRequest $request)
    {
        $email = Str::lower($request->input('email'));

        $existingUser = Especialista::where('email', $email)->first();

        if ($existingUser && $existingUser->locked_until && $existingUser->locked_until->getTimestamp() > now()->getTimestamp()) {
            $remaining = $existingUser->locked_until->diffInMinutes(now());
            return back()->withErrors([
                'email' => "Cuenta bloqueada. Intente nuevamente en {$remaining} minutos.",
            ])->onlyInput('email');
        }

        if ($existingUser && !$existingUser->is_active) {
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas son incorrectas.',
            ])->onlyInput('email');
        }

        if ($existingUser && $existingUser->trashed()) {
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas son incorrectas.',
            ])->onlyInput('email');
        }

        $credentials = $request->only('email', 'password');
        $passwordPlain = $request->input('password');

        if (!Auth::attempt($credentials)) {
            if ($existingUser) {
                $existingUser->increment('failed_login_attempts');
                if ($existingUser->failed_login_attempts >= 3) {
                    $existingUser->locked_until = now()->utc()->addMinutes(15);
                    $existingUser->failed_login_attempts = 0;
                    $existingUser->save();

                    return back()->withErrors([
                        'email' => 'Cuenta bloqueada por seguridad. Intente nuevamente en 15 minutos.',
                    ])->onlyInput('email');
                }
            }

            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas son incorrectas.',
            ])->onlyInput('email');
        }

        // Regenerar sesión para prevenir session fixation
        $request->session()->regenerate();

        // Resetear contador de intentos fallidos y desbloquear cuenta tras login exitoso
        /** @var Especialista $especialista */
        $especialista = Auth::user();
        $especialista->failed_login_attempts = 0;
        $especialista->locked_until = null;

        // Generar KDF salt si el especialista no tiene
        if (empty($especialista->kdf_salt)) {
            $especialista->kdf_salt = $this->kdf->generateSalt();
        }

        // Derivar clave simétrica a partir de la contraseña + salt
        $saltRaw = base64_decode($especialista->kdf_salt, strict: true);
        $derivedKey = $this->kdf->derive($passwordPlain, $saltRaw);
        session(['_sym_key' => base64_encode($derivedKey)]);

        $especialista->save();

        // Emitir token JWT en cookie HttpOnly
        $jwtToken = $this->jwt->create($especialista);
        $jwtCookie = cookie('pandora_token', $jwtToken, minutes: 480, httpOnly: true, sameSite: 'Strict');

        // Limpiar contraseña de memoria
        sodium_memzero($passwordPlain);

        if ($especialista->hasRole('sysadmin')) {
            return redirect()->intended('/admin/dashboard#');
        }

        return redirect()->intended('/dashboard#');
    }
}
