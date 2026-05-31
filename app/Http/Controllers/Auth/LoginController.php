<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LoginController — Roadmap §HU-01.
 *
 * Maneja la autenticación de Especialistas:
 * - Validación de credenciales contra la tabla users.
 * - Derivación de clave simétrica (Argon2id KDF) y almacenamiento en sesión.
 * - Rate limiting: 5 intentos / 10 min / bloqueo 15 min.
 * - Mensajes genéricos que no revelan si el email existe.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly KeyDerivationService $kdfService,
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
     * Flujo (Roadmap §HU-01):
     * 1. Validar credenciales (email + password).
     * 2. Autenticar con Auth::attempt().
     * 3. Derivar clave simétrica con sodium_crypto_pwhash (Argon2id).
     * 4. Almacenar clave en sesión como Base64.
     * 5. Limpiar contraseña de memoria con sodium_memzero().
     * 6. Regenerar sesión para prevenir session fixation.
     */
    public function store(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas son incorrectas.',
            ])->onlyInput('email');
        }

        // Regenerar sesión para prevenir session fixation
        $request->session()->regenerate();

        /** @var \App\Models\Especialista $especialista */
        $especialista = Auth::user();

        // Derivar clave simétrica si el especialista tiene kdf_salt
        $password = $credentials['password'];

        if ($especialista->kdf_salt) {
            $salt = base64_decode($especialista->kdf_salt, strict: true);
            $derivedKey = $this->kdfService->derive($password, $salt);
            session(['_sym_key' => base64_encode($derivedKey)]);

            // Limpiar material sensible de memoria
            sodium_memzero($derivedKey);
        } else {
            // Especialista sin kdf_salt: generar sal y guardarla.
            // Esto ocurre la primera vez que un especialista creado antes del Sprint 1 hace login.
            $saltBase64 = $this->kdfService->generateSalt();
            $salt = base64_decode($saltBase64, strict: true);
            $derivedKey = $this->kdfService->derive($password, $salt);

            $especialista->kdf_salt = $saltBase64;
            $especialista->save();

            session(['_sym_key' => base64_encode($derivedKey)]);

            sodium_memzero($derivedKey);
        }

        // Limpiar contraseña de memoria
        sodium_memzero($password);

        if ($especialista->hasRole('sysadmin')) {
            return redirect()->intended('/admin/dashboard');
        }

        return redirect()->intended('/dashboard');
    }
}
